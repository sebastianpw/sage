<?php
// public/scrollmagic_images_map_run.php
require __DIR__ . '/bootstrap.php';

// Helper: JSON error
function sendJsonError($message, $code = 400, $details = '') {
    header('Content-Type: application/json; charset=utf-8', true, $code);
    $response = ['error' => $message];
    if (!empty($details) && (getenv('APP_ENV') !== 'production')) {
        $response['details'] = $details;
    }
    echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    sendJsonError('Server misconfiguration: Database connection missing.', 500);
}

// --- Params ---
$offset = max(0, (int)($_GET['offset'] ?? 0));
$batchSize = max(1, min(500, (int)($_GET['batch_size'] ?? 100)));
$map_run_id = isset($_GET['map_run_id']) ? (int)$_GET['map_run_id'] : 0;

if ($map_run_id <= 0) {
    sendJsonError('Invalid Map Run ID');
}

// --- Build Query ---
// We select basic frame info for the scroll view. 
// Ordering by ID ASC means we see the run from start to finish.
$sqlBase = "FROM `frames` f WHERE f.map_run_id = :mrid";

$countSql = "SELECT COUNT(*) " . $sqlBase;
$dataSql = "SELECT f.id, f.filename, f.name, f.entity_type, f.entity_id 
            " . $sqlBase . " 
            ORDER BY f.id ASC 
            LIMIT :limit OFFSET :offset";

// --- Execute ---
try {
    // Count
    $stmtCount = $pdo->prepare($countSql);
    $stmtCount->execute(['mrid' => $map_run_id]);
    $total = (int)$stmtCount->fetchColumn();

    // Fetch Data
    $stmtData = $pdo->prepare($dataSql);
    $stmtData->bindValue(':mrid', $map_run_id, PDO::PARAM_INT);
    $stmtData->bindValue(':limit', $batchSize, PDO::PARAM_INT);
    $stmtData->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmtData->execute();
    $rows = $stmtData->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    sendJsonError('Database Query Failed', 500, $e->getMessage());
}

// --- Output ---
$images = [];
foreach ($rows as $row) {
    $filename = $row['filename'] ?? '';
    if ($filename === '') continue;

    $parts = array_map('rawurlencode', explode('/', ltrim($filename, '/')));
    $url = '/' . implode('/', $parts);

    $images[] = [
        'id'          => (int)$row['id'],
        'filename'    => $filename,
        'url'         => $url,
        'caption'     => $row['name'] ?? pathinfo($filename, PATHINFO_FILENAME),
        'entity'      => $row['entity_type'],
        'entity_id'   => (int)$row['entity_id'],
    ];
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'total'  => $total,
    'offset' => $offset,
    'limit'  => $batchSize,
    'count'  => count($images),
    'images' => $images
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>