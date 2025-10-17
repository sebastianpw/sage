<?php
// slideshow_images.php
require __DIR__ . '/bootstrap.php'; // adjust if necessary

// Use GET parameter if provided, otherwise fall back to system default
$framesDir = isset($_GET['dir']) ? $_GET['dir'] : $framesDirRel;

// Security: Validate the directory exists and is within allowed paths
// Prevent directory traversal attacks
$framesDir = str_replace(['..', '\\'], '', $framesDir); // Remove dangerous characters
$fullPath = realpath(__DIR__ . '/' . $framesDir);
$allowedBasePath = realpath(__DIR__);

if (!$fullPath || !is_dir($fullPath) || strpos($fullPath, $allowedBasePath) !== 0) {
    header('HTTP/1.1 403 Forbidden');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Invalid or unauthorized directory']);
    exit;
}

// Config
$limitDefault = 100;
$limitMax = 500;
$cacheTtlSeconds = 3600; // 1 hour caching for image metadata
$cacheDir = __DIR__ . '/cache';

// Create a unique cache file per directory to avoid conflicts
$cacheSafeDir = preg_replace('/[^a-z0-9_-]/i', '_', $framesDir);
$cacheFile = $cacheDir . '/frames_index_' . $cacheSafeDir . '.json';

// Ensure cache dir
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0755, true);
}

// params
$offset = max(0, (int)($_GET['offset'] ?? 0));
$limit = (int)($_GET['limit'] ?? $limitDefault);
$limit = max(1, min($limit, $limitMax));
$refresh = isset($_GET['refresh']) ? (bool)$_GET['refresh'] : false;

// Build / read index & metadata
$needReindex = $refresh || !file_exists($cacheFile) || (time() - filemtime($cacheFile) > $cacheTtlSeconds);

if ($needReindex) {
    $items = [];
    $dir = rtrim($framesDir, '/\\');
    if (is_dir($dir)) {
        $it = new DirectoryIterator($dir);
        foreach ($it as $fileinfo) {
            if ($fileinfo->isFile()) {
                $fname = $fileinfo->getFilename();
                if (preg_match('/\.(jpe?g|png|webp|gif|svg)$/i', $fname)) {
                    $full = $dir . DIRECTORY_SEPARATOR . $fname;
                    $w = null; $h = null;
                    // try getimagesize (may fail for some types, catch warnings)
                    $size = @getimagesize($full);
                    if ($size && isset($size[0], $size[1])) {
                        $w = (int)$size[0];
                        $h = (int)$size[1];
                    }
                    $items[] = [
                        'filename' => $fname,
                        'w' => $w,
                        'h' => $h
                    ];
                }
            }
        }
        // natural sort by filename
        usort($items, function($a, $b){
            return strnatcasecmp($a['filename'], $b['filename']);
        });
    }

    // write cache
    file_put_contents($cacheFile, json_encode([
        'generated' => time(),
        'base_dir' => $framesDir,
        'items' => $items
    ]), LOCK_EX);
}

// load from cache
$raw = @file_get_contents($cacheFile);
$json = @json_decode($raw, true);
$items = $json['items'] ?? [];

// total
$total = count($items);

// slice
$slice = array_slice($items, $offset, $limit);

// Construct public URL base: use the directory we're browsing
$publicBase = '/' . trim($framesDir, '/');

$images = [];
foreach ($slice as $it) {
    $f = $it['filename'];
    $images[] = [
        'filename' => $f,
        'url' => $publicBase . '/' . rawurlencode($f),
        'caption' => pathinfo($f, PATHINFO_FILENAME),
        'w' => isset($it['w']) && $it['w'] ? (int)$it['w'] : null,
        'h' => isset($it['h']) && $it['h'] ? (int)$it['h'] : null
    ];
}

// JSON output
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'total' => $total,
    'offset' => $offset,
    'limit' => $limit,
    'count' => count($images),
    'images' => $images
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
