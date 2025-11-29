<?php
// public/save_final_image_edit.php
// Takes a temporary image file and saves it to the database as a new frame
// This is called when the user clicks the "Save" button

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

use App\Core\FramesManager;
use App\Core\SpwBase;

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

// Required params
$entity = isset($data['entity']) ? preg_replace('/[^a-z0-9_]/i', '', $data['entity']) : null;
$originalFrameId = isset($data['original_frame_id']) ? intval($data['original_frame_id']) : null;
$tempFilename = $data['temp_filename'] ?? null;
$editOperations = $data['operations'] ?? []; // Array of operations performed
$userId = $_SESSION['user_id'] ?? null;

if (!$entity || !$originalFrameId || !$tempFilename) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

try {
    $fm = FramesManager::getInstance();
    $spw = SpwBase::getInstance();
    $projectRoot = $spw->getProjectPath();
    
    // Verify temp file exists
    $tempFullPath = $projectRoot . '/public/' . ltrim($tempFilename, '/');
    if (!file_exists($tempFullPath)) {
        throw new Exception("Temporary file not found: {$tempFilename}");
    }
    
    // Load original frame
    $orig = $fm->loadFrameRow($originalFrameId);
    if (!$orig) {
        throw new Exception("Original frame not found: {$originalFrameId}");
    }
    
    // Get a proper frame basename from DB counter
    $finalBasename = $fm->getNextFrameBasenameFromDB();
    
    // Determine final filename with proper extension
    $pi = pathinfo($tempFilename);
    $extension = $pi['extension'] ?? 'png';
    $dirnameRel = ($pi['dirname'] && $pi['dirname'] !== '.') ? $pi['dirname'] : '';
    $finalRel = ($dirnameRel ? (rtrim($dirnameRel, '/') . '/') : '') . $finalBasename . '.' . $extension;
    $finalFullPath = $projectRoot . '/public/' . ltrim($finalRel, '/');
    
    // Copy temp file to final location
    if (!copy($tempFullPath, $finalFullPath)) {
        throw new Exception("Failed to copy temp file to final location");
    }
    
    // Build note describing the operations
    $operationsText = !empty($editOperations) 
        ? implode(' → ', $editOperations) 
        : 'Multi-step edit';
    $note = "Saved edit: {$operationsText}";
    
    // Register in database
    $registerOpts = [
        'tool' => 'image-editor-module',
        'mode' => 'multi-step',
        'userId' => $userId,
        'note' => $note,
        'coords' => ['operations' => $editOperations] // Store operation history
    ];
    
    $result = $fm->registerDerivedFrameFromOriginal($orig, $finalRel, null, $registerOpts);
    
    if (empty($result['success'])) {
        throw new Exception($result['message'] ?? 'Frame registration failed');
    }
    
    // Mark as applied immediately
    $applyResult = $fm->applyVersion($result['image_edit_id']);
    if (empty($applyResult['success'])) {
        // Not critical, frame is still saved
        error_log("Warning: Could not mark frame as applied: " . ($applyResult['message'] ?? 'unknown'));
    }
    
    // Clean up temp file
    @unlink($tempFullPath);
    
    // Success response
    echo json_encode([
        'success' => true,
        'message' => 'Image saved successfully',
        'new_frame_id' => $result['new_frame_id'],
        'map_run_id' => $result['map_run_id'],
        'chain_id' => $result['chain_id'],
        'filename' => $finalRel,
        'operations' => $editOperations
    ]);
    
} catch (Throwable $e) {
    error_log("save_final_image_edit.php Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Save failed: ' . $e->getMessage()
    ]);
}
