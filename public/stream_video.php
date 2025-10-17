<?php 
require_once __DIR__ . '/bootstrap.php'; 
require __DIR__ . '/env_locals.php';

// stream_video.php

// simple sanitization: expect path relative to FRAMES_ROOT
$rel = isset($_GET['file']) ? $_GET['file'] : '';
$rel = str_replace(['..','\\'], '', $rel);
$full = realpath(FRAMES_ROOT . '/' . ltrim($rel, '/'));

if (!$full || strpos($full, realpath(FRAMES_ROOT)) !== 0 || !is_file($full)) {
    http_response_code(404);
    exit('File not found');
}

$size = filesize($full);
$fm = fopen($full, 'rb');
$length = $size;
$start = 0;
$end = $size - 1;

header('Content-Type: video/mp4'); // change if webm
header('Accept-Ranges: bytes');

if (isset($_SERVER['HTTP_RANGE'])) {
    // parse range header
    if (!preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $matches)) {
        header('HTTP/1.1 416 Requested Range Not Satisfiable');
        exit;
    }
    $start = ($matches[1] !== '') ? intval($matches[1]) : $start;
    $end = ($matches[2] !== '') ? intval($matches[2]) : $end;
    if ($start > $end || $start > $size - 1) {
        header('HTTP/1.1 416 Requested Range Not Satisfiable');
        exit;
    }
    $length = $end - $start + 1;
    fseek($fm, $start);
    header('HTTP/1.1 206 Partial Content');
    header("Content-Range: bytes $start-$end/$size");
    header("Content-Length: $length");
} else {
    header("Content-Length: $size");
}

$buffer = 8192;
$sent = 0;
while ($sent < $length && !feof($fm)) {
    $toRead = min($buffer, $length - $sent);
    $data = fread($fm, $toRead);
    echo $data;
    flush();
    $sent += strlen($data);
}

fclose($fm);
exit;
