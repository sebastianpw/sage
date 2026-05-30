<?php
// api_map_runs.php - Backend API for map runs
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
        
        case 'export_zip':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Method Not Allowed');
            }

            $mapRunId = (int)($_POST['map_run_id'] ?? 0);
            if (!$mapRunId) throw new Exception('Invalid Map Run ID');

            // 1. Verify Map Run exists
            $stmt = $pdo->prepare("SELECT id FROM map_runs WHERE id = ?");
            $stmt->execute([$mapRunId]);
            if (!$stmt->fetch()) throw new Exception('Map run not found');

            // 2. Fetch Frames
            // We order by ID ASC to maintain the sequence of the run
            $stmt = $pdo->prepare("SELECT filename FROM frames WHERE map_run_id = ? ORDER BY id ASC");
            $stmt->execute([$mapRunId]);
            $frames = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!$frames) throw new Exception('No frames found for this map run');

            // 3. Create ZIP
            $zipName = 'map_run_' . $mapRunId . '_' . date('Ymd_His') . '.zip';
            $zipPath = sys_get_temp_dir() . '/' . $zipName;

            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new Exception('Could not create ZIP file on server');
            }

            $docRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
            $filesAdded = 0;

            foreach ($frames as $f) {
                // Ensure filename has leading slash for concatenation with docRoot
                $relPath = '/' . ltrim($f['filename'], '/');
                $fullPath = $docRoot . $relPath;

                if (file_exists($fullPath)) {
                    // Use basename to store flat in zip
                    $localName = basename($fullPath);
                    $zip->addFile($fullPath, $localName);
                    $filesAdded++;
                }
            }

            $zip->close();

            if ($filesAdded === 0) {
                throw new Exception('No physical image files found for this map run');
            }

            // 4. Return Download URL
            // We point back to this API with the download_zip action
            $downloadUrl = 'api_map_runs.php?action=download_zip&file=' . urlencode($zipName);

            echo json_encode([
                'success' => true, 
                'download_url' => $downloadUrl,
                'count' => $filesAdded
            ]);
            break;

        case 'download_zip':
            // Verify method and parameters
            if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
                http_response_code(405);
                exit('Method Not Allowed');
            }

            $file = $_GET['file'] ?? '';

            // Strict filename validation to prevent directory traversal or unauthorized access
            // Format must match: map_run_{id}_{timestamp}.zip
            if (!preg_match('/^map_run_\d+_\d{8}_\d{6}\.zip$/', $file)) {
                http_response_code(400);
                exit('Invalid filename');
            }

            $filepath = sys_get_temp_dir() . '/' . $file;

            if (!file_exists($filepath)) {
                http_response_code(404);
                exit('File not found or expired');
            }

            // Clear output buffer to ensure binary safety
            if (ob_get_level()) ob_end_clean();

            // Send Download Headers
            header('Content-Description: File Transfer');
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $file . '"');
            header('Content-Transfer-Encoding: binary');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($filepath));

            // Stream file and delete
            readfile($filepath);
            @unlink($filepath);
            exit;

        default:
            throw new Exception('Unknown action');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
