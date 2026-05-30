<?php
// public/api_lore.php
header('Content-Type: application/json');
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../src/Service/LoreAccessService.php';

use App\Service\LoreAccessService;

$docId = $_GET['doc_id'] ?? null;
$mode = $_GET['mode'] ?? 'full'; // full, entity, category, context
$query = $_GET['query'] ?? null;

if (!$docId) {
    echo json_encode(['error' => 'Missing doc_id']);
    exit;
}

try {
    $service = new LoreAccessService($pdo);
    $service->loadDoc((int)$docId);

    $response = [];

    switch ($mode) {
        case 'entity':
            if (!$query) throw new Exception("Missing query param for entity mode");
            $response = $service->getEntity($query);
            break;

        case 'context':
            // Optimized for AI Context Window injection
            if (!$query) throw new Exception("Missing query param for context mode");
            $response = $service->buildAgentContext($query);
            break;

        case 'category':
            // e.g. mode=category&query=characters&role=Protagonist
            if (!$query) throw new Exception("Missing query param (category name)");
            $role = $_GET['role'] ?? null;
            $response = $service->queryEntities($query, $role);
            break;

        case 'story':
            $response = $service->getStoryEngine();
            break;

        case 'full':
        default:
            // Warning: Massive payload
            $response = [
                'story' => $service->getStoryEngine(),
                'categories' => array_keys($service->queryEntities('characters') ? ['characters'=>1] : []) // filtered list
            ];
            break;
    }

    echo json_encode(['status' => 'success', 'data' => $response], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
