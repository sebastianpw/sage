<?php
// save_video.php
// Saves the recorded video blob to disk

// 1. Check for upload errors
if ($_FILES['video']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(500);
    echo "Upload failed with error code: " . $_FILES['video']['error'];
    echo "\nHint: Check 'upload_max_filesize' and 'post_max_size' in php.ini";
    exit;
}

// 2. Generate Filename (YYYY-MM-DD_HH-MM-SS.mp4)
$timestamp = date('Y-m-d_H-i-s');
// We trust the extension sent by the browser, or default to mp4
$extension = 'mp4'; 
if (isset($_POST['format']) && strpos($_POST['format'], 'webm') !== false) {
    $extension = 'webm';
}

$filename = "recording_" . $timestamp . "." . $extension;
$destination = __DIR__ . DIRECTORY_SEPARATOR . $filename;

// 3. Move file
if (move_uploaded_file($_FILES['video']['tmp_name'], $destination)) {
    echo "Saved: " . $filename;
} else {
    http_response_code(500);
    echo "Failed to write file to disk. Check permissions.";
}
?>