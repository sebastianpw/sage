<?php
// multiplane_api.php
require_once "error_reporting.php";
require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/json');

use App\Core\FramesManager;
use App\Core\SpwBase;

$spw = SpwBase::getInstance();
global $pdo; 
if (!isset($pdo)) $pdo = $spw->db;

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    if ($action === 'save') {
        // --- SAVE ARRANGEMENT ---
        $id = isset($_POST['id']) && !empty($_POST['id']) ? (int)$_POST['id'] : null;
        $composite_id = (int)$_POST['composite_id'];
        $name = $_POST['name'] ?? 'Arrangement';
        $config = $_POST['config'];

        if ($id) {
            $stmt = $pdo->prepare("UPDATE multiplane_arrangements SET layer_config = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$config, $id]);
            echo json_encode(['success' => true, 'id' => $id, 'message' => 'Arrangement updated']);
        } else {
            $stmt = $pdo->prepare("INSERT INTO multiplane_arrangements (composite_id, name, layer_config) VALUES (?, ?, ?)");
            $stmt->execute([$composite_id, $name, $config]);
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId(), 'message' => 'Arrangement created']);
        }
    } 
    
    elseif ($action === 'list') {
        // --- LIST ---
        $composite_id = (int)$_GET['composite_id'];
        $stmt = $pdo->prepare("SELECT id, name, updated_at FROM multiplane_arrangements WHERE composite_id = ? ORDER BY updated_at DESC");
        $stmt->execute([$composite_id]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } 
    
    elseif ($action === 'load') {
        // --- LOAD ---
        $id = (int)$_GET['id'];
        $stmt = $pdo->prepare("SELECT * FROM multiplane_arrangements WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $row]);
    }

    elseif ($action === 'export_render') {
        // --- EXPORT RENDER ---
        $composite_id = (int)$_POST['composite_id'];
        $layersJson = $_POST['layers']; 
        
        if (!$composite_id || !$layersJson) throw new Exception("Missing data");

        $layers = json_decode($layersJson, true);
        if (!is_array($layers)) throw new Exception("Invalid layer data");

        $projectRoot = $spw->getProjectPath(); 
        $publicRoot = $projectRoot . '/public';

        $pyLayers = [];
        foreach ($layers as $l) {
            $relPath = ltrim($l['filename'], '/');
            $absPath = $publicRoot . '/' . $relPath;
            
            $pyLayers[] = [
                'filepath' => $absPath,
                'x' => (float)$l['x'], 'y' => (float)$l['y'],
                'width' => (float)$l['width'], 'height' => (float)$l['height'],
                'scaleX' => (float)$l['scaleX'], 'scaleY' => (float)$l['scaleY'],
                'rotation' => (float)$l['rotation'], 'zIndex' => (int)$l['zIndex']
            ];
        }

        $pyPayload = json_encode(['layers' => $pyLayers, 'canvas_width' => 1024, 'canvas_height' => 1024]);

        $ch = curl_init("http://localhost:8009/image/render_composite");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $pyPayload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) throw new Exception("Renderer Error: " . $response);

        $pyRes = json_decode($response, true);
        if (!$pyRes || empty($pyRes['temp_path']) || !file_exists($pyRes['temp_path'])) throw new Exception("Invalid response");

        $fm = FramesManager::getInstance();
        $framesDirAbs = rtrim($spw->getFramesDir(), '/');
        $framesDirRel = rtrim($spw->getFramesDirRel(), '/');

        if (!is_dir($framesDirAbs)) mkdir($framesDirAbs, 0777, true);

        $basename = $fm->getNextFrameBasenameFromDB();
        $filename = $basename . '.png';
        $finalPath = $framesDirAbs . '/' . $filename;
        $filenameRel = $framesDirRel . '/' . $filename;

        if (!rename($pyRes['temp_path'], $finalPath)) {
            if (copy($pyRes['temp_path'], $finalPath)) unlink($pyRes['temp_path']);
            else throw new Exception("Failed to move file");
        }

        $pdo->beginTransaction();
        try {
            $stmtC = $pdo->prepare("SELECT name FROM composites WHERE id = ?");
            $stmtC->execute([$composite_id]);
            $compName = $stmtC->fetchColumn() ?: 'Unknown';

            $mapRunId = $fm->createMapRun('composites', "HQ Render: $compName");
            $style = 'multiplane'; $style_id = 999;

            $stmtF = $pdo->prepare("INSERT INTO frames (map_run_id, name, filename, prompt, entity_type, entity_id, style, style_id, created_at) VALUES (?, ?, ?, ?, 'composites', ?, ?, ?, NOW())");
            $prompt = "Multiplane Render of ID $composite_id";
            $stmtF->execute([$mapRunId, $basename, $filenameRel, $prompt, $composite_id, $style, $style_id]);
            $newFrameId = $pdo->lastInsertId();

            $stmtLink = $pdo->prepare("INSERT INTO frames_2_composites (from_id, to_id) VALUES (?, ?)");
            $stmtLink->execute([$newFrameId, $composite_id]);

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'High Quality Frame Rendered!', 'frame_id' => $newFrameId, 'filename' => $filenameRel]);
        } catch (Exception $e) {
            $pdo->rollBack();
            if (file_exists($finalPath)) @unlink($finalPath);
            throw $e;
        }
    }
    
    // --- VIDEO SETTINGS ACTIONS ---
    elseif ($action === 'get_video_settings') {
        $composite_id = (int)$_GET['composite_id'];
        $stmt = $pdo->prepare("SELECT * FROM multiplane_settings WHERE composite_id = ?");
        $stmt->execute([$composite_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$row) {
            $row = [
                'frames' => 60, 'fps' => 30, 'move_x' => 100, 'move_y' => 0, 
                'zoom_start' => 1.0, 'zoom_end' => 1.05,
                'focal_distance' => 10.0,
                'scale_reference' => 10.0,
                'frustum_height' => 10.0
            ];
        }
        echo json_encode(['success' => true, 'data' => $row]);
    }

    elseif ($action === 'save_video_settings') {
        $composite_id = (int)$_POST['composite_id'];
        $frames = (int)$_POST['frames'];
        $fps = (int)$_POST['fps'];
        $move_x = (int)$_POST['move_x'];
        $move_y = (int)$_POST['move_y'];
        $zoom_start = (float)$_POST['zoom_start'];
        $zoom_end = (float)$_POST['zoom_end'];
        $focal_distance = (float)($_POST['focal_distance'] ?? 10.0);
        $scale_reference = (float)($_POST['scale_reference'] ?? 10.0);
        $frustum_height = (float)($_POST['frustum_height'] ?? 10.0);
        
        $sql = "INSERT INTO multiplane_settings (composite_id, frames, fps, move_x, move_y, zoom_start, zoom_end, focal_distance, scale_reference, frustum_height, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                frames = VALUES(frames), fps = VALUES(fps), move_x = VALUES(move_x), move_y = VALUES(move_y),
                zoom_start = VALUES(zoom_start), zoom_end = VALUES(zoom_end), 
                focal_distance = VALUES(focal_distance), scale_reference = VALUES(scale_reference), frustum_height = VALUES(frustum_height),
                updated_at = NOW()";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$composite_id, $frames, $fps, $move_x, $move_y, $zoom_start, $zoom_end, $focal_distance, $scale_reference, $frustum_height]);
        
        echo json_encode(['success' => true, 'message' => 'Video settings saved successfully']);
    }

    // --- LAYER SETTINGS ACTIONS ---
    elseif ($action === 'get_layer_settings') {
        $composite_id = (int)$_GET['composite_id'];
        $frame_id = (int)$_GET['frame_id'];
        $stmt = $pdo->prepare("SELECT * FROM multiplane_layers WHERE composite_id = ? AND frame_id = ?");
        $stmt->execute([$composite_id, $frame_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$row) {
            $row = ['speed' => 0.5, 'z_index' => 0, 'distance' => null, 'real_height' => null];
        }
        echo json_encode(['success' => true, 'data' => $row]);
    }

    elseif ($action === 'save_layer_settings') {
        $composite_id = (int)$_POST['composite_id'];
        $frame_id = (int)$_POST['frame_id'];
        $speed = (float)$_POST['speed'];
        $z_index = (int)$_POST['z_index'];
        $distance = !empty($_POST['distance']) ? (float)$_POST['distance'] : 10.0;
        $real_height = !empty($_POST['real_height']) ? (float)$_POST['real_height'] : null;
        
        $sql = "INSERT INTO multiplane_layers (composite_id, frame_id, speed, z_index, distance, real_height)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                speed = VALUES(speed), z_index = VALUES(z_index), distance = VALUES(distance), real_height = VALUES(real_height)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$composite_id, $frame_id, $speed, $z_index, $distance, $real_height]);
        echo json_encode(['success' => true, 'message' => 'Layer settings saved successfully']);
    }

    else {
        throw new Exception("Invalid action");
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>