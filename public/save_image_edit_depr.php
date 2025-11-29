<?php

/* DEPRECATED - NOT IN USE ANYMORE */

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';
// The ImageEditTool.php file must be in the same directory (public/) for this to work.
require_once __DIR__ . '/ImageEditTool.php';

use App\Core\FramesManager;

header('Content-Type: application/json; charset=utf-8');

// Load raw body
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

// Sanitize / read params
$entity = isset($data['entity']) ? preg_replace('/[^a-z0-9_]/i','', $data['entity']) : null;
$frameId = isset($data['frame_id']) ? intval($data['frame_id']) : null;
$entityId = isset($data['entity_id']) ? intval($data['entity_id']) : null;
$coords = $data['coords'] ?? null;
$mode = $data['mode'] ?? 'mask'; // Default to 'mask'
$toolName = $data['tool'] ?? 'jquery-cropper';
$note = $data['note'] ?? null;
$applyImmediately = isset($data['apply_immediately']) && intval($data['apply_immediately']) === 1 ? 1 : 0;
$mapRunId = isset($data['map_run_id']) ? intval($data['map_run_id']) : null;
$userId = $_SESSION['user_id'] ?? null;

// Basic validation
if (!$entity || !$frameId || !$coords || !is_array($coords)) {
    echo json_encode(['success' => false, 'message' => 'Missing required params: entity, frame_id, coords']);
    exit;
}

// Sanitize coords numerically
$coordsSan = [
    'x' => isset($coords['x']) ? intval(round($coords['x'])) : 0,
    'y' => isset($coords['y']) ? intval(round($coords['y'])) : 0,
    'width' => isset($coords['width']) ? intval(round($coords['width'])) : 0,
    'height' => isset($coords['height']) ? intval(round($coords['height'])) : 0,
];

try {
    // Get frames manager instance (DB operations)
    $fm = FramesManager::getInstance();

    // Load original frame row
    $orig = $fm->loadFrameRow($frameId);
    if (!$orig) {
        echo json_encode(['success' => false, 'message' => 'Source frame not found']);
        exit;
    }

    // Instantiate ImageEditTool
    $iet = new ImageEditTool();

    // Reserve unique base name from DB
    $forcedBasename = $fm->getNextFrameBasenameFromDB();

    // Create derived image file using the selected mode
    $derivedRel = null;
    try {
        if ($mode === 'crop') {
            $derivedRel = $iet->createCroppedImage($orig['filename'], $coordsSan, $forcedBasename);
        } else { // Default to 'mask' for safety
            $derivedRel = $iet->createMaskedImage($orig['filename'], $coordsSan, $forcedBasename);
        }
    } catch (Exception $e) {
        $dbg = $iet->getLastError();
        $msg = 'Image creation failed: ' . $e->getMessage();
        if ($dbg && strpos($msg, $dbg) === false) $msg .= ' | detail: ' . $dbg;
        echo json_encode(['success' => false, 'message' => $msg]);
        exit;
    }

    // Register derived frame in DB
    $registerOpts = [
        'coords' => $coordsSan,
        'tool' => $toolName,
        'mode' => $mode, // Save the actual mode used
        'userId' => $userId,
        'note' => $note
    ];
    $result = $fm->registerDerivedFrameFromOriginal($orig, $derivedRel, $mapRunId, $registerOpts);

    if (empty($result['success'])) {
        $dbg = $fm->getLastError();
        $msg = $result['message'] ?? 'Registration failed';
        if ($dbg && strpos($msg, $dbg) === false) $msg .= ' | detail: ' . $dbg;
        echo json_encode(['success' => false, 'message' => $msg]);
        exit;
    }

    // Optionally apply immediately
    if ($applyImmediately) {
        $apply = $fm->applyVersion(intval($result['image_edit_id']));
        if (empty($apply['success'])) {
            $result['apply_success'] = false;
            $result['apply_message'] = $apply['message'] ?? 'apply failed';
        } else {
            $result['apply_success'] = true;
            $result['apply_message'] = $apply['message'] ?? 'Applied';
        }
        echo json_encode($result);
        exit;
    }

    // Normal successful creation (not applied)
    echo json_encode($result);
    exit;

} catch (Throwable $e) {
    // Catch any other unexpected exceptions
    error_log("save_image_edit.php Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    echo json_encode(['success' => false, 'message' => 'An unexpected error occurred: ' . $e->getMessage()]);
    exit;
}

