<?php
// public/babylon_model.php
require __DIR__ . '/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

$id = $_GET['id'] ?? null;
if (!$id) {
    http_response_code(400);
    echo json_encode(['error'=>'missing id']);
    exit;
}

$fname = rawurldecode($id);
if (preg_match('/^[a-zA-Z0-9_\-\.]+$/', $fname) !== 1) {
    http_response_code(400);
    echo json_encode(['error'=>'invalid id']);
    exit;
}

$projectRoot = PROJECT_ROOT ?? dirname(__DIR__, 1);

// FILESYSTEM folder where models live (on disk)
$modelsDirFs = rtrim($projectRoot, '/') . '/public/models'; // keep this pointing to the real FS folder

// PUBLIC URL prefix that browsers should use (since 'public' is docroot)
$modelsUrlPrefix = '/models';

$path = $modelsDirFs . '/' . $fname;

if (!file_exists($path)) {
    http_response_code(404);
    echo json_encode(['error'=>'not found']);
    exit;
}

echo json_encode([
    'id' => rawurlencode($fname),
    'name' => $fname,
    'url' => $modelsUrlPrefix . '/' . $fname,
    'size' => filesize($path),
    'mtime' => filemtime($path),
], JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
