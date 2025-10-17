<?php
// ajax_codeboard.php - Enhanced with export/delete support
require_once __DIR__ . '/error_reporting.php';
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getSysPDO();
$aiProvider = new \App\Core\AIProvider($spw->getFileLogger());
$rateLimiter = new \App\Core\ModelRateLimiter();
$ci = new \App\Core\CodeIntelligence($spw, $aiProvider, $rateLimiter);

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? 'get_file';
$fileId = isset($_GET['file_id']) ? (int)$_GET['file_id'] : 0;

try {
    switch ($action) {
        case 'get_file':
            if ($fileId <= 0) {
                throw new Exception('file_id required');
            }

            $stmt = $pdo->prepare('SELECT * FROM code_files WHERE id = ?');
            $stmt->execute([$fileId]);
            $file = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$file) {
                throw new Exception('file not found');
            }

            $stmt = $pdo->prepare('SELECT * FROM code_classes WHERE file_id = ? ORDER BY id');
            $stmt->execute([$fileId]);
            $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Parse JSON fields
            foreach ($classes as &$class) {
                $class['interfaces'] = json_decode($class['interfaces'] ?? '[]', true);
                $class['methods'] = json_decode($class['methods'] ?? '[]', true);
            }

            $stmt = $pdo->prepare('SELECT chunk_index, tokens_estimate, provider, created_at FROM code_analysis_log WHERE file_id = ? ORDER BY chunk_index');
            $stmt->execute([$fileId]);
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'file' => $file,
                'classes' => $classes,
                'logs' => $logs
            ]);
            break;

        case 'delete_file':
            if ($fileId <= 0) {
                throw new Exception('file_id required');
            }

            $ci->deleteFile($fileId);
            echo json_encode(['success' => true, 'message' => 'File deleted successfully']);
            break;

        case 'export_file':
            if ($fileId <= 0) {
                throw new Exception('file_id required');
            }

            $data = $ci->exportFileAsJson($fileId);
            if (!$data) {
                throw new Exception('Export failed');
            }

            echo json_encode(['success' => true, 'data' => $data]);
            break;

        case 'bulk_export':
            $fileIds = $_POST['file_ids'] ?? [];
            if (empty($fileIds)) {
                throw new Exception('No files selected');
            }

            $fileIds = array_map('intval', $fileIds);
            $data = $ci->exportFilesAsJson($fileIds);

            echo json_encode(['success' => true, 'data' => $data]);
            break;

        case 'bulk_delete':
            $fileIds = $_POST['file_ids'] ?? [];
            if (empty($fileIds)) {
                throw new Exception('No files selected');
            }

            $fileIds = array_map('intval', $fileIds);
            $deleted = $ci->deleteFiles($fileIds);

            echo json_encode(['success' => true, 'deleted' => $deleted]);
            break;

        default:
            throw new Exception('Invalid action');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
