<?php
// public/scrollmagic_images_prm.php
require __DIR__ . '/bootstrap.php';

// Helper function for sending JSON error and exiting
function sendJsonError($message, $code = 400, $details = '') {
    header('Content-Type: application/json; charset=utf-8', true, $code);
    $response = ['error' => $message];
    if (!empty($details) && (getenv('APP_ENV') !== 'production')) {
        $response['details'] = $details;
    }
    echo json_encode($response);
    exit;
}

// Ensure $pdo and $spw are available
if (!isset($pdo) || !($pdo instanceof PDO) || !isset($spw)) {
    sendJsonError('Server misconfiguration: bootstrap.php must provide $pdo and $spw.', 500);
}

// --- Whitelists for Security ---
$sortableFrameColumns = ['id', 'name', 'filename', 'created_at'];
$sortableEntityColumns = ['id', 'name', 'order', 'created_at', 'updated_at'];

// --- Get all parameters ---
// Pagination
$offset = max(0, (int)($_GET['offset'] ?? 0));
$batchSize = max(1, min(500, (int)($_GET['batch_size'] ?? 100)));

// Filters
$entityTypesRaw = trim($_GET['entity_type'] ?? '');
$entityIdsRaw = trim($_GET['entity_id'] ?? '');
$fromFrameId = isset($_GET['from_frame_id']) && ctype_digit($_GET['from_frame_id']) ? (int)$_GET['from_frame_id'] : null;
$overallLimit = isset($_GET['limit']) && ctype_digit($_GET['limit']) ? (int)$_GET['limit'] : null;

// Sorting
$sortByInput = trim($_GET['sort_by'] ?? 'frames.id');
$sortOrder = (strtoupper(trim($_GET['sort_order'] ?? '')) === 'DESC') ? 'DESC' : 'ASC';

// Raw query
$rawQuery = trim($_GET['query'] ?? '');

// --- Build SQL Query ---
$params = [];
$baseSql = '';

