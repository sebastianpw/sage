<?php
// public/SketchMigImporter.php
// =============================================================================
// SAGE Sketch Migration — IMPORT PHASE
//
// Reads sketchmig_bundle / sketchmig_sketch / sketchmig_frame rows that were
// loaded into this instance's DB (via the SQL dump from the ZIP), then:
//   1. Mints new sketch names (deduplicating against existing sketches)
//   2. Mints new frame names via frame_counter (atomic)
//   3. Copies image files from the extracted ZIP staging dir to FRAMES_ROOT
//   4. Inserts sketches, sketch_analysis, sketch_sequence_analysis,
//      frames, and frames_2_sketches rows
//   5. Patches img2img / cnmap FK references using the new IDs
//   6. Marks the bundle as imported and clears staging rows
// =============================================================================

class SketchMigImporter
{
    private \PDO    $pdo;
    private \mysqli $mysqli;    // needed for atomic frame_counter UPDATE trick
    private string  $framesDir;     // absolute path to FRAMES_ROOT
    private string  $framesDirRel;  // relative (stored in frames.filename)
    private string  $importXtDir;   // absolute path to extracted base dir
    private string  $currentStagingDir; // specific to the active uploadId

    // Maps populated during import
    private array $metaSketchToNewId = []; // meta_sketch_key => new sketches.id
    private array $metaFrameToNewId  = []; // meta_frame_key  => new frames.id
    private array $metaFrameToNewName = []; // meta_frame_key => new frame name

    private array $log = [];

    public function __construct(
        \PDO    $pdo,
        \mysqli $mysqli,
        string  $framesDir,
        string  $framesDirRel,
        string  $importXtDir
    ) {
        $this->pdo          = $pdo;
        $this->mysqli       = $mysqli;
        $this->framesDir    = rtrim($framesDir, '/');
        $this->framesDirRel = rtrim($framesDirRel, '/');
        $this->importXtDir  = rtrim($importXtDir, '/');
    }

    // -------------------------------------------------------------------------
    // Public: run the full import for a bundle
    // Returns ['success'=>bool, 'log'=>[...], 'sketch_count'=>int, 'frame_count'=>int]
    // -------------------------------------------------------------------------
    public function importBundle(int $bundleId, string $uploadId): array
    {
        $this->log = [];
        $this->metaSketchToNewId  = [];
        $this->metaFrameToNewId   = [];
        $this->metaFrameToNewName = [];
        $this->currentStagingDir  = $this->importXtDir . '/' . $uploadId;

        try {
            $bundle = $this->loadBundle($bundleId);
            if (!$bundle) {
                throw new \RuntimeException("Bundle $bundleId not found.");
            }
            if ($bundle['status'] === 'imported') {
                throw new \RuntimeException("Bundle $bundleId already imported.");
            }

            $this->log("=== Starting import for bundle #{$bundleId}: {$bundle['label']} ===");

            $sketches = $this->loadMetaSketches($bundleId);
            $frames   = $this->loadMetaFrames($bundleId);

            $this->log("Found " . count($sketches) . " sketches and " . count($frames) . " frames.");

            $this->pdo->beginTransaction();

            // Create a single map_run for this import bundle
            $stmtMR = $this->pdo->prepare("INSERT INTO map_runs (entity_type, note) VALUES ('sketches', ?)");
            $stmtMR->execute(["Sketch Mig Import: " . $bundle['label']]);
            $mapRunId = (int)$this->pdo->lastInsertId();
            $this->log("Created map_run #{$mapRunId} for bundle.");

            // Phase 1: insert sketches
            foreach ($sketches as $sk) {
                $this->importSketch($sk, $mapRunId);
            }

            // Phase 2: insert frames (needs sketch IDs already resolved)
            foreach ($frames as $fr) {
                $this->importFrame($fr, $mapRunId);
            }

            // Phase 3: patch img2img / cnmap references on sketches
            $this->patchSketchFrameRefs($sketches);

            // Phase 4: patch img2img / cnmap references on frames
            $this->patchFrameFrameRefs($frames);

            // Phase 5: mark bundle imported (keep upload_id for purge capability)
            $this->pdo->prepare(
                "UPDATE sketchmig_bundle SET status='imported', imported_at=NOW(),
                 import_note=? WHERE id=?"
            )->execute(["staging_dir:{$uploadId}\n" . implode(" | ", array_slice($this->log, -3)), $bundleId]);

            $this->pdo->commit();

            $this->log("=== Import complete. Sketches: " . count($sketches) . ", Frames: " . count($frames) . " ===");

            return [
                'success'       => true,
                'log'           => $this->log,
                'sketch_count'  => count($sketches),
                'frame_count'   => count($frames),
            ];

        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->log("ERROR: " . $e->getMessage());
            
            // Preserve the upload_id in the note so we can still resume/purge if needed
            $this->pdo->prepare(
                "UPDATE sketchmig_bundle SET status='failed', import_note=? WHERE id=?"
            )->execute(["staging_dir:{$uploadId}\nERROR: " . $e->getMessage(), $bundleId]);

            return [
                'success' => false,
                'log'     => $this->log,
                'error'   => $e->getMessage(),
            ];
        }
    }

