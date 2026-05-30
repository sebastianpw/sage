<?php

require_once __DIR__ . '/bootstrap.php';

use App\Core\PyApiVectorService;

// -----------------------------
$collection = 'sage_sketches_nu';
$queryText  = 'test query';
$limit      = 5;

$poolIds = [3511,2701,1413,2235,3648];

// -----------------------------
$vs = new PyApiVectorService();

$out = [
    'note' => 'Inspecting REAL client behavior',
];

// -----------------------------
// RUN WITHOUT FILTER
// -----------------------------
try {
    $res = $vs->queryJson($queryText, $collection, $limit, 'primary');

    $out['unrestricted_type'] = gettype($res);
    $out['unrestricted_sample'] = is_array($res)
        ? array_slice($res, 0, 2)
        : $res;

} catch (\Throwable $e) {
    $out['unrestricted_error'] = $e->getMessage();
}

// -----------------------------
// RUN WITH FILTER (AS YOU DO)
// -----------------------------
$filter = [
    'sketch_id' => [
        '$in' => $poolIds
    ]
];

try {
    // EXACT same call signature you use in production
    $resFiltered = $vs->queryJson(
        $queryText,
        $collection,
        $limit,
        'primary',
        $filter
    );

    $out['filtered_type'] = gettype($resFiltered);
    $out['filtered_sample'] = is_array($resFiltered)
        ? array_slice($resFiltered, 0, 2)
        : $resFiltered;

} catch (\Throwable $e) {
    $out['filtered_error'] = $e->getMessage();
}

// -----------------------------
// CRITICAL: dump METHOD SIGNATURE
// -----------------------------
try {
    $ref = new ReflectionMethod($vs, 'queryJson');

    $params = [];
    foreach ($ref->getParameters() as $p) {
        $params[] = $p->getName();
    }

    $out['queryJson_params'] = $params;

} catch (\Throwable $e) {
    $out['reflection_error'] = $e->getMessage();
}

// -----------------------------
header('Content-Type: application/json');
echo json_encode($out, JSON_PRETTY_PRINT);