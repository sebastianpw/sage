<?php
// public/ajax_spawns_gallery.php
// Dedicated AJAX endpoint for spawns gallery that handles spawn types

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';
require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\SpwBase;

$spw = SpwBase::getInstance();
$mysqli = $spw->getMysqli();

// Get spawn type from request
$spawnTypeCode = isset($_GET['spawn_type']) ? $_GET['spawn_type'] : 'default';

// Load spawn type data from database
$spawnType = null;
if ($spawnTypeCode && $spawnTypeCode !== 'all') {
    $stmt = $mysqli->prepare("SELECT * FROM spawn_types WHERE code = ? AND active = 1 LIMIT 1");
    $stmt->bind_param('s', $spawnTypeCode);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $spawnType = $row;
    }
    $stmt->close();
}

// Determine which gallery class to use
$galleryClassName = 'App\\Gallery\\SpawnsGallery';

// Try spawn-type-specific class first (e.g., SpawnsGalleryReference)
if ($spawnType && $spawnType['code'] !== 'default') {
    $specificClassName = 'App\\Gallery\\SpawnsGallery' . ucfirst($spawnType['code']);
    if (class_exists($specificClassName)) {
        $galleryClassName = $specificClassName;
    }
}

// Check if class exists
if (!class_exists($galleryClassName)) {
    header('HTTP/1.1 400 Bad Request');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => "Gallery class not found: $galleryClassName"]);
    exit;
}

// Ensure ajax_gallery flag is set so render() returns JSON
$_GET['ajax_gallery'] = '1';

// Instantiate gallery with spawn type
$gallery = new $galleryClassName($spawnType);

// Render JSON (render() already echoes JSON and exits for AJAX requests)
echo $gallery->render();
exit;
