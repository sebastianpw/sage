<?php
namespace App\Core;

use Exception;

/**
 * FramesManager
 *
 * Central singleton for frames, frames chains and map runs.
 * Purely database / semantic operations. Does NOT manipulate image pixels/files.
 */
class FramesManager
{
    private static ?self $instance = null;

    private \mysqli $mysqli;
    private SpwBase $spw;
    private string $projectRoot;
    private string $framesDir;      // absolute
    private string $framesDirRel;   // relative to public/
    private ?string $lastError = null;

    // --- Singleton -------------------------------------------------------
    private function __construct()
    {
        $this->spw = SpwBase::getInstance();
        $this->mysqli = $this->spw->getMysqli();
        $this->projectRoot = rtrim($this->spw->getProjectPath(), '/');
        $this->framesDir = rtrim($this->spw->getFramesDir(), '/');
        $this->framesDirRel = rtrim($this->spw->getFramesDirRel(), '/') . '/';
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    // --- Utilities -------------------------------------------------------
    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * Load a frame row by frame_id or by entity+entity_id (fallback)
     */
    public function loadFrameRow(?int $frameId, ?string $entity = null, ?int $entityId = null): ?array
    {
        if ($frameId) {
            $res = $this->mysqli->query("SELECT * FROM frames WHERE id = " . intval($frameId) . " LIMIT 1");
            if ($res && $res->num_rows) return $res->fetch_assoc();
        }
        if ($entity && $entityId) {
            $ent = $this->mysqli->real_escape_string($entity);
            $res = $this->mysqli->query(
                "SELECT * FROM frames WHERE entity_type = '{$ent}' AND entity_id = " . intval($entityId) .
                " ORDER BY created_at DESC LIMIT 1"
            );
            if ($res && $res->num_rows) return $res->fetch_assoc();
        }
        return null;
    }

    /**
     * Create a map_run row (returns id or false)
     */
    public function createMapRun(string $entityType, string $note = ''): false|int
    {
        $stmt = $this->mysqli->prepare("INSERT INTO map_runs (entity_type, note, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
        if (!$stmt) {
            $this->lastError = "prepare failed: " . $this->mysqli->error;
            return false;
        }
        $stmt->bind_param('ss', $entityType, $note);
        if (!$stmt->execute()) {
            $this->lastError = "execute failed: " . $stmt->error;
            return false;
        }
        $id = $stmt->insert_id;
        $stmt->close();
        return $id;
    }

    /**
     * Insert a new frames row (returns new frame id or false)
     * $orig should be an associative row (as returned by frames SELECT).
     */
    public function insertFrameFromOriginal(array $orig, int $mapRunId, string $derivedRel): false|int
    {
        $stmt = $this->mysqli->prepare(
            "INSERT INTO frames (map_run_id, name, filename, prompt, entity_type, entity_id, style, style_id, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())"
        );
        if (!$stmt) { $this->lastError = "prepare failed: " . $this->mysqli->error; return false; }

        $name = $orig['name'] ?? ($orig['filename'] ?? 'derived');
        $prompt = $orig['prompt'] ?? '';
        $entity_type = $orig['entity_type'] ?? '';
        $entity_id = intval($orig['entity_id'] ?? 0);
        $style = $orig['style'] ?? '';
        $style_id = intval($orig['style_id'] ?? 0);

        $types = 'issssisi';
        if (!$stmt->bind_param($types, $mapRunId, $name, $derivedRel, $prompt, $entity_type, $entity_id, $style, $style_id)) {
            $this->lastError = "bind_param failed: " . $stmt->error;
            return false;
        }

        if (!$stmt->execute()) {
            $this->lastError = "execute failed: " . $stmt->error;
            return false;
        }
        $newId = $stmt->insert_id;
        $stmt->close();
        return $newId;
    }

    /**
     * Insert frames_chains (pure lineage) - returns chain id or false
     */
    public function insertFramesChain(int $newFrameId, int $parentFrameId): false|int
    {
        $stmt = $this->mysqli->prepare("INSERT INTO frames_chains (frame_id, parent_frame_id, created_at, rolled_back) VALUES (?, ?, NOW(), 0)");
        if (!$stmt) { $this->lastError = "prepare failed: " . $this->mysqli->error; return false; }
        $stmt->bind_param('ii', $newFrameId, $parentFrameId);
        if (!$stmt->execute()) { $this->lastError = "execute failed: " . $stmt->error; return false; }
        $id = $stmt->insert_id;
        $stmt->close();
        return $id;
    }

    /**
     * Insert image_edits metadata row - returns image_edit id or false
     *
     * $coords is an array and will be JSON-encoded.
     */
    public function insertImageEdit(int $chainId, int $parentFrameId, ?int $derivedFrameId, string $derivedFilename, ?int $mapRunId, array $coords, ?string $tool, ?string $mode, ?int $userId, ?string $note): false|int
    {
        $coordsJson = json_encode($coords, JSON_UNESCAPED_UNICODE);

        $stmt = $this->mysqli->prepare("INSERT INTO image_edits (chain_id, parent_frame_id, derived_frame_id, derived_filename, map_run_id, tool, mode, coords_json, created_by, note, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        if (!$stmt) { $this->lastError = "prepare failed: " . $this->mysqli->error; return false; }

        $derivedFrameIdVal = $derivedFrameId ? intval($derivedFrameId) : 0;
        $mapRunIdVal = $mapRunId ? intval($mapRunId) : 0;
        $userIdVal = $userId ? intval($userId) : 0;
        $toolVal = $tool ?? '';
        $modeVal = $mode ?? '';
        $noteVal = $note ?? '';

        $types = 'iiisisssis';
        if (!$stmt->bind_param($types, $chainId, $parentFrameId, $derivedFrameIdVal, $derivedFilename, $mapRunIdVal, $toolVal, $modeVal, $coordsJson, $userIdVal, $noteVal)) {
            $this->lastError = "bind_param failed: " . $stmt->error;
            return false;
        }

        if (!$stmt->execute()) {
            $this->lastError = "execute failed: " . $stmt->error;
            return false;
        }
        $id = $stmt->insert_id;
        $stmt->close();
        return $id;
    }

    /**
    * Copy mappings from parent frame to new frame for the correct entity type.
    * This function correctly performs a "copy" without deleting the source mappings.
    */
    public function copyEntityMappings(int $parentFrameId, int $newFrameId): bool
    {
        $parentId = intval($parentFrameId);
        $newId = intval($newFrameId);
    
        $res = $this->mysqli->query("SELECT entity_type FROM frames WHERE id = $parentId LIMIT 1");
        if (!$res || $res->num_rows === 0) {
            $this->lastError = "Parent frame not found for ID $parentId";
            return false;
        }
        $row = $res->fetch_assoc();
        $entityType = $row['entity_type'] ?? null;
        if (empty($entityType)) {
            // It's possible for a frame to have no entity, which is not an error.
            return true;
        }
    
        $mappingTable = 'frames_2_' . $entityType;
    
        // Check if mapping table exists to prevent SQL errors
        $tableCheck = $this->mysqli->query("SHOW TABLES LIKE '{$this->mysqli->real_escape_string($mappingTable)}'");
        if ($tableCheck->num_rows === 0) {
            $this->lastError = "Mapping table '{$mappingTable}' does not exist.";
            return false;
        }

        $res = $this->mysqli->query("SELECT to_id FROM `{$mappingTable}` WHERE from_id = $parentId");
        if (!$res) {
            $this->lastError = "Failed to query mappings from {$mappingTable}: " . $this->mysqli->error;
            return false;
        }
    
        while ($r = $res->fetch_assoc()) {
            $to = intval($r['to_id']);
            $this->mysqli->query("INSERT IGNORE INTO `{$mappingTable}` (from_id, to_id) VALUES ($newId, $to)");
        }
    
        return true;
    }

    public function registerDerivedFrameFromOriginal(array $orig, string $derivedRel, ?int $mapRunId = null, array $opts = []): array
    {
        $coords = $opts['coords'] ?? [];
        $tool = $opts['tool'] ?? null;
        $mode = $opts['mode'] ?? null;
        $userId = $opts['userId'] ?? null;
        $note = $opts['note'] ?? null;

        // Verify derived file exists
        $derivedPath = $this->projectRoot . '/public/' . ltrim($derivedRel, '/');
        if (!file_exists($derivedPath)) {
            $this->lastError = "Derived file not found: " . $derivedPath;
            return ['success' => false, 'message' => $this->lastError];
        }

        $this->mysqli->begin_transaction();
        try {
            if (!$mapRunId) {
                $mapRunId = $this->createMapRun($orig['entity_type'] ?? '', $note ?? ("Derived from frame " . intval($orig['id'] ?? 0)));
                if (!$mapRunId) throw new Exception('Failed to create map_run: ' . $this->lastError);
            }

            $newFrameId = $this->insertFrameFromOriginal($orig, $mapRunId, $derivedRel);
            if (!$newFrameId) throw new Exception('Failed to insert new frame: ' . $this->lastError);

            $parentId = intval($orig['id'] ?? 0);
            $chainId = $this->insertFramesChain($newFrameId, $parentId);
            if (!$chainId) throw new Exception('Failed to insert frames_chains: ' . $this->lastError);

            $imageEditId = $this->insertImageEdit($chainId, $parentId, $newFrameId, $derivedRel, $mapRunId, $coords, $tool, $mode, $userId, $note);
            if (!$imageEditId) throw new Exception('Failed to insert image_edits: ' . $this->lastError);

            // This correctly copies the mappings upon creation.
            if (!$this->copyEntityMappings($parentId, $newFrameId)) {
                throw new Exception('Failed to copy entity mappings: ' . $this->lastError);
            }

            $this->mysqli->commit();

            return [
                'success' => true,
                'map_run_id' => intval($mapRunId),
                'new_frame_id' => intval($newFrameId),
                'chain_id' => intval($chainId),
                'image_edit_id' => intval($imageEditId),
                'derived_filename' => $derivedRel
            ];
        } catch (Exception $e) {
            $this->mysqli->rollback();
            $msg = $e->getMessage();
            if ($this->lastError && strpos($msg, $this->lastError) === false) {
                $msg .= ' | detail: ' . $this->lastError;
            }
            return ['success' => false, 'message' => $msg];
        }
    }

    public function listVersions(int $parentFrameId): array
    {
        $p = intval($parentFrameId);
        $sql = "SELECT ie.*, fc.parent_frame_id, f.filename AS derived_filename, u.name AS created_by_name
                FROM image_edits ie
                LEFT JOIN frames_chains fc ON fc.id = ie.chain_id
                LEFT JOIN frames f ON f.id = ie.derived_frame_id
                LEFT JOIN user u ON u.id = ie.created_by
                WHERE ie.parent_frame_id = $p
                ORDER BY ie.created_at DESC";
        $res = $this->mysqli->query($sql);
        if (!$res) return [];
        $out = [];
        while ($row = $res->fetch_assoc()) $out[] = $row;
        return $out;
    }

    public function getNextFrameBasenameFromDB(): string
    {
        $sql = "UPDATE frame_counter SET next_frame = LAST_INSERT_ID(next_frame + 1)";
        if (!$this->mysqli->query($sql)) {
            throw new \RuntimeException("Failed to update frame_counter: " . $this->mysqli->error);
        }

        $res = $this->mysqli->query("SELECT LAST_INSERT_ID() AS frame_num");
        $row = $res->fetch_assoc();
        $num = (int)$row['frame_num'];

        return 'frame' . str_pad((string)$num, 7, '0', STR_PAD_LEFT);
    }

    /**
     * CORRECTED: Apply a version. This function's ONLY job is to mark the
     * image_edit record as 'applied'. All mapping logic has been removed
     * because it was redundant and destructive.
     */
    public function applyVersion(?int $imageEditId = null, ?int $derivedFrameId = null): array
    {
        if (!$imageEditId && !$derivedFrameId) {
            return ['success' => false, 'message' => 'No identifier provided for applying version'];
        }

        $this->mysqli->begin_transaction();
        try {
            // Find the image_edits record to get its ID
            if ($imageEditId) {
                $res = $this->mysqli->query("SELECT id, parent_frame_id, derived_frame_id FROM image_edits WHERE id = " . intval($imageEditId) . " LIMIT 1");
            } else {
                $res = $this->mysqli->query("SELECT id, parent_frame_id, derived_frame_id FROM image_edits WHERE derived_frame_id = " . intval($derivedFrameId) . " LIMIT 1");
            }

            if (!$res || $res->num_rows === 0) {
                throw new Exception('image_edits record not found');
            }
            $row = $res->fetch_assoc();
            $editIdToUpdate = intval($row['id']);
            $parentFrameId = intval($row['parent_frame_id']);
            $derivedFrameId = intval($row['derived_frame_id']);

            // *** THE FIX: The mapping logic that deleted parent mappings has been removed. ***
            // The mappings are already correctly copied when the version is first created.
            // This function now only marks the version as applied.

            $stmt = $this->mysqli->prepare("UPDATE image_edits SET applied = 1, applied_at = NOW() WHERE id = ?");
            if (!$stmt) throw new Exception('prepare failed for update');
            $stmt->bind_param('i', $editIdToUpdate);
            if (!$stmt->execute()) throw new Exception('failed to mark image_edit as applied: ' . $stmt->error);

            $this->mysqli->commit();
            return [
                'success' => true, 
                'message' => 'Version marked as applied', 
                'derived_frame_id' => $derivedFrameId, 
                'parent_frame_id' => $parentFrameId
            ];
        } catch (Exception $e) {
            $this->mysqli->rollback();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Revert a version: un-applies the edit.
     * For now, this just means setting applied = 0. We will NOT change mappings here either,
     * to keep the logic consistent and non-destructive.
     */
    public function revertVersion(?int $imageEditId = null, ?int $derivedFrameId = null): array
    {
        if (!$imageEditId && !$derivedFrameId) {
            return ['success' => false, 'message' => 'No identifier provided for reverting version'];
        }
        
        $this->mysqli->begin_transaction();
        try {
            if ($imageEditId) {
                $res = $this->mysqli->query("SELECT id FROM image_edits WHERE id = " . intval($imageEditId) . " LIMIT 1");
            } else {
                $res = $this->mysqli->query("SELECT id FROM image_edits WHERE derived_frame_id = " . intval($derivedFrameId) . " LIMIT 1");
            }
            if (!$res || $res->num_rows === 0) {
                throw new Exception('image_edits record not found');
            }
            $row = $res->fetch_assoc();
            $editIdToUpdate = intval($row['id']);

            // Mark image_edits.applied = 0
            $stmt = $this->mysqli->prepare("UPDATE image_edits SET applied = 0 WHERE id = ?");
            if (!$stmt) throw new Exception('prepare failed for revert');
            $stmt->bind_param('i', $editIdToUpdate);
            if (!$stmt->execute()) throw new Exception('failed to update image_edits for revert');

            // Optionally mark the chain as rolled back
            // $this->mysqli->query("UPDATE frames_chains SET rolled_back = 1 WHERE frame_id = " . intval($derivedFrameId));

            $this->mysqli->commit();
            return ['success' => true, 'message' => 'Reverted version'];
        } catch (Exception $e) {
            $this->mysqli->rollback();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}

