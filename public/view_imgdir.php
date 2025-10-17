<?php
require "e.php";
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';
require __DIR__ . "/ImgDirGallery.php";

$framesDirRel = str_replace(PROJECT_ROOT . '/public/', '', FRAMES_ROOT);

// whitelist of allowed base folders (relative paths)
$selectableFolders = [$framesDirRel];

// get folder param safely
$requestedFolder = $_GET['folder'] ?? $framesDirRel;

// normalize requested folder to avoid ../ tricks
$requestedFolder = ltrim($requestedFolder, '/\\'); // remove leading slashes
$requestedFolder = preg_replace('#\.\.[/\\\]#', '', $requestedFolder); // strip traversal

// check against whitelist
if (!in_array($requestedFolder, $selectableFolders, true)) {
    // fallback to default
    $requestedFolder = $framesDirRel;
}

$gallery = new ImgDirGallery(__DIR__ . '/' . $requestedFolder, $selectableFolders);

$spw = \App\Core\SpwBase::getInstance();
$spw->renderLayout($eruda . $gallery->render(), "Frames Browser");