if (!empty($rawQuery)) {
    // --- Handle Raw Query with Security Checks ---
    if (stripos($rawQuery, 'SELECT') !== 0) sendJsonError('Invalid query: must start with SELECT.');
    if (preg_match('/(INSERT|UPDATE|DELETE|DROP|CREATE|ALTER|TRUNCATE|;|--|\/\*)/i', $rawQuery)) sendJsonError('Invalid query: forbidden keywords or characters detected.');
    if (stripos($rawQuery, 'FROM frames') === false) sendJsonError('Invalid query: must select from the `frames` table.');
    
    $baseSql = preg_replace('/\s+(LIMIT|OFFSET)\s+[0-9,\s]+$/i', '', $rawQuery);
    $countSql = "SELECT COUNT(*) FROM (" . $baseSql . ") AS subquery_raw";
    $dataSql = $baseSql;
    
} else {
    // --- Build Query Dynamically ---
    require __DIR__ . '/entity_icons.php';
    $allowedEntityTypes = array_keys($entityIcons);

    // Validate and parse Sort By
    $sortByParts = explode('.', $sortByInput);
    if (count($sortByParts) !== 2 || !preg_match('/^[a-z0-9_]+$/i', $sortByParts[0]) || !preg_match('/^[a-z0-9_]+$/i', $sortByParts[1])) {
        sendJsonError("Invalid sort_by format. Expected 'table.column'.");
    }
    list($sortTable, $sortColumn) = $sortByParts;
    
    $entityTypes = $entityTypesRaw ? array_map('trim', explode(',', $entityTypesRaw)) : [];
    
    foreach ($entityTypes as $type) {
        if (!in_array($type, $allowedEntityTypes) || !preg_match('/^[a-z0-9_]+$/', $type)) {
            sendJsonError("Invalid or disallowed entity_type provided: " . htmlspecialchars($type));
        }
    }
    
    // --- Define query components ---
    $select = "SELECT DISTINCT f.id, f.filename, f.name, f.entity_type, f.entity_id";
    $from = "FROM `frames` f";
    $joins = [];
    $where = [];
    $orderBySql = '';
    
    $sortJoinNeeded = false;
    if ($sortTable === 'frames') {
        if (!in_array($sortColumn, $sortableFrameColumns)) sendJsonError("Invalid sort column for frames: " . htmlspecialchars($sortColumn));
        $orderBySql = "ORDER BY f.`{$sortColumn}` {$sortOrder}";
    } else {
        if (!in_array($sortColumn, $sortableEntityColumns)) sendJsonError("Invalid sort column for entity: " . htmlspecialchars($sortColumn));
        if (!in_array($sortTable, $allowedEntityTypes)) sendJsonError("Cannot sort by table that is not a valid entity type: " . htmlspecialchars($sortTable));
        if (count($entityTypes) !== 1 || $entityTypes[0] !== $sortTable) sendJsonError("To sort by '{$sortByInput}', you must also filter by a single 'entity_type={$sortTable}'.");
        
        $sortJoinNeeded = true;
        $orderBySql = "ORDER BY e.`{$sortColumn}` {$sortOrder}";
        $select .= ", e.`{$sortColumn}`";
    }

    if (!empty($entityTypes)) {
        if (count($entityTypes) === 1) {
            $entityType = $entityTypes[0];
            $mapTable = 'frames_2_' . $entityType;
            $joins[] = "JOIN `{$mapTable}` map ON f.id = map.from_id";
            
            $entityIds = $entityIdsRaw ? array_map('intval', array_filter(explode(',', $entityIdsRaw), 'is_numeric')) : [];
            
            if ($sortJoinNeeded || !empty($entityIds)) {
                 $joins[] = "JOIN `{$entityType}` e ON map.to_id = e.id";
            }
            if (!empty($entityIds)) {
                $placeholders = implode(',', array_fill(0, count($entityIds), '?'));
                $where[] = "e.id IN ({$placeholders})";
                $params = array_merge($params, $entityIds);
            }
        } else {
            $existsClauses = [];
            foreach ($entityTypes as $type) {
                $mapTable = 'frames_2_' . $type;
                $existsClauses[] = "EXISTS (SELECT 1 FROM `{$mapTable}` WHERE from_id = f.id)";
            }
            $where[] = "(" . implode(' OR ', $existsClauses) . ")";
        }
    }

    if ($fromFrameId !== null) {
        $where[] = "f.id >= ?";
        $params[] = $fromFrameId;
    }

    $joinClause = implode(' ', $joins);
    $whereClause = !empty($where) ? ' WHERE ' . implode(' AND ', $where) : '';
    
    $countSql = "SELECT COUNT(DISTINCT f.id) " . $from . " " . $joinClause . " " . $whereClause;
    $dataSql = $select . " " . $from . " " . $joinClause . " " . $whereClause . " " . $orderBySql;
}

// --- Execute Queries ---
try {
    $stmtCount = $pdo->prepare($countSql);
    $stmtCount->execute($params);
    $total = (int)$stmtCount->fetchColumn();

    $finalSql = $dataSql;
    if ($overallLimit !== null) {
        $finalSql .= " LIMIT " . $overallLimit;
        $total = min($total, $overallLimit); 
    }
    
    $finalSql .= " LIMIT :limit OFFSET :offset";
    
    $stmtData = $pdo->prepare($finalSql);
    foreach ($params as $key => $value) {
        $stmtData->bindValue($key + 1, $value);
    }
    $stmtData->bindValue(':limit', $batchSize, PDO::PARAM_INT);
    $stmtData->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmtData->execute();
    $rows = $stmtData->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    sendJsonError('DB query failed.', 500, $e->getMessage());
}

// --- Format Response ---
$images = [];
foreach ($rows as $row) {
    $filename = $row['filename'] ?? '';
    if ($filename === '') continue;

    $parts = array_map('rawurlencode', explode('/', ltrim($filename, '/')));
    $urlPath = implode('/', $parts);
    $url = '/' . $urlPath;

    $images[] = [
        'id'        => (int)$row['id'],
        'filename'  => $filename,
        'url'       => $url,
        'caption'   => $row['name'] ?? pathinfo($filename, PATHINFO_FILENAME),
        'w'         => 768,
        'h'         => 768,
        'entity'    => $row['entity_type'] ?? $entityTypesRaw,
        'entity_id' => (int)($row['entity_id'] ?? 0),
    ];
}

// Output final JSON
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'total' => $total,
    'offset' => $offset,
    'limit' => $batchSize,
    'count' => count($images),
    'images' => $images
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);