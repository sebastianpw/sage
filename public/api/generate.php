<?php
// public/api/generate.php

require_once __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../env_locals.php';
require __DIR__ . '/../../vendor/autoload.php';

use App\Entity\GeneratorConfig;
use App\Service\GeneratorService;
use App\Service\Schema\SchemaValidator;
use App\Service\Schema\ResponseNormalizer;

header('Content-Type: application/json; charset=utf-8');

try {
    // Auth check
    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
        exit;
    }

    // Required: config_id
    $configId = $_REQUEST['config_id'] ?? $_REQUEST['configId'] ?? null;
    if (!$configId) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Missing config_id parameter']);
        exit;
    }

    // Load config
    $spw = \App\Core\SpwBase::getInstance();
    $em = $spw->getEntityManager();
    
    $config = $em->getRepository(GeneratorConfig::class)
        ->findOneBy(['configId' => $configId, 'active' => true]);

    if (!$config) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => "Generator config '{$configId}' not found"]);
        exit;
    }

    // Collect parameters (everything except config_id, model, temperature, max_tokens)
    $params = $_REQUEST;
    unset($params['config_id'], $params['configId'], $params['PHPSESSID']);

    // Extract AI options
    $aiOptions = [];
    if (isset($params['temperature'])) {
        $aiOptions['temperature'] = (float)$params['temperature'];
        unset($params['temperature']);
    }
    if (isset($params['max_tokens'])) {
        $aiOptions['max_tokens'] = (int)$params['max_tokens'];
        unset($params['max_tokens']);
    }
    if (isset($params['model'])) {
        // Allow per-request model override
        $config->setModel($params['model']);
        unset($params['model']);
    }

    // Generate
    $service = new GeneratorService(
        $spw->getAIProvider(),
        new SchemaValidator(),
        new ResponseNormalizer(),
        $spw->getFileLogger()
    );

    $result = $service->generate($config, $params, $aiOptions);

    echo json_encode($result->toArray(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;

} catch (\Throwable $e) {
    http_response_code(500);
    $err = [
        'ok' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ];
    
    if (isset($spw)) {
        $spw->getFileLogger()->error(['Generator API error' => ['message' => $e->getMessage()]]);
    }
    
    echo json_encode($err, JSON_UNESCAPED_UNICODE);
    exit;
}
