<?php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

use App\Tools\ImageEditor;
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

$action = $data['action'] ?? null;
$entity = $data['entity'] ?? null;
$entityId = isset($data['entity_id']) ? intval($data['entity_id']) : null;
$frameId = isset($data['frame_id']) ? intval($data['frame_id']) : null;

if (!$action || !$entity || (!$frameId && !$entityId)) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

try {
    $fm = FramesManager::getInstance();
    $imageEditor = new ImageEditor();
    
    // Load frame
    if (!$frameId && $entityId) {
        $frame = $fm->loadFrameRow(null, $entity, $entityId);
        if (!$frame) {
            echo json_encode(['success' => false, 'message' => 'Frame not found']);
            exit;
        }
        $frameId = intval($frame['id']);
    } else {
        $frame = $fm->loadFrameRow($frameId, null, null);
        if (!$frame) {
            echo json_encode(['success' => false, 'message' => 'Frame not found']);
            exit;
        }
    }
    
    // Get next frame basename
    $forcedBasename = $fm->getNextFrameBasenameFromDB();
    $derivedRel = null;
    
    switch ($action) {
        case 'rotate':
            $angle = floatval($data['angle'] ?? 0);
            $derivedRel = $imageEditor->rotateImage($frame['filename'], $angle, $forcedBasename);
            break;
            
        case 'resize':
            $width = intval($data['width'] ?? 0);
            $height = intval($data['height'] ?? 0);
            $maintain = boolval($data['maintain_aspect'] ?? true);
            
            if ($width < 1 || $height < 1) {
                echo json_encode(['success' => false, 'message' => 'Invalid dimensions']);
                exit;
            }
            
            $derivedRel = $imageEditor->resizeImage($frame['filename'], $width, $height, $forcedBasename, $maintain);
            break;
            
        case 'filter':
            $filterType = $data['filter_type'] ?? 'grayscale';
            $params = $data['params'] ?? [];
            
            $derivedRel = $imageEditor->applyFilter($frame['filename'], $filterType, $params, $forcedBasename);
            break;
            
        case 'layer':
            $layers = $data['layers'] ?? [];
            $canvasWidth = intval($data['canvas_width'] ?? 1024);
            $canvasHeight = intval($data['canvas_height'] ?? 1024);
            
            $derivedRel = $imageEditor->layerImages($layers, $forcedBasename, $canvasWidth, $canvasHeight);
            break;
            
        case 'info':
            $info = $imageEditor->getImageInfo($frame['filename']);
            if ($info) {
                echo json_encode(['success' => true, 'info' => $info]);
            } else {
                echo json_encode(['success' => false, 'message' => $imageEditor->getLastError()]);
            }
            exit;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action']);
            exit;
    }
    
    if (!$derivedRel) {
        $error = $imageEditor->getLastError() ?? 'Operation failed';
        echo json_encode(['success' => false, 'message' => $error]);
        exit;
    }
    
    // Register the derived frame
    $registerOpts = [
        'coords' => $data['coords'] ?? [],
        'tool' => 'image-editor',
        'mode' => $action,
        'userId' => $_SESSION['user_id'] ?? null,
        'note' => $data['note'] ?? "Image editor: {$action}"
    ];
    
    $mapRunId = isset($data['map_run_id']) ? intval($data['map_run_id']) : null;
    $result = $fm->registerDerivedFrameFromOriginal($frame, $derivedRel, $mapRunId, $registerOpts);
    
    if (empty($result['success'])) {
        echo json_encode([
            'success' => false,
            'message' => $result['message'] ?? 'Registration failed',
            'detail' => $fm->getLastError()
        ]);
        exit;
    }
    
    // Optionally apply immediately
    if (isset($data['apply_immediately']) && $data['apply_immediately']) {
        $apply = $fm->applyVersion(intval($result['image_edit_id']), null);
        if (empty($apply['success'])) {
            echo json_encode([
                'success' => true,
                'filename' => $derivedRel,
                'new_frame_id' => $result['new_frame_id'],
                'image_edit_id' => $result['image_edit_id'],
                'apply_success' => false,
                'apply_message' => $apply['message'] ?? 'Apply failed'
            ]);
            exit;
        }
    }
    
    echo json_encode([
        'success' => true,
        'filename' => $derivedRel,
        'new_frame_id' => $result['new_frame_id'],
        'chain_id' => $result['chain_id'],
        'image_edit_id' => $result['image_edit_id'],
        'map_run_id' => $result['map_run_id']
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Exception: ' . $e->getMessage()
    ]);
}
