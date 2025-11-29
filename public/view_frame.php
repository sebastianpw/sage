<?php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

use App\Core\SpwBase;
use App\Gallery\FrameDetails;
use App\UI\Modules\ModuleRegistry;

$spw = SpwBase::getInstance();
$mysqli = $spw->getMysqli();

// Get parameter
$frameId = isset($_GET['frame_id']) ? (int)$_GET['frame_id'] : 0;
$isModalView = isset($_GET['view']) && $_GET['view'] === 'modal';
$zoomLevel = isset($_GET['zoom']) ? (float)$_GET['zoom'] : 1.0;
if ($zoomLevel <= 0.1 || $zoomLevel > 5) {
    $zoomLevel = 1.0;
}

$frameDetails = new FrameDetails($mysqli);
if (!$frameDetails->load($frameId)) {
    die(htmlspecialchars($frameDetails->error ?: "Could not load frame."));
}

// Get entity information for module configuration
$entity = $frameDetails->frameData['entity_type'] ?? 'unknown';
$entityId = $frameDetails->frameData['entity_id'] ?? 0;

// Get module registry
$registry = ModuleRegistry::getInstance();

// Configure gear menu module
$gearMenu = $registry->create('gear_menu', [
    'position' => 'top-right',
    'icon' => '&#9881;',
    'icon_size' => '2em',
    'show_for_entities' => [$entity],
]);

$gearMenu->addAction($entity, [
    'label' => 'Import to Generative',
    'icon' => '⚡',
    'callback' => 'window.importGenerative(entity, entityId, frameId);'
]);

$gearMenu->addAction($entity, [
    'label' => 'Edit Entity',
    'icon' => '✏️',
    'callback' => 'window.editEntity(entity, entityId, frameId);'
]);

$gearMenu->addAction($entity, [
    'label' => 'Edit Image',
    'icon' => '🖌️',
    'callback' => 'const $w = $(wrapper); if (typeof ImageEditorModal !== "undefined") { ImageEditorModal.open({ entity: entity, entityId: entityId, frameId: frameId, src: $w.find("img").attr("src") }); }'
]);

$gearMenu->addAction($entity, [
    'label' => 'View Frame Chain',
    'icon' => '🔗', // A chain link icon
    'callback' => 'window.showFrameChainInModal(frameId);'
]);

$gearMenu->addAction($entity, [
    'label' => 'Add to Storyboard',
    'icon' => '🎬',
    'callback' => 'window.selectStoryboard(frameId, $(wrapper));'
]);

$gearMenu->addAction($entity, [
    'label' => 'Assign to Composite',
    'icon' => '🧩',
    'callback' => 'window.assignToComposite(entity, entityId, frameId);'
]);

$gearMenu->addAction($entity, [
    'label' => 'Import to ControlNet Map',
    'icon' => '☠️',
    'callback' => 'window.importControlNetMap(entity, entityId, frameId);'
]);

$gearMenu->addAction($entity, [
    'label' => 'Use Prompt Matrix',
    'icon' => '🌌',
    'callback' => 'window.usePromptMatrix(entity, entityId, frameId);'
]);

$gearMenu->addAction($entity, [
    'label' => 'Delete Frame',
    'icon' => '🗑️',
    'callback' => 'if (confirm("Delete this frame?")) { window.deleteFrame(entity, entityId, frameId); setTimeout(() => { window.location.href = "gallery_' . $entity . '_nu.php"; }, 1000); }'
]);

// Configure image editor module (match gallery settings)
$imageEditor = $registry->create('image_editor', [
    'modes' => ['mask', 'crop'],
    'show_transform_tab' => true,
    'show_filters_tab' => true, // This is a legacy setting now, but we leave it for consistency
    'enable_rotate' => true,
    'enable_resize' => true,
    'preset_filters' => [
        'grayscale', 'vintage', 'sepia', 'clarendon',
        'gingham', 'moon', 'lark', 'reyes', 'juno', 'slumber'
    ], // 'sharpen' has been removed
]);


ob_start();
?>
<!DOCTYPE html>
<html lang="en" data-theme="">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=0.7">
    <title>Frame #<?= $frameId ?> - <?= htmlspecialchars($frameDetails->entityName) ?></title>
    
    <script>
    (function() {
        try {
            var theme = localStorage.getItem('spw_theme');
            if (theme === 'dark') {
                document.documentElement.setAttribute('data-theme', 'dark');
            } else if (theme === 'light') {
                document.documentElement.setAttribute('data-theme', 'light');
            }
        } catch (e) {}
    })();
    </script>
    
    <link rel="stylesheet" href="/css/base.css">
    <link rel="stylesheet" href="/css/toast.css">
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="/js/toast.js"></script>
    <script src="/js/gear_menu_globals.js"></script>
    
    <style>
        body {
            zoom: <?= htmlspecialchars($zoomLevel) ?>;
        }
    </style>
</head>
<body>
<?php if(!$isModalView) { require "floatool.php"; } ?>

<?php
// Render modular components first
echo $gearMenu->render();
echo $imageEditor->render();

// Render the frame details content
echo $frameDetails->renderContent(); 
?>
    
<script>
// Initialize frame details when ready
$(document).ready(function(){
    // Initialize gear menu on the frame image
    if (window.GearMenu && typeof window.GearMenu.attach === 'function') {
        window.GearMenu.attach(document);
    }
    
    initializeFrameDetailsScripts();
});
</script>

<?php
require __DIR__ . '/modal_frame_details.php';
?>

</body>
</html>
<?php
$content = ob_get_clean();
echo $content;
