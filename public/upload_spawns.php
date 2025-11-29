<?php
// public/upload_spawns.php

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

require __DIR__ . "/SpawnGalleryManager.php";
require __DIR__ . "/SpawnUpload.php";
require __DIR__ . "/../vendor/autoload.php";

use App\UI\Modules\ModuleRegistry;

$mysqli = $spw->getMysqli();

// Initialize gallery manager
$galleryManager = new SpawnGalleryManager($mysqli);

// Check if spawn type is specified in URL
if (isset($_GET['spawn_type'])) {
    $galleryManager->setActiveType($_GET['spawn_type']);
}

// Get module registry
$registry = ModuleRegistry::getInstance();

// Configure gear menu module for spawns
$gearMenu = $registry->create('gear_menu', [
    'position' => 'top-right',
    'icon' => '&#9881;',
    'icon_size' => '2em',
    'show_for_entities' => ['spawns'],
]);

// First action: View Frame in modal
$gearMenu->addAction('spawns', [
    'label' => 'View Frame',
    'icon' => '👁️',
    'callback' => 'window.showFrameDetailsModal(frameId);'
]);

$gearMenu->addAction('spawns', [
    'label' => 'Import to Generative',
    'icon' => '⚡',
    'callback' => 'window.importGenerative(entity, entityId, frameId);'
]);

$gearMenu->addAction('spawns', [
    'label' => 'Add to Storyboard',
    'icon' => '🎬',
    'callback' => 'window.selectStoryboard(frameId, $(wrapper));'
]);

$gearMenu->addAction('spawns', [
    'label' => 'Edit Image',
    'icon' => '🖌️',
    'callback' => 'const $w = $(wrapper); ImageEditorModal.open({ entity: entity, entityId: entityId, frameId: frameId, src: $w.find(\'img\').attr(\'src\') });'
]);

$gearMenu->addAction('spawns', [
        'label' => 'View Frame Chain',
        'icon' => '🔗', // A chain link icon
        'callback' => 'window.showFrameChainInModal(frameId);'
    ]);
    
$gearMenu->addAction('spawns', [
    'label' => 'Assign to Composite',
    'icon' => '🧩',
    'callback' => 'window.assignToComposite(entity, entityId, frameId);'
]);

$gearMenu->addAction('spawns', [
    'label' => 'Import to ControlNet Map',
    'icon' => '☠️',
    'callback' => 'window.importControlNetMap(entity, entityId, frameId);'
]);

$gearMenu->addAction('spawns', [
    'label' => 'Use Prompt Matrix',
    'icon' => '🌌',
    'callback' => 'window.usePromptMatrix(entity, entityId, frameId);'
]);

$gearMenu->addAction('spawns', [
    'label' => 'Delete Frame',
    'icon' => '🗑️',
    'callback' => 'window.deleteFrame(entity, entityId, frameId);'
]);

// Configure image editor module
$imageEditor = $registry->create('image_editor', [
    'modes' => ['mask', 'crop'],
    'show_transform_tab' => true,
    'show_filters_tab' => true,
    'enable_rotate' => true,
    'enable_resize' => true,
    'preset_filters' => [
        'grayscale', 'vintage', 'sepia', 'clarendon',
        'gingham', 'moon', 'lark', 'reyes', 'juno', 'slumber'
    ],
]);

// Render tabs for different spawn types
$tabsHtml = $galleryManager->renderTypeTabs();

// Render gallery for active type (empty for now, will be in upload form)
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

// Load the frame details modal
ob_start();
require __DIR__ . '/modal_frame_details.php';
$frameDetailsModal = ob_get_clean();

// Combine all content - inject gear menu globals and module renders
$content = $eruda 
         . '<script src="/js/gear_menu_globals.js"></script>'
         . $gearMenu->render()
         . $imageEditor->render()
         . $frameDetailsModal
         . $tabsHtml 
         . $galleryHtml 
         . $uploadHtml;

$spw->renderLayout($content, "Upload Spawns", $spw->getProjectPath() . '/templates/gallery.php');
