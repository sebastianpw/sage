<?php 
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';
// save_image_edit.php (updated: ImageEditTool -> FramesManager orchestration)
//
// - Reserves DB-backed basename via FramesManager::getNextFrameBasenameFromDB()
// - Creates derived image using ImageEditTool (filesystem only) with forced basename.
// - Registers derived frame / chain / image_edit in DB via FramesManager (atomic).
// - Optionally applies the version immediately via FramesManager::applyVersion().

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

// sanitize / read params
$entity = isset($data['entity']) ? preg_replace('/[^a-z0-9_]/i','', $data['entity']) : null;
$frameId = isset($data['frame_id']) ? intval($data['frame_id']) : null;
$entityId = isset($data['entity_id']) ? intval($data['entity_id']) : null;
$coords = $data['coords'] ?? null;
$mode = $data['mode'] ?? 'crop';
$toolName = $data['tool'] ?? 'cropper';
$note = $data['note'] ?? null;
$applyImmediately = isset($data['apply_immediately']) && intval($data['apply_immediately']) === 1 ? 1 : 0;
$mapRunId = isset($data['map_run_id']) ? intval($data['map_run_id']) : null;
$userId = $_SESSION['user_id'] ?? null;

// basic validation
if (!$entity || (!$frameId && !$entityId) || !$coords || !is_array($coords)) {
    echo json_encode(['success' => false, 'message' => 'Missing required params: entity + (frame_id or entity_id) + coords (object)']);
    exit;
}

// sanitize coords numerically
$coordsSan = [
    'x' => isset($coords['x']) ? intval(round($coords['x'])) : 0,
    'y' => isset($coords['y']) ? intval(round($coords['y'])) : 0,
    'width' => isset($coords['width']) ? intval(round($coords['width'])) : 0,
    'height' => isset($coords['height']) ? intval(round($coords['height'])) : 0,
];

try {
    // get frames manager instance (DB operations)
    $fm = FramesManager::getInstance();

    // if only entity_id given, load latest frame for that entity
    if (!$frameId && $entityId) {
        $row = $fm->loadFrameRow(null, $entity, $entityId);
        if (!$row) {
            echo json_encode(['success' => false, 'message' => 'No frame found for given entity_id']);
            exit;
        }
        $frameId = intval($row['id']);
    }

    // load original frame row
    $orig = $fm->loadFrameRow($frameId, null, null);
    if (!$orig) {
        echo json_encode(['success' => false, 'message' => 'Source frame not found']);
        exit;
    }

    // instantiate ImageEditTool (filesystem-only)
    $imageEditToolPath = PROJECT_ROOT . '/src/Tools/ImageEditTool.php';
    if (!file_exists($imageEditToolPath)) {
        // fallback to legacy path (in case you kept ImageEditTool next to endpoints)
        $imageEditToolPath = __DIR__ . '/ImageEditTool.php';
    }
    if (!file_exists($imageEditToolPath)) {
        echo json_encode(['success' => false, 'message' => 'ImageEditTool file not found: ' . $imageEditToolPath]);
        exit;
    }
    require_once $imageEditToolPath;
    $iet = new ImageEditTool(PROJECT_ROOT);

    // Reserve unique base name from DB (thread-safe)
    try {
        $forcedBasename = $fm->getNextFrameBasenameFromDB(); // e.g. 'frame0000123'
    } catch (Exception $e) {
        $dbg = $fm->getLastError();
        $msg = 'Failed to reserve frame basename: ' . $e->getMessage();
        if ($dbg && strpos($msg, $dbg) === false) $msg .= ' | detail: ' . $dbg;
        echo json_encode(['success' => false, 'message' => $msg]);
        exit;
    }

    // create derived image file (filesystem operation) using forced basename
    try {
        $derivedRel = $iet->createDerivedImage($orig['filename'], $coordsSan, $forcedBasename);
    } catch (Exception $e) {
        // include last error if available
        $dbg = $iet->getLastError();
        $msg = 'Image creation failed: ' . $e->getMessage();
        if ($dbg && strpos($msg, $dbg) === false) $msg .= ' | detail: ' . $dbg;
        echo json_encode(['success' => false, 'message' => $msg]);
        exit;
    }

    // register derived frame in DB (atomic, DB-only)
    $registerOpts = [
        'coords' => $coordsSan,
        'tool' => $toolName,
        'mode' => $mode,
        'userId' => $userId,
        'note' => $note
    ];
    $result = $fm->registerDerivedFrameFromOriginal($orig, $derivedRel, $mapRunId, $registerOpts);

    if (empty($result['success'])) {
        // include FramesManager lastError for debugging
        $dbg = $fm->getLastError();
        $msg = $result['message'] ?? 'Registration failed';
        if ($dbg && strpos($msg, $dbg) === false) $msg .= ' | detail: ' . $dbg;
        echo json_encode(['success' => false, 'message' => $msg]);
        exit;
    }

    // optionally apply immediately
    if ($applyImmediately) {
        $apply = $fm->applyVersion(intval($result['image_edit_id']), null);
        if (empty($apply['success'])) {
            // return creation success but include apply failure details
            echo json_encode([
                'success' => true,
                'map_run_id' => $result['map_run_id'],
                'new_frame_id' => $result['new_frame_id'],
                'chain_id' => $result['chain_id'],
                'image_edit_id' => $result['image_edit_id'],
                'derived_filename' => $result['derived_filename'],
                'apply_success' => false,
                'apply_message' => $apply['message'] ?? 'apply failed'
            ]);
            exit;
        }
        // applied successfully
        echo json_encode([
            'success' => true,
            'map_run_id' => $result['map_run_id'],
            'new_frame_id' => $result['new_frame_id'],
            'chain_id' => $result['chain_id'],
            'image_edit_id' => $result['image_edit_id'],
            'derived_filename' => $result['derived_filename'],
            'apply_success' => true,
            'apply_message' => $apply['message'] ?? 'Applied'
        ]);
        exit;
    }

    // normal successful creation (not applied)
    echo json_encode([
        'success' => true,
        'map_run_id' => $result['map_run_id'],
        'new_frame_id' => $result['new_frame_id'],
        'chain_id' => $result['chain_id'],
        'image_edit_id' => $result['image_edit_id'],
        'derived_filename' => $result['derived_filename']
    ]);
    exit;

} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Exception: ' . $e->getMessage()]);
    exit;
}
