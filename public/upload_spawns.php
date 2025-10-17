<?php

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

require __DIR__ . "/SpawnGalleryManager.php";
require __DIR__ . "/SpawnUpload.php";
require __DIR__ . "/../vendor/autoload.php";

$mysqli = $spw->getMysqli();

// Initialize gallery manager
$galleryManager = new SpawnGalleryManager($mysqli);

// Check if spawn type is specified in URL
if (isset($_GET['spawn_type'])) {
    $galleryManager->setActiveType($_GET['spawn_type']);
}

// Render tabs for different spawn types
$tabsHtml = $galleryManager->renderTypeTabs();

// Render gallery for active type
//$galleryHtml = $galleryManager->renderGallery();
$galleryHtml = '';

// Render upload form if enabled
$uploadHtml = '';
if ($galleryManager->isUploadEnabled()) {
    $activeType = $galleryManager->getActiveType();
    $uploader = new SpawnUpload($mysqli, $activeType);
    $uploadHtml = $uploader->render();
} else {
    $uploadHtml = '<p>Upload is not enabled for this spawn type.</p>';
}

// Combine all content
$content = $eruda . $tabsHtml . $galleryHtml . '<hr>' . $uploadHtml;

$spw->renderLayout($content, "Upload Spawns", $spw->getProjectPath() . '/templates/gallery.php');
