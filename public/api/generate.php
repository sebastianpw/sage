<?php
// /api/generate.php
require_once __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../env_locals.php';

use App\Entity\GeneratorConfig;
use App\Service\GeneratorService;
use App\Service\Schema\SchemaValidator;
use App\Service\Schema\ResponseNormalizer;

header('Content-Type: application/json; charset=utf-8');

try {
    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
        exit;
    }

    // Get request data
    $input = $_SERVER['REQUEST_METHOD'] === 'POST' 
        ? json_decode(file_get_contents('php://input'), true) ?? $_POST
        : $_GET;

    $configId = $input['config_id'] ?? null;
    if (!$configId) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Missing config_id']);
        exit;
    }

    $em = $spw->getEntityManager();
    $repo = $em->getRepository(GeneratorConfig::class);
    $config = $repo->findOneBy(['configId' => $configId, 'userId' => $userId, 'active' => true]);

    if (!$config) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Generator not found or inactive']);
        exit;
    }

    // Info mode - return config structure only
    if (isset($input['_info'])) {
        echo json_encode([
            'ok' => true,
            'config' => [
                'config_id' => $config->getConfigId(),
                'title' => $config->getTitle(),
                'model' => $config->getModel(),
                'parameters' => $config->getParameters(),
                'output_schema' => $config->getOutputSchema(),
                'uses_oracle' => $config->getOracleConfig() !== null,
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Generate mode
    $validator = new SchemaValidator();
    $normalizer = new ResponseNormalizer();
    $service = new GeneratorService(
        $spw->getAIProvider(),
        $validator,
        $normalizer,
        $spw->getFileLogger()
    );

    // Extract parameters (remove internal keys)
    $params = $input;
    unset($params['config_id'], $params['_info']);

    // AI options
    $aiOptions = [];
    if (isset($params['temperature'])) {
        $aiOptions['temperature'] = (float)$params['temperature'];
        unset($params['temperature']);
    }
    if (isset($params['max_tokens'])) {
        $aiOptions['max_tokens'] = (int)$params['max_tokens'];
        unset($params['max_tokens']);
    }

    $result = $service->generate($config, $params, $aiOptions);

    echo json_encode($result->toArray(), JSON_UNESCAPED_UNICODE);

} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_UNESCAPED_UNICODE);
}
