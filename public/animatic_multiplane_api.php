<?php
// public/animatic_multiplane_api.php  v3
// SAGE AI — MultiVid 2.5D API
require_once "error_reporting.php";
require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/json');

use App\Core\SpwBase;
use App\Core\FramesManager;

$spw = SpwBase::getInstance();
global $pdo;
if (!isset($pdo)) $pdo = $spw->getPDO();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

function ensureMultividTables(\PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `multivid_arrangements` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `animatic_id` int(11) NOT NULL,
        `name` varchar(100) NOT NULL DEFAULT 'Untitled Arrangement',
        `description` text DEFAULT NULL,
        `layer_config` longtext DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `idx_animatic` (`animatic_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `multivid_layers` (
        `animatic_id` int(11) NOT NULL,
        `asset_type` enum('frame','video') NOT NULL DEFAULT 'frame',
        `asset_id` int(11) NOT NULL,
        `z_index` int(11) NOT NULL DEFAULT 0,
        `speed` float NOT NULL DEFAULT 0.5,
        `distance` float NOT NULL DEFAULT 10,
        `real_height` float DEFAULT NULL,
        `opacity` float NOT NULL DEFAULT 1.0,
        `start_offset` float NOT NULL DEFAULT 0.0,
        `end_offset` float DEFAULT NULL,
        `playback_speed` float NOT NULL DEFAULT 1.0,
        PRIMARY KEY (`animatic_id`, `asset_type`, `asset_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci");

    try {
        $cols = $pdo->query("SHOW COLUMNS FROM multivid_layers")->fetchAll(\PDO::FETCH_COLUMN);
        if (!in_array('start_offset', $cols))
            $pdo->exec("ALTER TABLE `multivid_layers` ADD COLUMN `start_offset` float NOT NULL DEFAULT 0.0");
        if (!in_array('end_offset', $cols))
            $pdo->exec("ALTER TABLE `multivid_layers` ADD COLUMN `end_offset` float DEFAULT NULL");
        if (!in_array('playback_speed', $cols))
            $pdo->exec("ALTER TABLE `multivid_layers` ADD COLUMN `playback_speed` float NOT NULL DEFAULT 1.0");
    } catch (\Exception $e) { /* table may not exist yet, ignore */ }

    $pdo->exec("CREATE TABLE IF NOT EXISTS `multivid_settings` (
        `animatic_id` int(11) NOT NULL,
        `duration_ms` int(11) NOT NULL DEFAULT 3000,
        `fps` int(11) NOT NULL DEFAULT 30,
        `move_x` int(11) NOT NULL DEFAULT 80,
        `move_y` int(11) NOT NULL DEFAULT 0,
        `zoom_start` float NOT NULL DEFAULT 1.0,
        `zoom_end` float NOT NULL DEFAULT 1.04,
        `focal_distance` float NOT NULL DEFAULT 10.0,
        `frustum_height` float NOT NULL DEFAULT 10.0,
        `scale_reference` float NOT NULL DEFAULT 10.0,
        `canvas_width` int(11) NOT NULL DEFAULT 1024,
        `canvas_height` int(11) NOT NULL DEFAULT 1024,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`animatic_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `multivid_render_jobs` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `animatic_id` int(11) NOT NULL,
        `arrangement_id` int(11) DEFAULT NULL,
        `status` enum('queued','processing','completed','failed') NOT NULL DEFAULT 'queued',
        `task_id` varchar(64) DEFAULT NULL,
        `video_id` int(11) DEFAULT NULL,
        `error_msg` text DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `idx_animatic` (`animatic_id`),
        KEY `idx_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci");
}

try {
    ensureMultividTables($pdo);

    // ── List animatics (paginated, searchable) ────────────────────────────────
    if ($action === 'list_animatics') {
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 10;
        $q       = trim($_GET['q'] ?? '');
        $offset  = (int)(($page - 1) * $perPage);
        $limit   = (int)$perPage;

        if ($q !== '') {
            $like = '%' . $q . '%';
            $totalStmt = $pdo->prepare("SELECT COUNT(*) FROM animatics WHERE id = ? OR name LIKE ?");
            $totalStmt->execute([(is_numeric($q) ? (int)$q : -1), $like]);
            $total = (int)$totalStmt->fetchColumn();

            $stmt = $pdo->prepare("SELECT id, name, description FROM animatics WHERE id = ? OR name LIKE ? ORDER BY id DESC LIMIT $limit OFFSET $offset");
            $stmt->execute([(is_numeric($q) ? (int)$q : -1), $like]);
        } else {
            $total = (int)$pdo->query("SELECT COUNT(*) FROM animatics")->fetchColumn();
            $stmt  = $pdo->prepare("SELECT id, name, description FROM animatics ORDER BY id DESC LIMIT $limit OFFSET $offset");
            $stmt->execute();
        }

        $totalPages = max(1, (int)ceil($total / $perPage));
        echo json_encode([
            'success'     => true,
            'data'        => $stmt->fetchAll(\PDO::FETCH_ASSOC),
            'total'       => $total,
            'total_pages' => $totalPages,
            'page'        => $page,
        ]);
    }

    // ── Create animatic ───────────────────────────────────────────────────────
    elseif ($action === 'create_animatic') {
        $name = trim($_POST['name'] ?? '');
        if ($name === '') throw new \Exception("Name is required");
        $pdo->prepare("INSERT INTO animatics (name, created_at, updated_at) VALUES (?, NOW(), NOW())")
            ->execute([$name]);
        $id = (int)$pdo->lastInsertId();
        echo json_encode(['success' => true, 'id' => $id]);
    }

    // ── Browse frames or videos (paginated, searchable) ───────────────────────
    elseif ($action === 'browse_assets') {
        $type    = ($_GET['asset_type'] ?? '') === 'video' ? 'video' : 'frame';
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 12;
        $q       = trim($_GET['q'] ?? '');
        $offset  = (int)(($page - 1) * $perPage);
        $limit   = (int)$perPage;

        if ($type === 'frame') {
            if ($q !== '') {
                $like = '%' . $q . '%';
                $idQ  = is_numeric($q) ? (int)$q : -1;
                $totalStmt = $pdo->prepare("SELECT COUNT(*) FROM frames WHERE id = ? OR name LIKE ? OR filename LIKE ?");
                $totalStmt->execute([$idQ, $like, $like]);
                $total = (int)$totalStmt->fetchColumn();
                $stmt  = $pdo->prepare("SELECT id, filename, name FROM frames WHERE id = ? OR name LIKE ? OR filename LIKE ? ORDER BY id DESC LIMIT $limit OFFSET $offset");
                $stmt->execute([$idQ, $like, $like]);
            } else {
                $total = (int)$pdo->query("SELECT COUNT(*) FROM frames")->fetchColumn();
                $stmt  = $pdo->prepare("SELECT id, filename, name FROM frames ORDER BY id DESC LIMIT $limit OFFSET $offset");
                $stmt->execute();
            }
        } else {
            if ($q !== '') {
                $like = '%' . $q . '%';
                $idQ  = is_numeric($q) ? (int)$q : -1;
                $totalStmt = $pdo->prepare("SELECT COUNT(*) FROM videos WHERE id = ? OR name LIKE ? OR url LIKE ?");
                $totalStmt->execute([$idQ, $like, $like]);
                $total = (int)$totalStmt->fetchColumn();
                $stmt  = $pdo->prepare("SELECT id, url, name, thumbnail FROM videos WHERE id = ? OR name LIKE ? OR url LIKE ? ORDER BY id DESC LIMIT $limit OFFSET $offset");
                $stmt->execute([$idQ, $like, $like]);
            } else {
                $total = (int)$pdo->query("SELECT COUNT(*) FROM videos")->fetchColumn();
                $stmt  = $pdo->prepare("SELECT id, url, name, thumbnail FROM videos ORDER BY id DESC LIMIT $limit OFFSET $offset");
                $stmt->execute();
            }
        }

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        if ($type === 'frame') {
            foreach ($rows as &$r) {
                if (!isset($r['name']) || $r['name'] === '') $r['name'] = $r['filename'];
            }
            unset($r);
        } else {
            foreach ($rows as &$r) {
                $r['filename'] = $r['url'] ?? '';
                if (!isset($r['name']) || $r['name'] === '') $r['name'] = $r['url'];
            }
            unset($r);
        }

        $totalPages = max(1, (int)ceil($total / $perPage));
        echo json_encode([
            'success'     => true,
            'data'        => $rows,
            'total'       => $total,
            'total_pages' => $totalPages,
            'page'        => $page,
        ]);
    }

    // ── Assign frame to animatic ──────────────────────────────────────────────
    elseif ($action === 'assign_frame') {
        $aid     = (int)$_POST['animatic_id'];
        $frameId = (int)$_POST['frame_id'];
        if (!$aid || !$frameId) throw new \Exception("animatic_id and frame_id required");
        $chk = $pdo->prepare("SELECT id FROM frames WHERE id = ?");
        $chk->execute([$frameId]);
        if (!$chk->fetch()) throw new \Exception("Frame #$frameId not found");
        $pdo->prepare("INSERT IGNORE INTO animatic_frames (animatic_id, frame_id, created_at) VALUES (?, ?, NOW())")
            ->execute([$aid, $frameId]);
        echo json_encode(['success' => true]);
    }

    // ── Assign video to animatic ──────────────────────────────────────────────
    elseif ($action === 'assign_video') {
        $aid     = (int)$_POST['animatic_id'];
        $videoId = (int)$_POST['video_id'];
        if (!$aid || !$videoId) throw new \Exception("animatic_id and video_id required");
        $chk = $pdo->prepare("SELECT id FROM videos WHERE id = ?");
        $chk->execute([$videoId]);
        if (!$chk->fetch()) throw new \Exception("Video #$videoId not found");
        $pdo->prepare("INSERT IGNORE INTO animatic_videos (animatic_id, video_id, created_at) VALUES (?, ?, NOW())")
            ->execute([$aid, $videoId]);
        echo json_encode(['success' => true]);
    }

    // ── Unassign frame from animatic ──────────────────────────────────────────
    elseif ($action === 'unassign_frame') {
        $aid     = (int)$_POST['animatic_id'];
        $frameId = (int)$_POST['frame_id'];
        if (!$aid || !$frameId) throw new \Exception("animatic_id and frame_id required");
        $pdo->prepare("DELETE FROM animatic_frames WHERE animatic_id = ? AND frame_id = ?")
            ->execute([$aid, $frameId]);
        // Also clean up layer settings for this asset
        $pdo->prepare("DELETE FROM multivid_layers WHERE animatic_id = ? AND asset_type = 'frame' AND asset_id = ?")
            ->execute([$aid, $frameId]);
        echo json_encode(['success' => true]);
    }

    // ── Unassign video from animatic ──────────────────────────────────────────
    elseif ($action === 'unassign_video') {
        $aid     = (int)$_POST['animatic_id'];
        $videoId = (int)$_POST['video_id'];
        if (!$aid || !$videoId) throw new \Exception("animatic_id and video_id required");
        $pdo->prepare("DELETE FROM animatic_videos WHERE animatic_id = ? AND video_id = ?")
            ->execute([$aid, $videoId]);
        // Also clean up layer settings for this asset
        $pdo->prepare("DELETE FROM multivid_layers WHERE animatic_id = ? AND asset_type = 'video' AND asset_id = ?")
            ->execute([$aid, $videoId]);
        echo json_encode(['success' => true]);
    }

    elseif ($action === 'save_arrangement') {
        $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
        $aid = (int)$_POST['animatic_id'];
        $name = trim($_POST['name'] ?? 'Arrangement');
        $config = $_POST['config'] ?? '{}';
        if ($id) {
            $pdo->prepare("UPDATE multivid_arrangements SET name=?, layer_config=?, updated_at=NOW() WHERE id=?")
                ->execute([$name, $config, $id]);
            echo json_encode(['success' => true, 'id' => $id]);
        } else {
            $pdo->prepare("INSERT INTO multivid_arrangements (animatic_id, name, layer_config) VALUES (?,?,?)")
                ->execute([$aid, $name, $config]);
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
        }
    }

    elseif ($action === 'list_arrangements') {
        $aid = (int)$_GET['animatic_id'];
        $stmt = $pdo->prepare("SELECT id, name, updated_at FROM multivid_arrangements WHERE animatic_id=? ORDER BY updated_at DESC");
        $stmt->execute([$aid]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(\PDO::FETCH_ASSOC)]);
    }

    elseif ($action === 'load_arrangement') {
        $stmt = $pdo->prepare("SELECT * FROM multivid_arrangements WHERE id=?");
        $stmt->execute([(int)$_GET['id']]);
        echo json_encode(['success' => true, 'data' => $stmt->fetch(\PDO::FETCH_ASSOC)]);
    }

    elseif ($action === 'delete_arrangement') {
        $pdo->prepare("DELETE FROM multivid_arrangements WHERE id=?")->execute([(int)$_POST['id']]);
        echo json_encode(['success' => true]);
    }

    elseif ($action === 'get_settings') {
        $aid = (int)$_GET['animatic_id'];
        $stmt = $pdo->prepare("SELECT * FROM multivid_settings WHERE animatic_id=?");
        $stmt->execute([$aid]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [
            'animatic_id' => $aid, 'duration_ms' => 3000, 'fps' => 30,
            'move_x' => 80, 'move_y' => 0, 'zoom_start' => 1.0, 'zoom_end' => 1.04,
            'focal_distance' => 10.0, 'frustum_height' => 10.0, 'scale_reference' => 10.0,
            'canvas_width' => 1024, 'canvas_height' => 1024,
        ];
        echo json_encode(['success' => true, 'data' => $row]);
    }

    elseif ($action === 'save_settings') {
        $aid = (int)$_POST['animatic_id'];
        $zs  = max(0.1, (float)$_POST['zoom_start']);
        $ze  = max(0.1, (float)$_POST['zoom_end']);
        $sql = "INSERT INTO multivid_settings
                    (animatic_id,duration_ms,fps,move_x,move_y,zoom_start,zoom_end,
                     focal_distance,frustum_height,scale_reference,canvas_width,canvas_height,created_at,updated_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())
                ON DUPLICATE KEY UPDATE
                    duration_ms=VALUES(duration_ms),fps=VALUES(fps),
                    move_x=VALUES(move_x),move_y=VALUES(move_y),
                    zoom_start=VALUES(zoom_start),zoom_end=VALUES(zoom_end),
                    focal_distance=VALUES(focal_distance),frustum_height=VALUES(frustum_height),
                    scale_reference=VALUES(scale_reference),
                    canvas_width=VALUES(canvas_width),canvas_height=VALUES(canvas_height),
                    updated_at=NOW()";
        $pdo->prepare($sql)->execute([
            $aid, (int)$_POST['duration_ms'], (int)$_POST['fps'],
            (int)$_POST['move_x'], (int)$_POST['move_y'], $zs, $ze,
            (float)$_POST['focal_distance'], (float)$_POST['frustum_height'],
            (float)$_POST['scale_reference'],
            (int)$_POST['canvas_width'], (int)$_POST['canvas_height'],
        ]);
        echo json_encode(['success' => true]);
    }

    elseif ($action === 'get_layer_settings') {
        $aid  = (int)$_GET['animatic_id'];
        $type = $_GET['asset_type'] === 'video' ? 'video' : 'frame';
        $aid2 = (int)$_GET['asset_id'];
        $stmt = $pdo->prepare("SELECT * FROM multivid_layers WHERE animatic_id=? AND asset_type=? AND asset_id=?");
        $stmt->execute([$aid, $type, $aid2]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [
            'speed' => 0.5, 'z_index' => 0, 'distance' => 10.0,
            'real_height' => null, 'opacity' => 1.0,
            'start_offset' => 0.0, 'end_offset' => null, 'playback_speed' => 1.0,
        ];
        echo json_encode(['success' => true, 'data' => $row]);
    }

    elseif ($action === 'save_layer_settings') {
        $aid      = (int)$_POST['animatic_id'];
        $type     = $_POST['asset_type'] === 'video' ? 'video' : 'frame';
        $aid2     = (int)$_POST['asset_id'];
        $speed    = (float)$_POST['speed'];
        $zindex   = (int)$_POST['z_index'];
        $dist     = (float)($_POST['distance'] ?? 10.0);
        $rh       = !empty($_POST['real_height']) ? (float)$_POST['real_height'] : null;
        $opacity  = min(1.0, max(0.0, (float)($_POST['opacity'] ?? 1.0)));
        $startOff = max(0.0, (float)($_POST['start_offset'] ?? 0.0));
        $endOff   = isset($_POST['end_offset']) && $_POST['end_offset'] !== '' ? (float)$_POST['end_offset'] : null;
        $pbSpeed  = max(0.1, (float)($_POST['playback_speed'] ?? 1.0));

        $pdo->prepare("INSERT INTO multivid_layers
                (animatic_id,asset_type,asset_id,z_index,speed,distance,real_height,opacity,start_offset,end_offset,playback_speed)
            VALUES (?,?,?,?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE
                z_index=VALUES(z_index),speed=VALUES(speed),distance=VALUES(distance),
                real_height=VALUES(real_height),opacity=VALUES(opacity),
                start_offset=VALUES(start_offset),end_offset=VALUES(end_offset),
                playback_speed=VALUES(playback_speed)")
            ->execute([$aid, $type, $aid2, $zindex, $speed, $dist, $rh, $opacity, $startOff, $endOff, $pbSpeed]);
        echo json_encode(['success' => true]);
    }

    elseif ($action === 'get_assets') {
        $aid = (int)$_GET['animatic_id'];

        $stmtF = $pdo->prepare("
            SELECT f.id, f.filename, f.name, 'frame' as asset_type,
                   COALESCE(ml.z_index,0) z_index, COALESCE(ml.speed,0.5) speed,
                   COALESCE(ml.distance,10.0) distance, ml.real_height,
                   COALESCE(ml.opacity,1.0) opacity,
                   COALESCE(ml.start_offset,0.0) start_offset,
                   ml.end_offset,
                   COALESCE(ml.playback_speed,1.0) playback_speed
            FROM animatic_frames af
            JOIN frames f ON af.frame_id=f.id
            LEFT JOIN multivid_layers ml ON (ml.animatic_id=af.animatic_id AND ml.asset_type='frame' AND ml.asset_id=f.id)
            WHERE af.animatic_id=?
            ORDER BY COALESCE(ml.z_index,0) ASC, af.created_at ASC");
        $stmtF->execute([$aid]);

        $stmtV = $pdo->prepare("
            SELECT v.id, v.url as filename, v.name, v.type as mime_type,
                   COALESCE(v.duration,0) as duration_s, 'video' as asset_type,
                   COALESCE(ml.z_index,50) z_index, COALESCE(ml.speed,0.7) speed,
                   COALESCE(ml.distance,8.0) distance, ml.real_height,
                   COALESCE(ml.opacity,1.0) opacity,
                   COALESCE(ml.start_offset,0.0) start_offset,
                   ml.end_offset,
                   COALESCE(ml.playback_speed,1.0) playback_speed
            FROM animatic_videos av
            JOIN videos v ON av.video_id=v.id
            LEFT JOIN multivid_layers ml ON (ml.animatic_id=av.animatic_id AND ml.asset_type='video' AND ml.asset_id=v.id)
            WHERE av.animatic_id=?
            ORDER BY COALESCE(ml.z_index,50) ASC, av.created_at ASC");
        $stmtV->execute([$aid]);

        echo json_encode(['success' => true,
            'frames' => $stmtF->fetchAll(\PDO::FETCH_ASSOC),
            'videos' => $stmtV->fetchAll(\PDO::FETCH_ASSOC)]);
    }

    elseif ($action === 'register_video') {
        $aid = (int)$_POST['animatic_id'];
        if (!$aid) throw new \Exception("animatic_id required");
        if (!isset($_FILES['video']) || $_FILES['video']['error'] !== UPLOAD_ERR_OK)
            throw new \Exception("No video file");

        $fm        = FramesManager::getInstance();
        $videosDir = $spw->getProjectPath() . '/public/videos';
        $thumbsDir = $videosDir . '/thumbnails';
        if (!is_dir($videosDir)) mkdir($videosDir, 0755, true);
        if (!is_dir($thumbsDir)) mkdir($thumbsDir, 0755, true);

        $pdo->exec("UPDATE video_counter SET next_video = next_video + 1");
        $nextId   = (int)$pdo->query("SELECT next_video FROM video_counter LIMIT 1")->fetchColumn();
        $basename = "video" . str_pad($nextId, 7, '0', STR_PAD_LEFT);
        $mime     = $_FILES['video']['type'] ?? 'video/webm';
        $ext      = (strpos($mime, 'mp4') !== false) ? 'mp4' : 'webm';
        $filename = $basename . '.' . $ext;
        $destPath = $videosDir . '/' . $filename;
        $thumbName= $basename . '.jpg';
        $thumbPath= $thumbsDir . '/' . $thumbName;

        if (!move_uploaded_file($_FILES['video']['tmp_name'], $destPath))
            throw new \Exception("Failed to move uploaded video");

        $im = imagecreatetruecolor(320, 180);
        imagefilledrectangle($im, 0, 0, 320, 180, imagecolorallocate($im, 20, 24, 36));
        imagestring($im, 3, 10, 80, 'MultiVid Preview Export', imagecolorallocate($im, 0, 200, 160));
        imagejpeg($im, $thumbPath, 85);
        imagedestroy($im);

        $mrId  = $fm->createMapRun('animatics', "MultiVid Preview animatic#$aid");
        $stmtN = $pdo->prepare("SELECT name FROM animatics WHERE id=?");
        $stmtN->execute([$aid]);
        $aName = $stmtN->fetchColumn() ?: "Animatic #$aid";

        $pdo->prepare("INSERT INTO videos (map_run_id,name,description,url,thumbnail,duration,type,file_size,width,height,created_at)
                        VALUES (?,?,?,?,?,?,?,?,?,?,NOW())")
            ->execute([$mrId, $filename, "MultiVid Preview: $aName",
                "videos/$filename", "videos/thumbnails/$thumbName",
                (int)($_POST['duration_s'] ?? 0), "video/$ext", filesize($destPath),
                (int)($_POST['canvas_width'] ?? 1024), (int)($_POST['canvas_height'] ?? 1024)]);
        $videoId = (int)$pdo->lastInsertId();
        $pdo->prepare("INSERT IGNORE INTO videos_2_animatics (from_id,to_id) VALUES (?,?)")->execute([$videoId, $aid]);
        echo json_encode(['success' => true, 'video_id' => $videoId, 'url' => "videos/$filename"]);
    }

    elseif ($action === 'queue_render') {
        $aid    = (int)$_POST['animatic_id'];
        $arr_id = !empty($_POST['arrangement_id']) ? (int)$_POST['arrangement_id'] : null;
        if (!$aid)    throw new \Exception("animatic_id required");
        if (!$arr_id) throw new \Exception("arrangement_id required — save arrangement first");

        $pdo->prepare("INSERT INTO multivid_render_jobs (animatic_id, arrangement_id, status) VALUES (?,?,'queued')")
            ->execute([$aid, $arr_id]);
        $jobId = (int)$pdo->lastInsertId();

        $projectRoot = $spw->getProjectPath();
        $script = escapeshellarg($projectRoot . '/bash/genmultivid.sh');
        exec("nohup bash $script " . (int)$jobId . " > /dev/null 2>&1 &");

        echo json_encode(['success' => true, 'job_id' => $jobId]);
    }

    elseif ($action === 'render_status') {
        $jobId = (int)$_GET['job_id'];
        $stmt  = $pdo->prepare("SELECT * FROM multivid_render_jobs WHERE id=?");
        $stmt->execute([$jobId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) throw new \Exception("Job not found");
        $res = ['success' => true, 'status' => $row['status'], 'job' => $row];
        if ($row['video_id']) {
            $vs = $pdo->prepare("SELECT url, thumbnail FROM videos WHERE id=?");
            $vs->execute([$row['video_id']]);
            $res['video'] = $vs->fetch(\PDO::FETCH_ASSOC);
        }
        echo json_encode($res);
    }

    elseif ($action === 'list_render_jobs') {
        $aid  = (int)$_GET['animatic_id'];
        $stmt = $pdo->prepare("SELECT j.*, v.url as video_url FROM multivid_render_jobs j
                                LEFT JOIN videos v ON j.video_id=v.id
                                WHERE j.animatic_id=? ORDER BY j.created_at DESC LIMIT 20");
        $stmt->execute([$aid]);
        echo json_encode(['success' => true, 'jobs' => $stmt->fetchAll(\PDO::FETCH_ASSOC)]);
    }

    elseif ($action === 'get_animatic') {
        $stmt = $pdo->prepare("SELECT id, name, description FROM animatics WHERE id=?");
        $stmt->execute([(int)$_GET['animatic_id']]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) throw new \Exception("Animatic not found");
        echo json_encode(['success' => true, 'data' => $row]);
    }

    else {
        throw new \Exception("Unknown action: $action");
    }

} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
