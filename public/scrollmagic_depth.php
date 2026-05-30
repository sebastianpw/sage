<?php
// public/scrollmagic_depth.php
require __DIR__ . '/bootstrap.php';

function sendJsonError($message, $code = 400) {
    header('Content-Type: application/json; charset=utf-8', true, $code);
    echo json_encode(['error' => $message]);
    exit;
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    sendJsonError('Server misconfiguration: Database connection missing.', 500);
}

// --- Params ---
$offset = max(0, (int)($_GET['offset'] ?? 0));
$batchSize = max(1, min(500, (int)($_GET['batch_size'] ?? 100)));

// --- Queries ---
$whereClause = "WHERE `depth_map_filename` IS NOT NULL AND `depth_map_filename` != ''";

$countSql = "SELECT COUNT(id) FROM `frames` $whereClause";
$dataSql = "SELECT id, depth_map_filename AS filename, name, created_at 
            FROM `frames` 
            $whereClause 
            ORDER BY id DESC 
            LIMIT ? OFFSET ?";

// --- Execute ---
try {
    $stmtCount = $pdo->query($countSql);
    $total = (int)$stmtCount->fetchColumn();

    $stmtData = $pdo->prepare($dataSql);
    $stmtData->bindValue(1, $batchSize, PDO::PARAM_INT);
    $stmtData->bindValue(2, $offset, PDO::PARAM_INT);
    $stmtData->execute();
    
    $rows = $stmtData->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    sendJsonError('Database Query Failed', 500);
}

// --- Output ---
$images =[];
foreach ($rows as $row) {
    $filename = $row['filename'] ?? '';
    if ($filename === '') continue;

    $parts = array_map('rawurlencode', explode('/', ltrim($filename, '/')));
    $url = '/' . implode('/', $parts);

    $images[] =[
        'id'          => (int)$row['id'],
        'filename'    => $filename,
        'url'         => $url,
        'caption'     => $row['name'] ? $row['name'] . ' (Depth)' : pathinfo($filename, PATHINFO_FILENAME),
        'entity'      => 'frames',
        'entity_id'   => (int)$row['id']
    ];
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'total'  => $total,
    'offset' => $offset,
    'limit'  => $batchSize,
    'count'  => count($images),
    'images' => $images
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

