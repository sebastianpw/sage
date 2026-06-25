<?php
/**
 * bang_api.php
 * BANG! — Comic Panel Composer API
 * Handles canvas CRUD, arrangement save/load, element sync, export jobs.
 */
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

header('Content-Type: application/json; charset=utf-8');

use App\Core\FramesManager;
use App\Core\SpwBase;

$spw = SpwBase::getInstance();
// Fix: Reliably bind PDO so CRUD operations do not encounter fatal errors.
$pdo = $spw->getPDO();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {

        // ── Canvas CRUD ──────────────────────────────────────────────────────

        case 'create_canvas': {
            $compositeId = (int)($_POST['composite_id'] ?? 0);
            $name        = trim($_POST['name'] ?? 'Untitled Panel Strip');
            $width       = (int)($_POST['canvas_width']  ?? 1024);
            $height      = (int)($_POST['canvas_height'] ?? 1448);
            $bgColor     = trim($_POST['bg_color'] ?? '#000000');

            if (!$compositeId) throw new Exception('composite_id required');

            $stmt = $pdo->prepare("
                INSERT INTO bang_canvases (composite_id, name, canvas_width, canvas_height, bg_color)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$compositeId, $name, $width, $height, $bgColor]);
            $canvasId = (int)$pdo->lastInsertId();

            // Create initial empty arrangement
            $aStmt = $pdo->prepare("
                INSERT INTO bang_arrangements (canvas_id, composite_id, name, scene_json, is_active)
                VALUES (?, ?, 'Initial Draft', ?, 1)
            ");
            $emptyScene = json_encode([
                'elements' => [],
                'canvas_width'  => $width,
                'canvas_height' => $height,
                'bg_color'      => $bgColor,
            ]);
            $aStmt->execute([$canvasId, $compositeId, $emptyScene]);
            $arrId = (int)$pdo->lastInsertId();

            echo json_encode(['success' => true, 'canvas_id' => $canvasId, 'arrangement_id' => $arrId]);
            break;
        }

        case 'get_canvas': {
            $canvasId = (int)($_GET['canvas_id'] ?? 0);
            if (!$canvasId) throw new Exception('canvas_id required');

            $stmt = $pdo->prepare("SELECT * FROM bang_canvases WHERE id = ?");
            $stmt->execute([$canvasId]);
            $canvas = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$canvas) throw new Exception('Canvas not found');

            // Get active arrangement
            $aStmt = $pdo->prepare("SELECT * FROM bang_arrangements WHERE canvas_id = ? AND is_active = 1 ORDER BY updated_at DESC LIMIT 1");
            $aStmt->execute([$canvasId]);
            $arr = $aStmt->fetch(PDO::FETCH_ASSOC);

            // Get composite frames
            $fStmt = $pdo->prepare("
                SELECT f.id, f.filename, f.name
                FROM composite_frames cf
                JOIN frames f ON cf.frame_id = f.id
                WHERE cf.composite_id = ?
                ORDER BY cf.created_at ASC
            ");
            $fStmt->execute([$canvas['composite_id']]);
            $frames = $fStmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success'     => true,
                'canvas'      => $canvas,
                'arrangement' => $arr,
                'frames'      => $frames,
            ]);
            break;
        }

        case 'list_canvases_for_composite': {
            $compositeId = (int)($_GET['composite_id'] ?? 0);
            if (!$compositeId) throw new Exception('composite_id required');

            $stmt = $pdo->prepare("
                SELECT bc.*, 
                       (SELECT COUNT(*) FROM bang_arrangements ba WHERE ba.canvas_id = bc.id) AS arrangement_count
                FROM bang_canvases bc
                WHERE bc.composite_id = ?
                ORDER BY bc.updated_at DESC
            ");
            $stmt->execute([$compositeId]);
            echo json_encode(['success' => true, 'canvases' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;
        }

        case 'update_canvas_settings': {
            $canvasId = (int)($_POST['canvas_id'] ?? 0);
            if (!$canvasId) throw new Exception('canvas_id required');

            $fields = [];
            $params = [];
            foreach (['name', 'bg_color'] as $f) {
                if (isset($_POST[$f])) { $fields[] = "$f = ?"; $params[] = trim($_POST[$f]); }
            }
            foreach (['canvas_width', 'canvas_height'] as $f) {
                if (isset($_POST[$f])) { $fields[] = "$f = ?"; $params[] = (int)$_POST[$f]; }
            }
            if (empty($fields)) throw new Exception('Nothing to update');

            $params[] = $canvasId;
            $pdo->prepare("UPDATE bang_canvases SET " . implode(', ', $fields) . " WHERE id = ?")
                ->execute($params);

            echo json_encode(['success' => true]);
            break;
        }

        case 'delete_canvas': {
            $canvasId = (int)($_POST['canvas_id'] ?? 0);
            if (!$canvasId) throw new Exception('canvas_id required');

            // Remove arrangements and elements
            $pdo->prepare("DELETE FROM bang_elements WHERE canvas_id = ?")->execute([$canvasId]);
            $pdo->prepare("DELETE FROM bang_arrangements WHERE canvas_id = ?")->execute([$canvasId]);
            $pdo->prepare("DELETE FROM bang_canvases WHERE id = ?")->execute([$canvasId]);

            echo json_encode(['success' => true]);
            break;
        }

        // ── Arrangement Save / Load ──────────────────────────────────────────

        case 'save_arrangement': {
            $canvasId    = (int)($_POST['canvas_id'] ?? 0);
            $compositeId = (int)($_POST['composite_id'] ?? 0);
            $arrId       = isset($_POST['arrangement_id']) && $_POST['arrangement_id'] !== '' ? (int)$_POST['arrangement_id'] : null;
            $sceneJson   = $_POST['scene_json'] ?? '{}';
            $name        = trim($_POST['name'] ?? 'Draft');

            if (!$canvasId || !$compositeId) throw new Exception('canvas_id and composite_id required');

            // Validate JSON
            $decoded = json_decode($sceneJson, true);
            if ($decoded === null) throw new Exception('Invalid scene_json');

            if ($arrId) {
                $pdo->prepare("UPDATE bang_arrangements SET scene_json = ?, name = ?, updated_at = NOW() WHERE id = ? AND canvas_id = ?")
                    ->execute([$sceneJson, $name, $arrId, $canvasId]);
            } else {
                // Deactivate others, create new active
                $pdo->prepare("UPDATE bang_arrangements SET is_active = 0 WHERE canvas_id = ?")
                    ->execute([$canvasId]);
                $stmt = $pdo->prepare("INSERT INTO bang_arrangements (canvas_id, composite_id, name, scene_json, is_active) VALUES (?, ?, ?, ?, 1)");
                $stmt->execute([$canvasId, $compositeId, $name, $sceneJson]);
                $arrId = (int)$pdo->lastInsertId();
            }

            // Sync element index
            _syncElementIndex($pdo, $arrId, $canvasId, $decoded);

            echo json_encode(['success' => true, 'arrangement_id' => $arrId]);
            break;
        }

        case 'list_arrangements': {
            $compositeId = (int)($_GET['composite_id'] ?? 0);
            if (!$compositeId) throw new Exception('composite_id required');

            $stmt = $pdo->prepare("
                SELECT a.id, a.name, a.is_active, a.updated_at, a.canvas_id, c.name as canvas_name
                FROM bang_arrangements a
                JOIN bang_canvases c ON a.canvas_id = c.id
                WHERE a.composite_id = ? 
                ORDER BY a.updated_at DESC
            ");
            $stmt->execute([$compositeId]);
            echo json_encode(['success' => true, 'arrangements' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;
        }

        case 'load_arrangement': {
            $arrId = (int)($_GET['arrangement_id'] ?? 0);
            if (!$arrId) throw new Exception('arrangement_id required');

            $stmt = $pdo->prepare("SELECT * FROM bang_arrangements WHERE id = ?");
            $stmt->execute([$arrId]);
            $arr = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$arr) throw new Exception('Arrangement not found');

            $cStmt = $pdo->prepare("SELECT * FROM bang_canvases WHERE id = ?");
            $cStmt->execute([$arr['canvas_id']]);
            $canvas = $cStmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'arrangement' => $arr, 'canvas' => $canvas]);
            break;
        }

        case 'duplicate_arrangement': {
            $arrId = (int)($_POST['arrangement_id'] ?? 0);
            $newName = trim($_POST['name'] ?? 'Copy');
            if (!$arrId) throw new Exception('arrangement_id required');

            $stmt = $pdo->prepare("SELECT * FROM bang_arrangements WHERE id = ?");
            $stmt->execute([$arrId]);
            $arr = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$arr) throw new Exception('Arrangement not found');

            $ins = $pdo->prepare("INSERT INTO bang_arrangements (canvas_id, composite_id, name, scene_json, is_active) VALUES (?, ?, ?, ?, 0)");
            $ins->execute([$arr['canvas_id'], $arr['composite_id'], $newName, $arr['scene_json']]);
            $newId = (int)$pdo->lastInsertId();

            echo json_encode(['success' => true, 'arrangement_id' => $newId]);
            break;
        }

        // ── Export / Render ("Bouncing") ─────────────────────────────────────

        case 'export_render': {
            $canvasId    = (int)($_POST['canvas_id'] ?? 0);
            $compositeId = (int)($_POST['composite_id'] ?? 0);
            $arrId       = (int)($_POST['arrangement_id'] ?? 0);
            $sceneJson   = $_POST['scene_json'] ?? '';
            $width       = (int)($_POST['canvas_width']  ?? 1024);
            $height      = (int)($_POST['canvas_height'] ?? 1448);
            $bgColor     = trim($_POST['bg_color'] ?? '#000000');
            $pyapiUrl    = rtrim($_POST['pyapi_url'] ?? 'http://127.0.0.1:8009', '/');

            if (!$compositeId || !$sceneJson) throw new Exception('composite_id and scene_json required');

            $scene  = json_decode($sceneJson, true);
            if (!$scene) throw new Exception('Invalid scene_json');

            $publicPathAbs = $spw->getProjectPath() . '/public';

            // Build payload for PyAPI
            $elements = $scene['elements'] ?? [];

            // Resolve absolute paths for image and innerImage elements
            foreach ($elements as &$el) {
                $elType = $el['type'] ?? '';

                if ($elType === 'image' && !empty($el['filename'])) {
                    $fn = ltrim($el['filename'], '/');
                    $el['abs_path'] = rtrim($publicPathAbs, '/') . '/' . $fn;
                }

                if ($elType === 'panel' && !empty($el['innerImageFilename'])) {
                    $fn = ltrim($el['innerImageFilename'], '/');
                    $el['innerImageAbsPath'] = rtrim($publicPathAbs, '/') . '/' . $fn;
                }
            }
            unset($el);

            $payload = [
                'canvas_width'  => $width,
                'canvas_height' => $height,
                'bg_color'      => $bgColor,
                'elements'      => $elements,
            ];

            $ch = curl_init("$pyapiUrl/bang/render");
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 120);

            $response  = curl_exec($ch);
            $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) throw new Exception("cURL error: $curlError");
            if ($httpCode !== 200) throw new Exception("PyAPI error HTTP $httpCode: $response");

            $pyRes = json_decode($response, true);
            if (!$pyRes || empty($pyRes['temp_path']) || !file_exists($pyRes['temp_path'])) {
                throw new Exception("Invalid PyAPI response: $response");
            }

            // Exactly replicating multiplane's export mechanics
            $fm = FramesManager::getInstance();
            $framesDirAbs = rtrim($spw->getFramesDir(), '/');
            $framesDirRel = rtrim($spw->getFramesDirRel(), '/');
            if (!is_dir($framesDirAbs)) mkdir($framesDirAbs, 0777, true);

            // Correctly bumps frame counter seamlessly
            $basename    = $fm->getNextFrameBasenameFromDB();
            $filename    = $basename . '.png';
            $finalPath   = $framesDirAbs . '/' . $filename;
            $filenameRel = $framesDirRel . '/' . $filename;

            if (!rename($pyRes['temp_path'], $finalPath)) {
                if (copy($pyRes['temp_path'], $finalPath)) unlink($pyRes['temp_path']);
                else throw new Exception("Failed to move rendered file");
            }

            $pdo->beginTransaction();

            // Fetch composite name
            $stmtCN   = $pdo->prepare("SELECT name FROM composites WHERE id = ?");
            $stmtCN->execute([$compositeId]);
            $compName = $stmtCN->fetchColumn() ?: 'Panel Strip';

            // Document Map Run
            $mapRunId = $fm->createMapRun('composites', "BANG! Render: $compName");

            // Attach new result to frames
            $style = 'multiplane'; $style_id = 999;
            $stmtF = $pdo->prepare("INSERT INTO frames (map_run_id, name, filename, prompt, entity_type, entity_id, style, style_id, created_at) VALUES (?, ?, ?, ?, 'composites', ?, 'bang', $style_id, NOW())");
            $stmtF->execute([$mapRunId, $basename, $filenameRel, "BANG! Panel Strip — Composite #$compositeId", $compositeId]);
            $newFrameId = (int)$pdo->lastInsertId();

            // Link frame → composite backward mapping specifically requested
            $pdo->prepare("INSERT IGNORE INTO frames_2_composites (from_id, to_id) VALUES (?, ?)")
                ->execute([$newFrameId, $compositeId]);

            // Register export strictly for BANG log
            $pdo->prepare("INSERT INTO bang_exports (canvas_id, arrangement_id, frame_id, composite_id, export_width, export_height, status) VALUES (?, ?, ?, ?, ?, ?, 'done')")
                ->execute([$canvasId ?: 0, $arrId ?: 0, $newFrameId, $compositeId, $width, $height]);

            $pdo->commit();

            echo json_encode([
                'success'  => true,
                'frame_id' => $newFrameId,
                'filename' => $filenameRel,
                'message'  => "BANG! Panel Strip rendered — Frame #$newFrameId",
            ]);
            break;
        }

        // ── Font Registry ────────────────────────────────────────────────────

        case 'list_fonts': {
            $stmt = $pdo->query("SELECT * FROM bang_fonts WHERE is_active = 1 ORDER BY sort_order ASC, name ASC");
            echo json_encode(['success' => true, 'fonts' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;
        }

        // ── Composites helper (for the main page composite picker) ───────────

        case 'list_composites': {
            $q      = trim($_GET['q'] ?? '');
            $page   = max(1, (int)($_GET['page'] ?? 1));
            $limit  = 12;
            $offset = ($page - 1) * $limit;

            $where  = ['1=1'];
            $params = [];
            if ($q !== '') {
                $where[] = '(name LIKE ? OR id = ?)';
                $params[] = "%$q%";
                $params[] = (int)$q;
            }
            $wc = implode(' AND ', $where);

            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM composites WHERE $wc");
            $countStmt->execute($params);
            $total = (int)$countStmt->fetchColumn();

            $stmt = $pdo->prepare("SELECT id, name FROM composites WHERE $wc ORDER BY id DESC LIMIT $limit OFFSET $offset");
            $stmt->execute($params);

            echo json_encode([
                'success'    => true,
                'composites' => $stmt->fetchAll(PDO::FETCH_ASSOC),
                'pages'      => max(1, ceil($total / $limit)),
            ]);
            break;
        }
        
        
       
        // ── Narrative Sequence Import (for Picker Tabs) ──────────────────────

        case 'list_narrative_sequences': {
            $q      = trim($_GET['q'] ?? '');
            $page   = max(1, (int)($_GET['page'] ?? 1));
            $limit  = 12;
            $offset = ($page - 1) * $limit;

            $where  = ['1=1'];
            $params = [];
            if ($q !== '') {
                $where[] = '(name LIKE ? OR id = ?)';
                $params[] = "%$q%";
                $params[] = (int)$q;
            }
            $wc = implode(' AND ', $where);

            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM narrative_sequences WHERE $wc");
            $countStmt->execute($params);
            $total = (int)$countStmt->fetchColumn();

            
            $stmt = $pdo->prepare("SELECT id, name, sequence_data FROM narrative_sequences WHERE $wc ORDER BY id DESC LIMIT $limit OFFSET $offset");
            $stmt->execute($params);
            $seqs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($seqs as &$s) {
                $data = json_decode($s['sequence_data'] ?? '[]', true);
                $s['frame_count'] = is_array($data) ? count($data) : 0;
                unset($s['sequence_data']); // Keep payload small
            }
            unset($s);

            echo json_encode([
                'success'   => true,
                'sequences' => $seqs,
                'pages'     => max(1, ceil($total / $limit)),
            ]);
            break;
        }

        case 'import_narrative_sequence': {
            $seqId = (int)($_POST['sequence_id'] ?? 0);
            if (!$seqId) throw new Exception('sequence_id required');

            // 1. Fetch the sequence
            $stmt = $pdo->prepare("SELECT * FROM narrative_sequences WHERE id = ?");
            $stmt->execute([$seqId]);
            $seq = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$seq) throw new Exception('Sequence not found');

            $seqData = json_decode($seq['sequence_data'] ?? '[]', true);
            if (!is_array($seqData) || empty($seqData)) {
                throw new Exception('Sequence is empty or contains invalid data');
            }

            $pdo->beginTransaction();

            // 2. Create the new composite
            $compName = "Import: " . $seq['name'];
            $compDesc = "Auto-imported from Narrative Sequence #$seqId";
            $cStmt = $pdo->prepare("INSERT INTO composites (name, description) VALUES (?, ?)");
            $cStmt->execute([$compName, $compDesc]);
            $newCompId = (int)$pdo->lastInsertId();

            // 3. Resolve frames and link them
            $linkStmt = $pdo->prepare("INSERT IGNORE INTO composite_frames (composite_id, frame_id) VALUES (?, ?)");
            
            // Prepare statement for legacy fallback (get latest frame for sketch)
            $fallbackStmt = $pdo->prepare("
                SELECT f.id 
                FROM frames f 
                JOIN frames_2_sketches fs ON f.id = fs.from_id 
                WHERE fs.to_id = ? 
                ORDER BY f.id DESC LIMIT 1
            ");

            foreach ($seqData as $item) {
                $frameId = null;
                $sketchId = null;

                if (is_array($item)) {
                    // Modern format: {"sketch_id": 123, "frame_id": 456}
                    $frameId  = !empty($item['frame_id']) ? (int)$item['frame_id'] : null;
                    $sketchId = !empty($item['sketch_id']) ? (int)$item['sketch_id'] : null;
                } else {
                    // Legacy format: just an integer sketch ID
                    $sketchId = (int)$item;
                }

                // If no hardcoded frame ID is present, fetch the latest one for the sketch
                if (!$frameId && $sketchId) {
                    $fallbackStmt->execute([$sketchId]);
                    $frameId = $fallbackStmt->fetchColumn();
                }

                // If we successfully resolved a frame, link it to the new composite
                if ($frameId) {
                    $linkStmt->execute([$newCompId, (int)$frameId]);
                }
            }

            $pdo->commit();

            echo json_encode(['success' => true, 'composite_id' => $newCompId]);
            break;
        }

        
        
        

        default:
            throw new Exception("Unknown action: $action");
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// ── Helpers ──────────────────────────────────────────────────────────────────

function _syncElementIndex(PDO $pdo, int $arrId, int $canvasId, array $scene): void
{
    try {
        $pdo->prepare("DELETE FROM bang_elements WHERE arrangement_id = ?")->execute([$arrId]);

        $elements = $scene['elements'] ?? [];
        if (empty($elements)) return;

        $stmt = $pdo->prepare("
            INSERT INTO bang_elements (arrangement_id, canvas_id, element_uid, element_type, frame_id, text_content, x, y, z_index)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($elements as $el) {
            $uid     = $el['uid']      ?? uniqid('el_', true);
            $type    = $el['type']     ?? 'image';
            
            // Fix: Enforce Strict MySQL Enum Mapping mapping sub-shapes cleanly back to allowed format.
            if (in_array($type, ['shout', 'thought', 'whisper'])) {
                $type = 'balloon';
            }
            if (in_array($type, ['panel', 'speed_lines', 'impact_frame'])) {
                $type = 'shape'; // Prevent ENUM failures
            }
            
            $frameId = !empty($el['frame_id']) ? (int)$el['frame_id'] : null;
            $text    = $el['text']     ?? null;
            $x       = (float)($el['x'] ?? 0);
            $y       = (float)($el['y'] ?? 0);
            $z       = (int)($el['z_index'] ?? 0);

            $stmt->execute([$arrId, $canvasId, $uid, $type, $frameId, $text, $x, $y, $z]);
        }
    } catch (Exception $e) {
        error_log("BANG! element index sync failed: " . $e->getMessage());
    }
}