<?php
// scrollmagic_images.php
require __DIR__ . '/bootstrap.php'; // must expose $pdo (PDO connection) and $spw (SpwBase instance)
const URL_PREFIX = ''; // optional CDN prefix or empty

// Config
$limitDefault = 100;
$limitMax = 500;
$cacheTtlSeconds = 3600; // 1 hour caching
$cacheDir = __DIR__ . '/cache';

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
    $limit = 0; // limit=0 -> return all
}
$refresh = isset($_GET['refresh']) ? (bool)$_GET['refresh'] : false;
$publicBaseParam = trim((string)($_GET['base'] ?? ''), '/');

// New: db table parameter (dbt). Default to 'frames'
$dbt = isset($_GET['dbt']) ? (string)$_GET['dbt'] : 'frames';
// Strict validation: only letters, numbers and underscore allowed
if (!preg_match('/^[A-Za-z0-9_]+$/', $dbt)) {
    header('Content-Type: application/json; charset=utf-8', true, 400);
    echo json_encode(['error' => 'Invalid dbt parameter. Only alphanumeric and underscore allowed.']);
    exit;
}

// Ensure $pdo and $spw
if (!isset($pdo) || !($pdo instanceof PDO) || !isset($spw)) {
    header('Content-Type: application/json; charset=utf-8', true, 500);
    echo json_encode(['error' => 'Server misconfiguration: bootstrap.php must provide $pdo and $spw.']);
    exit;
}

// Verify table exists in current DB (defensive)
try {
    $dbName = $spw->getDbName();
    $chkStmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = :schema AND table_name = :table LIMIT 1');
    $chkStmt->execute([':schema' => $dbName, ':table' => $dbt]);
    $exists = (int)$chkStmt->fetchColumn() > 0;
    if (!$exists) {
        header('Content-Type: application/json; charset=utf-8', true, 404);
        echo json_encode(['error' => "Table '{$dbt}' not found in database '{$dbName}'."]);
        exit;
    }
} catch (Throwable $e) {
    header('Content-Type: application/json; charset=utf-8', true, 500);
    echo json_encode(['error' => 'Failed to verify table existence.', 'details' => $e->getMessage()]);
    exit;
}

// Build cache filename per table to avoid conflicts
$cacheFile = $cacheDir . '/frames_index_scrollmagic_' . $dbt . '.json';

// Caching / indexing
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
        // Query the validated table name. Safe because $dbt passed validation and existence check.
        $stmt = $pdo->query("SELECT `filename` FROM `" . $dbt . "`");
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

        // natural sort
        usort($items, function($a, $b){
            return strnatcasecmp($a['filename'], $b['filename']);
        });

        file_put_contents($cacheFile, json_encode([
            'generated' => time(),
            'dbt' => $dbt,
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

// total and slicing
$total = count($items);
if ($limit === 0) {
    $slice = array_slice($items, $offset);
    $usedLimit = 0;
} else {
    $slice = array_slice($items, $offset, $limit);
    $usedLimit = $limit;
}

// public base (optional)
$publicBase = $publicBaseParam !== '' ? ('/' . $publicBaseParam) : '';

// build images
$images = [];
foreach ($slice as $it) {
    $f = $it['filename'];

    // encode path parts safely
    $parts = array_map('rawurlencode', explode('/', ltrim($f, '/')));
    $urlPath = implode('/', $parts);
    // if frames are served from public folder, ensure proper relative path
    $url = (URL_PREFIX !== '' ? rtrim(URL_PREFIX, '/') . '/' : '') . $urlPath;

    $images[] = [
        'filename' => $f,
        'url' => $url,
        'caption' => pathinfo($f, PATHINFO_FILENAME),
        'w' => $it['w'] ?? 768,
        'h' => $it['h'] ?? 768
    ];
}

// Output
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'total' => $total,
    'offset' => $offset,
    'limit' => $usedLimit,
    'count' => count($images),
    'images' => $images
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
