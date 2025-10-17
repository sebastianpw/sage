<?php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';
require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\SpwBase;

// which entity to load? default to 'characters'
$entity = isset($_GET['entity']) ? preg_replace('/[^a-z0-9_]/i', '', $_GET['entity']) : 'characters';

// Compute FQCN dynamically
$className = 'App\\Gallery\\' 
           . str_replace(' ', '', ucwords(str_replace('_', ' ', $entity))) 
           . "Gallery";

// check class existence (no more file includes)
if (!class_exists($className)) {
    header('HTTP/1.1 400 Bad Request');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => "Gallery class not found: $className"]);
    exit;
}

$spw = SpwBase::getInstance();

// ensure ajax_gallery flag is set so render() returns JSON
$_GET['ajax_gallery'] = '1';

// instantiate gallery
$gallery = new $className();

// render JSON branch (render() already echoes JSON and exits)
echo $gallery->render();
exit;
