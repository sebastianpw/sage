<?php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

use App\Core\SpwBase;
use App\Gallery\FrameDetails; // Make sure your autoloader can find this class

$spw = SpwBase::getInstance();
$mysqli = $spw->getMysqli();

// Get parameter
$frameId = isset($_GET['frame_id']) ? (int)$_GET['frame_id'] : 0;
$isModalView = isset($_GET['view']) && $_GET['view'] === 'modal';
$zoomLevel = isset($_GET['zoom']) ? (float)$_GET['zoom'] : 0.55;
if ($zoomLevel <= 0.1 || $zoomLevel > 5) {
    $zoomLevel = 0.55; // Sanity check for a reasonable range
}

$frameDetails = new FrameDetails($mysqli);
if (!$frameDetails->load($frameId)) {
    // Use the error message from the class
    die(htmlspecialchars($frameDetails->error ?: "Could not load frame."));
}

ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=<?= htmlspecialchars($zoomLevel) ?>">
    <title>Frame #<?= $frameId ?> - <?= htmlspecialchars($frameDetails->entityName) ?></title>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <link rel="stylesheet" href="/css/toast.css">
    <script src="/js/toast.js"></script>
    
    <link rel="stylesheet" href="css/gallery_gearicon_menu.css">
    <script src="js/gallery_gearicon_menu.js"></script>
    <script src="js/gear_menu_globals.js"></script>  
    <style>
        body { margin: 0; padding: 20px; background: #000; color: #ccc; font-family: sans-serif; zoom: <?= htmlspecialchars($zoomLevel) ?>; }
        .frame-container { max-width: 1200px; margin: 0 auto; }
    </style>
</head>
<body>
<?php if(!$isModalView) { require "floatool.php"; } ?>

<?php
// Render the reusable content block
echo $frameDetails->renderContent(); 
?>
    
<script>
// On a full page load, we initialize the scripts on document ready.
$(document).ready(function(){
    initializeFrameDetailsScripts();
});
</script>

<!-- Keep your Image editor scripts here, as they are part of the page shell -->
<script src="/js/image_editor_modal.js"></script>
<?php if (\App\Core\SpwBase::CDN_USAGE): ?>
    <!-- ... CropperJS etc. ... -->
<?php endif; ?>

</body>
</html>
<?php
$content = ob_get_clean();
echo $content;

