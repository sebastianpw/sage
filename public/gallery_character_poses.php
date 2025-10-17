<?php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

use App\Core\SpwBase;

$spw = SpwBase::getInstance();

$entity = "character_poses";

// Convert entity to FQCN dynamically
$className = 'App\\Gallery\\' 
           . str_replace(' ', '', ucwords(str_replace('_', ' ', $entity))) 
           . "Gallery";

if (!class_exists($className)) {
    die("Gallery class '$className' not found.");
}

$gallery = new $className($spw->getMysqli(), $spw);

$spw->renderLayout(
    $eruda . $gallery->render(),
    ucwords($entity) . " Gallery",
    $spw->getProjectPath() . '/templates/gallery.php'
);
