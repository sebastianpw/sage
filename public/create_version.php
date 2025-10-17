<?php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php'; // ensures SpwBase::getInstance() exists and DB available

use App\Core\FramesManager;

// $frameId and $coords come from the request
$frameId = intval($_POST['frame_id'] ?? 0);
$coords = $_POST['coords'] ?? ['x'=>0,'y'=>0,'width'=>100,'height'=>100];
$tool = $_POST['tool'] ?? 'cropper';
$mode = $_POST['mode'] ?? 'crop';
$userId = intval($_POST['user_id'] ?? 0);
$note = $_POST['note'] ?? null;

$fm = FramesManager::getInstance();
$orig = $fm->loadFrameRow($frameId);
if (!$orig) {
    echo json_encode(['success'=>false,'message'=>'Source frame not found']);
    exit;
}

$spw = \App\Core\SpwBase::getInstance();
$projectRoot = $spw->getProjectPath();

// require ImageEditTool
$imageEditToolPath = $projectRoot . '/src/Tools/ImageEditTool.php';
if (!file_exists($imageEditToolPath)) {
    $imageEditToolPath = __DIR__ . '/ImageEditTool.php';
}
if (!file_exists($imageEditToolPath)) {
    echo json_encode(['success'=>false,'message'=>'ImageEditTool file not found: ' . $imageEditToolPath]);
    exit;
}
require_once $imageEditToolPath;
$iet = new ImageEditTool($projectRoot);

try {
    // Reserve/obtain unique basename from DB (thread-safe)
    try {
        $forcedBasename = $fm->getNextFrameBasenameFromDB(); // e.g. 'frame0000123'
    } catch (Exception $e) {
        echo json_encode(['success'=>false,'message'=>'Failed to reserve frame basename: '.$e->getMessage()]);
        exit;
    }

    // filesystem operation - create derived image using forced basename
    $derivedRel = $iet->createDerivedImage($orig['filename'], $coords, $forcedBasename);
} catch (Exception $e) {
    echo json_encode(['success'=>false,'message'=>'Image creation failed: '.$e->getMessage()]);
    exit;
}

// Now register the derived frame in DB (atomic)
$res = $fm->registerDerivedFrameFromOriginal($orig, $derivedRel, null, [
    'coords'=>$coords, 'tool'=>$tool, 'mode'=>$mode, 'userId'=>$userId, 'note'=>$note
]);

echo json_encode($res);
