<?php
// storyboards_v2_api.php - Backend API for database-driven storyboards
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
        case 'list':
            // Get all storyboards with frame counts
            $stmt = $pdo->query("
                SELECT s.*, COUNT(sf.id) as frame_count
                FROM storyboards s
                LEFT JOIN storyboard_frames sf ON s.id = sf.storyboard_id
                GROUP BY s.id
                ORDER BY s.updated_at DESC
            ");
            $storyboards = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get first frame thumbnail for each storyboard
            foreach ($storyboards as &$sb) {
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

        case 'create':
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            
            if (empty($name)) {
                throw new Exception('Storyboard name is required');
            }
            
            // Generate unique directory name
            $dirName = 'storyboard' . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
            $directory = '/storyboards/' . $dirName;
            
            // Create physical directory
            $docRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
            $physicalPath = $docRoot . $directory;
            
            if (!is_dir($physicalPath)) {
                if (!mkdir($physicalPath, 0777, true)) {
                    throw new Exception('Failed to create storyboard directory');
                }
            }
            
            // Insert into database
            $stmt = $pdo->prepare("
                INSERT INTO storyboards (name, description, directory) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$name, $description, $directory]);
            $id = $pdo->lastInsertId();
            
            echo json_encode(['success' => true, 'id' => $id, 'directory' => $directory]);
            break;

        case 'update':
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            
            if (!$id || empty($name)) {
                throw new Exception('Invalid parameters');
            }
            
            $stmt = $pdo->prepare("
                UPDATE storyboards 
                SET name = ?, description = ? 
                WHERE id = ?
            ");
            $stmt->execute([$name, $description, $id]);
            
            echo json_encode(['success' => true]);
            break;

        case 'delete':
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) {
                throw new Exception('Invalid storyboard ID');
            }
            
            // Get directory path
            $stmt = $pdo->prepare("SELECT directory FROM storyboards WHERE id = ?");
            $stmt->execute([$id]);
            $sb = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$sb) {
                throw new Exception('Storyboard not found');
            }
            
            // Delete all frames from database
            $stmt = $pdo->prepare("DELETE FROM storyboard_frames WHERE storyboard_id = ?");
            $stmt->execute([$id]);
            
            // Delete storyboard
            $stmt = $pdo->prepare("DELETE FROM storyboards WHERE id = ?");
            $stmt->execute([$id]);
            
            // Optionally delete physical directory (commented out for safety)
            // $docRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
            // $physicalPath = $docRoot . $sb['directory'];
            // if (is_dir($physicalPath)) {
            //     // Recursive delete would go here
            // }
            
            echo json_encode(['success' => true]);
            break;

        case 'get_frames':
            $storyboardId = (int)($_GET['storyboard_id'] ?? 0);
            if (!$storyboardId) {
                throw new Exception('Invalid storyboard ID');
            }
            
            $stmt = $pdo->prepare("
                SELECT sf.*, f.prompt, f.entity_type, f.entity_id
                FROM storyboard_frames sf
                LEFT JOIN frames f ON sf.frame_id = f.id
                WHERE sf.storyboard_id = ?
                ORDER BY sf.sort_order ASC
            ");
            $stmt->execute([$storyboardId]);
            $frames = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'data' => $frames]);
            break;

        case 'save_order':
            $storyboardId = (int)($_POST['storyboard_id'] ?? 0);
            $orderJson = $_POST['order'] ?? '[]';
            $order = json_decode($orderJson, true);
            
            if (!$storyboardId || !is_array($order)) {
                throw new Exception('Invalid parameters');
            }
            
            // Update sort_order for each frame
            $stmt = $pdo->prepare("
                UPDATE storyboard_frames 
                SET sort_order = ? 
                WHERE id = ? AND storyboard_id = ?
            ");
            
            foreach ($order as $idx => $frameId) {
                $stmt->execute([$idx, $frameId, $storyboardId]);
            }
            
            echo json_encode(['success' => true]);
            break;

        case 'delete_frame':
            $frameId = (int)($_POST['frame_id'] ?? 0);
            if (!$frameId) {
                throw new Exception('Invalid frame ID');
            }
            
            // Get frame info for file deletion
            $stmt = $pdo->prepare("
                SELECT filename, is_copied 
                FROM storyboard_frames 
                WHERE id = ?
            ");
            $stmt->execute([$frameId]);
            $frame = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($frame && $frame['is_copied']) {
                $docRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
                $filePath = $docRoot . $frame['filename'];
                if (file_exists($filePath)) {
                    @unlink($filePath);
                }
            }
            
            // Delete from database
            $stmt = $pdo->prepare("DELETE FROM storyboard_frames WHERE id = ?");
            $stmt->execute([$frameId]);
            
            echo json_encode(['success' => true]);
            break;

        case 'rename_in_order':
            $storyboardId = (int)($_POST['storyboard_id'] ?? 0);
            $orderJson = $_POST['order'] ?? '[]';
            $order = json_decode($orderJson, true);
            
            if (!$storyboardId || !is_array($order)) {
                throw new Exception('Invalid parameters');
            }
            
            // Get storyboard directory
            $stmt = $pdo->prepare("SELECT directory FROM storyboards WHERE id = ?");
            $stmt->execute([$storyboardId]);
            $sb = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$sb) {
                throw new Exception('Storyboard not found');
            }
            
            $docRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
            $storyboardDir = $docRoot . $sb['directory'];
            
            $errors = [];
            $updateStmt = $pdo->prepare("
                UPDATE storyboard_frames 
                SET filename = ?, sort_order = ? 
                WHERE id = ?
            ");
            
            foreach ($order as $idx => $frameId) {
                // Get current frame
                $stmt = $pdo->prepare("SELECT * FROM storyboard_frames WHERE id = ?");
                $stmt->execute([$frameId]);
                $frame = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$frame || !$frame['is_copied']) {
                    continue;
                }
                
                $currentPath = $docRoot . $frame['filename'];
                if (!file_exists($currentPath)) {
                    $errors[] = "File not found: " . $frame['filename'];
                    continue;
                }
                
                // Generate new filename with 3-digit prefix
                $ext = pathinfo($frame['filename'], PATHINFO_EXTENSION);
                $basename = pathinfo($frame['filename'], PATHINFO_FILENAME);
                
                // Remove existing prefix if present
                $cleanBase = preg_replace('/^\d{1,3}_/', '', $basename);
                
                // Create new filename
                $prefix = str_pad($idx + 1, 3, '0', STR_PAD_LEFT);
                $newFilename = $prefix . '_' . $cleanBase . '.' . $ext;
                $newPath = $storyboardDir . '/' . $newFilename;
                $newRelPath = $sb['directory'] . '/' . $newFilename;
                
                // Rename physical file
                if ($currentPath !== $newPath) {
                    if (file_exists($newPath)) {
                        $errors[] = "Destination exists: " . $newFilename;
                        continue;
                    }
                    
                    if (!@rename($currentPath, $newPath)) {
                        $errors[] = "Rename failed: " . $basename;
                        continue;
                    }
                }
                
                // Update database
                $updateStmt->execute([$newRelPath, $idx, $frameId]);
            }
            
            if (count($errors)) {
                echo json_encode(['success' => false, 'message' => implode('; ', $errors)]);
            } else {
                echo json_encode(['success' => true]);
            }
            break;

        case 'export_zip':
            $storyboardId = (int)($_POST['storyboard_id'] ?? 0);
            if (!$storyboardId) {
                throw new Exception('Invalid storyboard ID');
            }
            
            // Get storyboard info
            $stmt = $pdo->prepare("SELECT * FROM storyboards WHERE id = ?");
            $stmt->execute([$storyboardId]);
            $sb = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$sb) {
                throw new Exception('Storyboard not found');
            }
            
            // Get all frames in order
            $stmt = $pdo->prepare("
                SELECT * FROM storyboard_frames 
                WHERE storyboard_id = ? AND is_copied = 1 
                ORDER BY sort_order ASC
            ");
            $stmt->execute([$storyboardId]);
            $frames = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($frames)) {
                throw new Exception('No frames to export');
            }
            
            $docRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
            $zipFilename = 'storyboard_' . preg_replace('/[^a-z0-9_-]/i', '_', $sb['name']) 
                         . '_' . date('Ymd_His') . '.zip';
            $zipPath = sys_get_temp_dir() . '/' . $zipFilename;
            
            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new Exception('Failed to create ZIP file');
            }
            
            // Add frames to ZIP
            foreach ($frames as $frame) {
                $filePath = $docRoot . $frame['filename'];
                if (file_exists($filePath)) {
                    $zip->addFile($filePath, basename($frame['filename']));
                }
            }
            
            // Add metadata JSON
            $metadata = [
                'storyboard' => $sb,
                'frames' => $frames,
                'exported_at' => date('c')
            ];
            $zip->addFromString('metadata.json', json_encode($metadata, JSON_PRETTY_PRINT));
            
            $zip->close();
            
            // Return download URL (actual download handled by separate script)
            echo json_encode([
                'success' => true, 
                'download_url' => '/storyboards_v2_download.php?file=' . urlencode($zipFilename)
            ]);
            break;

        default:
            throw new Exception('Unknown action');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
