<?php
// public/editorial_api.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();
$publicPathAbs = rtrim($spw->getPublicPath(), '/');

header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

function getSortOrderInput($val) {
    $v = trim((string)$val);
    if ($v === '' || $v === '0') return null;
    return (int)$v;
}

try {
    switch ($action) {
        // =====================================================================
        // EPISODE DETAILS
        // =====================================================================
        case 'get_episode_details':
            $episodeId = (int)($_GET['episode_id'] ?? 0);
            if (!$episodeId) throw new Exception('Episode ID required');
            $stmt = $pdo->prepare("SELECT e.*, s.name as season_name, ser.name as series_name FROM editorial_episodes e LEFT JOIN editorial_seasons s ON e.season_id = s.id LEFT JOIN editorial_series ser ON s.series_id = ser.id WHERE e.id = ?");
            $stmt->execute([$episodeId]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$data) throw new Exception('Episode not found');
            echo json_encode(['success' => true, 'data' => $data]);
            break;

        case 'list_sequences':
            $episodeId = (int)($_GET['episode_id'] ?? 0);
            if (!$episodeId) throw new Exception('Episode ID required');
            $stmt = $pdo->prepare("SELECT * FROM editorial_sequences WHERE episode_id = ? ORDER BY sort_order ASC, id ASC");
            $stmt->execute([$episodeId]);
            $sequences = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($sequences as &$seq) {
                $sql = "SELECT v.thumbnail FROM editorial_scenes sc JOIN editorial_shots sh ON sc.id = sh.scene_id JOIN videos v ON sh.video_id = v.id WHERE sc.sequence_id = ? ORDER BY sc.sort_order ASC, sh.sort_order ASC LIMIT 1";
                $sth = $pdo->prepare($sql);
                $sth->execute([$seq['id']]);
                $seq['thumbnail'] = $sth->fetchColumn() ?: null;
                $cnt = $pdo->prepare("SELECT COUNT(*) FROM editorial_scenes WHERE sequence_id = ?");
                $cnt->execute([$seq['id']]);
                $seq['scene_count'] = $cnt->fetchColumn();
            }
            echo json_encode(['success' => true, 'data' => $sequences]);
            break;
            
        case 'create_sequence':
            $episodeId = (int)($_POST['episode_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $sortOrder = getSortOrderInput($_POST['sort_order'] ?? '');
            if (!$episodeId || empty($name)) throw new Exception('Name and Episode ID required');
            $stmt = $pdo->prepare("INSERT INTO editorial_sequences (episode_id, name, description, sort_order) VALUES (?, ?, ?, ?)");
            $stmt->execute([$episodeId, $name, $_POST['description'] ?? '', $sortOrder]);
            echo json_encode(['success' => true]);
            break;

        case 'update_sequence':
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $sortOrder = getSortOrderInput($_POST['sort_order'] ?? '');
            if (!$id || empty($name)) throw new Exception('Invalid parameters');
            $stmt = $pdo->prepare("UPDATE editorial_sequences SET name = ?, description = ?, sort_order = ? WHERE id = ?");
            $stmt->execute([$name, $_POST['description'] ?? '', $sortOrder, $id]);
            echo json_encode(['success' => true]);
            break;

        case 'delete_sequence':
            $id = (int)($_POST['id'] ?? 0);
            $chk = $pdo->prepare("SELECT COUNT(*) FROM editorial_scenes WHERE sequence_id = ?");
            $chk->execute([$id]);
            if ($chk->fetchColumn() > 0) throw new Exception('Cannot delete sequence containing scenes.');
            $stmt = $pdo->prepare("DELETE FROM editorial_sequences WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
            break;

        case 'reorder_sequences':
            $ids = json_decode($_POST['ids'] ?? '[]', true);
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE editorial_sequences SET sort_order = ? WHERE id = ?");
            foreach ($ids as $idx => $id) { $stmt->execute([$idx, (int)$id]); }
            $pdo->commit();
            echo json_encode(['success' => true]);
            break;

        case 'get_sequence_details':
            $id = (int)($_GET['sequence_id'] ?? 0);
            $stmt = $pdo->prepare("SELECT s.*, e.name as episode_name, e.id as episode_id FROM editorial_sequences s JOIN editorial_episodes e ON s.episode_id = e.id WHERE s.id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'data' => $stmt->fetch(PDO::FETCH_ASSOC)]);
            break;

        case 'list_scenes':
            $seqId = (int)($_GET['sequence_id'] ?? 0);
            if (!$seqId) throw new Exception('Sequence ID required');
            $stmt = $pdo->prepare("SELECT * FROM editorial_scenes WHERE sequence_id = ? ORDER BY sort_order ASC, id ASC");
            $stmt->execute([$seqId]);
            $scenes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($scenes as &$scene) {
                $sql = "SELECT v.thumbnail FROM editorial_shots sh JOIN videos v ON sh.video_id = v.id WHERE sh.scene_id = ? ORDER BY sh.sort_order ASC LIMIT 1";
                $sth = $pdo->prepare($sql);
                $sth->execute([$scene['id']]);
                $scene['thumbnail'] = $sth->fetchColumn() ?: null;
                $cnt = $pdo->prepare("SELECT COUNT(*) FROM editorial_shots WHERE scene_id = ?");
                $cnt->execute([$scene['id']]);
                $scene['shot_count'] = $cnt->fetchColumn();
            }
            echo json_encode(['success' => true, 'data' => $scenes]);
            break;

        case 'create_scene':
            $seqId = (int)($_POST['sequence_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $sortOrder = getSortOrderInput($_POST['sort_order'] ?? '');
            if (!$seqId || empty($name)) throw new Exception('Name and Sequence ID required');
            $dirName = 'scene_' . date('Ymd_His') . '_' . substr(md5(uniqid()), 0, 4);
            $relPath = '/editorial/' . $dirName;
            $absPath = $publicPathAbs . $relPath;
            if (!file_exists($absPath)) mkdir($absPath, 0777, true);
            $stmt = $pdo->prepare("INSERT INTO editorial_scenes (sequence_id, name, description, directory, sort_order) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$seqId, $name, $_POST['description'] ?? '', $relPath, $sortOrder]);
            echo json_encode(['success' => true]);
            break;

        case 'update_scene':
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $sortOrder = getSortOrderInput($_POST['sort_order'] ?? '');
            if (!$id || empty($name)) throw new Exception('Invalid parameters');
            $stmt = $pdo->prepare("UPDATE editorial_scenes SET name = ?, description = ?, sort_order = ? WHERE id = ?");
            $stmt->execute([$name, $_POST['description'] ?? '', $sortOrder, $id]);
            echo json_encode(['success' => true]);
            break;

        case 'delete_scene':
            $id = (int)($_POST['id'] ?? 0);
            $chk = $pdo->prepare("SELECT COUNT(*) FROM editorial_shots WHERE scene_id = ?");
            $chk->execute([$id]);
            if ($chk->fetchColumn() > 0) throw new Exception('Cannot delete non-empty scene.');
            $stmt = $pdo->prepare("SELECT directory FROM editorial_scenes WHERE id = ?");
            $stmt->execute([$id]);
            $scene = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt = $pdo->prepare("DELETE FROM editorial_scenes WHERE id = ?");
            $stmt->execute([$id]);
            if ($scene && $scene['directory']) {
                $absPath = $publicPathAbs . $scene['directory'];
                if (is_dir($absPath)) @rmdir($absPath);
            }
            echo json_encode(['success' => true]);
            break;

        case 'reorder_scenes':
            $ids = json_decode($_POST['ids'] ?? '[]', true);
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE editorial_scenes SET sort_order = ? WHERE id = ?");
            foreach ($ids as $idx => $id) { $stmt->execute([$idx, (int)$id]); }
            $pdo->commit();
            echo json_encode(['success' => true]);
            break;

        case 'get_scene_details':
            $id = (int)($_GET['scene_id'] ?? 0);
            $stmt = $pdo->prepare("SELECT sc.*, seq.name as sequence_name, seq.id as sequence_id FROM editorial_scenes sc JOIN editorial_sequences seq ON sc.sequence_id = seq.id WHERE sc.id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'data' => $stmt->fetch(PDO::FETCH_ASSOC)]);
            break;

        case 'list_shots':
            $sceneId = (int)($_GET['scene_id'] ?? 0);
            if (!$sceneId) throw new Exception('Scene ID required');
            $stmt = $pdo->prepare(
                "SELECT s.*, v.thumbnail as video_thumbnail, v.duration as video_duration,
                        va.to_id as animatic_id
                 FROM editorial_shots s
                 LEFT JOIN videos v ON s.video_id = v.id
                 LEFT JOIN videos_2_animatics va ON va.from_id = s.video_id
                 WHERE s.scene_id = ?
                 ORDER BY s.sort_order ASC, s.id ASC"
            );
            $stmt->execute([$sceneId]);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'add_shot':
            $sceneId = (int)($_POST['scene_id'] ?? 0);
            $videoId = (int)($_POST['video_id'] ?? 0);
            if (!$sceneId || !$videoId) throw new Exception('Parameters missing');
            $stmt = $pdo->prepare("SELECT directory FROM editorial_scenes WHERE id = ?");
            $stmt->execute([$sceneId]);
            $scene = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$scene || !$scene['directory']) throw new Exception('Scene directory error');
            $stmt = $pdo->prepare("SELECT * FROM videos WHERE id = ?");
            $stmt->execute([$videoId]);
            $video = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$video) throw new Exception('Video not found');
            $videoUrl = $video['url'];
            $srcAbs = $publicPathAbs . '/' . ltrim($videoUrl, '/');
            if (!file_exists($srcAbs)) throw new Exception('Source video file missing: ' . basename($videoUrl));
            $ext = pathinfo($srcAbs, PATHINFO_EXTENSION);
            $baseName = pathinfo($srcAbs, PATHINFO_FILENAME);
            $newFilename = $baseName . '_' . uniqid() . '.' . $ext;
            $destRel = $scene['directory'] . '/' . $newFilename;
            $destAbs = $publicPathAbs . $destRel;
            if (!copy($srcAbs, $destAbs)) throw new Exception('Failed to copy video file');
            $stmt = $pdo->prepare("INSERT INTO editorial_shots (scene_id, name, description, video_id, filename, is_copied, duration_est) VALUES (?, ?, ?, ?, ?, 1, ?)");
            $stmt->execute([$sceneId, $video['name'], $video['description'], $videoId, $destRel, $video['duration'] ?: 2]);
            echo json_encode(['success' => true]);
            break;

        case 'delete_shot':
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $pdo->prepare("SELECT filename FROM editorial_shots WHERE id = ?");
            $stmt->execute([$id]);
            $shot = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($shot && $shot['filename']) {
                $abs = $publicPathAbs . '/' . ltrim($shot['filename'], '/');
                if (file_exists($abs)) @unlink($abs);
            }
            $pdo->prepare("DELETE FROM editorial_shots WHERE id = ?")->execute([$id]);
            echo json_encode(['success' => true]);
            break;

        case 'reorder_shots':
            $ids = json_decode($_POST['ids'] ?? '[]', true);
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE editorial_shots SET sort_order = ? WHERE id = ?");
            foreach ($ids as $idx => $id) { $stmt->execute([$idx, (int)$id]); }
            $pdo->commit();
            echo json_encode(['success' => true]);
            break;

        case 'auto_prefix_shots':
            $sceneId = (int)($_POST['scene_id'] ?? 0);
            $ids = json_decode($_POST['order'] ?? '[]', true);
            if (!$sceneId || empty($ids)) throw new Exception('Invalid parameters');
            $stmt = $pdo->prepare("SELECT directory FROM editorial_scenes WHERE id = ?");
            $stmt->execute([$sceneId]);
            $scene = $stmt->fetch(PDO::FETCH_ASSOC);
            $dirAbs = $publicPathAbs . $scene['directory'];
            $upd = $pdo->prepare("UPDATE editorial_shots SET filename = ? WHERE id = ?");
            $get = $pdo->prepare("SELECT filename FROM editorial_shots WHERE id = ?");
            foreach ($ids as $idx => $sid) {
                $get->execute([$sid]);
                $shot = $get->fetch(PDO::FETCH_ASSOC);
                if (!$shot || !$shot['filename']) continue;
                $oldAbs = $publicPathAbs . '/' . ltrim($shot['filename'], '/');
                if (!file_exists($oldAbs)) continue;
                $info = pathinfo($shot['filename']);
                $cleanName = preg_replace('/^\d{3}_/', '', $info['basename']);
                $prefix = str_pad($idx + 1, 3, '0', STR_PAD_LEFT);
                $newName = $prefix . '_' . $cleanName;
                if ($newName === $info['basename']) continue;
                $newAbs = $dirAbs . '/' . $newName;
                if (rename($oldAbs, $newAbs)) {
                    $newRel = $scene['directory'] . '/' . $newName;
                    $upd->execute([$newRel, $sid]);
                }
            }
            echo json_encode(['success' => true]);
            break;

        case 'export_zip':
            $sceneId = (int)($_POST['scene_id'] ?? 0);
            $stmt = $pdo->prepare("SELECT name, directory FROM editorial_scenes WHERE id = ?");
            $stmt->execute([$sceneId]);
            $scene = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt = $pdo->prepare("SELECT filename FROM editorial_shots WHERE scene_id = ? ORDER BY sort_order ASC");
            $stmt->execute([$sceneId]);
            $shots = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (empty($shots)) throw new Exception('No shots to export');
            $zipName = 'scene_' . preg_replace('/[^a-z0-9_-]/i', '_', $scene['name']) . '.zip';
            $tmpPath = sys_get_temp_dir() . '/' . $zipName;
            $zip = new ZipArchive();
            if ($zip->open($tmpPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new Exception('ZIP creation failed');
            foreach ($shots as $shot) {
                $abs = $publicPathAbs . '/' . ltrim($shot['filename'], '/');
                if (file_exists($abs)) $zip->addFile($abs, basename($shot['filename']));
            }
            $zip->close();
            echo json_encode(['success' => true, 'download_url' => 'editorial_download.php?file=' . urlencode($zipName)]);
            break;

        case 'get_scene_playlist':
            $sceneId = (int)($_GET['scene_id'] ?? 0);
            if (!$sceneId) throw new Exception('Scene ID required');
            $stmt = $pdo->prepare("SELECT s.name, s.filename, s.duration_est, v.thumbnail as video_thumbnail FROM editorial_shots s LEFT JOIN videos v ON s.video_id = v.id WHERE s.scene_id = ? ORDER BY s.sort_order ASC, s.id ASC");
            $stmt->execute([$sceneId]);
            $shots = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $data =[];
            foreach ($shots as $shot) {
                if (!empty($shot['filename'])) { $data[] = $shot; }
            }
            echo json_encode(['success' => true, 'data' => $data]);
            break;

        case 'list_categories':
            $stmt = $pdo->query("SELECT id, name FROM video_categories ORDER BY sort_order ASC, name ASC");
            echo json_encode(['success' => true, 'categories' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'list_playlists':
            $stmt = $pdo->query("SELECT id, name FROM video_playlists ORDER BY sort_order ASC, name ASC");
            echo json_encode(['success' => true, 'playlists' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'get_sequence_videos':
            $seqId = (int)($_GET['seq_id'] ?? 0);
            if (!$seqId) throw new Exception('seq_id required');
            // Sequence → all sketch IDs → all frames (direct + mapped) → animatics (img2img_frame_id) → videos
            $stmt = $pdo->prepare(
                "SELECT DISTINCT v.id, v.name, v.url, v.thumbnail, v.duration, v.file_size,
                        va.to_id as animatic_id
                 FROM videos v
                 JOIN videos_2_animatics va ON va.from_id = v.id
                 JOIN animatics a ON va.to_id = a.id
                 JOIN frames f ON a.img2img_frame_id = f.id
                 WHERE v.is_active = 1
                   AND (
                       (f.entity_type = 'sketches' AND f.entity_id IN (
                           SELECT CASE WHEN JSON_TYPE(jt.val) = 'INTEGER'
                                       THEN JSON_VALUE(jt.val, '$')
                                       ELSE JSON_VALUE(jt.val, '$.sketch_id')
                                  END
                           FROM narrative_sequences ns,
                           JSON_TABLE(ns.sequence_data, '$[*]' COLUMNS(val JSON PATH '$')) jt
                           WHERE ns.id = ?
                       ))
                       OR f.id IN (
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
                 ORDER BY v.created_at DESC"
            );
            $stmt->execute([$seqId, $seqId]);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'list_narrative_sequences':
            $stmt = $pdo->query("SELECT id, name, description FROM narrative_sequences ORDER BY id DESC");
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'list_storyboards':
            $search = trim($_GET['search'] ?? '');
            $page   = max(1, (int)($_GET['page'] ?? 1));
            $limit  = 20;
            $offset = ($page - 1) * $limit;
            
            $whereSQL = "WHERE is_archived = 0";
            $params =[];
            
            // Storyboards might have 'name', 'title', 'description'
            $cols =[];
            try {
                $cols = $pdo->query("SHOW COLUMNS FROM storyboards")->fetchAll(PDO::FETCH_COLUMN);
            } catch(Exception $e) { }
            
            $hasName = in_array('name', $cols);
            $hasTitle = in_array('title', $cols);
            $hasDesc = in_array('description', $cols);
            
            if ($search !== '') {
                $conds =[];
                if ($hasName) { $conds[] = "name LIKE ?"; $params[] = '%' . $search . '%'; }
                if ($hasTitle) { $conds[] = "title LIKE ?"; $params[] = '%' . $search . '%'; }
                if ($hasDesc) { $conds[] = "description LIKE ?"; $params[] = '%' . $search . '%'; }
                
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
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true, 
                'data' => $rows,
                'pagination' =>[
                    'page'  => $page,
                    'pages' => ceil($total / $limit),
                    'total' => $total
                ]
            ]);
            break;

        // --- UPDATED SEARCH WITH PAGINATION + NODE FILTER + FUZZ FILTER + STORYBOARDS ---
        case 'search_videos':
            $q        = $_GET['q'] ?? '';
            $nodeId   = (int)($_GET['node_id']      ?? 0);
            $seqId    = (int)($_GET['seq_id']        ?? 0);
            $fuzzCandId = (int)($_GET['fuzz_cand_id'] ?? 0);
            $storyboardId = (int)($_GET['storyboard_id'] ?? 0);
            $inclDesc = (int)($_GET['include_descendants'] ?? 1);
            $page     = max(1, (int)($_GET['page']   ?? 1));
            $limit    = max(1, (int)($_GET['limit']  ?? 10));
            $offset   = ($page - 1) * $limit;

            $joins  = "";
            $where  =["v.is_active = 1"];
            $params =[];

            if ($storyboardId) {
                // Fetch all frame references from the storyboard
                $sfStmt = $pdo->prepare(
                    "SELECT sf.frame_id, f.entity_type, f.entity_id
                     FROM storyboard_frames sf
                     JOIN frames f ON f.id = sf.frame_id
                     WHERE sf.storyboard_id = ?
                       AND f.entity_type IS NOT NULL
                       AND f.entity_id IS NOT NULL"
                );
                $sfStmt->execute([$storyboardId]);
                $sbFrames = $sfStmt->fetchAll(PDO::FETCH_ASSOC);

                if (empty($sbFrames)) {
                    $where[] = "1 = 0";
                } else {
                    $entityGroups =[];
                    foreach ($sbFrames as $row) {
                        $key = $row['entity_type'] . '|' . $row['entity_id'];
                        $entityGroups[$key] = ['entity_type' => $row['entity_type'], 'entity_id' => (int)$row['entity_id']];
                    }

                    $allFrameIds =[];
                    $allowedTables =['sketches','characters','locations','spawns','generatives','animas',
                                      'artifacts','lotations','character_poses','character_anima_poses',
                                      'character_expressions','animatics','composites'];

                    foreach ($entityGroups as $eg) {
                        $eType = $eg['entity_type'];
                        $eId   = $eg['entity_id'];

                        if (!in_array($eType, $allowedTables)) continue;

                        $directStmt = $pdo->prepare("SELECT id FROM frames WHERE entity_type = ? AND entity_id = ?");
                        $directStmt->execute([$eType, $eId]);
                        foreach ($directStmt->fetchAll(PDO::FETCH_COLUMN) as $fid) {
                            $allFrameIds[] = (int)$fid;
                        }

                        $mapTable = "frames_2_{$eType}";
                        $checkMap = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($mapTable));
                        if ($checkMap && $checkMap->rowCount() > 0) {
                            $mapStmt = $pdo->prepare("SELECT from_id FROM `$mapTable` WHERE to_id = ?");
                            $mapStmt->execute([$eId]);
                            foreach ($mapStmt->fetchAll(PDO::FETCH_COLUMN) as $fid) {
                                $allFrameIds[] = (int)$fid;
                            }
                        }
                    }

                    $allFrameIds = array_unique($allFrameIds);

                    if (empty($allFrameIds)) {
                        $where[] = "1 = 0";
                    } else {
                        $inClause = implode(',', $allFrameIds);
                        $where[] = "v.id IN (
                            SELECT va2.from_id
                            FROM videos_2_animatics va2
                            JOIN animatics an ON va2.to_id = an.id
                            WHERE an.img2img_frame_id IN ($inClause)
                        )";
                    }
                }

            } elseif ($fuzzCandId) {
                // Fuzz candidate → sketch IDs via fuzz_mentions → frames → animatics → videos
                // Mirrors the join chain in view_fuzz_preview.php get_candidate_videos
                $where[] = "v.id IN (
                    SELECT DISTINCT va2.from_id
                    FROM videos_2_animatics va2
                    JOIN animatics an ON va2.to_id = an.id
                    JOIN frames fr ON an.img2img_frame_id = fr.id
                    WHERE (
                        (fr.entity_type = 'sketches' AND fr.entity_id IN (
                            SELECT DISTINCT source_row_id
                            FROM fuzz_mentions
                            WHERE candidate_id = ?
                              AND source_table IN ('sketches','sketch_analysis','sketch_lore_history','sketch_ingredients')
                              AND source_row_id IS NOT NULL
                        ))
                        OR fr.id IN (
                            SELECT f2s.from_id FROM frames_2_sketches f2s
                            WHERE f2s.to_id IN (
                                SELECT DISTINCT source_row_id
                                FROM fuzz_mentions
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
                // Narrative sequence → sketches → all their frames → animatics (img2img_frame_id) → videos
                $where[] = "v.id IN (
                    SELECT DISTINCT va2.from_id
                    FROM videos_2_animatics va2
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

            if ($q) {
                $where[] = "(v.name LIKE ? OR v.description LIKE ?)";
                $params[] = "%$q%";
                $params[] = "%$q%";
            }

            $whereStr = " WHERE " . implode(" AND ", $where);

            // Get Total Count
            $countSql = "SELECT COUNT(DISTINCT v.id) FROM videos v $joins $whereStr";
            $stmt = $pdo->prepare($countSql);
            $stmt->execute($params);
            $totalItems = (int)$stmt->fetchColumn();
            $totalPages = (int)ceil($totalItems / $limit);

            // Get Data — include animatic_id so the picker can show the 🎬 badge
            $sql = "SELECT DISTINCT v.id, v.name, v.thumbnail, v.duration, v.description,
                           va.to_id as animatic_id
                    FROM videos v
                    LEFT JOIN videos_2_animatics va ON va.from_id = v.id
                    $joins $whereStr
                    ORDER BY v.created_at DESC
                    LIMIT $limit OFFSET $offset";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data'    => $videos,
                'pagination' =>[
                    'total' => $totalItems,
                    'page'  => $page,
                    'limit' => $limit,
                    'pages' => $totalPages,
                ],
            ]);
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}