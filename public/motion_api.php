<?php
// public/motion_api.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

use App\Core\SpwBase;
use App\Core\FramesManager;

header('Content-Type: application/json');

$spw = SpwBase::getInstance();
$pdo = $spw->getPDO();
$fm = FramesManager::getInstance();

$action = $_REQUEST['action'] ?? '';

try {
    // --- DB INIT ---
    $pdo->exec("CREATE TABLE IF NOT EXISTS `motion_camera_presets` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `name` varchar(100) NOT NULL,
        `config` longtext NOT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // --- LOAD SETUP ---
    if ($action === 'load_setup') {
        $animaticId = (int)($_GET['animatic_id'] ?? 0);
        if (!$animaticId) throw new Exception("Animatic ID required");

        $stmt = $pdo->prepare("SELECT * FROM motion_setups WHERE animatic_id = ? ORDER BY is_active DESC, id DESC LIMIT 1");
        $stmt->execute([$animaticId]);
        $setup = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$setup) {
            $defaultEnv = json_encode(['scrollSpeed' => 0.5, 'altitude' => 20, 'bgBrightness' => 1.0, 'bgTint' => '#ffffff']);
            $stmtIns = $pdo->prepare("INSERT INTO motion_setups (animatic_id, name, is_active, environment_config) VALUES (?, 'Default Scenario', 1, ?)");
            $stmtIns->execute([$animaticId, $defaultEnv]);
            $stmt->execute([$animaticId]);
            $setup = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        $setupId = $setup['id'];

        // Sync Assets
        $stmtLayers = $pdo->prepare("SELECT frame_id, video_id, mesh_id, role, z_index FROM motion_layers WHERE motion_setup_id = ?");
        $stmtLayers->execute([$setupId]);
        $existing = $stmtLayers->fetchAll(PDO::FETCH_ASSOC);
        
        $mapExisting = []; $maxZ = 0;
        foreach($existing as $r) {
            if($r['frame_id']) $mapExisting['f'.$r['frame_id']] = true;
            if($r['video_id']) $mapExisting['v'.$r['video_id']] = true;
            if($r['mesh_id'])  $mapExisting['m'.$r['mesh_id']] = true;
            if($r['z_index'] > $maxZ) $maxZ = $r['z_index'];
        }

        $allFrames = $pdo->prepare("SELECT frame_id FROM animatic_frames WHERE animatic_id = ? ORDER BY created_at ASC");
        $allFrames->execute([$animaticId]);
        $frameList = $allFrames->fetchAll(PDO::FETCH_COLUMN);

        $allVideos = $pdo->prepare("SELECT video_id FROM animatic_videos WHERE animatic_id = ? ORDER BY created_at ASC");
        $allVideos->execute([$animaticId]);
        $videoList = $allVideos->fetchAll(PDO::FETCH_COLUMN);

        $allMeshes = $pdo->prepare("SELECT mesh_id FROM animatic_meshes WHERE animatic_id = ? ORDER BY created_at ASC");
        $allMeshes->execute([$animaticId]);
        $meshList = $allMeshes->fetchAll(PDO::FETCH_COLUMN);

        $pdo->beginTransaction();
        $layerIns = $pdo->prepare("INSERT INTO motion_layers (motion_setup_id, frame_id, video_id, mesh_id, role, z_index, layer_config) VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        foreach ($frameList as $fid) {
            if (!isset($mapExisting['f'.$fid])) {
                $role = ($maxZ === 0) ? 'background' : 'plane';
                $config = json_encode(['scaleX'=>1, 'scaleY'=>1, 'rotation'=>0]);
                $layerIns->execute([$setupId, $fid, null, null, $role, ++$maxZ, $config]);
            }
        }
        foreach ($videoList as $vid) {
            if (!isset($mapExisting['v'.$vid])) {
                $config = json_encode(['scaleX'=>1, 'scaleY'=>1, 'rotation'=>-1.57]);
                $layerIns->execute([$setupId, null, $vid, null, 'plane', 100 + (++$maxZ), $config]);
            }
        }
        foreach ($meshList as $mid) {
            if (!isset($mapExisting['m'.$mid])) {
                $config = json_encode(['scaleFactor'=>1.0, 'rotX'=>0, 'rotY'=>3.14, 'rotZ'=>0]);
                $layerIns->execute([$setupId, null, null, $mid, 'model3d', 200 + (++$maxZ), $config]);
            }
        }
        $pdo->commit();

        $sqlLayers = "
            SELECT ml.*, f.filename as frame_filename, v.url as video_url, m.filename as mesh_filename
            FROM motion_layers ml
            LEFT JOIN frames f ON ml.frame_id = f.id
            LEFT JOIN videos v ON ml.video_id = v.id
            LEFT JOIN meshes m ON ml.mesh_id = m.id
            WHERE ml.motion_setup_id = ?
            ORDER BY ml.z_index ASC
        ";
        $stmtL = $pdo->prepare($sqlLayers);
        $stmtL->execute([$setupId]);
        
        echo json_encode(['success' => true, 'setup' => $setup, 'layers' => $stmtL->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    // --- SAVE SETUP ---
    if ($action === 'save_setup') {
        $setupId = (int)$_POST['setup_id'];
        $envConfig = $_POST['environment_config'];
        $layersData = json_decode($_POST['layers_config'], true);

        $pdo->beginTransaction();
        $pdo->prepare("UPDATE motion_setups SET environment_config = ?, updated_at = NOW() WHERE id = ?")->execute([$envConfig, $setupId]);
        $stmtLayer = $pdo->prepare("UPDATE motion_layers SET layer_config = ?, role = ? WHERE id = ?");
        foreach ($layersData as $l) {
            $stmtLayer->execute([json_encode($l['config']), $l['role'], $l['id']]);
        }
        $pdo->commit();
        echo json_encode(['success' => true]);
        exit;
    }

    // --- PRESETS ---
    if ($action === 'list_camera_presets') {
        $stmt = $pdo->query("SELECT id, name, config FROM motion_camera_presets ORDER BY name ASC");
        echo json_encode(['success' => true, 'presets' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }
    if ($action === 'save_camera_preset') {
        $stmt = $pdo->prepare("INSERT INTO motion_camera_presets (name, config) VALUES (?, ?)");
        $stmt->execute([$_POST['name'], $_POST['config']]);
        echo json_encode(['success' => true]);
        exit;
    }
    if ($action === 'update_camera_preset') {
        $stmt = $pdo->prepare("UPDATE motion_camera_presets SET config = ? WHERE id = ?");
        $stmt->execute([$_POST['config'], (int)$_POST['id']]);
        echo json_encode(['success' => true]);
        exit;
    }

    // --- TAKES ---
    if ($action === 'list_takes') {
        $animaticId = (int)$_GET['animatic_id'];
        $stmt = $pdo->prepare("SELECT id, name, duration, created_at FROM motion_takes WHERE animatic_id = ? ORDER BY id DESC");
        $stmt->execute([$animaticId]);
        echo json_encode(['success' => true, 'takes' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }
    if ($action === 'load_take') {
        $takeId = (int)$_GET['take_id'];
        $stmt = $pdo->prepare("SELECT telemetry_data FROM motion_takes WHERE id = ?");
        $stmt->execute([$takeId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'telemetry' => json_decode($row['telemetry_data'])]);
        exit;
    }

    // --- SAVE VIDEO ---
    if ($action === 'save_video') {
        $animaticId = (int)$_POST['animatic_id'];
        if (!$animaticId) throw new Exception("Animatic ID required.");

        // 1. Save Telemetry
        if (isset($_POST['telemetry'])) {
            $telemetryJson = $_POST['telemetry'];
            $data = json_decode($telemetryJson, true);
            $duration = (is_array($data) && count($data) > 0) ? end($data)['time'] : 0;
            $stmtTake = $pdo->prepare("INSERT INTO motion_takes (animatic_id, name, telemetry_data, duration, created_at) VALUES (?, ?, ?, ?, NOW())");
            $takeName = "Take " . date("H:i:s");
            $stmtTake->execute([$animaticId, $takeName, $telemetryJson, $duration]);
        }

        // 2. Save Video
        $videoId = 0;
        if (isset($_FILES['video']) && $_FILES['video']['error'] === UPLOAD_ERR_OK) {
            $videosDir = $spw->getProjectPath() . '/public/videos';
            $thumbsDir = $videosDir . '/thumbnails';
            if (!is_dir($videosDir)) mkdir($videosDir, 0755, true);
            if (!is_dir($thumbsDir)) mkdir($thumbsDir, 0755, true);

            $pdo->exec("UPDATE video_counter SET next_video = next_video + 1");
            $stmt = $pdo->query("SELECT next_video FROM video_counter LIMIT 1");
            $nextId = $stmt->fetchColumn();
            $basename = "video" . str_pad($nextId, 7, '0', STR_PAD_LEFT);
            $ext = (isset($_POST['format']) && strpos($_POST['format'], 'mp4') !== false) ? 'mp4' : 'webm';
            $filename = $basename . '.' . $ext;
            $destPath = $videosDir . '/' . $filename;
            $thumbName = $basename . '.jpg';
            $thumbPath = $thumbsDir . '/' . $thumbName;

            if (move_uploaded_file($_FILES['video']['tmp_name'], $destPath)) {
                $im = imagecreatetruecolor(320, 180);
                $bg = imagecolorallocate($im, 40, 40, 40);
                imagefilledrectangle($im, 0, 0, 320, 180, $bg);
                imagejpeg($im, $thumbPath);
                imagedestroy($im);

                $mrId = $fm->createMapRun('motion_module', "Recording #$animaticId");
                $sql = "INSERT INTO videos (map_run_id, name, description, url, thumbnail, duration, type, file_size, width, height, created_at) VALUES (?, ?, ?, ?, ?, 0, ?, ?, 0, 0, NOW())";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$mrId, $filename, "Motion Rec", 'videos/'.$filename, 'videos/thumbnails/'.$thumbName, "video/$ext", filesize($destPath)]);
                $videoId = $pdo->lastInsertId();
                $pdo->prepare("INSERT INTO videos_2_animatics (from_id, to_id) VALUES (?, ?)")->execute([$videoId, $animaticId]);
            }
        }

        echo json_encode(['success' => true, 'video_id' => $videoId]);
        exit;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}