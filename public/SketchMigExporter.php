<?php
// public/SketchMigExporter.php
// =============================================================================
// SAGE Sketch Migration — EXPORT PHASE
//
// Reads sketches (and related data) from the live DB, strips all real IDs,
// writes meta-state rows into sketchmig_bundle / sketchmig_sketch /
// sketchmig_frame, then produces a downloadable ZIP containing:
//   - bundle.sql  (INSERT statements for all three sketchmig_* tables)
//   - frames/     (directory of all associated image files)
// =============================================================================

class SketchMigExporter
{
    private \PDO    $pdo;
    private string  $framesDir;   // absolute path to frames directory
    private string  $projectPath; // PROJECT_ROOT

    public function __construct(\PDO $pdo, string $framesDir, string $projectPath)
    {
        $this->pdo         = $pdo;
        $this->framesDir   = rtrim($framesDir, '/');
        $this->projectPath = rtrim($projectPath, '/');
    }

    // -------------------------------------------------------------------------
    // Public: export a set of sketch IDs into a bundle, return bundle_id
    // -------------------------------------------------------------------------
    public function exportBundle(array $sketchIds, string $label, string $sourceDb = ''): int
    {
        if (empty($sketchIds)) {
            throw new \InvalidArgumentException('No sketch IDs provided.');
        }

        $sketchIds = array_map('intval', $sketchIds);
        $placeholders = implode(',', array_fill(0, count($sketchIds), '?'));

        // ------------------------------------------------------------------
        // 1. Create bundle row
        // ------------------------------------------------------------------
        $stmt = $this->pdo->prepare(
            "INSERT INTO sketchmig_bundle (label, source_db, status) VALUES (?, ?, 'pending')"
        );
        $stmt->execute([$label, $sourceDb]);
        $bundleId = (int)$this->pdo->lastInsertId();

        try {
            // ------------------------------------------------------------------
            // 2. Load sketches
            // ------------------------------------------------------------------
            $stmt = $this->pdo->prepare(
                "SELECT s.*,
                        sa.entities, sa.classification, sa.scoring, sa.thematics,
                        sa.recommendations, sa.overall_quality,
                        ssa.narrative_function, ssa.layer, ssa.energy, ssa.position,
                        ssa.standalone, ssa.intensity, ssa.shot_scale, ssa.edit_relationship,
                        ssa.structure_type, ssa.fabula_position, ssa.syuzhet_position,
                        ssa.character_presence, ssa.world_specificity,
                        ssa.narrative_function_mask, ssa.layer_mask,
                        ssa.short_logline, ssa.connective_hint, ssa.tags,
                        ssa.confidence, ssa.novelty, ssa.thematic_relevance,
                        ssa.transition_usability
                 FROM sketches s
                 LEFT JOIN sketch_analysis sa ON sa.sketch_id = s.id
                 LEFT JOIN sketch_sequence_analysis ssa ON ssa.sketch_id = s.id
                 WHERE s.id IN ($placeholders)
                 ORDER BY s.id ASC"
            );
            $stmt->execute($sketchIds);
            $sketches = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (empty($sketches)) {
                throw new \RuntimeException('No sketches found for given IDs.');
            }

            // ------------------------------------------------------------------
            // 3. Load all frames for these sketches
            // ------------------------------------------------------------------
            $stmt = $this->pdo->prepare(
                "SELECT f.*, f2s.to_id AS sketch_id
                 FROM frames f
                 JOIN frames_2_sketches f2s ON f2s.from_id = f.id
                 WHERE f2s.to_id IN ($placeholders)
                 ORDER BY f.id ASC"
            );
            $stmt->execute($sketchIds);
            $frames = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Build lookup: frame_id -> meta_key
            $frameKeyMap = []; // orig_frame_id => meta_frame_key
            foreach ($frames as $fr) {
                $frameKeyMap[$fr['id']] = 'fr_' . $fr['id'];
            }

            // ------------------------------------------------------------------
            // 4. Insert sketchmig_sketch rows
            // ------------------------------------------------------------------
            $sketchCount = 0;
            foreach ($sketches as $sk) {
                $metaSketchKey = 'sk_' . $sk['id'];

                // Resolve img2img / cnmap frame references to meta keys
                $img2imgMetaFrameKey = null;
                if ($sk['img2img_frame_id'] && isset($frameKeyMap[$sk['img2img_frame_id']])) {
                    $img2imgMetaFrameKey = $frameKeyMap[$sk['img2img_frame_id']];
                }
                $cnmapMetaFrameKey = null;
                if ($sk['cnmap_frame_id'] && isset($frameKeyMap[$sk['cnmap_frame_id']])) {
                    $cnmapMetaFrameKey = $frameKeyMap[$sk['cnmap_frame_id']];
                }

                $ins = $this->pdo->prepare(
                    "INSERT INTO sketchmig_sketch (
                        bundle_id, meta_sketch_key, name, `order`, description, description_raw,
                        prompt_negative, seed, searchable, mood,
                        img2img, depth2img, img2img_meta_frame_key, img2img_prompt,
                        cnmap, cnmap_meta_frame_key, cnmap_prompt,
                        sa_entities, sa_classification, sa_scoring, sa_thematics,
                        sa_recommendations, sa_overall_quality,
                        ssa_narrative_function, ssa_layer, ssa_energy, ssa_position,
                        ssa_standalone, ssa_intensity, ssa_shot_scale, ssa_edit_relationship,
                        ssa_structure_type, ssa_fabula_position, ssa_syuzhet_position,
                        ssa_character_presence, ssa_world_specificity,
                        ssa_narrative_function_mask, ssa_layer_mask,
                        ssa_short_logline, ssa_connective_hint, ssa_tags,
                        ssa_confidence, ssa_novelty, ssa_thematic_relevance, ssa_transition_usability
                    ) VALUES (
                        :bundle_id, :meta_sketch_key, :name, :order, :description, :description_raw,
                        :prompt_negative, :seed, :searchable, :mood,
                        :img2img, :depth2img, :img2img_meta_frame_key, :img2img_prompt,
                        :cnmap, :cnmap_meta_frame_key, :cnmap_prompt,
                        :sa_entities, :sa_classification, :sa_scoring, :sa_thematics,
                        :sa_recommendations, :sa_overall_quality,
                        :ssa_narrative_function, :ssa_layer, :ssa_energy, :ssa_position,
                        :ssa_standalone, :ssa_intensity, :ssa_shot_scale, :ssa_edit_relationship,
                        :ssa_structure_type, :ssa_fabula_position, :ssa_syuzhet_position,
                        :ssa_character_presence, :ssa_world_specificity,
                        :ssa_narrative_function_mask, :ssa_layer_mask,
                        :ssa_short_logline, :ssa_connective_hint, :ssa_tags,
                        :ssa_confidence, :ssa_novelty, :ssa_thematic_relevance, :ssa_transition_usability
                    )"
                );
                $ins->execute([
                    ':bundle_id'              => $bundleId,
                    ':meta_sketch_key'        => $metaSketchKey,
                    ':name'                   => $sk['name'],
                    ':order'                  => $sk['order'],
                    ':description'            => $sk['description'],
                    ':description_raw'        => $sk['description_raw'],
                    ':prompt_negative'        => $sk['prompt_negative'],
                    ':seed'                   => $sk['seed'],
                    ':searchable'             => $sk['searchable'],
                    ':mood'                   => $sk['mood'],
                    ':img2img'                => $sk['img2img'],
                    ':depth2img'              => $sk['depth2img'],
                    ':img2img_meta_frame_key' => $img2imgMetaFrameKey,
                    ':img2img_prompt'         => $sk['img2img_prompt'],
                    ':cnmap'                  => $sk['cnmap'],
                    ':cnmap_meta_frame_key'   => $cnmapMetaFrameKey,
                    ':cnmap_prompt'           => $sk['cnmap_prompt'],
                    ':sa_entities'            => $sk['entities'],
                    ':sa_classification'      => $sk['classification'],
                    ':sa_scoring'             => $sk['scoring'],
                    ':sa_thematics'           => $sk['thematics'],
                    ':sa_recommendations'     => $sk['recommendations'],
                    ':sa_overall_quality'     => $sk['overall_quality'] ?? 0,
                    ':ssa_narrative_function' => $sk['narrative_function'],
                    ':ssa_layer'              => $sk['layer'],
                    ':ssa_energy'             => $sk['energy'],
                    ':ssa_position'           => $sk['position'],
                    ':ssa_standalone'         => $sk['standalone'],
                    ':ssa_intensity'          => $sk['intensity'],
                    ':ssa_shot_scale'         => $sk['shot_scale'],
                    ':ssa_edit_relationship'  => $sk['edit_relationship'],
                    ':ssa_structure_type'     => $sk['structure_type'],
                    ':ssa_fabula_position'    => $sk['fabula_position'],
                    ':ssa_syuzhet_position'   => $sk['syuzhet_position'],
                    ':ssa_character_presence' => $sk['character_presence'],
                    ':ssa_world_specificity'  => $sk['world_specificity'],
                    ':ssa_narrative_function_mask' => $sk['narrative_function_mask'] ?? 0,
                    ':ssa_layer_mask'         => $sk['layer_mask'] ?? 0,
                    ':ssa_short_logline'      => $sk['short_logline'],
                    ':ssa_connective_hint'    => $sk['connective_hint'],
                    ':ssa_tags'               => $sk['tags'],
                    ':ssa_confidence'         => $sk['confidence'] ?? 0,
                    ':ssa_novelty'            => $sk['novelty'],
                    ':ssa_thematic_relevance' => $sk['thematic_relevance'],
                    ':ssa_transition_usability' => $sk['transition_usability'],
                ]);
                $sketchCount++;
            }

            // ------------------------------------------------------------------
            // 5. Insert sketchmig_frame rows
            // ------------------------------------------------------------------
            $frameCount = 0;
            foreach ($frames as $fr) {
                $metaFrameKey   = 'fr_' . $fr['id'];
                $metaSketchKey  = 'sk_' . $fr['sketch_id'];
                $zipPath        = 'frames/' . basename($fr['filename']);

                // Resolve self-referential img2img / cnmap keys
                $img2imgMetaFrameKey = null;
                if ($fr['img2img_frame_id'] && isset($frameKeyMap[$fr['img2img_frame_id']])) {
                    $img2imgMetaFrameKey = $frameKeyMap[$fr['img2img_frame_id']];
                }
                $cnmapMetaFrameKey = null;
                if ($fr['cnmap_frame_id'] && isset($frameKeyMap[$fr['cnmap_frame_id']])) {
                    $cnmapMetaFrameKey = $frameKeyMap[$fr['cnmap_frame_id']];
                }

                $ins = $this->pdo->prepare(
                    "INSERT INTO sketchmig_frame (
                        bundle_id, meta_frame_key, meta_sketch_key,
                        name_orig, prompt, prompt_negative, seed, style, model,
                        img2img_meta_frame_key, img2img_prompt,
                        cnmap, cnmap_meta_frame_key, cnmap_prompt,
                        rating, zip_path
                    ) VALUES (
                        :bundle_id, :meta_frame_key, :meta_sketch_key,
                        :name_orig, :prompt, :prompt_negative, :seed, :style, :model,
                        :img2img_meta_frame_key, :img2img_prompt,
                        :cnmap, :cnmap_meta_frame_key, :cnmap_prompt,
                        :rating, :zip_path
                    )"
                );
                $ins->execute([
                    ':bundle_id'              => $bundleId,
                    ':meta_frame_key'         => $metaFrameKey,
                    ':meta_sketch_key'        => $metaSketchKey,
                    ':name_orig'              => $fr['name'],
                    ':prompt'                 => $fr['prompt'],
                    ':prompt_negative'        => $fr['prompt_negative'],
                    ':seed'                   => $fr['seed'],
                    ':style'                  => $fr['style'],
                    ':model'                  => $fr['model'],
                    ':img2img_meta_frame_key' => $img2imgMetaFrameKey,
                    ':img2img_prompt'         => $fr['img2img_prompt'],
                    ':cnmap'                  => $fr['cnmap'],
                    ':cnmap_meta_frame_key'   => $cnmapMetaFrameKey,
                    ':cnmap_prompt'           => $fr['cnmap_prompt'],
                    ':rating'                 => $fr['rating'] ?? 0,
                    ':zip_path'               => $zipPath,
                ]);
                $frameCount++;
            }

