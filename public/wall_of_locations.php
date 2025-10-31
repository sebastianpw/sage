<?php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

use App\Core\SpwBase;

$spw = SpwBase::getInstance();

$entity = "wall_of_locations";

$gallery = new \App\Gallery\WallOfLocationsGallery($spw->getMysqli(), $spw);

$spw->renderLayout(
    $gallery->render(),
    "Wall of Images",
    $spw->getProjectPath() . '/templates/gallery.php'
);
