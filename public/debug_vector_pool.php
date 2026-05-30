<?php
// public/debug_vector_pool.php
// RUN: php debug_vector_pool.php

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../src/Core/PyApiVectorService.php';

use App\Core\PyApiVectorService;

// ----------------------- Config -----------------------
$collection = 'sage_sketches_nu';
$limit      = 5;
$qText      = "Visual DNA"; // Generic query

$vs = new PyApiVectorService();

echo "====== DEBUG: COMPOUND FILTER CHECK ======\n";

// ------------------------------------------------------
// STAGE 1: GET POOL (Using 'primary')
// ------------------------------------------------------
echo "\n--- STAGE 1: Get Valid IDs (Type: primary) ---\n";

try {
    // Mimic the API's Stage 1 query
    $res = $vs->queryJson($qText, $collection, 10, 'text', ['type' => 'primary']);
    
    // Mimic the API's extraction logic
    $candidates = $vs->extractSketchIds($res);
    $poolIds = [];
    foreach ($candidates as $c) {
        $poolIds[] = (int)$c['sketch_id'];
    }
    $poolIds = array_values(array_unique($poolIds));
    
    if (empty($poolIds)) {
        die("STOP: No items found in Stage 1.\n");
    }

    echo "Pool Detected (" . count($poolIds) . " items): " . json_encode($poolIds) . "\n";

} catch (Exception $e) {
    die("Stage 1 Error: " . $e->getMessage() . "\n");
}

// ------------------------------------------------------
// STAGE 2: TEST COMPOUND FILTERS
// ------------------------------------------------------
echo "\n--- STAGE 2: Testing Filter Combinations ---\n";

/**
 * Run a test with specific WHERE clause
 */
function runTest($vs, $label, $collection, $where) {
    global $qText;
    echo "\nTEST: $label\n";
    echo "Filter Payload: " . json_encode($where) . "\n";

    try {
        // Query exactly like the API does (queryJson)
        $res = $vs->queryJson($qText, $collection, 5, 'text', $where);
        
        $count = count($res['result']['ids'][0] ?? []);
        echo "Result: Found $count items.\n";
        
        if ($count > 0) {
            $meta = $res['result']['metadatas'][0][0] ?? [];
            echo "Sample Match: ID=" . ($meta['sketch_id'] ?? '?') . 
                 " | Type=" . ($meta['type'] ?? '?') . "\n";
        } else {
            echo "Result: [FAIL] - Filter returned nothing.\n";
        }

    } catch (Exception $e) {
        echo "CRITICAL ERROR: " . $e->getMessage() . "\n";
    }
}

// 1. SIMPLE: ID Only (We know this works from previous test, but verifying)
runTest($vs, "1. Simple: sketch_id only", $collection, [
    'sketch_id' => ['$in' => $poolIds]
]);

// 2. SIMPLE: Type Only (Should return many results)
runTest($vs, "2. Simple: type only", $collection, [
    'type' => 'primary'
]);

// 3. COMPOUND: ID + Type (The suspected failure point)
// This mirrors the API code: array_merge(['type' => 'primary'], $poolFilter)
$compoundWhere = array_merge(
    ['type' => 'primary'], 
    ['sketch_id' => ['$in' => $poolIds]]
);

runTest($vs, "3. COMPOUND: type='primary' AND sketch_id IN [...]", $collection, $compoundWhere);

// 4. COMPOUND: Narrative Check
// Do these IDs have narrative vectors?
$compoundNarrative = array_merge(
    ['type' => 'narrative'], 
    ['sketch_id' => ['$in' => $poolIds]]
);

runTest($vs, "4. COMPOUND: type='narrative' AND sketch_id IN [...]", $collection, $compoundNarrative);

echo "\n====== END OF TEST ======\n";