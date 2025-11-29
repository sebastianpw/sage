<?php
require_once __DIR__ . '/bootstrap.php';
require      __DIR__ . '/env_locals.php';

use App\Oracle\Bloom;

header('Content-Type: application/json');

try {
    // Input validation and sanitization
    $dictionaryIdsStr = $_REQUEST['dictionary_ids'] ?? '';
    if (empty($dictionaryIdsStr)) {
        throw new \InvalidArgumentException('Parameter "dictionary_ids" is required.');
    }
    $dictionaryIds = array_map('intval', explode(',', $dictionaryIdsStr));
    
    $numWords = isset($_REQUEST['num_words']) ? (int)$_REQUEST['num_words'] : 200;
    $errorRate = isset($_REQUEST['error_rate']) ? (float)$_REQUEST['error_rate'] : 0.01;
    $seed = isset($_REQUEST['seed']) && !empty($_REQUEST['seed']) ? (int)$_REQUEST['seed'] : null;

    if ($numWords <= 0 || $numWords > 5000) {
        throw new \InvalidArgumentException('Parameter "num_words" must be between 1 and 5000.');
    }
    if ($errorRate <= 0 || $errorRate >= 1) {
        throw new \InvalidArgumentException('Parameter "error_rate" must be between 0 and 1.');
    }

    $oracle = new Bloom(); // Assumes default pyapi URL is fine
    $hint = $oracle->generateHint($dictionaryIds, $numWords, $errorRate, $seed);

    echo json_encode(['success' => true, 'data' => $hint]);

} catch (\InvalidArgumentException $e) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (\Exception $e) {
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

