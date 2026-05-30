<?php
// public/scrollmagic_images_multi_prm.php
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

// --- Whitelists ---
$sortableFrameColumns = ['id', 'name', 'filename', 'created_at'];
$sortableEntityColumns = ['id', 'name', 'order', 'created_at', 'updated_at'];
$allowedEntityTypes = ['frames', 'storyboards', 'sketches', 'generatives']; 

// --- Standard Params ---
$offset = max(0, (int)($_GET['offset'] ?? 0));
$batchSize = max(1, min(500, (int)($_GET['batch_size'] ?? 100)));

$entityTypesRaw = trim($_GET['entity_type'] ?? '');
$entityIdsRaw = trim($_GET['entity_id'] ?? '');
$fromFrameId = isset($_GET['from_frame_id']) && ctype_digit($_GET['from_frame_id']) ? (int)$_GET['from_frame_id'] : null;
$overallLimit = isset($_GET['limit']) && ctype_digit($_GET['limit']) ? (int)$_GET['limit'] : null;
$rawQuery = trim($_GET['query'] ?? '');

$sortByInput = trim($_GET['sort_by'] ?? 'frames.id');
$sortOrder = (strtoupper(trim($_GET['sort_order'] ?? '')) === 'ASC') ? 'ASC' : 'DESC'; 

// --- Multi/Storyboard Params ---
$storyboardIdsRaw = trim($_GET['storyboard_ids'] ?? '');
$assignmentLogic = trim($_GET['assignment_logic'] ?? ''); 
$orderBy = trim($_GET['order_by'] ?? ''); // 'usage'

// Parse storyboard IDs
$storyboardIds = [];
if ($storyboardIdsRaw !== '') {
    $parts = explode(',', $storyboardIdsRaw);
    foreach ($parts as $p) {
        $val = trim($p);
        if (ctype_digit($val)) {
            $storyboardIds[] = (int)$val;
        }
    }
}

// --- Build Query ---
$params = [];

