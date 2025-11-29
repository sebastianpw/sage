<?php

/* DEPRECATED - NOT IN USE ANYMORE */

// public/apply_image_edit.php
// Marks the current temporary frame as "applied" and saves it permanently

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

use App\Core\FramesManager;

header('Content-Type: application/json; charset=utf-8');

$raw = file_get_contents('php://input');
if (!$raw) {
    echo json_encode(['success' => false, 'message' => 'No payload']);
    exit;
}

$data = json_decode($raw, true);
if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

$frameId = isset($data['frame_id']) ? intval($data['frame_id']) : null;

if (!$frameId) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameter: frame_id']);
    exit;
}

try {
    $fm = FramesManager::getInstance();
    
    // Apply the version (marks it as applied in the database)
    $result = $fm->applyVersion(null, $frameId);
    
    if (empty($result['success'])) {
        throw new Exception($result['message'] ?? 'Failed to apply version');
    }
    
    // Return success with the frame information
    echo json_encode([
        'success' => true,
        'message' => 'Changes saved successfully',
        'frame_id' => $frameId,
        'derived_frame_id' => $result['derived_frame_id'] ?? null,
        'parent_frame_id' => $result['parent_frame_id'] ?? null
    ]);
    
} catch (Throwable $e) {
    error_log("apply_image_edit.php Error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred: ' . $e->getMessage()
    ]);
}
