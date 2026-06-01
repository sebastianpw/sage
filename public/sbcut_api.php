<?php
// public/sbcut_api.php
// API for the Storyboard Split/Copy & Reorder Tool
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {

        case 'copy_storyboard':
            $storyboardId = (int)($_POST['storyboard_id'] ?? 0);
            $newName      = trim($_POST['new_name'] ?? '');
            
            if (!$storyboardId || !$newName) {
                throw new Exception('Storyboard ID and new name are required.');
            }

            $pdo->beginTransaction();

            // Fetch original
            $stmt = $pdo->prepare("SELECT * FROM storyboards WHERE id = ?");
            $stmt->execute([$storyboardId]);
            $sb = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$sb) throw new Exception('Original storyboard not found.');

            // Create new directory for the copy
            $dirName = 'storyboard' . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
            $newDirectory = '/storyboards/' . $dirName;
            $docRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
            if (!is_dir($docRoot . $newDirectory)) mkdir($docRoot . $newDirectory, 0777, true);

            // Insert new storyboard
            $ins = $pdo->prepare("
                INSERT INTO storyboards (name, description, directory, category_id, editorial_scene_id, custom_tag) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $ins->execute([
                $newName,
                $sb['description'],
                $newDirectory,
                $sb['category_id'],
                $sb['editorial_scene_id'],
                $sb['custom_tag']
            ]);
            $newId = (int)$pdo->lastInsertId();

            // Duplicate frames
            $framesStmt = $pdo->prepare("SELECT * FROM storyboard_frames WHERE storyboard_id = ? ORDER BY sort_order ASC");
            $framesStmt->execute([$storyboardId]);
            $frames = $framesStmt->fetchAll(PDO::FETCH_ASSOC);

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
                    '', // Empty filename so it gets copied automatically on view
                    $f['sort_order'], 
                    $f['original_filename']
                ]);
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'new_storyboard_id' => $newId]);
            break;

        case 'split_storyboard':
            $storyboardId = (int)($_POST['storyboard_id'] ?? 0);
            $splitIndex   = (int)($_POST['split_index'] ?? 0);
            $newName      = trim($_POST['new_name'] ?? '');
            
            if (!$storyboardId || !$newName || $splitIndex <= 0) {
                throw new Exception('Invalid parameters for split.');
            }

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT * FROM storyboards WHERE id = ?");
            $stmt->execute([$storyboardId]);
            $sb = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$sb) throw new Exception('Original storyboard not found.');

            // Fetch frames to split
            $framesStmt = $pdo->prepare("SELECT * FROM storyboard_frames WHERE storyboard_id = ? ORDER BY sort_order ASC, id ASC");
            $framesStmt->execute([$storyboardId]);
            $frames = $framesStmt->fetchAll(PDO::FETCH_ASSOC);

            if ($splitIndex >= count($frames)) {
                throw new Exception('Split index is out of bounds.');
            }

            // Split arrays
            $part1 = array_slice($frames, 0, $splitIndex);
            $part2 = array_slice($frames, $splitIndex);

            // Create new directory for Part 2
            $dirName = 'storyboard' . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
            $newDirectory = '/storyboards/' . $dirName;
            $docRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
            if (!is_dir($docRoot . $newDirectory)) mkdir($docRoot . $newDirectory, 0777, true);

            // Insert new storyboard for Part 2
            $ins = $pdo->prepare("
                INSERT INTO storyboards (name, description, directory, category_id, editorial_scene_id, custom_tag) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $ins->execute([
                $newName,
                $sb['description'],
                $newDirectory,
                $sb['category_id'],
                $sb['editorial_scene_id'],
                $sb['custom_tag']
            ]);
            $newId = (int)$pdo->lastInsertId();

            $insertFrame = $pdo->prepare("
                INSERT INTO storyboard_frames 
                (storyboard_id, frame_id, name, description, filename, sort_order, is_copied, original_filename) 
                VALUES (?, ?, ?, ?, '', ?, 0, ?)
            ");
            $deleteFrame = $pdo->prepare("DELETE FROM storyboard_frames WHERE id = ?");

            // Move Part 2 to new Storyboard
            foreach ($part2 as $idx => $f) {
                // Insert into new 
                $insertFrame->execute([
                    $newId,
                    $f['frame_id'],
                    $f['name'],
                    $f['description'],
                    $idx, // reset sort order starting from 0
                    $f['original_filename']
                ]);

                // Delete from old
                $deleteFrame->execute([$f['id']]);

                // Physically unlink the old file to save space if it was copied
                if ($f['is_copied'] && $f['filename']) {
                    $physicalPath = $docRoot . $f['filename'];
                    if (file_exists($physicalPath)) {
                        @unlink($physicalPath);
                    }
                }
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'new_storyboard_id' => $newId, 'original_id' => $storyboardId]);
            break;

        case 'reorder_storyboard':
            $storyboardId = (int)($_POST['storyboard_id'] ?? 0);
            $orderRaw     = $_POST['order'] ?? '';
            
            if (!$storyboardId || $orderRaw === '') {
                throw new Exception('Invalid parameters for reorder.');
            }

            // Split comma-separated IDs
            $frameIds = array_filter(explode(',', $orderRaw), 'is_numeric');
            $frameIds = array_map('intval', $frameIds);

            $pdo->beginTransaction();
            
            $upd = $pdo->prepare("UPDATE storyboard_frames SET sort_order = ? WHERE id = ? AND storyboard_id = ?");
            foreach ($frameIds as $idx => $fId) {
                $upd->execute([$idx, $fId, $storyboardId]);
            }
            
            $pdo->commit();
            echo json_encode(['success' => true]);
            break;

        case 'remove_storyboard_item':
            $storyboardId  = (int)($_POST['storyboard_id'] ?? 0);
            $frameRecordId = (int)($_POST['frame_record_id'] ?? 0);
            
            if (!$storyboardId || !$frameRecordId) {
                throw new Exception('Invalid parameters for removal.');
            }
            
            $pdo->beginTransaction();
            
            // Check if frame exists and is part of this storyboard
            $stmt = $pdo->prepare("SELECT filename, is_copied FROM storyboard_frames WHERE id = ? AND storyboard_id = ? FOR UPDATE");
            $stmt->execute([$frameRecordId, $storyboardId]);
            $frame = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$frame) {
                throw new Exception('Storyboard frame not found.');
            }
            
            // Delete record
            $del = $pdo->prepare("DELETE FROM storyboard_frames WHERE id = ?");
            $del->execute([$frameRecordId]);
            
            // Unlink file if it was a distinct local copy generated by the storyboard system
            if ($frame['is_copied'] && !empty($frame['filename'])) {
                $docRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
                $physicalPath = $docRoot . $frame['filename'];
                if (file_exists($physicalPath) && is_file($physicalPath)) {
                    @unlink($physicalPath);
                }
            }
            
            $pdo->commit();
            echo json_encode(['success' => true]);
            break;

            
        case 'export_storyboard':
            $storyboardId = (int)($_POST['storyboard_id'] ?? 0);
            if (!$storyboardId) throw new Exception('Storyboard ID required.');

            $stmt = $pdo->prepare("SELECT * FROM storyboards WHERE id = ?");
            $stmt->execute([$storyboardId]);
            $sb = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$sb) throw new Exception('Storyboard not found.');

            $framesStmt = $pdo->prepare("SELECT * FROM storyboard_frames WHERE storyboard_id = ? ORDER BY sort_order ASC, id ASC");
            $framesStmt->execute([$storyboardId]);
            $frames = $framesStmt->fetchAll(PDO::FETCH_ASSOC);

            $exportData = [
                'storyboard_id' => $sb['id'],
                'name'          => $sb['name'],
                'description'   => $sb['description'],
                'category_id'   => $sb['category_id'],
                'created_at'    => $sb['created_at'],
                'updated_at'    => $sb['updated_at'],
                'frames'        => $frames
            ];

            echo json_encode(['success' => true, 'export' => $exportData]);
            break;

        case 'delete_storyboard':
            $storyboardId = (int)($_POST['storyboard_id'] ?? 0);
            if (!$storyboardId) throw new Exception('Storyboard ID required.');

            // Fetch storyboard dir and frames to delete physical files
            $stmt = $pdo->prepare("SELECT directory FROM storyboards WHERE id = ?");
            $stmt->execute([$storyboardId]);
            $sb = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($sb) {
                $framesStmt = $pdo->prepare("SELECT filename, is_copied FROM storyboard_frames WHERE storyboard_id = ?");
                $framesStmt->execute([$storyboardId]);
                $docRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
                
                // Delete copied image files
                foreach ($framesStmt->fetchAll(PDO::FETCH_ASSOC) as $f) {
                    if ($f['is_copied'] && !empty($f['filename'])) {
                        $physicalPath = $docRoot . $f['filename'];
                        if (file_exists($physicalPath) && is_file($physicalPath)) {
                            @unlink($physicalPath);
                        }
                    }
                }
                
                // Try to delete the directory itself
                if (!empty($sb['directory'])) {
                    $dirPath = $docRoot . $sb['directory'];
                    if (is_dir($dirPath)) {
                        @rmdir($dirPath); 
                    }
                }
            }

            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM storyboard_frames WHERE storyboard_id = ?")->execute([$storyboardId]);
            $pdo->prepare("DELETE FROM storyboards WHERE id = ?")->execute([$storyboardId]);
            $pdo->commit();

            echo json_encode(['success' => true]);
            break;


        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}