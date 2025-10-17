<?php 
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

// list_versions.php (updated to use FramesManager)
//
// Returns JSON: { success: true, versions: [...] }

use App\Core\FramesManager;

header('Content-Type: application/json; charset=utf-8');

$parentFrameId = isset($_GET['parent_frame_id']) ? intval($_GET['parent_frame_id']) : 0;
if (!$parentFrameId) {
    echo json_encode(['success' => false, 'message' => 'Missing parent_frame_id']);
    exit;
}

try {
    $fm = FramesManager::getInstance();
    $rows = $fm->listVersions($parentFrameId);
    echo json_encode(['success' => true, 'versions' => $rows]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Exception: ' . $e->getMessage()]);
}
exit;