// 1. RAW QUERY MODE 
if (!empty($rawQuery)) {
    if (stripos($rawQuery, 'SELECT') !== 0) sendJsonError('Invalid query: must start with SELECT.');
    if (preg_match('/(INSERT|UPDATE|DELETE|DROP|CREATE|ALTER|TRUNCATE|;|--|\/\*)/i', $rawQuery)) sendJsonError('Invalid query: forbidden keywords.');
    if (stripos($rawQuery, 'FROM frames') === false) sendJsonError('Invalid query: must select from the `frames` table.');

    $baseSql = preg_replace('/\s+(LIMIT|OFFSET)\s+[0-9,\s]+$/i', '', $rawQuery);
    $countSql = "SELECT COUNT(*) FROM (" . $baseSql . ") AS subq_raw";
    $dataSql = $baseSql;

} 
// 2. AGGREGATION MODE (Storyboards logic)
// Added 'global_any' to the list
elseif (!empty($storyboardIds) || in_array($assignmentLogic, ['global_multi', 'global_any', 'selected_any', 'selected_all', 'selected_multi'])) {
    
    // JOIN storyboards table to get names for the new bubble feature
    $fromAgg = "FROM `frames` f 
                JOIN `storyboard_frames` sf ON sf.frame_id = f.id
                JOIN `storyboards` s ON sf.storyboard_id = s.id"; 
    
    $whereAgg = [];
    $havingClause = "";
    
    // Determine placeholders for "IN (?)"
    $placeholders = '';
    if (!empty($storyboardIds)) {
        $placeholders = implode(',', array_fill(0, count($storyboardIds), '?'));
    }

    // Prepare expressions
    $countExpr = ""; 
    $selectExpr = "";

    // Logic Switch
    switch ($assignmentLogic) {
        case 'global_multi':
            // Count distinct storyboards globally > 1
            $countExpr = "COUNT(DISTINCT sf.storyboard_id)";
            $selectExpr = "$countExpr AS usage_count";
            $havingClause = "HAVING $countExpr > 1"; 
            break;

        case 'global_any':
            // NEW: Count distinct storyboards globally >= 1 (Basically "In any storyboard")
            $countExpr = "COUNT(DISTINCT sf.storyboard_id)";
            $selectExpr = "$countExpr AS usage_count";
            $havingClause = "HAVING $countExpr >= 1"; 
            break;

        case 'selected_all':
            if (empty($storyboardIds)) sendJsonError("'Selected All' requires storyboard selection.");
            $whereAgg[] = "sf.storyboard_id IN ({$placeholders})";
            foreach ($storyboardIds as $sid) $params[] = $sid;
            
            $countExpr = "COUNT(DISTINCT sf.storyboard_id)";
            $selectExpr = "$countExpr AS matched_count";
            $havingClause = "HAVING $countExpr = " . count($storyboardIds);
            break;

        case 'selected_multi':
            if (empty($storyboardIds)) sendJsonError("'Selected Multi' requires storyboard selection.");
            $whereAgg[] = "sf.storyboard_id IN ({$placeholders})";
            foreach ($storyboardIds as $sid) $params[] = $sid;
            
            $countExpr = "COUNT(DISTINCT sf.storyboard_id)";
            $selectExpr = "$countExpr AS matched_count";
            $havingClause = "HAVING $countExpr > 1";
            break;
            
        case 'selected_any':
        default:
            if (!empty($storyboardIds)) {
                $whereAgg[] = "sf.storyboard_id IN ({$placeholders})";
                foreach ($storyboardIds as $sid) $params[] = $sid;
            } elseif ($assignmentLogic == 'selected_any') {
                 sendJsonError("'Selected Any' requires storyboard selection.");
            }
            $countExpr = "COUNT(DISTINCT sf.storyboard_id)";
            $selectExpr = "$countExpr AS usage_count";
            break;
    }

    $whereClauseAgg = !empty($whereAgg) ? ' WHERE ' . implode(' AND ', $whereAgg) : '';
    
    // Add GROUP_CONCAT to get names
    // We use SEPARATOR |~| or something standard, here ', ' for display
    $namesExpr = "GROUP_CONCAT(DISTINCT s.name ORDER BY s.name ASC SEPARATOR ', ') AS storyboard_names";

    // Count Query
    $countSql = "SELECT COUNT(*) FROM (
                    SELECT f.id 
                    {$fromAgg} 
                    {$whereClauseAgg} 
                    GROUP BY f.id 
                    {$havingClause}
                 ) AS subq_agg";

    // Sorting
    if ($orderBy === 'usage') {
        $orderSql = "ORDER BY 2 DESC, f.id DESC"; 
    } else {
        $orderSql = "ORDER BY f.id DESC";
    }

    $dataSql = "SELECT f.id, f.filename, f.name, f.entity_type, f.entity_id, {$selectExpr}, {$namesExpr}
                {$fromAgg} 
                {$whereClauseAgg} 
                GROUP BY f.id 
                {$havingClause} 
                {$orderSql}";

} 
// 3. STANDARD MODE
else {
    
    $select = "SELECT DISTINCT f.id, f.filename, f.name, f.entity_type, f.entity_id";
    $from = "FROM `frames` f";
    $joins = [];
    $where = [];
    $orderBySql = '';

    $sortByParts = explode('.', $sortByInput);
    if (count($sortByParts) !== 2) $sortByParts = ['frames', 'id'];
    list($sortTable, $sortColumn) = $sortByParts;
    
    if ($sortTable === 'frames') {
        $orderBySql = "ORDER BY f.`{$sortColumn}` {$sortOrder}";
    } else {
        $joins[] = "JOIN `{$sortTable}` e ON 1=1"; 
        $orderBySql = "ORDER BY e.`{$sortColumn}` {$sortOrder}";
        $select .= ", e.`{$sortColumn}`";
    }

    $entityTypes = $entityTypesRaw ? explode(',', $entityTypesRaw) : [];
    if (!empty($entityTypes)) {
        if (count($entityTypes) === 1) {
            $eType = trim($entityTypes[0]);
            $mapTable = 'frames_2_' . $eType;
            $joins[] = "JOIN `{$mapTable}` map ON f.id = map.from_id";
            if ($sortTable !== 'frames') {
                 $joins[] = "JOIN `{$eType}` e ON map.to_id = e.id"; 
            }
            
            if ($entityIdsRaw) {
                $eIds = array_map('intval', explode(',', $entityIdsRaw));
                $inQuery = implode(',', array_fill(0, count($eIds), '?'));
                $where[] = "map.to_id IN ($inQuery)";
                foreach($eIds as $id) $params[] = $id;
            }
        } else {
            $ors = [];
            foreach($entityTypes as $et) {
                $mapTable = 'frames_2_' . trim($et);
                $ors[] = "EXISTS (SELECT 1 FROM `{$mapTable}` WHERE from_id = f.id)";
            }
            $where[] = "(" . implode(' OR ', $ors) . ")";
        }
    }

    if ($fromFrameId !== null) {
        $where[] = "f.id >= ?";
        $params[] = $fromFrameId;
    }

    $joinClause = implode(' ', array_unique($joins));
    $whereClause = !empty($where) ? ' WHERE ' . implode(' AND ', $where) : '';

    $countSql = "SELECT COUNT(DISTINCT f.id) " . $from . " " . $joinClause . " " . $whereClause;
    $dataSql = $select . " " . $from . " " . $joinClause . " " . $whereClause . " " . $orderBySql;
}

// --- Execute ---
try {
    $stmtCount = $pdo->prepare($countSql);
    $stmtCount->execute($params);
    $total = (int)$stmtCount->fetchColumn();

    $finalSql = $dataSql;
    if ($overallLimit !== null) {
        $finalSql .= " LIMIT " . $overallLimit;
        $total = min($total, $overallLimit);
    }
    
    $finalSql .= " LIMIT ? OFFSET ?";

    $stmtData = $pdo->prepare($finalSql);
    
    $bindIdx = 1;
    foreach ($params as $v) {
        $stmtData->bindValue($bindIdx++, $v, PDO::PARAM_INT);
    }
    $stmtData->bindValue($bindIdx++, $batchSize, PDO::PARAM_INT);
    $stmtData->bindValue($bindIdx++, $offset, PDO::PARAM_INT);
    
    $stmtData->execute();
    $rows = $stmtData->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    sendJsonError('Database Query Failed', 500, $e->getMessage() . " | SQL: " . $dataSql);
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
        'w'           => 768,
        'h'           => 768,
        'entity'      => $row['entity_type'] ?? $entityTypesRaw,
        'entity_id'   => (int)($row['entity_id'] ?? 0),
        'usage_count' => (int)($row['usage_count'] ?? $row['matched_count'] ?? 0),
        'storyboard_names' => $row['storyboard_names'] ?? '' // New field
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