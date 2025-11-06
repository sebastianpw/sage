<?php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

use App\Core\SpwBase;
use App\Gallery\CharacterDetails;

$spw = SpwBase::getInstance();
$mysqli = $spw->getMysqli();

// Get parameter
$characterId = isset($_GET['character_id']) ? (int)$_GET['character_id'] : 0;
$isModalView = isset($_GET['view']) && $_GET['view'] === 'modal';
$zoomLevel = isset($_GET['zoom']) ? (float)$_GET['zoom'] : 1;
if ($zoomLevel <= 0.1 || $zoomLevel > 5) {
    $zoomLevel = 1;
}

$characterDetails = new CharacterDetails($mysqli);
if (!$characterDetails->load($characterId)) {
    die(htmlspecialchars($characterDetails->error ?: "Could not load character."));
}

ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=<?= htmlspecialchars($zoomLevel) ?>">
    <title>Character: <?= htmlspecialchars($characterDetails->name) ?></title>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <link rel="stylesheet" href="/css/toast.css">
    <script src="/js/toast.js"></script>
    
    <style>
        body { 
            margin: 0; 
            padding: 20px; 
            background: #000; 
            color: #ccc; 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            zoom: <?= htmlspecialchars($zoomLevel) ?>; 
        }
    </style>
</head>
<body>
<?php if(!$isModalView) { require "floatool.php"; } ?>

<?php
// Render the character details content
echo $characterDetails->renderContent(); 

echo $eruda;
?>

</body>
</html>
<?php
$content = ob_get_clean();
echo $content;
