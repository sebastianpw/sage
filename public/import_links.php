<?php
// import_links.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php'; // provides $pdoSys, $fileLogger, etc.

use App\Core\ImportTabs;

header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? null;
if (!$action) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'missing action']);
    exit;
}

$importer = new ImportTabs($fileLogger ?? null);

try {
    if ($action === 'analyze') {
        $raw = $_POST['raw'] ?? '';
        $parent = isset($_POST['parent_id']) ? (int)$_POST['parent_id'] : 999;
        $forceLocal = isset($_POST['force_local_parse']) && ($_POST['force_local_parse'] === '1' || $_POST['force_local_parse'] === 'true');
        $result = $importer->parseRawImport($raw, $parent, ['force_local_parse' => $forceLocal]);
        echo json_encode($result);
        exit;
    }

    if ($action === 'apply') {
        $itemsJson = $_POST['items'] ?? '[]';
        $items = json_decode($itemsJson, true);
        if (!is_array($items)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'invalid items payload']);
            exit;
        }
        // default table is pages_dashboard, but allow override
        $table = $_POST['table'] ?? 'pages_dashboard';
        // apply to DB
        $res = $importer->applyToDatabase($pdoSys, $table, $items);
        echo json_encode($res);
        exit;
    }

    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'unknown action']);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    exit;
}
