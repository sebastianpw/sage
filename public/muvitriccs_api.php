<?php
// public/muvitriccs_api.php
// SAGE AI — MuviTriccs Video Transition API
// Handles: project CRUD, slot management, transition settings, render jobs,
//          video registration, PyAPI proxy for render/status/download.

require_once "error_reporting.php";
require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/json');

use App\Core\SpwBase;

$spw = SpwBase::getInstance();
global $pdo;
if (!isset($pdo)) $pdo = $spw->getPDO();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ── PyAPI base URL ─────────────────────────────────────────────────────────────
function getPyApiUrl(): string {
    $script = \App\Core\SpwBase::getInstance()->getProjectPath() . '/bash/pyapi_echo.sh';
    $url = trim((string)shell_exec('sh ' . escapeshellarg($script)));
    return $url !== '' ? rtrim($url, '/') : 'http://127.0.0.1:8009';
}

// ── Table bootstrap ────────────────────────────────────────────────────────────
function ensureMuviTriccsTable(\PDO $pdo): void {

    // Projects: a named sequence of slots
    $pdo->exec("CREATE TABLE IF NOT EXISTS `muvitriccs_projects` (
        `id`          int(11) NOT NULL AUTO_INCREMENT,
        `name`        varchar(150) NOT NULL DEFAULT 'Untitled Project',
        `description` text DEFAULT NULL,
        `animatic_id` int(11) DEFAULT NULL COMMENT 'Optional animatic context',
        `canvas_w`    int(11) NOT NULL DEFAULT 1080,
        `canvas_h`    int(11) NOT NULL DEFAULT 1080,
        `fps`         int(11) NOT NULL DEFAULT 30,
        `created_at`  timestamp NOT NULL DEFAULT current_timestamp(),
        `updated_at`  timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `idx_animatic` (`animatic_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    // Slots: each slot is one media asset in the chain
    // slot_order determines sequence: 0 → 1 → 2 → …
    // The transition stored on slot N is the transition BETWEEN slot N and slot N+1
    $pdo->exec("CREATE TABLE IF NOT EXISTS `muvitriccs_slots` (
        `id`              int(11) NOT NULL AUTO_INCREMENT,
        `project_id`      int(11) NOT NULL,
        `slot_order`      int(11) NOT NULL DEFAULT 0,
        `asset_type`      enum('video','frame') NOT NULL DEFAULT 'video',
        `asset_id`        int(11) NOT NULL,
        `label`           varchar(150) DEFAULT NULL,
        `trim_start`      float NOT NULL DEFAULT 0.0  COMMENT 'seconds into source to start',
        `trim_end`        float DEFAULT NULL           COMMENT 'NULL = use full asset',
        `playback_speed`  float NOT NULL DEFAULT 1.0,
        `transition_name` varchar(64) NOT NULL DEFAULT 'cross_dissolve'
                          COMMENT 'Transition INTO the NEXT slot (ignored on last slot)',
        `transition_duration_frames` int(11) NOT NULL DEFAULT 24,
        `transition_intensity`       float NOT NULL DEFAULT 1.0,
        `transition_easing`          varchar(64) NOT NULL DEFAULT 'ease_in_out_cubic',
        `transition_seed`            int(11) NOT NULL DEFAULT 42,
        `created_at`      timestamp NOT NULL DEFAULT current_timestamp(),
        `updated_at`      timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `idx_project_order` (`project_id`, `slot_order`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    // Render jobs: one per transition pair
    $pdo->exec("CREATE TABLE IF NOT EXISTS `muvitriccs_render_jobs` (
        `id`            int(11) NOT NULL AUTO_INCREMENT,
        `project_id`    int(11) NOT NULL,
        `slot_a_id`     int(11) NOT NULL  COMMENT 'outgoing slot',
        `slot_b_id`     int(11) NOT NULL  COMMENT 'incoming slot',
        `transition_name` varchar(64) NOT NULL DEFAULT '' COMMENT 'Snapshot of transition type at render time',
        `pyapi_task_id` varchar(64) DEFAULT NULL,
        `status`        enum('queued','processing','completed','failed') NOT NULL DEFAULT 'queued',
        `video_id`      int(11) DEFAULT NULL COMMENT 'resulting video in videos table',
        `error_msg`     text DEFAULT NULL,
        `created_at`    timestamp NOT NULL DEFAULT current_timestamp(),
        `updated_at`    timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `idx_project`  (`project_id`),
        KEY `idx_status`   (`status`),
        KEY `idx_slots`    (`slot_a_id`, `slot_b_id`),
        KEY `idx_trans`    (`transition_name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");


}

// ── cURL helper ────────────────────────────────────────────────────────────────
function pyApiCall(string $method, string $url, array $postFields = [], array $files = []): array {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 600);

    if (strtoupper($method) === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        $body = $postFields;
        foreach ($files as $key => $path) {
            $body[$key] = new \CURLFile($path, mime_content_type($path) ?: 'application/octet-stream', basename($path));
        }
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err      = curl_error($ch);
    curl_close($ch);

    if ($err) throw new \Exception("cURL error: $err");
    if ($httpCode !== 200) {
        $decoded = json_decode($response ?: '', true);
        $detail  = $decoded['detail'] ?? $response;
        throw new \Exception("PyAPI HTTP $httpCode: $detail");
    }
    $decoded = json_decode($response ?: '', true);
    if (json_last_error() !== JSON_ERROR_NONE) throw new \Exception("Invalid JSON from PyAPI");
    return $decoded;
}

// ── Asset resolution helpers ───────────────────────────────────────────────────
function resolveAssetPath(\PDO $pdo, string $assetType, int $assetId): ?string {
    $spw = \App\Core\SpwBase::getInstance();
    $publicPath = $spw->getProjectPath() . '/public/';
    if ($assetType === 'frame') {
        $stmt = $pdo->prepare("SELECT filename FROM frames WHERE id = ?");
        $stmt->execute([$assetId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) return null;
        return $publicPath . ltrim($row['filename'], '/');
    } else {
        $stmt = $pdo->prepare("SELECT url FROM videos WHERE id = ?");
        $stmt->execute([$assetId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) return null;
        return $publicPath . ltrim($row['url'], '/');
    }
}

function getAssetInfo(\PDO $pdo, string $assetType, int $assetId): array {
    if ($assetType === 'frame') {
        $stmt = $pdo->prepare("SELECT id, filename, name, '' as url, '' as thumbnail FROM frames WHERE id = ?");
    } else {
        $stmt = $pdo->prepare("SELECT id, url, name, url as filename, thumbnail FROM videos WHERE id = ?");
    }
    $stmt->execute([$assetId]);
    return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
}

// ── REGISTER VIDEO helper ──────────────────────────────────────────────────────
function registerTransitionVideo(\PDO $pdo, string $tempPath, int $projectId, string $label): int {
    $spw = \App\Core\SpwBase::getInstance();
    $videosDir = $spw->getProjectPath() . '/public/videos';
    $thumbsDir = $videosDir . '/thumbnails';
    if (!is_dir($videosDir)) mkdir($videosDir, 0755, true);
    if (!is_dir($thumbsDir)) mkdir($thumbsDir, 0755, true);

    $pdo->exec("UPDATE video_counter SET next_video = next_video + 1");
    $nextId   = (int)$pdo->query("SELECT next_video FROM video_counter LIMIT 1")->fetchColumn();
    $basename = "video" . str_pad($nextId, 7, '0', STR_PAD_LEFT);
    $ext      = pathinfo($tempPath, PATHINFO_EXTENSION) ?: 'mp4';
    $filename = $basename . '.' . $ext;
    $destPath = $videosDir . '/' . $filename;
    $thumbName= $basename . '.jpg';
    $thumbPath= $thumbsDir . '/' . $thumbName;

    if (!copy($tempPath, $destPath)) {
        throw new \Exception("Failed to copy rendered video to $destPath");
    }

    // Minimal thumbnail placeholder
    $im = imagecreatetruecolor(320, 180);
    imagefilledrectangle($im, 0, 0, 320, 180, imagecolorallocate($im, 15, 18, 28));
    imagestring($im, 4, 10, 70, 'MuviTriccs Transition', imagecolorallocate($im, 0, 200, 160));
    imagestring($im, 3, 10, 95, substr($label, 0, 40), imagecolorallocate($im, 180, 200, 220));
    imagejpeg($im, $thumbPath, 85);
    imagedestroy($im);

    // Probe duration via ffprobe if available
    $duration = 0;
    try {
        $dur = trim((string)shell_exec('ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 ' . escapeshellarg($destPath)));
        $duration = (int)round((float)$dur);
    } catch (\Exception $e) {}

    $fm    = \App\Core\FramesManager::getInstance();
    $mrId  = $fm->createMapRun('muvitriccs_projects', "MuviTriccs project#$projectId");

    $pdo->prepare("INSERT INTO videos (map_run_id,name,description,url,thumbnail,duration,type,file_size,created_at)
                   VALUES (?,?,?,?,?,?,?,?,NOW())")
        ->execute([
            $mrId,
            $filename,
            "MuviTriccs: $label",
            "videos/$filename",
            "videos/thumbnails/$thumbName",
            $duration,
            "video/$ext",
            (int)filesize($destPath),
        ]);
    return (int)$pdo->lastInsertId();
}


// ── Main dispatch ──────────────────────────────────────────────────────────────
try {
    ensureMuviTriccsTable($pdo);

    if ($action === 'list_transitions') {
        $pyUrl  = getPyApiUrl();
        $result = pyApiCall('GET', "$pyUrl/muvitriccs/transitions");
        echo json_encode(['success' => true, 'transitions' => $result['transitions'] ?? []]);
    }

    elseif ($action === 'create_project') {
        $name        = trim($_POST['name'] ?? '') ?: 'Untitled Project';
        $description = trim($_POST['description'] ?? '');
        $animaticId  = !empty($_POST['animatic_id'])  ? (int)$_POST['animatic_id']  : null;
        $canvasW     = max(64, (int)($_POST['canvas_w'] ?? 1080));
        $canvasH     = max(64, (int)($_POST['canvas_h'] ?? 1080));
        $fps         = max(10, min(60, (int)($_POST['fps'] ?? 30)));

        $pdo->prepare("INSERT INTO muvitriccs_projects
                       (name, description, animatic_id, canvas_w, canvas_h, fps)
                       VALUES (?,?,?,?,?,?)")
            ->execute([$name, $description, $animaticId, $canvasW, $canvasH, $fps]);
        $id = (int)$pdo->lastInsertId();
        echo json_encode(['success' => true, 'id' => $id]);
    }

    elseif ($action === 'update_project') {
        $id          = (int)$_POST['id'];
        $name        = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $canvasW     = max(64, (int)($_POST['canvas_w'] ?? 1080));
        $canvasH     = max(64, (int)($_POST['canvas_h'] ?? 1080));
        $fps         = max(10, min(60, (int)($_POST['fps'] ?? 30)));
        $pdo->prepare("UPDATE muvitriccs_projects SET name=?, description=?, canvas_w=?, canvas_h=?, fps=?, updated_at=NOW() WHERE id=?")
            ->execute([$name, $description, $canvasW, $canvasH, $fps, $id]);
        echo json_encode(['success' => true]);
    }

    elseif ($action === 'delete_project') {
        $id = (int)$_POST['id'];
        $pdo->prepare("DELETE FROM muvitriccs_render_jobs WHERE project_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM muvitriccs_slots WHERE project_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM muvitriccs_projects WHERE id=?")->execute([$id]);
        echo json_encode(['success' => true]);
    }

    elseif ($action === 'get_project') {
        $id   = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM muvitriccs_projects WHERE id=?");
        $stmt->execute([$id]);
        $row  = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) throw new \Exception("Project not found");
        echo json_encode(['success' => true, 'data' => $row]);
    }

    elseif ($action === 'list_projects') {
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 12;
        $q       = trim($_GET['q'] ?? '');
        $offset  = ($page - 1) * $perPage;

        if ($q !== '') {
            $like = '%' . $q . '%';
            $idQ  = is_numeric($q) ? (int)$q : -1;
            $stmt2 = $pdo->prepare("SELECT COUNT(*) FROM muvitriccs_projects WHERE id=? OR name LIKE ?");
            $stmt2->execute([$idQ, $like]);
            $total = (int)$stmt2->fetchColumn();
            $stmt  = $pdo->prepare("SELECT * FROM muvitriccs_projects WHERE id=? OR name LIKE ? ORDER BY id DESC LIMIT $perPage OFFSET $offset");
            $stmt->execute([$idQ, $like]);
        } else {
            $total = (int)$pdo->query("SELECT COUNT(*) FROM muvitriccs_projects")->fetchColumn();
            $stmt  = $pdo->prepare("SELECT * FROM muvitriccs_projects ORDER BY id DESC LIMIT $perPage OFFSET $offset");
            $stmt->execute();
        }

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $sc = $pdo->prepare("SELECT COUNT(*) FROM muvitriccs_slots WHERE project_id=?");
            $sc->execute([$r['id']]);
            $r['slot_count'] = (int)$sc->fetchColumn();
        }
        unset($r);

        echo json_encode([
            'success'     => true,
            'data'        => $rows,
            'total'       => $total,
            'total_pages' => max(1, (int)ceil($total / $perPage)),
            'page'        => $page,
        ]);
    }

    elseif ($action === 'get_slots') {
        $projectId = (int)($_GET['project_id'] ?? 0);
        $stmt = $pdo->prepare("
            SELECT s.*,
                   (SELECT status FROM muvitriccs_render_jobs j WHERE j.slot_a_id = s.id ORDER BY id DESC LIMIT 1) as job_status,
                   (SELECT video_id FROM muvitriccs_render_jobs j WHERE j.slot_a_id = s.id AND status='completed' ORDER BY id DESC LIMIT 1) as job_video_id
            FROM muvitriccs_slots s
            WHERE s.project_id=?
            ORDER BY s.slot_order ASC, s.id ASC
        ");
        $stmt->execute([$projectId]);
        $slots = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($slots as &$slot) {
            $info = getAssetInfo($pdo, $slot['asset_type'], (int)$slot['asset_id']);
            $slot['asset_name']      = $info['name'] ?? ('Asset #' . $slot['asset_id']);
            $slot['asset_filename']  = $info['filename'] ?? '';
            $slot['asset_thumbnail'] = $info['thumbnail'] ?? '';
        }
        unset($slot);

        echo json_encode(['success' => true, 'slots' => $slots]);
    }

    elseif ($action === 'add_slot') {
        $projectId  = (int)$_POST['project_id'];
        $assetType  = $_POST['asset_type'] === 'frame' ? 'frame' : 'video';
        $assetId    = (int)$_POST['asset_id'];
        if (!$projectId || !$assetId) throw new \Exception("project_id and asset_id required");

        $maxOrder = $pdo->prepare("SELECT COALESCE(MAX(slot_order),0) FROM muvitriccs_slots WHERE project_id=?");
        $maxOrder->execute([$projectId]);
        $nextOrder = (int)$maxOrder->fetchColumn() + 1;

        $pdo->prepare("INSERT INTO muvitriccs_slots
                       (project_id, slot_order, asset_type, asset_id,
                        label, trim_start, trim_end, playback_speed,
                        transition_name, transition_duration_frames,
                        transition_intensity, transition_easing, transition_seed)
                       VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([
                $projectId, $nextOrder, $assetType, $assetId,
                trim($_POST['label'] ?? ''),
                max(0, (float)($_POST['trim_start'] ?? 0)),
                !empty($_POST['trim_end']) ? (float)$_POST['trim_end'] : null,
                max(0.1, (float)($_POST['playback_speed'] ?? 1.0)),
                $_POST['transition_name'] ?? 'cross_dissolve',
                max(2, min(120, (int)($_POST['transition_duration_frames'] ?? 24))),
                max(0.1, (float)($_POST['transition_intensity'] ?? 1.0)),
                $_POST['transition_easing'] ?? 'ease_in_out_cubic',
                (int)($_POST['transition_seed'] ?? 42),
            ]);
        $id = (int)$pdo->lastInsertId();
        echo json_encode(['success' => true, 'id' => $id]);
    }

    elseif ($action === 'update_slot') {
        $id = (int)$_POST['id'];
        $pdo->prepare("UPDATE muvitriccs_slots SET
                       label=?,
                       trim_start=?,
                       trim_end=?,
                       playback_speed=?,
                       transition_name=?,
                       transition_duration_frames=?,
                       transition_intensity=?,
                       transition_easing=?,
                       transition_seed=?,
                       updated_at=NOW()
                   WHERE id=?")
            ->execute([
                trim($_POST['label'] ?? ''),
                max(0, (float)($_POST['trim_start'] ?? 0)),
                !empty($_POST['trim_end']) ? (float)$_POST['trim_end'] : null,
                max(0.1, (float)($_POST['playback_speed'] ?? 1.0)),
                $_POST['transition_name'] ?? 'cross_dissolve',
                max(2, min(120, (int)($_POST['transition_duration_frames'] ?? 24))),
                max(0.1, (float)($_POST['transition_intensity'] ?? 1.0)),
                $_POST['transition_easing'] ?? 'ease_in_out_cubic',
                (int)($_POST['transition_seed'] ?? 42),
                $id,
            ]);
        echo json_encode(['success' => true]);
    }

    elseif ($action === 'delete_slot') {
        $id = (int)$_POST['id'];
        $pdo->prepare("DELETE FROM muvitriccs_slots WHERE id=?")->execute([$id]);
        $pid = (int)($_POST['project_id'] ?? 0);
        if ($pid) {
            $slots = $pdo->prepare("SELECT id FROM muvitriccs_slots WHERE project_id=? ORDER BY slot_order ASC, id ASC");
            $slots->execute([$pid]);
            $remaining = $slots->fetchAll(\PDO::FETCH_COLUMN);
            $upd = $pdo->prepare("UPDATE muvitriccs_slots SET slot_order=? WHERE id=?");
            foreach ($remaining as $i => $sid) {
                $upd->execute([$i, $sid]);
            }
        }
        echo json_encode(['success' => true]);
    }

    elseif ($action === 'reorder_slots') {
        $orderedIds = json_decode($_POST['ordered_ids'] ?? '[]', true);
        if (!is_array($orderedIds)) throw new \Exception("ordered_ids must be JSON array");
        $upd = $pdo->prepare("UPDATE muvitriccs_slots SET slot_order=?, updated_at=NOW() WHERE id=?");
        foreach ($orderedIds as $i => $sid) {
            $upd->execute([$i, (int)$sid]);
        }
        echo json_encode(['success' => true]);
    }

    elseif ($action === 'browse_assets') {
        $type    = ($_GET['asset_type'] ?? '') === 'frame' ? 'frame' : 'video';
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 12;
        $q       = trim($_GET['q'] ?? '');
        $offset  = ($page - 1) * $perPage;

        // Optional filter params (from picker fly-out)
        $nodeId       = (int)($_GET['node_id']       ?? 0);
        $seqId        = (int)($_GET['seq_id']        ?? 0);
        $fuzzCandId   = (int)($_GET['fuzz_cand_id']  ?? 0);
        $storyboardId = (int)($_GET['storyboard_id'] ?? 0);
        $inclDesc     = (int)($_GET['include_descendants'] ?? 1);

        if ($type === 'frame') {
            // Frames do not support tree/seq/fuzz/storyboard filtering — plain search only
            if ($q !== '') {
                $like = '%' . $q . '%';
                $idQ  = is_numeric($q) ? (int)$q : -1;
                $cnt  = $pdo->prepare("SELECT COUNT(*) FROM frames WHERE id=? OR name LIKE ? OR filename LIKE ?");
                $cnt->execute([$idQ, $like, $like]);
                $total = (int)$cnt->fetchColumn();
                $stmt  = $pdo->prepare("SELECT id, filename, name FROM frames WHERE id=? OR name LIKE ? OR filename LIKE ? ORDER BY id DESC LIMIT $perPage OFFSET $offset");
                $stmt->execute([$idQ, $like, $like]);
            } else {
                $total = (int)$pdo->query("SELECT COUNT(*) FROM frames")->fetchColumn();
                $stmt  = $pdo->prepare("SELECT id, filename, name FROM frames ORDER BY id DESC LIMIT $perPage OFFSET $offset");
                $stmt->execute();
            }
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($rows as &$r) {
                $r['asset_type'] = 'frame';
                if (empty($r['name'])) $r['name'] = $r['filename'];
            }
            unset($r);
        } else {
            // Video: build WHERE clauses respecting picker filter modes
            $joins  = "";
            $where  = ["v.is_active = 1"];
            $params = [];

            if ($storyboardId) {
                $sfStmt = $pdo->prepare(
                    "SELECT sf.frame_id, f.entity_type, f.entity_id
                     FROM storyboard_frames sf
                     JOIN frames f ON f.id = sf.frame_id
                     WHERE sf.storyboard_id = ?
                       AND f.entity_type IS NOT NULL AND f.entity_id IS NOT NULL"
                );
                $sfStmt->execute([$storyboardId]);
                $sbFrames = $sfStmt->fetchAll(\PDO::FETCH_ASSOC);

                if (empty($sbFrames)) {
                    $where[] = "1 = 0";
                } else {
                    $entityGroups = [];
                    foreach ($sbFrames as $row) {
                        $key = $row['entity_type'] . '|' . $row['entity_id'];
                        $entityGroups[$key] = ['entity_type' => $row['entity_type'], 'entity_id' => (int)$row['entity_id']];
                    }
                    $allFrameIds = [];
                    $allowedTables = ['sketches','characters','locations','spawns','generatives','animas',
                                      'artifacts','lotations','character_poses','character_anima_poses',
                                      'character_expressions','animatics','composites'];
                    foreach ($entityGroups as $eg) {
                        $eType = $eg['entity_type'];
                        $eId   = $eg['entity_id'];
                        if (!in_array($eType, $allowedTables)) continue;
                        $directStmt = $pdo->prepare("SELECT id FROM frames WHERE entity_type = ? AND entity_id = ?");
                        $directStmt->execute([$eType, $eId]);
                        foreach ($directStmt->fetchAll(\PDO::FETCH_COLUMN) as $fid) { $allFrameIds[] = (int)$fid; }
                        $mapTable = "frames_2_{$eType}";
                        $checkMap = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($mapTable));
                        if ($checkMap && $checkMap->rowCount() > 0) {
                            $mapStmt = $pdo->prepare("SELECT from_id FROM `$mapTable` WHERE to_id = ?");
                            $mapStmt->execute([$eId]);
                            foreach ($mapStmt->fetchAll(\PDO::FETCH_COLUMN) as $fid) { $allFrameIds[] = (int)$fid; }
                        }
                    }
                    $allFrameIds = array_unique($allFrameIds);
                    if (empty($allFrameIds)) {
                        $where[] = "1 = 0";
                    } else {
                        $inClause = implode(',', $allFrameIds);
                        $where[] = "v.id IN (
                            SELECT va2.from_id FROM videos_2_animatics va2
                            JOIN animatics an ON va2.to_id = an.id
                            WHERE an.img2img_frame_id IN ($inClause)
                        )";
                    }
                }

            } elseif ($fuzzCandId) {
                $where[] = "v.id IN (
                    SELECT DISTINCT va2.from_id FROM videos_2_animatics va2
                    JOIN animatics an ON va2.to_id = an.id
                    JOIN frames fr ON an.img2img_frame_id = fr.id
                    WHERE (
                        (fr.entity_type = 'sketches' AND fr.entity_id IN (
                            SELECT DISTINCT source_row_id FROM fuzz_mentions
                            WHERE candidate_id = ?
                              AND source_table IN ('sketches','sketch_analysis','sketch_lore_history','sketch_ingredients')
                              AND source_row_id IS NOT NULL
                        ))
                        OR fr.id IN (
                            SELECT f2s.from_id FROM frames_2_sketches f2s
                            WHERE f2s.to_id IN (
                                SELECT DISTINCT source_row_id FROM fuzz_mentions
                                WHERE candidate_id = ?
                                  AND source_table IN ('sketches','sketch_analysis','sketch_lore_history','sketch_ingredients')
                                  AND source_row_id IS NOT NULL
                            )
                        )
                    )
                )";
                $params[] = $fuzzCandId;
                $params[] = $fuzzCandId;

            } elseif ($seqId) {
                $where[] = "v.id IN (
                    SELECT DISTINCT va2.from_id FROM videos_2_animatics va2
                    JOIN animatics an ON va2.to_id = an.id
                    JOIN frames fr ON an.img2img_frame_id = fr.id
                    WHERE (
                        (fr.entity_type = 'sketches' AND fr.entity_id IN (
                            SELECT CASE WHEN JSON_TYPE(jt.val) = 'INTEGER'
                                        THEN JSON_VALUE(jt.val, '$')
                                        ELSE JSON_VALUE(jt.val, '$.sketch_id')
                                   END
                            FROM narrative_sequences ns,
                            JSON_TABLE(ns.sequence_data, '$[*]' COLUMNS(val JSON PATH '$')) jt
                            WHERE ns.id = ?
                        ))
                        OR fr.id IN (
                            SELECT f2s.from_id FROM frames_2_sketches f2s
                            WHERE f2s.to_id IN (
                                SELECT CASE WHEN JSON_TYPE(jt2.val) = 'INTEGER'
                                            THEN JSON_VALUE(jt2.val, '$')
                                            ELSE JSON_VALUE(jt2.val, '$.sketch_id')
                                       END
                                FROM narrative_sequences ns2,
                                JSON_TABLE(ns2.sequence_data, '$[*]' COLUMNS(val JSON PATH '$')) jt2
                                WHERE ns2.id = ?
                            )
                        )
                    )
                )";
                $params[] = $seqId;
                $params[] = $seqId;

            } elseif ($nodeId) {
                if ($inclDesc) {
                    $where[] = "v.id IN (
                        SELECT vti.video_id FROM video_tree_items vti
                        WHERE vti.node_id IN (
                            WITH RECURSIVE desc_nodes AS (
                                SELECT id FROM video_tree_nodes WHERE id = ?
                                UNION ALL
                                SELECT n.id FROM video_tree_nodes n
                                INNER JOIN desc_nodes d ON n.parent_id = d.id
                            )
                            SELECT id FROM desc_nodes
                        )
                    )";
                    $params[] = $nodeId;
                } else {
                    $where[] = "v.id IN (SELECT vti.video_id FROM video_tree_items vti WHERE vti.node_id = ?)";
                    $params[] = $nodeId;
                }
            }

            if ($q !== '') {
                $like = '%' . $q . '%';
                $idQ  = is_numeric($q) ? (int)$q : -1;
                $where[] = "(v.id = ? OR v.name LIKE ? OR v.url LIKE ?)";
                $params[] = $idQ;
                $params[] = $like;
                $params[] = $like;
            }

            $whereStr = "WHERE " . implode(" AND ", $where);

            $cntStmt = $pdo->prepare("SELECT COUNT(DISTINCT v.id) FROM videos v $joins $whereStr");
            $cntStmt->execute($params);
            $total = (int)$cntStmt->fetchColumn();

            $dataStmt = $pdo->prepare(
                "SELECT DISTINCT v.id, v.url, v.name, v.thumbnail
                 FROM videos v $joins $whereStr
                 ORDER BY v.id DESC LIMIT $perPage OFFSET $offset"
            );
            $dataStmt->execute($params);
            $rows = $dataStmt->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($rows as &$r) {
                $r['asset_type'] = 'video';
                $r['filename']   = $r['url'];
                if (empty($r['name'])) $r['name'] = $r['url'];
            }
            unset($r);
        }

        echo json_encode([
            'success'     => true,
            'data'        => $rows,
            'total'       => $total,
            'total_pages' => max(1, (int)ceil($total / $perPage)),
            'page'        => $page,
        ]);
    }

    // ── Picker filter data sources ─────────────────────────────────────────────

    elseif ($action === 'list_narrative_sequences') {
        $stmt = $pdo->query("SELECT id, name, description FROM narrative_sequences ORDER BY id DESC");
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(\PDO::FETCH_ASSOC)]);
    }

    elseif ($action === 'list_storyboards') {
        $search = trim($_GET['search'] ?? '');
        $page   = max(1, (int)($_GET['page'] ?? 1));
        $limit  = 20;
        $offset = ($page - 1) * $limit;

        $whereSQL = "WHERE is_archived = 0";
        $params   = [];

        $cols = [];
        try {
            $cols = $pdo->query("SHOW COLUMNS FROM storyboards")->fetchAll(\PDO::FETCH_COLUMN);
        } catch (\Exception $e) {}

        $hasName  = in_array('name', $cols);
        $hasTitle = in_array('title', $cols);
        $hasDesc  = in_array('description', $cols);

        if ($search !== '') {
            $conds = [];
            if ($hasName)  { $conds[] = "name LIKE ?";        $params[] = '%' . $search . '%'; }
            if ($hasTitle) { $conds[] = "title LIKE ?";       $params[] = '%' . $search . '%'; }
            if ($hasDesc)  { $conds[] = "description LIKE ?"; $params[] = '%' . $search . '%'; }
            if (!empty($conds)) {
                $whereSQL .= " AND (" . implode(" OR ", $conds) . ")";
            } else {
                $whereSQL .= " AND id = ?";
                $params[] = intval($search);
            }
        }

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM storyboards $whereSQL");
        $countStmt->execute($params);
        $total = $countStmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT * FROM storyboards $whereSQL ORDER BY id DESC LIMIT $limit OFFSET $offset");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        echo json_encode([
            'success'    => true,
            'data'       => $rows,
            'pagination' => [
                'page'  => $page,
                'pages' => ceil($total / $limit),
                'total' => $total,
            ],
        ]);
    }

    elseif ($action === 'list_fuzz_candidates') {
        $search = trim($_GET['search'] ?? '');
        $page   = max(1, (int)($_GET['page'] ?? 1));
        $limit  = (int)($_GET['limit'] ?? 20);
        $offset = ($page - 1) * $limit;

        $whereSQL = "WHERE status IN ('promoted','canonized')";
        $params   = [];

        if ($search !== '') {
            $whereSQL .= " AND label LIKE ?";
            $params[] = '%' . $search . '%';
        }

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM fuzz_candidates $whereSQL");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT c.id, c.label, c.concept_type, c.status
             FROM fuzz_candidates c
             $whereSQL
             ORDER BY c.updated_at DESC
             LIMIT $limit OFFSET $offset"
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        echo json_encode([
            'success'    => true,
            'status'     => 'ok',
            'data'       => $rows,
            'pagination' => [
                'page'  => $page,
                'pages' => max(1, (int)ceil($total / $limit)),
                'total' => $total,
            ],
        ]);
    }

    elseif ($action === 'queue_render') {
        $projectId = (int)$_POST['project_id'];
        $slotAId   = (int)$_POST['slot_a_id'];
        $slotBId   = (int)$_POST['slot_b_id'];
        if (!$projectId || !$slotAId || !$slotBId) throw new \Exception("project_id, slot_a_id, slot_b_id required");

        $proj = $pdo->prepare("SELECT * FROM muvitriccs_projects WHERE id=?");
        $proj->execute([$projectId]);
        $project = $proj->fetch(\PDO::FETCH_ASSOC);
        if (!$project) throw new \Exception("Project not found");

        $stmtA = $pdo->prepare("SELECT * FROM muvitriccs_slots WHERE id=?");
        $stmtA->execute([$slotAId]);
        $slotA = $stmtA->fetch(\PDO::FETCH_ASSOC);
        if (!$slotA) throw new \Exception("Slot A not found");

        $stmtB = $pdo->prepare("SELECT * FROM muvitriccs_slots WHERE id=?");
        $stmtB->execute([$slotBId]);
        $slotB = $stmtB->fetch(\PDO::FETCH_ASSOC);
        if (!$slotB) throw new \Exception("Slot B not found");

        $pathA = resolveAssetPath($pdo, $slotA['asset_type'], (int)$slotA['asset_id']);
        $pathB = resolveAssetPath($pdo, $slotB['asset_type'], (int)$slotB['asset_id']);
        if (!$pathA || !file_exists($pathA)) throw new \Exception("Asset A file not found on disk: $pathA");
        if (!$pathB || !file_exists($pathB)) throw new \Exception("Asset B file not found on disk: $pathB");

        $pdo->prepare("INSERT INTO muvitriccs_render_jobs
                       (project_id, slot_a_id, slot_b_id, transition_name, status)
                       VALUES (?,?,?,?,'queued')")
            ->execute([$projectId, $slotAId, $slotBId, $slotA['transition_name']]);
        $jobId = (int)$pdo->lastInsertId();

        $pyUrl    = getPyApiUrl();
        $endpoint = "$pyUrl/muvitriccs/render";

        $postFields = [
            'transition_name'            => $slotA['transition_name'],
            'duration_frames'            => $slotA['transition_duration_frames'],
            'fps'                        => $project['fps'],
            'output_w'                   => $project['canvas_w'],
            'output_h'                   => $project['canvas_h'],
            'intensity'                  => $slotA['transition_intensity'],
            'easing'                     => $slotA['transition_easing'],
            'seed'                       => $slotA['transition_seed'],
            'tail_a_frames'              => -1,
            'head_b_frames'              => -1,
        ];

        try {
            $result = pyApiCall('POST', $endpoint, $postFields, [
                'asset_a' => $pathA,
                'asset_b' => $pathB,
            ]);

            $pyTaskId = $result['task_id'] ?? null;
            $pdo->prepare("UPDATE muvitriccs_render_jobs SET pyapi_task_id=?, status='processing', updated_at=NOW() WHERE id=?")
                ->execute([$pyTaskId, $jobId]);

            echo json_encode([
                'success'       => true,
                'job_id'        => $jobId,
                'pyapi_task_id' => $pyTaskId,
            ]);
        } catch (\Exception $e) {
            $pdo->prepare("UPDATE muvitriccs_render_jobs SET status='failed', error_msg=?, updated_at=NOW() WHERE id=?")
                ->execute([$e->getMessage(), $jobId]);
            throw $e;
        }
    }

    elseif ($action === 'poll_render') {
        $jobId = (int)($_GET['job_id'] ?? $_POST['job_id'] ?? 0);
        $stmt  = $pdo->prepare("SELECT * FROM muvitriccs_render_jobs WHERE id=?");
        $stmt->execute([$jobId]);
        $job   = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$job) throw new \Exception("Job not found");

        if (in_array($job['status'], ['completed', 'failed'])) {
            echo json_encode([
                'success'  => true,
                'status'   => $job['status'],
                'progress' => $job['status'] === 'completed' ? 100 : 0,
                'video_id' => $job['video_id'],
                'error'    => $job['error_msg'],
            ]);
            return;
        }

        if (!$job['pyapi_task_id']) {
            echo json_encode(['success' => true, 'status' => $job['status'], 'progress' => 0]);
            return;
        }

        $pyUrl    = getPyApiUrl();
        $pyStatus = pyApiCall('GET', "$pyUrl/muvitriccs/status/{$job['pyapi_task_id']}");
        $pyState  = $pyStatus['status'] ?? 'processing';
        $progress = (int)($pyStatus['progress'] ?? 0);

        if ($pyState === 'completed') {
            $dlUrl   = "$pyUrl/muvitriccs/download/{$job['pyapi_task_id']}";
            $tmpFile = tempnam(sys_get_temp_dir(), 'mtriccs_') . '.mp4';
            $fh = fopen($tmpFile, 'wb');
            $ch = curl_init($dlUrl);
            curl_setopt($ch, CURLOPT_FILE, $fh);
            curl_setopt($ch, CURLOPT_TIMEOUT, 300);
            curl_exec($ch);
            $dlCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            fclose($fh);

            if ($dlCode !== 200 || !file_exists($tmpFile) || filesize($tmpFile) < 100) {
                $pdo->prepare("UPDATE muvitriccs_render_jobs SET status='failed', error_msg='Download failed', updated_at=NOW() WHERE id=?")
                    ->execute([$jobId]);
                echo json_encode(['success' => true, 'status' => 'failed', 'error' => 'Download from PyAPI failed']);
                return;
            }

            $proj = $pdo->prepare("SELECT name FROM muvitriccs_projects WHERE id=?");
            $proj->execute([$job['project_id']]);
            $projName = $proj->fetchColumn() ?: "Project #{$job['project_id']}";

            $label   = "Transition · $projName · Job#$jobId";
            $videoId = registerTransitionVideo($pdo, $tmpFile, (int)$job['project_id'], $label);
            @unlink($tmpFile);

            $pdo->prepare("UPDATE muvitriccs_render_jobs SET status='completed', video_id=?, updated_at=NOW() WHERE id=?")
                ->execute([$videoId, $jobId]);

            echo json_encode([
                'success'  => true,
                'status'   => 'completed',
                'progress' => 100,
                'video_id' => $videoId,
            ]);

        } elseif ($pyState === 'failed') {
            $errMsg = $pyStatus['error'] ?? 'PyAPI render failed';
            $pdo->prepare("UPDATE muvitriccs_render_jobs SET status='failed', error_msg=?, updated_at=NOW() WHERE id=?")
                ->execute([$errMsg, $jobId]);
            echo json_encode(['success' => true, 'status' => 'failed', 'error' => $errMsg]);
        } else {
            echo json_encode(['success' => true, 'status' => 'processing', 'progress' => $progress]);
        }
    }

    elseif ($action === 'list_render_jobs') {
        $projectId = (int)($_GET['project_id'] ?? 0);
        $stmt = $pdo->prepare("
            SELECT j.*,
                   v.url AS video_url, v.thumbnail AS video_thumbnail,
                   sa.slot_order AS slot_a_order,
                   sb.slot_order AS slot_b_order,
                   sa.transition_name
            FROM muvitriccs_render_jobs j
            LEFT JOIN muvitriccs_slots sa ON j.slot_a_id = sa.id
            LEFT JOIN muvitriccs_slots sb ON j.slot_b_id = sb.id
            LEFT JOIN videos v ON j.video_id = v.id
            WHERE j.project_id = ?
            ORDER BY j.created_at DESC
            LIMIT 30
        ");
        $stmt->execute([$projectId]);
        echo json_encode(['success' => true, 'jobs' => $stmt->fetchAll(\PDO::FETCH_ASSOC)]);
    }

    elseif ($action === 'get_video_url') {
        $videoId = (int)($_GET['video_id'] ?? 0);
        $stmt    = $pdo->prepare("SELECT url, thumbnail FROM videos WHERE id=?");
        $stmt->execute([$videoId]);
        $row     = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) throw new \Exception("Video not found");
        echo json_encode(['success' => true, 'url' => $row['url'], 'thumbnail' => $row['thumbnail']]);
    }

    elseif ($action === 'pyapi_health') {
        $pyUrl = getPyApiUrl();
        try {
            $result = pyApiCall('GET', "$pyUrl/muvitriccs/_health");
            echo json_encode(['success' => true, 'online' => true, 'data' => $result]);
        } catch (\Exception $e) {
            echo json_encode(['success' => true, 'online' => false, 'error' => $e->getMessage()]);
        }
    }

    // ── Transition demo / preview browse ──────────────────────────────────────
    // browse_transition_demos: paginated list of render jobs (completed) for a
    // given transition_name, with AJAX search on video name. 3 items per page.
    // Also returns whether each video is already assigned as a demo.

    elseif ($action === 'browse_transition_demos') {
        $transitionName = trim($_GET['transition_name'] ?? '');
        if ($transitionName === '') throw new \Exception("transition_name required");

        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 3;
        $q       = trim($_GET['q'] ?? '');
        $offset  = ($page - 1) * $perPage;

        // slot_a_id present  → instance tab: all renders from this specific connector
        // slot_a_id absent   → type tab: all renders globally for this transition type
        //                       using the snapshot column j.transition_name (not live slot join)
        $slotAId = (int)($_GET['slot_a_id'] ?? 0);

        $params    = [];
        $slotWhere = '';

        if ($slotAId > 0) {
            // Instance tab — filter by connector, join slot only for SELECT display
            $slotWhere = "j.slot_a_id = ?";
            $params[]  = $slotAId;
        } else {
            // Type tab — use the snapshotted transition_name on the job row itself.
            // This is accurate regardless of whether the slot was later edited.
            $slotWhere = "j.transition_name = ?";
            $params[]  = $transitionName;
        }

        $searchSQL = '';
        if ($q !== '') {
            $like = '%' . $q . '%';
            $idQ  = is_numeric($q) ? (int)$q : -1;
            $searchSQL = " AND (v.id = ? OR v.name LIKE ? OR v.url LIKE ?)";
            $params[] = $idQ;
            $params[] = $like;
            $params[] = $like;
        }

        // Count
        $cntStmt = $pdo->prepare(
            "SELECT COUNT(DISTINCT j.video_id)
             FROM muvitriccs_render_jobs j
             JOIN videos v ON j.video_id = v.id
             WHERE $slotWhere
               AND j.status = 'completed'
               AND j.video_id IS NOT NULL
               $searchSQL"
        );
        $cntStmt->execute($params);
        $total = (int)$cntStmt->fetchColumn();

        // Data — prepend the two subquery params for is_assigned / is_primary checks
        $dataParams = $params;
        array_unshift($dataParams, $transitionName, $transitionName);

        $dataStmt = $pdo->prepare(
            "SELECT DISTINCT
                j.video_id, j.id AS job_id,
                j.transition_name AS slot_transition_name,
                v.name AS video_name, v.url AS video_url, v.thumbnail,
                j.created_at AS rendered_at,
                (SELECT COUNT(*) FROM muvitriccs_transition_demos d
                 WHERE d.video_id = j.video_id AND d.transition_name = ?) AS is_assigned,
                (SELECT d2.is_primary FROM muvitriccs_transition_demos d2
                 WHERE d2.video_id = j.video_id AND d2.transition_name = ?
                 LIMIT 1) AS is_primary
             FROM muvitriccs_render_jobs j
             JOIN videos v ON j.video_id = v.id
             WHERE $slotWhere
               AND j.status = 'completed'
               AND j.video_id IS NOT NULL
               $searchSQL
             ORDER BY j.id DESC
             LIMIT $perPage OFFSET $offset"
        );
        $dataStmt->execute($dataParams);
        $rows = $dataStmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($rows as &$r) {
            $r['is_assigned'] = (int)$r['is_assigned'] > 0;
            $r['is_primary']  = (int)($r['is_primary'] ?? 0) === 1;
        }
        unset($r);

        echo json_encode([
            'success'     => true,
            'data'        => $rows,
            'total'       => $total,
            'total_pages' => max(1, (int)ceil($total / $perPage)),
            'page'        => $page,
        ]);
    }

    elseif ($action === 'assign_demo') {
        $transitionName = trim($_POST['transition_name'] ?? '');
        $videoId        = (int)($_POST['video_id'] ?? 0);
        $jobId          = (int)($_POST['job_id'] ?? 0);
        $label          = trim($_POST['label'] ?? '');
        $setPrimary     = (int)($_POST['set_primary'] ?? 0);
        if (!$transitionName || !$videoId) throw new \Exception("transition_name and video_id required");

        // Check if already assigned
        $check = $pdo->prepare("SELECT id FROM muvitriccs_transition_demos WHERE transition_name=? AND video_id=?");
        $check->execute([$transitionName, $videoId]);
        $existing = $check->fetchColumn();

        if ($existing) {
            // Update label/primary flag only
            $pdo->prepare("UPDATE muvitriccs_transition_demos SET label=?, updated_at=NOW() WHERE id=?")
                ->execute([$label ?: null, $existing]);
        } else {
            $pdo->prepare("INSERT INTO muvitriccs_transition_demos (transition_name, video_id, job_id, label, is_primary)
                           VALUES (?,?,?,?,0)")
                ->execute([$transitionName, $videoId, $jobId ?: null, $label ?: null]);
        }

        if ($setPrimary) {
            // Unset any existing primary for this transition, then set this one
            $pdo->prepare("UPDATE muvitriccs_transition_demos SET is_primary=0 WHERE transition_name=?")
                ->execute([$transitionName]);
            $pdo->prepare("UPDATE muvitriccs_transition_demos SET is_primary=1 WHERE transition_name=? AND video_id=?")
                ->execute([$transitionName, $videoId]);
        }

        echo json_encode(['success' => true]);
    }

    elseif ($action === 'unassign_demo') {
        $transitionName = trim($_POST['transition_name'] ?? '');
        $videoId        = (int)($_POST['video_id'] ?? 0);
        if (!$transitionName || !$videoId) throw new \Exception("transition_name and video_id required");
        $pdo->prepare("DELETE FROM muvitriccs_transition_demos WHERE transition_name=? AND video_id=?")
            ->execute([$transitionName, $videoId]);
        echo json_encode(['success' => true]);
    }

    elseif ($action === 'get_demo') {
        // Returns the primary demo video for a transition, if any
        $transitionName = trim($_GET['transition_name'] ?? '');
        if ($transitionName === '') throw new \Exception("transition_name required");
        $stmt = $pdo->prepare(
            "SELECT d.video_id, d.label, d.is_primary, v.url, v.thumbnail, v.name AS video_name
             FROM muvitriccs_transition_demos d
             JOIN videos v ON d.video_id = v.id
             WHERE d.transition_name = ?
             ORDER BY d.is_primary DESC, d.id DESC
             LIMIT 1"
        );
        $stmt->execute([$transitionName]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'demo' => $row ?: null]);
    }

    else {
        throw new \Exception("Unknown action: $action");
    }

} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
