<?php
// storyboards_api.php - Backend API for database-driven storyboards
require "error_reporting.php";
require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        
        // --- Categories --- //
        case 'get_categories':
            $stmt = $pdo->query("SELECT * FROM storyboard_categories ORDER BY sort_order ASC");
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'list':
            // Filter by archive status (default to 0/Active)
            $showArchived = (isset($_GET['archived']) && $_GET['archived'] === 'true') ? 1 : 0;

            $sql = "
                SELECT s.*, 
                       COUNT(sf.id) as frame_count,
                       cat.name as category_name,
                       cat.code as category_code,
                       sc.name as scene_name,
                       sq.name as sequence_name,
                       sq.id as sequence_id,
                       ep.name as episode_name,
                       ep.number as episode_number,
                       ep.id as episode_id
                FROM storyboards s
                LEFT JOIN storyboard_frames sf ON s.id = sf.storyboard_id
                LEFT JOIN storyboard_categories cat ON s.category_id = cat.id
                LEFT JOIN editorial_scenes sc ON s.editorial_scene_id = sc.id
                LEFT JOIN editorial_sequences sq ON sc.sequence_id = sq.id
                LEFT JOIN editorial_episodes ep ON sq.episode_id = ep.id
                WHERE s.is_archived = ?
                GROUP BY s.id
                ORDER BY s.updated_at DESC
            ";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$showArchived]);
            $storyboards = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($storyboards as &$sb) {
                // Find first available image (copied or not)
                $stmt = $pdo->prepare("
                    SELECT filename FROM storyboard_frames 
                    WHERE storyboard_id = ? AND is_copied = 1
                    ORDER BY sort_order ASC LIMIT 1
                ");
                $stmt->execute([$sb['id']]);
                $firstFrame = $stmt->fetch(PDO::FETCH_ASSOC);
                $sb['thumbnail'] = $firstFrame ? $firstFrame['filename'] : null;
            }
            
            echo json_encode(['success' => true, 'data' => $storyboards]);
            break;

        // --- Cascading Dropdown Data --- //

        case 'get_episodes':
            $stmt = $pdo->query("SELECT id, name, number FROM editorial_episodes ORDER BY number ASC");
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'get_sequences':
            $epId = (int)($_GET['episode_id'] ?? 0);
            $stmt = $pdo->prepare("SELECT id, name, sort_order FROM editorial_sequences WHERE episode_id = ? ORDER BY sort_order ASC");
            $stmt->execute([$epId]);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'get_scenes':
            $seqId = (int)($_GET['sequence_id'] ?? 0);
            $stmt = $pdo->prepare("SELECT id, name, sort_order FROM editorial_scenes WHERE sequence_id = ? ORDER BY sort_order ASC");
            $stmt->execute([$seqId]);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        // --- CRUD --- //

        case 'create':
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : 
                          $pdo->query("SELECT id FROM storyboard_categories WHERE code='misc'")->fetchColumn();
            $editorialSceneId = !empty($_POST['editorial_scene_id']) ? (int)$_POST['editorial_scene_id'] : null;
            $customTag = trim($_POST['custom_tag'] ?? '');

            if (empty($name)) throw new Exception('Storyboard name is required');
            
            $dirName = 'storyboard' . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
            $directory = '/storyboards/' . $dirName;
            
            $docRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
            if (!is_dir($docRoot . $directory)) mkdir($docRoot . $directory, 0777, true);
            
            $stmt = $pdo->prepare("INSERT INTO storyboards (name, description, directory, category_id, editorial_scene_id, custom_tag) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $description, $directory, $categoryId, $editorialSceneId, $customTag]);
            
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
            break;

        case 'update':
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
            $editorialSceneId = !empty($_POST['editorial_scene_id']) ? (int)$_POST['editorial_scene_id'] : null;
            $customTag = trim($_POST['custom_tag'] ?? '');
            
            if (!$id || empty($name)) throw new Exception('Invalid parameters');
            
            $stmt = $pdo->prepare("UPDATE storyboards SET name = ?, description = ?, category_id = ?, editorial_scene_id = ?, custom_tag = ? WHERE id = ?");
            $stmt->execute([$name, $description, $categoryId, $editorialSceneId, $customTag, $id]);
            echo json_encode(['success' => true]);
            break;

        case 'toggle_archive':
            $id = (int)($_POST['id'] ?? 0);
            $archiveStatus = (int)($_POST['is_archived'] ?? 0); // 1 to archive, 0 to restore
            
            if (!$id) throw new Exception('Invalid ID');

            $stmt = $pdo->prepare("UPDATE storyboards SET is_archived = ? WHERE id = ?");
            $stmt->execute([$archiveStatus, $id]);
            echo json_encode(['success' => true]);
            break;

        case 'copy':
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) throw new Exception('Invalid ID');

            // 1. Fetch Original
            $stmt = $pdo->prepare("SELECT * FROM storyboards WHERE id = ?");
            $stmt->execute([$id]);
            $src = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$src) throw new Exception('Source not found');

            // 2. Create New Entry
            $newName = "Copy of " . $src['name'];
            $dirName = 'storyboard' . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
            $newDirectory = '/storyboards/' . $dirName;

            $docRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
            if (!is_dir($docRoot . $newDirectory)) mkdir($docRoot . $newDirectory, 0777, true);

            $stmt = $pdo->prepare("
                INSERT INTO storyboards (name, description, directory, category_id, editorial_scene_id, custom_tag) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$newName, $src['description'], $newDirectory, $src['category_id'], $src['editorial_scene_id'], $src['custom_tag']]);
            $newId = $pdo->lastInsertId();

            // 3. Duplicate Frames (Database only, physical copy happens on view)
            $stmt = $pdo->prepare("SELECT * FROM storyboard_frames WHERE storyboard_id = ? ORDER BY sort_order ASC");
            $stmt->execute([$id]);
            $frames = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $insertFrame = $pdo->prepare("
                INSERT INTO storyboard_frames 
                (storyboard_id, frame_id, name, description, filename, sort_order, is_copied, original_filename) 
                VALUES (?, ?, ?, ?, ?, ?, 0, ?)
            ");

            foreach ($frames as $f) {
                $insertFrame->execute([
                    $newId, 
                    $f['frame_id'], 
                    $f['name'], 
                    $f['description'], 
                    '', // Reset filename
                    $f['sort_order'], 
                    $f['original_filename']
                ]);
            }

            echo json_encode(['success' => true, 'new_id' => $newId]);
            break;

        case 'clone_to_narrative':
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) throw new Exception('Invalid storyboard ID');

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT sf.*, f.entity_type, f.entity_id, f.filename as frame_filename, f.prompt, f.prompt_negative, f.seed, f.name as source_frame_name
                FROM storyboard_frames sf
                LEFT JOIN frames f ON sf.frame_id = f.id
                WHERE sf.storyboard_id = ?
                ORDER BY sf.sort_order ASC
            ");
            $stmt->execute([$id]);
            $storyboardFrames = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($storyboardFrames)) {
                $pdo->rollBack();
                throw new Exception('Storyboard has no frames');
            }

            $toMigrate = [];
            foreach ($storyboardFrames as $sf) {
                if ($sf['frame_id'] && $sf['entity_type'] && $sf['entity_type'] !== 'sketches') {
                    $toMigrate[$sf['entity_type']][$sf['entity_id']][] = $sf;
                }
            }

            $migratedFrameMap = [];

            if (!empty($toMigrate)) {
                $stmtCheckEntityMap = $pdo->prepare("SELECT target_sketch_id FROM sketch_migration_entities WHERE source_type = ? AND source_id = ?");
                $stmtInsertEntityMap = $pdo->prepare("INSERT INTO sketch_migration_entities (source_type, source_id, target_sketch_id) VALUES (?, ?, ?)");

                $stmtSketch = $pdo->prepare("
                    INSERT INTO sketches 
                    (name, description, prompt_negative, seed, created_at, updated_at, active_map_run_id, regenerate_images)
                    VALUES (?, ?, ?, ?, NOW(), NOW(), ?, 0)
                ");

                $stmtFrame = $pdo->prepare("
                    INSERT INTO frames 
                    (map_run_id, name, filename, prompt, prompt_negative, seed, entity_type, entity_id, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, 'sketches', ?, NOW())
                ");

                $stmtLink = $pdo->prepare("INSERT INTO frames_2_sketches (from_id, to_id) VALUES (?, ?)");

                $stmtCheckFrameMap = $pdo->prepare("SELECT target_frame_id FROM sketch_migration_frames WHERE source_frame_id = ?");
                $stmtInsertFrameMap = $pdo->prepare("INSERT IGNORE INTO sketch_migration_frames (source_frame_id, target_frame_id) VALUES (?, ?)");

                foreach ($toMigrate as $sourceType => $entities) {
                    $note = "Storyboard Clone Migration from " . ucfirst($sourceType);
                    $stmtMR = $pdo->prepare("INSERT INTO map_runs (entity_type, note, created_at) VALUES ('sketches', ?, NOW())");
                    $stmtMR->execute([$note]);
                    $newMapRunId = $pdo->lastInsertId();

                    foreach ($entities as $sourceId => $frames) {
                        try {
                            $entityDataStmt = $pdo->prepare("SELECT * FROM `$sourceType` WHERE id = ?");
                            $entityDataStmt->execute([$sourceId]);
                            $eData = $entityDataStmt->fetch(PDO::FETCH_ASSOC);
                        } catch (Exception $e) {
                            $eData = null; // Table might not exist or other issues
                        }

                        if (!$eData) continue;

                        $stmtCheckEntityMap->execute([$sourceType, $sourceId]);
                        $targetSketchId = $stmtCheckEntityMap->fetchColumn();

                        if ($targetSketchId) {
                            $pdo->prepare("UPDATE sketches SET updated_at = NOW() WHERE id = ?")->execute([$targetSketchId]);
                        } else {
                            $baseName = $eData['name'] ?? 'Unknown';
                            $newName = $baseName;

                            $checkName = $pdo->prepare("SELECT id FROM sketches WHERE name = ?");
                            $checkName->execute([$newName]);
                            if ($checkName->fetch()) {
                                $newName = $baseName . " (Mig " . date('ymd') . ")";
                            }

                            $stmtSketch->execute([
                                $newName,
                                $eData['description'] ?? '',
                                $eData['prompt_negative'] ?? '',
                                $eData['seed'] ?? null,
                                $newMapRunId
                            ]);
                            $targetSketchId = $pdo->lastInsertId();
                            $stmtInsertEntityMap->execute([$sourceType, $sourceId, $targetSketchId]);
                        }

                        foreach ($frames as $fRow) {
                            $sourceFrameId = $fRow['frame_id'];

                            $stmtCheckFrameMap->execute([$sourceFrameId]);
                            $targetFrameId = $stmtCheckFrameMap->fetchColumn();

                            if (!$targetFrameId) {
                                $stmtFrame->execute([
                                    $newMapRunId,
                                    $fRow['source_frame_name'] ?? $eData['name'] ?? 'Frame',
                                    $fRow['frame_filename'] ?? $fRow['filename'],
                                    $fRow['prompt'],
                                    $fRow['prompt_negative'],
                                    $fRow['seed'],
                                    $targetSketchId
                                ]);
                                $targetFrameId = $pdo->lastInsertId();

                                $stmtLink->execute([$targetFrameId, $targetSketchId]);
                                $stmtInsertFrameMap->execute([$sourceFrameId, $targetFrameId]);
                            }

                            $migratedFrameMap[$sourceFrameId] = [
                                'sketch_id' => $targetSketchId,
                                'frame_id' => $targetFrameId
                            ];
                        }
                    }
                }
            }

            $sequenceData = [];
            foreach ($storyboardFrames as $sf) {
                if (!$sf['frame_id']) continue; // Skip standalone frames with no original frame

                if ($sf['entity_type'] === 'sketches') {
                    $sequenceData[] = [
                        'sketch_id' => (int)$sf['entity_id'],
                        'frame_id' => (int)$sf['frame_id']
                    ];
                } elseif (isset($migratedFrameMap[$sf['frame_id']])) {
                    $sequenceData[] = [
                        'sketch_id' => (int)$migratedFrameMap[$sf['frame_id']]['sketch_id'],
                        'frame_id' => (int)$migratedFrameMap[$sf['frame_id']]['frame_id']
                    ];
                }
            }

            $sbStmt = $pdo->prepare("SELECT * FROM storyboards WHERE id = ?");
            $sbStmt->execute([$id]);
            $sb = $sbStmt->fetch(PDO::FETCH_ASSOC);

            $seqName = "SB Clone: " . $sb['name'];
            $seqDesc = $sb['description'];

            $insertSeq = $pdo->prepare("INSERT INTO narrative_sequences (name, description, sequence_data, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())");
            $insertSeq->execute([$seqName, $seqDesc, json_encode($sequenceData)]);
            $newSeqId = $pdo->lastInsertId();

            $pdo->commit();

            echo json_encode(['success' => true, 'sequence_id' => $newSeqId]);
            break;

        case 'delete':
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) throw new Exception('Invalid ID');
            
            $stmt = $pdo->prepare("SELECT directory FROM storyboards WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) throw new Exception('Not found');
            
            $stmt = $pdo->prepare("DELETE FROM storyboard_frames WHERE storyboard_id = ?");
            $stmt->execute([$id]);
            $stmt = $pdo->prepare("DELETE FROM storyboards WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
            break;

        case 'get_frames':
            $storyboardId = (int)($_GET['storyboard_id'] ?? 0);
            if (!$storyboardId) throw new Exception('Invalid ID');
            
            $stmt = $pdo->prepare("
                SELECT sf.*, 
                       f.prompt, f.entity_type, f.entity_id, 
                       f.rating 
                FROM storyboard_frames sf
                LEFT JOIN frames f ON sf.frame_id = f.id
                WHERE sf.storyboard_id = ?
                ORDER BY sf.sort_order ASC
            ");
            $stmt->execute([$storyboardId]);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'save_order':
            $storyboardId = (int)($_POST['storyboard_id'] ?? 0);
            $order = json_decode($_POST['order'] ?? '[]', true);
            $stmt = $pdo->prepare("UPDATE storyboard_frames SET sort_order = ? WHERE id = ? AND storyboard_id = ?");
            foreach ($order as $idx => $frameId) $stmt->execute([$idx, $frameId, $storyboardId]);
            echo json_encode(['success' => true]);
            break;

        case 'delete_storyboard_frame':
            $storyboardFrameId = (int)($_POST['storyboard_frame_id'] ?? 0);
            if (!$storyboardFrameId) throw new Exception('Invalid storyboard_frame_id');

            $stmt = $pdo->prepare("DELETE FROM storyboard_frames WHERE id = ?");
            $stmt->execute([$storyboardFrameId]);

            echo json_encode(['success' => true, 'deleted_count' => 1]);
            break;

        case 'bulk_delete_storyboard_frames':
            $storyboardId = (int)($_POST['storyboard_id'] ?? 0);
            $storyboardFrameIds = json_decode($_POST['storyboard_frame_ids'] ?? '[]', true);

            if (!$storyboardId) throw new Exception('Invalid storyboard_id');
            if (!is_array($storyboardFrameIds) || empty($storyboardFrameIds)) {
                throw new Exception('No storyboard_frame_ids provided');
            }

            $storyboardFrameIds = array_values(array_filter(array_map('intval', $storyboardFrameIds), function ($id) {
                return $id > 0;
            }));

            if (empty($storyboardFrameIds)) {
                throw new Exception('No valid storyboard_frame_ids provided');
            }

            $placeholders = implode(',', array_fill(0, count($storyboardFrameIds), '?'));

            $stmt = $pdo->prepare("
                DELETE FROM storyboard_frames
                WHERE storyboard_id = ?
                  AND id IN ($placeholders)
            ");
            $params = array_merge([$storyboardId], $storyboardFrameIds);
            $stmt->execute($params);

            echo json_encode([
                'success' => true,
                'deleted_count' => $stmt->rowCount()
            ]);
            break;

        case 'delete_frame':
            $frameId = (int)($_POST['frame_id'] ?? 0);
            $stmt = $pdo->prepare("SELECT filename, is_copied FROM storyboard_frames WHERE id = ?");
            $stmt->execute([$frameId]);
            $frame = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($frame && $frame['is_copied']) {
                $f = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/') . $frame['filename'];
                if (file_exists($f)) @unlink($f);
            }
            $stmt = $pdo->prepare("DELETE FROM storyboard_frames WHERE id = ?");
            $stmt->execute([$frameId]);
            echo json_encode(['success' => true]);
            break;

        case 'rename_in_order':
            $storyboardId = (int)($_POST['storyboard_id'] ?? 0);
            $order = json_decode($_POST['order'] ?? '[]', true);
            $stmt = $pdo->prepare("SELECT directory FROM storyboards WHERE id = ?");
            $stmt->execute([$storyboardId]);
            $sb = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$sb) throw new Exception('Not found');
            
            $docRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
            $sbDir = $docRoot . $sb['directory'];
            $errors = [];
            
            foreach ($order as $idx => $frameId) {
                $stmt = $pdo->prepare("SELECT * FROM storyboard_frames WHERE id = ?");
                $stmt->execute([$frameId]);
                $frame = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$frame || !$frame['is_copied']) continue;
                
                $cur = $docRoot . $frame['filename'];
                if (!file_exists($cur)) { $errors[] = "Missing: {$frame['filename']}"; continue; }
                
                $ext = pathinfo($frame['filename'], PATHINFO_EXTENSION);
                $base = preg_replace('/^\d{1,3}_/', '', pathinfo($frame['filename'], PATHINFO_FILENAME));
                $new = str_pad($idx + 1, 3, '0', STR_PAD_LEFT) . '_' . $base . '.' . $ext;
                
                if ($cur !== $sbDir.'/'.$new) {
                    if (!@rename($cur, $sbDir.'/'.$new)) { $errors[] = "Rename failed: $base"; continue; }
                    $pdo->prepare("UPDATE storyboard_frames SET filename = ?, sort_order = ? WHERE id = ?")
                        ->execute([$sb['directory'].'/'.$new, $idx, $frameId]);
                }
            }
            if ($errors) echo json_encode(['success'=>false, 'message'=>implode('; ',$errors)]);
            else echo json_encode(['success'=>true]);
            break;

        case 'export_zip':
            $storyboardId = (int)($_POST['storyboard_id'] ?? 0);
            $stmt = $pdo->prepare("SELECT * FROM storyboards WHERE id = ?");
            $stmt->execute([$storyboardId]);
            $sb = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$sb) throw new Exception('Not found');
            
            $stmt = $pdo->prepare("SELECT * FROM storyboard_frames WHERE storyboard_id = ? AND is_copied=1 ORDER BY sort_order ASC");
            $stmt->execute([$storyboardId]);
            $frames = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!$frames) throw new Exception('No frames');
            
            $zipName = 'storyboard_' . preg_replace('/[^a-z0-9_-]/i', '_', $sb['name']) . '_' . date('Ymd_His') . '.zip';
            $zipPath = sys_get_temp_dir() . '/' . $zipName;
            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true) throw new Exception('Zip fail');
            
            $docRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
            foreach ($frames as $f) {
                if (file_exists($docRoot.$f['filename'])) $zip->addFile($docRoot.$f['filename'], basename($f['filename']));
            }
            $zip->addFromString('metadata.json', json_encode(['storyboard'=>$sb,'frames'=>$frames], JSON_PRETTY_PRINT));
            $zip->close();
            
            echo json_encode(['success'=>true, 'download_url'=>'/storyboards_download.php?file='.urlencode($zipName)]);
            break;

        default: throw new Exception('Unknown action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}