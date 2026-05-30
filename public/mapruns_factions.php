<?php

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';


require_once "FactionsGallery.php";

header('Content-Type: application/json');

$factionName = $_GET['faction'] ?? '';

if (!$factionName) {
    echo json_encode([]);
    exit;
}

$gallery = new FactionsGallery();
$mapRuns = $gallery->fetchMapRunsForFaction($factionName);

// Return JSON array of map runs
echo json_encode($mapRuns);
