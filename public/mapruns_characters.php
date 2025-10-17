<?php

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';


require_once "CharactersGallery.php";

header('Content-Type: application/json');

$characterName = $_GET['character'] ?? '';

if (!$characterName) {
    echo json_encode([]);
    exit;
}

$gallery = new CharactersGallery();
$mapRuns = $gallery->fetchMapRunsForCharacter($characterName);

// Return JSON array of map runs
echo json_encode($mapRuns);
