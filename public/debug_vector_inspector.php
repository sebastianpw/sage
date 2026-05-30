<?php
// public/debug_inspector.php
// RUN: php debug_inspector.php

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../src/Core/PyApiVectorService.php';

use App\Core\PyApiVectorService;

$collection = 'sage_sketches_nu';
$vs = new PyApiVectorService();

echo "--- INSPECTING COLLECTION: $collection ---\n";

// 1. Get 5 random items to check metadata types
$q = "test"; // dummy query
try {
    $res = $vs->queryJson($q, $collection, 5, 'primary'); // get 5 results
    
    if (empty($res['result']['ids'][0])) {
        die("No items found in collection.\n");
    }

    $metadatas = $res['result']['metadatas'][0];

    echo "Found " . count($metadatas) . " items.\n";
    foreach ($metadatas as $i => $meta) {
        if (isset($meta['sketch_id'])) {
            $val = $meta['sketch_id'];
            $type = gettype($val);
            echo "Item $i: sketch_id = " . var_export($val, true) . " (TYPE: $type)\n";
        } else {
            echo "Item $i: No sketch_id in metadata.\n";
            print_r($meta);
        }
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
