<?php
// scrollmagic_images_dir.php
require __DIR__ . '/bootstrap.php'; // must expose $pdo, $framesDirRel etc.

// Use GET parameter if provided, otherwise use the system default frames dir
$framesDir = isset($_GET['dir']) ? $_GET['dir'] : $framesDirRel;

// Security: remove traversal characters
$framesDir = str_replace(['..', '\\'], '', $framesDir);

// Resolve and validate directory path (must be inside public folder)
$requestedPath = realpath(__DIR__ . '/' . $framesDir);
$allowedBasePath = realpath(__DIR__); // public directory

if (!$requestedPath || !is_dir($requestedPath) || strpos($requestedPath, $allowedBasePath) !== 0) {
    header('HTTP/1.1 403 Forbidden');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Invalid or unauthorized directory']);
    exit;
}

// Config
$limitDefault = 100;
$limitMax = 500;
$cacheTtlSeconds = 3600;
$cacheDir = __DIR__ . '/cache';

// Create unique cache filename for the directory
$cacheSafeDir = preg_replace('/[^a-z0-9._-]/i', '_', trim($framesDir, '/'));
$cacheFile = $cacheDir . '/frames_index_dir_' . ($cacheSafeDir === '' ? 'root' : $cacheSafeDir) . '.json';

// Ensure cache dir
if (!is_dir($cacheDir)) { @mkdir($cacheDir, 0755, true); }

// params
$offset = max(0, (int)($_GET['offset'] ?? 0));
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : $limitDefault;
if ($limit > 0) $limit = max(1, min($limit, $limitMax)); else $limit = 0;
$refresh = isset($_GET['refresh']) ? (bool)$_GET['refresh'] : false;

$needReindex = $refresh || !file_exists($cacheFile) || (time() - filemtime($cacheFile) > $cacheTtlSeconds);
$items = [];

if (!$needReindex) {
    $raw = @file_get_contents($cacheFile);
    $json = @json_decode($raw, true);
    if (is_array($json) && isset($json['generated'], $json['items'])) {
        $items = $json['items'];
    } else {
        $needReindex = true;
    }
}

if ($needReindex) {
    $items = [];
    $dir = $requestedPath; // absolute path
    $di = new DirectoryIterator($dir);
    foreach ($di as $fileinfo) {
        if ($fileinfo->isFile()) {
            $fname = $fileinfo->getFilename();
            if (preg_match('/\.(jpe?g|png|webp|gif|svg)$/i', $fname)) {
                $full = $dir . DIRECTORY_SEPARATOR . $fname;
                $w = null; $h = null;
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
    usort($items, function($a,$b){ return strnatcasecmp($a['filename'],$b['filename']); });

    @file_put_contents($cacheFile, json_encode([
        'generated' => time(),
        'base_dir' => $framesDir,
        'items' => $items
    ], JSON_UNESCAPED_UNICODE), LOCK_EX);
}

// total and slice
$total = count($items);
if ($limit === 0) {
    $slice = array_slice($items, $offset);
    $usedLimit = 0;
} else {
    $slice = array_slice($items, $offset, $limit);
    $usedLimit = $limit;
}

// Build public base url path (relative to public/)
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

// Output JSON
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'total' => $total,
    'offset' => $offset,
    'limit' => $usedLimit,
    'count' => count($images),
    'images' => $images
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