    // -------------------------------------------------------------------------
    // Purge staging rows and extracted folder for a bundle
    // -------------------------------------------------------------------------
    public function purgeBundle(int $bundleId): void
    {
        $stmt = $this->pdo->prepare("SELECT import_note FROM sketchmig_bundle WHERE id=?");
        $stmt->execute([$bundleId]);
        $note = $stmt->fetchColumn();

        // Cascade deletes sketchmig_sketch and sketchmig_frame via FK
        $this->pdo->prepare("DELETE FROM sketchmig_bundle WHERE id=?")->execute([$bundleId]);
        $this->log("Staging rows for bundle #$bundleId purged.");

        if ($note && preg_match('/staging_dir:([a-zA-Z0-9_\-]+)/', $note, $m)) {
            $dir = $this->importXtDir . '/' . $m[1];
            if (is_dir($dir)) {
                $this->recursiveRmdir($dir);
                $this->log("Removed extraction directory: {$m[1]}");
            }
        }
    }

    private function recursiveRmdir(string $dir): void
    {
        if (!is_dir($dir)) return;
        $files = array_diff(scandir($dir), ['.','..']);
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->recursiveRmdir("$dir/$file") : unlink("$dir/$file");
        }
        rmdir($dir);
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    private function loadBundle(int $bundleId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM sketchmig_bundle WHERE id=?");
        $stmt->execute([$bundleId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    private function loadMetaSketches(int $bundleId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM sketchmig_sketch WHERE bundle_id=? ORDER BY id ASC");
        $stmt->execute([$bundleId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function loadMetaFrames(int $bundleId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM sketchmig_frame WHERE bundle_id=? ORDER BY id ASC");
        $stmt->execute([$bundleId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // -------------------------------------------------------------------------
    // Import a single sketch (no frame refs yet — patched later)
    // -------------------------------------------------------------------------
    private function importSketch(array $sk, int $mapRunId): void
    {
        $name = $this->uniqueSketchName($sk['name']);

        $stmt = $this->pdo->prepare(
            "INSERT INTO sketches (
                name, `order`, description, description_raw, prompt_negative, seed,
                searchable, mood, img2img, depth2img, img2img_prompt, cnmap, cnmap_prompt,
                regenerate_images, active_map_run_id
            ) VALUES (
                :name, :order, :description, :description_raw, :prompt_negative, :seed,
                :searchable, :mood, :img2img, :depth2img, :img2img_prompt, :cnmap, :cnmap_prompt,
                0, :active_map_run_id
            )"
        );
        $stmt->execute([
            ':name'              => $name,
            ':order'             => $sk['order'],
            ':description'       => $sk['description'],
            ':description_raw'   => $sk['description_raw'],
            ':prompt_negative'   => $sk['prompt_negative'],
            ':seed'              => $sk['seed'],
            ':searchable'        => $sk['searchable'],
            ':mood'              => $sk['mood'],
            ':img2img'           => $sk['img2img'],
            ':depth2img'         => $sk['depth2img'],
            ':img2img_prompt'    => $sk['img2img_prompt'],
            ':cnmap'             => $sk['cnmap'],
            ':cnmap_prompt'      => $sk['cnmap_prompt'],
            ':active_map_run_id' => $mapRunId,
        ]);
        $newSketchId = (int)$this->pdo->lastInsertId();
        $this->metaSketchToNewId[$sk['meta_sketch_key']] = $newSketchId;

        $this->log("Sketch [{$sk['meta_sketch_key']}] → #{$newSketchId} ('{$name}')");

        // sketch_analysis
        if ($sk['sa_entities'] || $sk['sa_classification'] || $sk['sa_overall_quality']) {
            $stmt = $this->pdo->prepare(
                "INSERT INTO sketch_analysis (
                    sketch_id, entities, classification, scoring, thematics,
                    recommendations, overall_quality
                ) VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $newSketchId,
                $sk['sa_entities'],
                $sk['sa_classification'],
                $sk['sa_scoring'],
                $sk['sa_thematics'],
                $sk['sa_recommendations'],
                $sk['sa_overall_quality'] ?? 0,
            ]);
        }

        // sketch_sequence_analysis
        if ($sk['ssa_energy'] || $sk['ssa_short_logline'] || $sk['ssa_confidence']) {
            $stmt = $this->pdo->prepare(
                "INSERT INTO sketch_sequence_analysis (
                    sketch_id, narrative_function, layer, energy, position, standalone,
                    intensity, shot_scale, edit_relationship, structure_type,
                    fabula_position, syuzhet_position, character_presence, world_specificity,
                    narrative_function_mask, layer_mask, short_logline, connective_hint,
                    tags, confidence, novelty, thematic_relevance, transition_usability
                ) VALUES (
                    :sketch_id, :narrative_function, :layer, :energy, :position, :standalone,
                    :intensity, :shot_scale, :edit_relationship, :structure_type,
                    :fabula_position, :syuzhet_position, :character_presence, :world_specificity,
                    :narrative_function_mask, :layer_mask, :short_logline, :connective_hint,
                    :tags, :confidence, :novelty, :thematic_relevance, :transition_usability
                )"
            );
            $stmt->execute([
                ':sketch_id'               => $newSketchId,
                ':narrative_function'      => $sk['ssa_narrative_function'],
                ':layer'                   => $sk['ssa_layer'],
                ':energy'                  => $sk['ssa_energy'],
                ':position'                => $sk['ssa_position'],
                ':standalone'              => $sk['ssa_standalone'],
                ':intensity'               => $sk['ssa_intensity'],
                ':shot_scale'              => $sk['ssa_shot_scale'],
                ':edit_relationship'       => $sk['ssa_edit_relationship'],
                ':structure_type'          => $sk['ssa_structure_type'],
                ':fabula_position'         => $sk['ssa_fabula_position'],
                ':syuzhet_position'        => $sk['ssa_syuzhet_position'],
                ':character_presence'      => $sk['ssa_character_presence'],
                ':world_specificity'       => $sk['ssa_world_specificity'],
                ':narrative_function_mask' => $sk['ssa_narrative_function_mask'] ?? 0,
                ':layer_mask'              => $sk['ssa_layer_mask'] ?? 0,
                ':short_logline'           => $sk['ssa_short_logline'],
                ':connective_hint'         => $sk['ssa_connective_hint'],
                ':tags'                    => $sk['ssa_tags'],
                ':confidence'              => $sk['ssa_confidence'] ?? 0,
                ':novelty'                 => $sk['ssa_novelty'],
                ':thematic_relevance'      => $sk['ssa_thematic_relevance'],
                ':transition_usability'    => $sk['ssa_transition_usability'],
            ]);
        }
    }

    // -------------------------------------------------------------------------
    // Import a single frame
    // -------------------------------------------------------------------------
    private function importFrame(array $fr, int $mapRunId): void
    {
        $newSketchId = $this->metaSketchToNewId[$fr['meta_sketch_key']] ?? null;
        if (!$newSketchId) {
            $this->log("WARN: Frame [{$fr['meta_frame_key']}] — sketch key {$fr['meta_sketch_key']} not resolved. Skipping.");
            return;
        }

        // Copy image file
        $srcZipPath = $this->currentStagingDir . '/' . ltrim($fr['zip_path'], '/');
        $newFrameName = $this->mintFrameName(); // uses frame_counter atomically
        $newFilename  = $this->framesDirRel . '/' . $newFrameName . '.jpg';
        $absDestPath  = $this->framesDir . '/' . $newFrameName . '.jpg';

        if (file_exists($srcZipPath)) {
            $this->convertToJpg($srcZipPath, $absDestPath);
        } else {
            $this->log("WARN: Image file not found at $srcZipPath for frame [{$fr['meta_frame_key']}]. Frame row inserted without file.");
        }

        // Insert frame row (img2img/cnmap frame refs patched later)
        $stmt = $this->pdo->prepare(
            "INSERT INTO frames (
                name, filename, prompt, prompt_negative, seed, entity_type, entity_id,
                style, model, rating, map_run_id
            ) VALUES (
                :name, :filename, :prompt, :prompt_negative, :seed, 'sketches', :entity_id,
                :style, :model, :rating, :map_run_id
            )"
        );
        $stmt->execute([
            ':name'            => $newFrameName,
            ':filename'        => $newFilename,
            ':prompt'          => $fr['prompt'],
            ':prompt_negative' => $fr['prompt_negative'],
            ':seed'            => $fr['seed'],
            ':entity_id'       => $newSketchId,
            ':style'           => $fr['style'],
            ':model'           => $fr['model'],
            ':rating'          => $fr['rating'] ?? 0,
            ':map_run_id'      => $mapRunId,
        ]);
        $newFrameId = (int)$this->pdo->lastInsertId();

        // Update sketchmig_frame so we can patch later
        $this->pdo->prepare(
            "UPDATE sketchmig_frame SET imported_frame_name=?, imported_frame_id=? WHERE meta_frame_key=? AND bundle_id=?"
        )->execute([$newFrameName, $newFrameId, $fr['meta_frame_key'], $fr['bundle_id']]);

        // Insert frames_2_sketches
        $this->pdo->prepare(
            "INSERT IGNORE INTO frames_2_sketches (from_id, to_id) VALUES (?, ?)"
        )->execute([$newFrameId, $newSketchId]);

        $this->metaFrameToNewId[$fr['meta_frame_key']]   = $newFrameId;
        $this->metaFrameToNewName[$fr['meta_frame_key']] = $newFrameName;

        $this->log("Frame [{$fr['meta_frame_key']}] → #{$newFrameId} ('{$newFrameName}') → sketch #{$newSketchId}");
    }

    // -------------------------------------------------------------------------
    // Patch sketches.img2img_frame_id / cnmap_frame_id now that all frame IDs known
    // -------------------------------------------------------------------------
    private function patchSketchFrameRefs(array $sketches): void
    {
        foreach ($sketches as $sk) {
            $newSketchId = $this->metaSketchToNewId[$sk['meta_sketch_key']] ?? null;
            if (!$newSketchId) continue;

            $updates = [];
            $params  = [];

            if ($sk['img2img_meta_frame_key'] && isset($this->metaFrameToNewId[$sk['img2img_meta_frame_key']])) {
                $newFrId  = $this->metaFrameToNewId[$sk['img2img_meta_frame_key']];
                $newFrName = $this->metaFrameToNewName[$sk['img2img_meta_frame_key']];
                $updates[] = "img2img_frame_id=?, img2img_frame_filename=?";
                array_push($params, $newFrId, $this->framesDirRel . '/' . $newFrName . '.jpg');
            }
            if ($sk['cnmap_meta_frame_key'] && isset($this->metaFrameToNewId[$sk['cnmap_meta_frame_key']])) {
                $newFrId  = $this->metaFrameToNewId[$sk['cnmap_meta_frame_key']];
                $newFrName = $this->metaFrameToNewName[$sk['cnmap_meta_frame_key']];
                $updates[] = "cnmap_frame_id=?, cnmap_frame_filename=?";
                array_push($params, $newFrId, $this->framesDirRel . '/' . $newFrName . '.jpg');
            }

            if (!empty($updates)) {
                $params[] = $newSketchId;
                $this->pdo->prepare("UPDATE sketches SET " . implode(', ', $updates) . " WHERE id=?")
                    ->execute($params);
            }
        }
    }

    // -------------------------------------------------------------------------
    // Patch frames.img2img_frame_id / cnmap_frame_id
    // -------------------------------------------------------------------------
    private function patchFrameFrameRefs(array $frames): void
    {
        foreach ($frames as $fr) {
            $newFrameId = $this->metaFrameToNewId[$fr['meta_frame_key']] ?? null;
            if (!$newFrameId) continue;

            $updates = [];
            $params  = [];

            if ($fr['img2img_meta_frame_key'] && isset($this->metaFrameToNewId[$fr['img2img_meta_frame_key']])) {
                $refId   = $this->metaFrameToNewId[$fr['img2img_meta_frame_key']];
                $refName = $this->metaFrameToNewName[$fr['img2img_meta_frame_key']];
                $updates[] = "img2img_frame_id=?, img2img_frame_filename=?, img2img_prompt=?";
                array_push($params, $refId, $this->framesDirRel . '/' . $refName . '.jpg', $fr['img2img_prompt']);
            }
            if ($fr['cnmap_meta_frame_key'] && isset($this->metaFrameToNewId[$fr['cnmap_meta_frame_key']])) {
                $refId   = $this->metaFrameToNewId[$fr['cnmap_meta_frame_key']];
                $refName = $this->metaFrameToNewName[$fr['cnmap_meta_frame_key']];
                $updates[] = "cnmap_frame_id=?, cnmap_frame_filename=?, cnmap_prompt=?";
                array_push($params, $refId, $this->framesDirRel . '/' . $refName . '.jpg', $fr['cnmap_prompt']);
            }

            if (!empty($updates)) {
                $params[] = $newFrameId;
                $this->pdo->prepare("UPDATE frames SET " . implode(', ', $updates) . " WHERE id=?")
                    ->execute($params);
            }
        }
    }

    // -------------------------------------------------------------------------
    // Mint a new frame name using frame_counter (atomic, same as bash worker)
    // -------------------------------------------------------------------------
    private function mintFrameName(): string
    {
        if (!$this->mysqli->query("UPDATE frame_counter SET next_frame = LAST_INSERT_ID(next_frame + 1)")) {
            throw new \RuntimeException("frame_counter update failed: " . $this->mysqli->error);
        }
        $res = $this->mysqli->query("SELECT LAST_INSERT_ID() AS n");
        $row = $res->fetch_assoc();
        return 'frame' . str_pad((string)(int)$row['n'], 7, '0', STR_PAD_LEFT);
    }

    // -------------------------------------------------------------------------
    // Ensure unique sketch name on target instance
    // -------------------------------------------------------------------------
    private function uniqueSketchName(string $baseName): string
    {
        $name = $baseName;
        $i = 1;
        while ($this->sketchNameExists($name)) {
            $name = $baseName . '_mig' . $i++;
        }
        return $name;
    }

    private function sketchNameExists(string $name): bool
    {
        $stmt = $this->pdo->prepare("SELECT id FROM sketches WHERE name=? LIMIT 1");
        $stmt->execute([$name]);
        return (bool)$stmt->fetch();
    }

    // -------------------------------------------------------------------------
    // Convert source image to JPEG for frames directory
    // -------------------------------------------------------------------------
    private function convertToJpg(string $src, string $dest): bool
    {
        $ext  = strtolower(pathinfo($src, PATHINFO_EXTENSION));
        $info = @getimagesize($src);
        $mime = $info['mime'] ?? null;

        if ($mime === 'image/jpeg' || in_array($ext, ['jpg','jpeg'])) {
            return copy($src, $dest);
        }

        $fn = match(true) {
            $mime === 'image/png'  || $ext === 'png'  => 'imagecreatefrompng',
            $mime === 'image/gif'  || $ext === 'gif'  => 'imagecreatefromgif',
            ($mime === 'image/webp' || $ext === 'webp') && function_exists('imagecreatefromwebp') => 'imagecreatefromwebp',
            default => null
        };

        if (!$fn) return copy($src, $dest); // fallback: copy as-is

        $srcImg = @$fn($src);
        if (!$srcImg) return false;

        $w = imagesx($srcImg); $h = imagesy($srcImg);
        $dst = imagecreatetruecolor($w, $h);
        imagefilledrectangle($dst, 0, 0, $w, $h, imagecolorallocate($dst, 255, 255, 255));
        imagecopy($dst, $srcImg, 0, 0, 0, 0, $w, $h);
        $ok = imagejpeg($dst, $dest, 92);
        imagedestroy($srcImg); imagedestroy($dst);
        return $ok;
    }

    private function log(string $msg): void
    {
        $this->log[] = '[' . date('H:i:s') . '] ' . $msg;
    }
}