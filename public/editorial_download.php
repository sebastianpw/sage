<?php
// public/editorial_download.php
$filename = $_GET['file'] ?? '';

if (!preg_match('/^scene_[a-zA-Z0-9_\-]+\.zip$/', $filename)) {
    http_response_code(400);
    die('Invalid filename');
}

$filepath = sys_get_temp_dir() . '/' . $filename;

if (!file_exists($filepath)) {
    http_response_code(404);
    die('File not found or expired');
}

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: no-cache, must-revalidate');

readfile($filepath);
@unlink($filepath); // Delete after download
exit;