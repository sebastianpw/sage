<?php

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

$pageTitle = "Test View";
$content = "";

ob_start(); 
?>

<div class="view-container">
<?php /* view specific content goes here */ ?>


    <?php require 'modal_frame_details.php'; ?>




<?php /*
    <a href="javascript:void(0);" onclick="showFrameDetailsModal(1234);">View Frame 1234</a>
    
    <!-- Or on an image click in a gallery -->
    <img src="..." data-frame-id="5678" class="gallery-thumb">
    
    <script>
    $('.gallery-thumb').on('click', function() {
        const frameId = $(this).data('frame-id');
        showFrameDetailsModal(frameId);
    });
    </script>
 */ ?>


<a href="javascript:void(0);" onclick="showFrameDetailsModal(1949,0.5);"  href="view_frame.php?entity=generatives&entity_id=143&frame_id=1949">
<img style="width: 200px; height: 200px;" src="/frames_starlightguardians_nu/frame0004455.jpg" alt="Observatory" class="frame-image">
</a>












</div>

<?php          
require "floatool.php";
$content = ob_get_clean();
$content .= $eruda;
$spw->renderLayout($content, $pageTitle);

