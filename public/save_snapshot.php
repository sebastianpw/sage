<?php
// save_snapshot.php
require __DIR__ . '/bootstrap.php'; // adjust if your bootstrap location differs
header('Content-Type: application/json; charset=utf-8');

// Accept only POST JSON
$raw = file_get_contents('php://input');
if (!$raw) {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>'empty request']);
    exit;
}

$data = json_decode($raw, true);
if (!is_array($data) || empty($data['image'])) {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>'invalid payload']);
    exit;
}

$imageData = $data['image']; // expected like "data:image/jpeg;base64,/9j/..."
$filenameHint = basename($data['filename'] ?? '');

if (!preg_match('#^data:image/(png|jpeg|jpg);base64,#i', $imageData, $m)) {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>'unsupported image format']);
    exit;
}

$ext = strtolower($m[1]) === 'png' ? 'png' : 'jpg';
$base64 = preg_replace('#^data:image/[^;]+;base64,#i', '', $imageData);
$decoded = base64_decode($base64);

if ($decoded === false) {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>'base64 decode failed']);
    exit;
}

// Compute paths
$projectRoot = PROJECT_ROOT ?? dirname(__DIR__, 2); // fallback
$snapshotsFs = rtrim($projectRoot, '/') . '/public/import/frames_2_spawns';
$snapshotsUrlPrefix = '/import/frames_2_spawns';

// ensure folder exists
if (!is_dir($snapshotsFs)) {
    mkdir($snapshotsFs, 0755, true);
}

// sanitize filename hint
if ($filenameHint) {
    $filenameHint = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $filenameHint);
    $filenameHint = substr($filenameHint, 0, 40);
}

// unique filename
$fname = ($filenameHint ? $filenameHint . '-' : '') . date('Ymd-His-') . bin2hex(random_bytes(6)) . '.' . $ext;
$path = $snapshotsFs . '/' . $fname;

if (file_put_contents($path, $decoded) === false) {
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'failed to write file']);
    exit;
}

// Optionally set permissions
@chmod($path, 0644);

$url = $snapshotsUrlPrefix . '/' . $fname;
echo json_encode(['success'=>true, 'url'=>$url, 'filename'=>$fname]);
exit;
