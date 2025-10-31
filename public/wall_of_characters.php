<?php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

use App\Core\SpwBase;

$spw = SpwBase::getInstance();

$entity = "wall_of_characters";

$gallery = new \App\Gallery\WallOfCharactersGallery($spw->getMysqli(), $spw);

$spw->renderLayout(
    $eruda . $gallery->render(),
    "Wall of Images",
    $spw->getProjectPath() . '/templates/gallery.php'
);
