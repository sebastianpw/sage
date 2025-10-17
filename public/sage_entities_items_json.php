<?php 
require_once __DIR__ . '/bootstrap.php'; 
require __DIR__ . '/env_locals.php';

// sage_entities_items_json.php

$items = [];
require 'sage_entities_items_array.php'; // now $items contains full DB rows

header('Content-Type: application/json');
echo json_encode($items);  // full array, all columns
