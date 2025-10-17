<?php
// storyboards_v2_download.php - Handle ZIP file downloads
require "error_reporting.php";

$filename = $_GET['file'] ?? '';

// Sanitize filename
if (!preg_match('/^storyboard_[a-zA-Z0-9_\-]+\.zip$/', $filename)) {
    http_response_code(400);
    die('Invalid filename');
}

$filepath = sys_get_temp_dir() . '/' . $filename;

if (!file_exists($filepath)) {
    http_response_code(404);
    die('File not found or expired');
}

// Set headers for download
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: public');

// Stream file and delete after
readfile($filepath);
@unlink($filepath);
exit;
