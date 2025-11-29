<?php
// public/gallery_locations_nu.php
// Auto-generated modular gallery view
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

use App\Core\SpwBase;
use App\Gallery\LocationsNuGallery;
use App\UI\Modules\ModuleRegistry;

$spw = SpwBase::getInstance();

// Get module registry
$registry = ModuleRegistry::getInstance();

$entity = 'locations';

// Configure gear menu module for locations
$gearMenu = $registry->create('gear_menu', [
    'position' => 'top-right',
    'icon' => '&#9881;',
    'icon_size' => '2em',
    'show_for_entities' => ['locations'],
]);

// First action: View Frame
$gearMenu->addAction('locations', [
    'label' => 'View Frame',
    'icon' => '👁️',
    'callback' => 'window.showFrameDetailsModal(frameId);'
]);

$gearMenu->addAction('locations', [
    'label' => 'Import to Generative',
    'icon' => '⚡',
    'callback' => 'window.importGenerative(entity, entityId, frameId);'
]);

$gearMenu->addAction('locations', [
    'label' => 'Edit Entity',
    'icon' => '✏️',
    'callback' => 'window.showEntityFormInModal(entity, entityId);'
]);

$gearMenu->addAction('locations', [
    'label' => 'Edit Image',
    'icon' => '🖌️',
    'callback' => 'const $w = $(wrapper); ImageEditorModal.open({ entity: entity, entityId: entityId, frameId: frameId, src: $w.find(\'img\').attr(\'src\') });'
]);

$gearMenu->addAction('locations', [
    'label' => 'View Frame Chain',
    'icon' => '🔗', // A chain link icon
    'callback' => 'window.showFrameChainInModal(frameId);'
]);

$gearMenu->addAction('locations', [
    'label' => 'Add to Storyboard',
    'icon' => '🎬',
    'callback' => 'window.selectStoryboard(frameId, $(wrapper));'
]);

$gearMenu->addAction('locations', [
    'label' => 'Assign to Composite',
    'icon' => '🧩',
    'callback' => 'window.showImportEntityModal({
        source: "' . $entity . '",
        target: "composites",
        source_entity_id: entityId,
        frame_id: frameId,
        target_entity_id: "",
        limit: 1,
        copy_name_desc: 0,
        composite: 1
    });'
]);

$gearMenu->addAction('locations', [
    'label' => 'Import to ControlNet Map',
    'icon' => '☠️',
    'callback' => 'window.importControlNetMap(entity, entityId, frameId);'
]);

$gearMenu->addAction('locations', [
    'label' => 'Use Prompt Matrix',
    'icon' => '🌌',
    'callback' => 'window.usePromptMatrix(entity, entityId, frameId);'
]);

$gearMenu->addAction('locations', [
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

// Create the gallery instance
$gallery = new LocationsNuGallery();

// Load the frame details modal
ob_start();
require __DIR__ . '/modal_frame_details.php';
$frameDetailsModal = ob_get_clean();

// Render everything together
$content = $eruda 
         . '<script src="/js/gear_menu_globals.js"></script>'
         . $gearMenu->render()
         . $imageEditor->render()
         . $frameDetailsModal
         . $gallery->render();

$spw->renderLayout(
    $content,
    "Locations Gallery (Modular)",
    $spw->getProjectPath() . '/templates/gallery.php'
);
