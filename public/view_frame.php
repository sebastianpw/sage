<?php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

use App\Core\SpwBase;
use App\Gallery\FrameDetails;
use App\UI\Modules\ModuleRegistry;
use App\Core\PyApiCVService;

$spw = SpwBase::getInstance();
$mysqli = $spw->getMysqli();

// --- AJAX Handler for Computer Vision ---
if (isset($_POST['action']) && $_POST['action'] === 'analyze_frame') {
    // Ensure no previous output corrupts the JSON
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json');

    try {
        $fId = (int)($_POST['frame_id'] ?? 0);
        if ($fId <= 0) throw new Exception("Invalid frame ID");

        $fd = new FrameDetails($mysqli);
        if (!$fd->load($fId)) throw new Exception("Frame not found");

        $filename = $fd->frameData['filename'];
        $absPath = $spw->getProjectPath() . '/public/' . $filename;

        if (!file_exists($absPath)) throw new Exception("Image file not found on server");

        // Initialize CV Service
        $cv = new PyApiCVService();
        
        // 1. Get prompt from user input, or fallback to default if empty
        $defaultPrompt = "Describe this image in a comma-separated format suitable for Stable Diffusion prompting. Describe every detail. Focus on visual elements, style, lighting, and composition. Do not include introductory text.";
        $prompt = trim($_POST['prompt'] ?? '');
        
        if (empty($prompt)) {
            $prompt = $defaultPrompt;
        }
        
        // 2. Perform analysis
        $result = $cv->analyze($absPath, $prompt);

        echo json_encode(['status' => 'success', 'data' => $result]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}
// ----------------------------------------

// Standard View Logic
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

$entity = $frameDetails->frameData['entity_type'] ?? 'unknown';
$entityId = $frameDetails->frameData['entity_id'] ?? 0;

$registry = ModuleRegistry::getInstance();

$gearMenu = $registry->create('gear_menu',[
    'position' => 'top-right',
    'icon' => '&#9881;',
    'icon_size' => '2em',
    'show_for_entities' => [$entity],
]);

$gearMenu->addStandardActions($entity,[
    'exclude' => ['view_frame'],
    'overrides' =>[
        'delete' =>[
            'callback' => 'if (confirm("Delete this frame?")) { window.deleteFrame(entity, entityId, frameId); setTimeout(() => { window.location.href = "gallery_' . $entity . '_nu.php"; }, 1000); }'
        ]
    ]
]);

$imageEditor = $registry->create('image_editor', [
    'modes' =>['mask', 'crop'],
    'show_transform_tab' => true,
    'show_filters_tab' => true,
    'enable_rotate' => true,
    'enable_resize' => true,
    'preset_filters' =>[
        'grayscale', 'vintage', 'sepia', 'clarendon',
        'gingham', 'moon', 'lark', 'reyes', 'juno', 'slumber'
    ], 
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
    
    <?php if (SpwBase::CDN_USAGE): ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.css" />
    <?php else: ?>
    <link rel="stylesheet" href="/vendor/photoswipe/photoswipe.css" />
    <?php endif; ?>
    
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
<?php if(!$isModalView) { require_once "forge_tool.php"; } ?>

<?php
echo $gearMenu->render();
echo $imageEditor->render();
echo $frameDetails->renderContent(); 
?>

<?php if (SpwBase::CDN_USAGE): ?>
<script src="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.umd.js"></script>
<script src="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe-lightbox.umd.js"></script>
<?php else: ?>
<script src="/vendor/photoswipe/photoswipe.umd.js"></script>
<script src="/vendor/photoswipe/photoswipe-lightbox.umd.js"></script>
<?php endif; ?>
    
<script>
$(document).ready(function(){
    if (window.GearMenu && typeof window.GearMenu.attach === 'function') {
        window.GearMenu.attach(document);
    }
    if (typeof initializeFrameDetailsScripts === 'function') {
        initializeFrameDetailsScripts();
    }
    
    try {
        const lightbox = new PhotoSwipeLightbox({
            gallery: '#pswp-depth-preview',
            children: 'a',
            pswpModule: PhotoSwipe
        });
        lightbox.init();
    } catch(e) {}
});
</script>

<?php require __DIR__ . '/modal_frame_details.php'; ?>


<?php echo $eruda; ?>


</body>
</html>
<?php
$content = ob_get_clean();
echo $content;
