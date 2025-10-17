<?php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

require __DIR__ . '/../vendor/autoload.php';

use App\Core\QuickGenManager;

header('Content-Type: application/json; charset=utf-8');

try {
    // Access control is handled in bootstrap() / AccessManager::authenticate() already.
    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
        exit;
    }

    // sessionId is required (identifier for the specially prepared generator chat session)
    $sessionId = $_REQUEST['sessionId'] ?? $_REQUEST['sid'] ?? null;
    if (!$sessionId) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Missing sessionId parameter']);
        exit;
    }

    // Optional model override
    $model = $_REQUEST['model'] ?? null;

    // Collect overrides: everything except 'sessionId' and 'model' will be considered a parameter override
    $overrides = $_REQUEST;
    unset($overrides['sessionId'], $overrides['sid'], $overrides['model'], $overrides['PHPSESSID']);

    // Instantiate manager
    $spw = \App\Core\SpwBase::getInstance();
    $qg = new QuickGenManager($spw->getEntityManager(), $spw->getAIProvider(), $spw->getFileLogger());



$options = [];
if (isset($_REQUEST['temperature'])) { $options['temperature'] = floatval($_REQUEST['temperature']); }
if (isset($_REQUEST['max_tokens'])) { $options['max_tokens'] = intval($_REQUEST['max_tokens']); }
$result = $qg->generateFromSessionId($sessionId, $overrides, $model, $options);

/*
    // Call generator (no persistence will happen)
	$result = $qg->generateFromSessionId($sessionId, $overrides, $model);
 */


    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;

} catch (\Throwable $e) {
    http_response_code(500);
    $err = [
        'ok' => false,
        'error' => $e->getMessage(),
        'trace' => method_exists($e, 'getTraceAsString') ? $e->getTraceAsString() : null
    ];
    // log server-side
    if (isset($spw) && method_exists($spw, 'getFileLogger')) {
        $spw->getFileLogger()->error(['QuickGen endpoint error' => ['message' => $e->getMessage()]]);
    }
    echo json_encode($err, JSON_UNESCAPED_UNICODE);
    exit;
}
