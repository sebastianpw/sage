<?php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';
require __DIR__ . '/VoicePool.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $vp = new VoicePool();
    $models = $vp->listModels();
    echo json_encode($models);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
