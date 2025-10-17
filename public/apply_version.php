<?php 
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';
// apply_version.php
require_once __DIR__ . '/ImageEditTool.php';

$spw = \App\Core\SpwBase::getInstance();
$mysqli = $spw->getMysqli();
$tool = new ImageEditTool($mysqli, PROJECT_ROOT);

header('Content-Type: application/json; charset=utf-8');

$imageEditId = isset($_REQUEST['image_edit_id']) ? intval($_REQUEST['image_edit_id']) : null;
$derivedFrameId = isset($_REQUEST['derived_frame_id']) ? intval($_REQUEST['derived_frame_id']) : null;

if (!$imageEditId && !$derivedFrameId) {
    echo json_encode(['success'=>false,'message'=>'Provide image_edit_id or derived_frame_id']);
    exit;
}

$res = $tool->applyVersion($imageEditId, $derivedFrameId);
echo json_encode($res);
exit;