            // ------------------------------------------------------------------
            // 6. Update bundle counts & mark exported
            // ------------------------------------------------------------------
            $this->pdo->prepare(
                "UPDATE sketchmig_bundle SET sketch_count=?, frame_count=?, status='exported' WHERE id=?"
            )->execute([$sketchCount, $frameCount, $bundleId]);

        } catch (\Throwable $e) {
            $this->pdo->prepare("UPDATE sketchmig_bundle SET status='failed', export_note=? WHERE id=?")
                ->execute([$e->getMessage(), $bundleId]);
            throw $e;
        }

        return $bundleId;
    }

    // -------------------------------------------------------------------------
    // Public: build the ZIP for a bundle and stream it, or return path
    // -------------------------------------------------------------------------
    public function buildZip(int $bundleId, string $outputPath): void
    {
        // Load bundle
        $stmt = $this->pdo->prepare("SELECT * FROM sketchmig_bundle WHERE id=?");
        $stmt->execute([$bundleId]);
        $bundle = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$bundle) throw new \RuntimeException("Bundle $bundleId not found.");

        // Load frames for file list
        $stmt = $this->pdo->prepare(
            "SELECT sf.zip_path, f.filename
             FROM sketchmig_frame sf
             JOIN frames f ON f.name = sf.name_orig
             WHERE sf.bundle_id = ?"
        );
        $stmt->execute([$bundleId]);
        $frameRows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Build SQL dump
        $sql = $this->buildSqlDump($bundleId);

        $zip = new \ZipArchive();
        if ($zip->open($outputPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Cannot create ZIP at $outputPath");
        }

        $zip->addFromString('bundle.sql', $sql);
        $zip->addFromString('bundle_meta.json', json_encode([
            'bundle_id'     => $bundleId,
            'label'         => $bundle['label'],
            'source_db'     => $bundle['source_db'],
            'sketch_count'  => $bundle['sketch_count'],
            'frame_count'   => $bundle['frame_count'],
            'exported_at'   => $bundle['created_at'],
            'sage_version'  => '1.0',
        ], JSON_PRETTY_PRINT));

        // Add image files
        $missing = 0;
        foreach ($frameRows as $row) {
            $absPath = $this->projectPath . '/public/' . ltrim($row['filename'], '/');
            if (file_exists($absPath)) {
                $zip->addFile($absPath, $row['zip_path']);
            } else {
                $missing++;
            }
        }

        $zip->close();

        if ($missing > 0) {
            // Non-fatal: update note
            $this->pdo->prepare("UPDATE sketchmig_bundle SET export_note=? WHERE id=?")
                ->execute(["ZIP built with $missing missing image files.", $bundleId]);
        }
    }

    // -------------------------------------------------------------------------
    // Build INSERT SQL dump for the bundle's sketchmig_* rows
    // -------------------------------------------------------------------------
    private function buildSqlDump(int $bundleId): string
    {
        $lines = [];
        $lines[] = "-- SAGE Sketch Migration Bundle SQL Dump";
        $lines[] = "-- Bundle ID (SOURCE): $bundleId — will be re-assigned on import";
        $lines[] = "-- Generated: " . date('Y-m-d H:i:s') . " UTC";
        $lines[] = "";
        $lines[] = "SET NAMES utf8mb4;";
        $lines[] = "";

        // Bundle row (id stripped — import will create new one)
        $stmt = $this->pdo->prepare("SELECT * FROM sketchmig_bundle WHERE id=?");
        $stmt->execute([$bundleId]);
        $bundle = $stmt->fetch(\PDO::FETCH_ASSOC);
        $lines[] = $this->rowToInsert('sketchmig_bundle',
            ['label','source_db','sketch_count','frame_count','status','export_note'],
            $bundle
        );
        $lines[] = "SET @new_bundle_id = LAST_INSERT_ID();";
        $lines[] = "";

        // Sketch rows
        $stmt = $this->pdo->prepare("SELECT * FROM sketchmig_sketch WHERE bundle_id=? ORDER BY id ASC");
        $stmt->execute([$bundleId]);
        $sketches = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $skFields = ['meta_sketch_key','name','order','description','description_raw',
            'prompt_negative','seed','searchable','mood',
            'img2img','depth2img','img2img_meta_frame_key','img2img_prompt',
            'cnmap','cnmap_meta_frame_key','cnmap_prompt',
            'sa_entities','sa_classification','sa_scoring','sa_thematics',
            'sa_recommendations','sa_overall_quality',
            'ssa_narrative_function','ssa_layer','ssa_energy','ssa_position',
            'ssa_standalone','ssa_intensity','ssa_shot_scale','ssa_edit_relationship',
            'ssa_structure_type','ssa_fabula_position','ssa_syuzhet_position',
            'ssa_character_presence','ssa_world_specificity',
            'ssa_narrative_function_mask','ssa_layer_mask',
            'ssa_short_logline','ssa_connective_hint','ssa_tags',
            'ssa_confidence','ssa_novelty','ssa_thematic_relevance','ssa_transition_usability'
        ];
        foreach ($sketches as $row) {
            $line = $this->rowToInsert('sketchmig_sketch', $skFields, $row, '@new_bundle_id');
            $lines[] = $line;
        }
        $lines[] = "";

        // Frame rows
        $stmt = $this->pdo->prepare("SELECT * FROM sketchmig_frame WHERE bundle_id=? ORDER BY id ASC");
        $stmt->execute([$bundleId]);
        $frames = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $frFields = ['meta_frame_key','meta_sketch_key','name_orig','prompt','prompt_negative',
            'seed','style','model','img2img_meta_frame_key','img2img_prompt',
            'cnmap','cnmap_meta_frame_key','cnmap_prompt','rating','zip_path'
        ];
        foreach ($frames as $row) {
            $line = $this->rowToInsert('sketchmig_frame', $frFields, $row, '@new_bundle_id');
            $lines[] = $line;
        }

        return implode("\n", $lines) . "\n";
    }

    // Produce a single INSERT statement for a row, injecting @new_bundle_id for bundle_id column
    private function rowToInsert(string $table, array $fields, array $row, ?string $bundleIdExpr = null): string
    {
        $cols   = [];
        $vals   = [];

        if ($bundleIdExpr !== null) {
            $cols[] = '`bundle_id`';
            $vals[] = $bundleIdExpr; // raw SQL expression, not quoted
        }

        foreach ($fields as $f) {
            $cols[] = '`' . $f . '`';
            $v = $row[$f] ?? null;
            if ($v === null) {
                $vals[] = 'NULL';
            } else {
                $vals[] = "'" . addslashes((string)$v) . "'";
            }
        }

        return "INSERT INTO `$table` (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ");";
    }
}
