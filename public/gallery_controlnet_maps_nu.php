<?php
// public/gallery_controlnet_maps_nu.php
// Auto-generated modular gallery view
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

use App\Core\SpwBase;
use App\Gallery\ControlnetMapsNuGallery;
use App\UI\Modules\ModuleRegistry;

$spw = SpwBase::getInstance();

// Get module registry
$registry = ModuleRegistry::getInstance();

$entity = 'controlnet_maps';

// Configure gear menu module for controlnet_maps
$gearMenu = $registry->create('gear_menu', [
    'position' => 'top-right',
    'icon' => '&#9881;',
    'icon_size' => '2em',
    'show_for_entities' => ['controlnet_maps'],
]);

// Use the new standard actions method
$gearMenu->addAction('controlnet_maps', [
    'label' => 'Assign to Character',
    'icon' => '👤',
    'callback' => 'window.showImportEntityModal({ source: "controlnet_maps", target: "characters", source_entity_id: entityId, frame_id: frameId, limit: 1, copy_name_desc: 0, controlnet: 1 });'
]);

$gearMenu->addAction('controlnet_maps', [
    'label' => 'Assign to Generative',
    'icon' => '⚡',
    'callback' => 'window.showImportEntityModal({ source: "controlnet_maps", target: "generatives", source_entity_id: entityId, frame_id: frameId, limit: 1, copy_name_desc: 0, controlnet: 1 });'
]);

$gearMenu->addAction('controlnet_maps', [
    'label' => 'Assign to Sketch',
    'icon' => '✏️',
    'callback' => 'window.showImportEntityModal({ source: "controlnet_maps", target: "sketches", source_entity_id: entityId, frame_id: frameId, limit: 1, copy_name_desc: 0, controlnet: 1 });'
]);

$gearMenu->addStandardActions($entity);

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
$gallery = new ControlnetMapsNuGallery();

// Load the frame details modal
ob_start();
require __DIR__ . '/modal_frame_details.php';
$frameDetailsModal = ob_get_clean();

// Render everything together
$content = ($eruda ?? '')
         . '<script src="/js/gear_menu_globals.js"></script>'
         . $gearMenu->render()
         
         . $frameDetailsModal
         . $gallery->render();

$spw->renderLayout(
    $content,
    "ControlnetMaps Gallery (Modular)",
    $spw->getProjectPath() . '/templates/gallery.php'
);

