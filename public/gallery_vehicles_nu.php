<?php
// public/gallery_vehicles_nu.php
// Auto-generated modular gallery view
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

use App\Core\SpwBase;
use App\Gallery\VehiclesNuGallery;
use App\UI\Modules\ModuleRegistry;

$spw = SpwBase::getInstance();

// Get module registry
$registry = ModuleRegistry::getInstance();

$entity = 'vehicles';

// Configure gear menu module for vehicles
$gearMenu = $registry->create('gear_menu', [
    'position' => 'top-right',
    'icon' => '&#9881;',
    'icon_size' => '2em',
    'show_for_entities' => ['vehicles'],
]);

// Use the new standard actions method
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
$gallery = new VehiclesNuGallery();

// Load the frame details modal
ob_start();
require __DIR__ . '/modal_frame_details.php';
$frameDetailsModal = ob_get_clean();

// Render everything together
$content = ($eruda ?? '')
         . '<script src="/js/gear_menu_globals.js"></script>'
         . $gearMenu->render()
         . $imageEditor->render()
         . $frameDetailsModal
         . $gallery->render();

$spw->renderLayout(
    $content,
    "Vehicles Gallery (Modular)",
    $spw->getProjectPath() . '/templates/gallery.php'
);

