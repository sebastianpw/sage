<?php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

use App\Core\SpwBase;
use App\Gallery\EntityDetails;

$spw = SpwBase::getInstance();
$mysqli = $spw->getMysqli();

// Allowed entities - keep in sync with EntityDetails::$allowedEntities
$allowed = ['locations','backgrounds','sketches','artifacts','vehicles','spawns','generatives'];

// Params
$entity = isset($_GET['entity']) ? trim($_GET['entity']) : '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isModalView = isset($_GET['view']) && $_GET['view'] === 'modal';
$zoomLevel = isset($_GET['zoom']) ? (float)$_GET['zoom'] : 1;
if ($zoomLevel <= 0.1 || $zoomLevel > 5) $zoomLevel = 1;

if (!in_array($entity, $allowed, true)) {
    http_response_code(400);
    die("Invalid entity.");
}

$entityDetails = new EntityDetails($mysqli);
if (!$entityDetails->load($entity, $id)) {
    die(htmlspecialchars($entityDetails->error ?: "Could not load entity."));
}

ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=<?= htmlspecialchars($zoomLevel) ?>">
    <title><?= htmlspecialchars(ucfirst($entity) . ': ' . ($entityDetails->data['name'] ?? $id)) ?></title>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <link rel="stylesheet" href="/css/toast.css">
    <script src="/js/toast.js"></script>

    <style>
        body { margin:0; padding:20px; background:#000; color:#ccc; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif; zoom: <?= htmlspecialchars($zoomLevel) ?>; }
    </style>
</head>
<body>
<?php if (!$isModalView) { require "floatool.php"; } ?>

<?php
echo $entityDetails->renderContent();
echo $eruda ?? '';
?>

</body>
</html>
<?php
$content = ob_get_clean();
echo $content;
