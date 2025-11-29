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

// First action: View Frame
$gearMenu->addAction('controlnet_maps', [
    'label' => 'Assign to Character',
    'icon' => '👤',
    'callback' => 'window.showImportEntityModal({
        source: "' . $entity . '",
        target: "characters",
        source_entity_id: entityId,
        frame_id: frameId,
        limit: 1,
        copy_name_desc: 0,
        controlnet: 1
    });'
]);

$gearMenu->addAction('controlnet_maps', [
    'label' => 'Assign to Generative',
    'icon' => '⚡',
    'callback' => 'window.showImportEntityModal({
        source: "' . $entity . '",
        target: "generatives",
        source_entity_id: entityId,
        frame_id: frameId,
        limit: 1,
        copy_name_desc: 0,
        controlnet: 1
    });'
]);

$gearMenu->addAction('controlnet_maps', [
    'label' => 'Assign to Sketch',
    'icon' => '✏️',
    'callback' => 'window.showImportEntityModal({
        source: "' . $entity . '",
        target: "sketches",
        source_entity_id: entityId,
        frame_id: frameId,
        limit: 1,
        copy_name_desc: 0,
        controlnet: 1
    });'
]);

$gearMenu->addAction('controlnet_maps', [
    'label' => 'View Frame',
    'icon' => '👁️',
    'callback' => 'window.showFrameDetailsModal(frameId);'
]);

$gearMenu->addAction('controlnet_maps', [
    'label' => 'Import to Generative',
    'icon' => '⚡',
    'callback' => 'window.importGenerative(entity, entityId, frameId);'
]);

$gearMenu->addAction('controlnet_maps', [
    'label' => 'Edit Entity',
    'icon' => '✏️',
    'callback' => 'window.showEntityFormInModal(entity, entityId);'
]);

$gearMenu->addAction('controlnet_maps', [
    'label' => 'Edit Image',
    'icon' => '🖌️',
    'callback' => 'const $w = $(wrapper); ImageEditorModal.open({ entity: entity, entityId: entityId, frameId: frameId, src: $w.find(\'img\').attr(\'src\') });'
]);

$gearMenu->addAction('controlnet_maps', [
    'label' => 'View Frame Chain',
    'icon' => '🔗', // A chain link icon
    'callback' => 'window.showFrameChainInModal(frameId);'
]);

$gearMenu->addAction('controlnet_maps', [
    'label' => 'Add to Storyboard',
    'icon' => '🎬',
    'callback' => 'window.selectStoryboard(frameId, $(wrapper));'
]);

$gearMenu->addAction('controlnet_maps', [
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

$gearMenu->addAction('controlnet_maps', [
    'label' => 'Import to ControlNet Map',
    'icon' => '☠️',
    'callback' => 'window.importControlNetMap(entity, entityId, frameId);'
]);

$gearMenu->addAction('controlnet_maps', [
    'label' => 'Use Prompt Matrix',
    'icon' => '🌌',
    'callback' => 'window.usePromptMatrix(entity, entityId, frameId);'
]);

$gearMenu->addAction('controlnet_maps', [
    'label' => 'Delete Frame',
    'icon' => '🗑️',
    'callback' => 'window.deleteFrame(entity, entityId, frameId);'
]);

// Create the gallery instance
$gallery = new ControlnetMapsNuGallery();

// Load the frame details modal
ob_start();
require __DIR__ . '/modal_frame_details.php';
$frameDetailsModal = ob_get_clean();

// Render everything together
$content = $eruda 
         . '<script src="/js/gear_menu_globals.js"></script>'
         . $gearMenu->render()
         . $frameDetailsModal
         . $gallery->render();

$spw->renderLayout(
    $content,
    "ControlnetMaps Gallery (Modular)",
    $spw->getProjectPath() . '/templates/gallery.php'
);
