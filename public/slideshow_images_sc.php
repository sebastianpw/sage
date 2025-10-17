<?php
// slideshow_images.php
require __DIR__ . '/bootstrap.php'; // must expose $pdo (PDO connection)
const URL_PREFIX = ''; // = "https://sebastianpw.github.io/sg_showcase_01";

// Config
$limitDefault = 100;
$limitMax = 500;
$cacheTtlSeconds = 3600; // 1 hour caching
$cacheDir = __DIR__ . '/cache';
$cacheFile = $cacheDir . '/frames_index_db_sc.json';

// Ensure cache dir
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0755, true);
}

// Params
$offset = max(0, (int)($_GET['offset'] ?? 0));
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : $limitDefault;
if ($limit > 0) {
    $limit = max(1, min($limit, $limitMax));
} else {
    $limit = 0; // limit=0 means: return all rows
}
$refresh = isset($_GET['refresh']) ? (bool)$_GET['refresh'] : false;
$publicBaseParam = trim((string)($_GET['base'] ?? ''), '/');

// Ensure $pdo
if (!isset($pdo) || !($pdo instanceof PDO)) {
    header('Content-Type: application/json; charset=utf-8', true, 500);
    echo json_encode(['error' => 'No $pdo found. bootstrap.php must provide a PDO connection.']);
    exit;
}

// Caching
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
    try {
        $stmt = $pdo->query("SELECT `filename` FROM `frames`");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $r) {
            $filename = (string)($r['filename'] ?? '');
            if ($filename === '') continue;

            $items[] = [
                'filename' => $filename,
                'w' => 768,
                'h' => 768
            ];
        }

        // Natural sort by filename
        usort($items, function($a, $b){
            return strnatcasecmp($a['filename'], $b['filename']);
        });

        file_put_contents($cacheFile, json_encode([
            'generated' => time(),
            'items' => $items
        ], JSON_UNESCAPED_UNICODE), LOCK_EX);
    } catch (Throwable $e) {
        header('Content-Type: application/json; charset=utf-8', true, 500);
        echo json_encode([
            'error' => 'DB query failed.',
            'details' => $e->getMessage()
        ]);
        exit;
    }
}

// Total
$total = count($items);

// Slice
if ($limit === 0) {
    $slice = array_slice($items, $offset);
    $usedLimit = 0;
} else {
    $slice = array_slice($items, $offset, $limit);
    $usedLimit = $limit;
}

// Public URL base
$publicBase = $publicBaseParam !== '' ? ('/' . $publicBaseParam) : '';

// Build image list
$images = [];
foreach ($slice as $it) {
    $f = $it['filename'];

    $parts = array_map('rawurlencode', explode('/', ltrim($f, '/')));
    $urlPath = implode('/', $parts);
    $url = $publicBase !== '' ? ($publicBase . '/' . $urlPath) : $urlPath;

    $images[] = [
        'filename' => $f,
        'url' => URL_PREFIX . '/' . $it['filename'],
        'caption' => pathinfo($f, PATHINFO_FILENAME),
        'w' => 768,
        'h' => 768
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
