<?php
// delete_frames_from_entity.php
//
// Web GET example:
// http://localhost:8080/delete_frames_from_entity.php?method=single&frame_id=1331
//
// CLI example:
// php delete_frames_from_entity.php method=single frame_id=1331
//
// AJAX example:
// fetch('/delete_frames_from_entity.php?ajax=1&method=single&frame_id=1331')
//     .then(res => res.json())
//     .then(data => console.log(data.result));
//
// --- Example URLs for the three deletion methods ---
//
// 1. Single frame delete:
// http://localhost:8080/delete_frames_from_entity.php?method=single&frame_id=1331
//
// 2. Delete all frames for a specific entity:
// http://localhost:8080/delete_frames_from_entity.php?method=all_entity&entity=characters&entity_id=42
//
// 3. Bulk delete for multiple entities:
// http://localhost:8080/delete_frames_from_entity.php?method=bulk_entities&entity=locations&start_id=100&limit=10


require __DIR__ . '/bootstrap.php';
require __DIR__ . '/EntityToTrashcanImporter.php';

$spw = \App\Core\SpwBase::getInstance();
$mysqli = $spw->getMysqli();

// --- DEFAULTS ---
$defaults = [
    'method' => 'single', // single, all_entity, bulk_entities
    'frame_id' => null,
    'entity' => null,
    'entity_id' => null,
    'start_id' => 0,
    'limit' => 1
];

// --- GET PARAMETERS ---
$params = [];
if (php_sapi_name() === 'cli') {
    global $argv;
    foreach ($argv as $arg) {
        if (strpos($arg, '=') !== false) {
            [$key, $value] = explode('=', $arg, 2);
            $params[$key] = $value;
        }
    }
} else {
    $params = $_GET;
}

$params = array_merge($defaults, $params);

$method    = $params['method'];
$frameId   = isset($params['frame_id']) ? (int)$params['frame_id'] : null;
$entity    = $params['entity'];
$entityId  = isset($params['entity_id']) ? (int)$params['entity_id'] : null;
$startId   = (int)$params['start_id'];
$limit     = (int)$params['limit'];

$result = [];

try {
    switch ($method) {

        case 'single':
            if (!$frameId) {
                throw new Exception("For 'single' method, frame_id is required.");
            }
            $deleted = EntityToTrashcanImporter::deleteFrameById($frameId);
            $result[] = "Frame ID $frameId moved to trash and deleted.";
            break;

        case 'all_entity':
            if (!$entity || !$entityId) {
                throw new Exception("For 'all_entity' method, entity and entity_id are required.");
            }
            $deleted = EntityToTrashcanImporter::deleteAllFramesForEntity($entity, $entityId);
            $result[] = "All frames for entity '$entity' with ID $entityId moved to trash and deleted ($deleted rows).";
            break;

        case 'bulk_entities':
            if (!$entity) {
                throw new Exception("For 'bulk_entities' method, entity is required.");
            }
            $deleted = EntityToTrashcanImporter::deleteFramesForEntities($entity, $startId, $limit);
            $result[] = "Frames for $limit entities of type '$entity' starting from ID $startId moved to trash and deleted ($deleted rows).";
            break;

        default:
            throw new Exception("Unknown method: $method");
    }
} catch (\Exception $e) {
    $result[] = "Failed: " . $e->getMessage();
}

// --- AJAX detection ---
$isAjax = !empty($params['ajax']) || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest');

if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => 'ok',
        'result' => $result
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Delete Frames</title>
</head>
<body>
<h2>Delete Frames</h2>
<?php foreach ($result as $line): ?>
    <div><?= htmlspecialchars($line) ?></div>
<?php endforeach; ?>
</body>
</html>
