<?php
// public/color_grade_api.php
// Save / load color grade profiles and presets.
// Called from the Grade tab in ImageEditorModule.

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

use App\Core\FramesManager;
use App\Core\SpwBase;

header('Content-Type: application/json; charset=utf-8');

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

$action = $data['action'] ?? null;
$userId = $_SESSION['user_id'] ?? null;

try {
    switch ($action) {

        // ── LIST PRESETS ──────────────────────────────────────────────────
        case 'list_presets': {
            $rows = [];
            $res  = $mysqli->query(
                "SELECT id, name, description, settings_json, thumbnail_frame_id, created_at
                 FROM color_grade_presets
                 ORDER BY name ASC"
            );
            while ($r = $res->fetch_assoc()) {
                $r['settings'] = json_decode($r['settings_json'], true);
                unset($r['settings_json']);
                $rows[] = $r;
            }
            echo json_encode(['success' => true, 'presets' => $rows]);
            break;
        }

        // ── SAVE PRESET ───────────────────────────────────────────────────
        case 'save_preset': {
            $name     = trim($data['name'] ?? '');
            $settings = $data['settings'] ?? null;
            $desc     = trim($data['description'] ?? '');
            $thumbId  = !empty($data['thumbnail_frame_id']) ? intval($data['thumbnail_frame_id']) : null;

            if (!$name)     throw new Exception('Preset name is required');
            if (!$settings) throw new Exception('Settings are required');

            $settingsJson = json_encode($settings, JSON_UNESCAPED_UNICODE);

            // Upsert by name
            $stmt = $mysqli->prepare(
                "INSERT INTO color_grade_presets
                    (name, description, settings_json, thumbnail_frame_id, created_by)
                 VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    description        = VALUES(description),
                    settings_json      = VALUES(settings_json),
                    thumbnail_frame_id = VALUES(thumbnail_frame_id),
                    updated_at         = NOW()"
            );
            $stmt->bind_param('sssii', $name, $desc, $settingsJson, $thumbId, $userId);
            $stmt->execute();
            $insertId = $stmt->insert_id ?: null;
            $stmt->close();

            // Fetch back the saved row id
            $res = $mysqli->query(
                "SELECT id FROM color_grade_presets WHERE name = '" . $mysqli->real_escape_string($name) . "' LIMIT 1"
            );
            $row = $res->fetch_assoc();

            echo json_encode(['success' => true, 'preset_id' => intval($row['id']), 'message' => 'Preset saved']);
            break;
        }

        // ── DELETE PRESET ─────────────────────────────────────────────────
        case 'delete_preset': {
            $presetId = intval($data['preset_id'] ?? 0);
            if (!$presetId) throw new Exception('Invalid preset_id');

            $mysqli->query("DELETE FROM color_grade_presets WHERE id = $presetId");
            echo json_encode(['success' => true, 'message' => 'Preset deleted']);
            break;
        }

        // ── RENDER (Pillow) + REGISTER ────────────────────────────────────
        // Called when user hits Save in the Grade tab.
        // The JS has already been doing canvas previews; this does the
        // full-quality Pillow render and then registers via FramesManager.
        case 'render_and_save': {
            $frameId    = intval($data['frame_id']    ?? 0);
            $settings   = $data['settings']           ?? null;
            $presetId   = !empty($data['preset_id'])  ? intval($data['preset_id'])  : null;
            $note       = trim($data['note']          ?? '');
            $operations = $data['operations']         ?? ['Color Grade'];

            if (!$frameId)  throw new Exception('frame_id is required');
            if (!$settings) throw new Exception('settings are required');

            $fm   = FramesManager::getInstance();
            $spw  = SpwBase::getInstance();

            // Load original frame
            $orig = $fm->loadFrameRow($frameId);
            if (!$orig) throw new Exception("Frame $frameId not found");

            $sourceFilename  = $orig['filename'];
            $sourceFullPath  = $spw->getProjectPath() . '/public/' . ltrim($sourceFilename, '/');
            if (!file_exists($sourceFullPath)) throw new Exception("Source file not found: $sourceFilename");

            // ── Call Pillow grade endpoint ────────────────────────────────
            $pyApiUrl    = 'http://127.0.0.1:8009/image/grade';
            $settingsJson = json_encode($settings, JSON_UNESCAPED_UNICODE);

            $postData = [
                'file'          => new CURLFile($sourceFullPath, mime_content_type($sourceFullPath), basename($sourceFullPath)),
                'settings_json' => $settingsJson,
            ];

            $ch = curl_init($pyApiUrl);
            curl_setopt($ch, CURLOPT_POST,          1);
            curl_setopt($ch, CURLOPT_POSTFIELDS,    $postData);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT,30);
            curl_setopt($ch, CURLOPT_TIMEOUT,       120);
            $responseBody = curl_exec($ch);
            $httpCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError    = curl_error($ch);
            curl_close($ch);

            if ($curlError) throw new Exception("PyAPI cURL error: $curlError");
            if ($httpCode !== 200) {
                $errJson = json_decode($responseBody, true);
                $detail  = $errJson['detail'] ?? $responseBody;
                throw new Exception("PyAPI returned HTTP $httpCode: $detail");
            }

            // ── Save rendered image to frames dir ─────────────────────────
            $basename   = $fm->getNextFrameBasenameFromDB();
            $pi         = pathinfo($sourceFilename);
            $ext        = $pi['extension'] ?? 'png';
            $framesDir  = rtrim($spw->getFramesDirRel(), '/');
            $finalRel   = $framesDir . '/' . $basename . '.' . $ext;
            $finalFull  = $spw->getProjectPath() . '/public/' . ltrim($finalRel, '/');

            if (file_put_contents($finalFull, $responseBody) === false) {
                throw new Exception('Failed to write graded image to disk');
            }

            // ── Register via FramesManager ────────────────────────────────
            $opText = !empty($note) ? $note : ('Color grade: ' . implode(', ', $operations));
            $result = $fm->registerDerivedFrameFromOriginal($orig, $finalRel, null, [
                'tool'   => 'color-grade',
                'mode'   => 'grade',
                'userId' => $userId,
                'note'   => $opText,
                'coords' => ['grade_settings' => $settings],
            ]);

            if (empty($result['success'])) throw new Exception($result['message'] ?? 'Frame registration failed');

            // Mark applied
            $fm->applyVersion($result['image_edit_id']);

            // ── Log to color_grade_profiles ───────────────────────────────
            $settingsJsonDb = json_encode($settings, JSON_UNESCAPED_UNICODE);
            $entityType     = $orig['entity_type'] ?? '';
            $entityId       = intval($orig['entity_id'] ?? 0);
            $derivedFrameId = intval($result['new_frame_id']);
            $mapRunId       = intval($result['map_run_id']);
            $noteEsc        = $mysqli->real_escape_string($opText);
            $presetIdVal    = $presetId ? intval($presetId) : 'NULL';

            $mysqli->query(
                "INSERT INTO color_grade_profiles
                    (frame_id, entity_type, entity_id, preset_id, settings_json,
                     derived_frame_id, map_run_id, created_by, note)
                 VALUES
                    ($frameId, '$entityType', $entityId, $presetIdVal, '$settingsJsonDb',
                     $derivedFrameId, $mapRunId, " . ($userId ? intval($userId) : 'NULL') . ", '$noteEsc')"
            );

            echo json_encode([
                'success'          => true,
                'message'          => 'Grade saved',
                'new_frame_id'     => $derivedFrameId,
                'map_run_id'       => $mapRunId,
                'filename'         => $finalRel,
                'image_edit_id'    => intval($result['image_edit_id']),
            ]);
            break;
        }

        default:
            throw new Exception("Unknown action: $action");
    }

} catch (Throwable $e) {
    error_log("color_grade_api.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
