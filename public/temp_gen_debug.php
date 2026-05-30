<?php
// temp_gen_debug.php - diagnostic generation tester
// Place this file in your public/ folder and run: php -d display_errors=1 temp_gen_debug.php

// 1) Force full error reporting
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// 2) Basic header
echo "\n=== TEMP GEN DEBUG START ===\n";
echo "PHP SAPI: " . php_sapi_name() . "\n";
echo "PHP Version: " . phpversion() . "\n";
echo "CWD: " . getcwd() . "\n";
echo "Script path: " . __FILE__ . "\n\n";

// 3) Quick syntax check (prints nothing on success) - optional, but informative

// 4) Check bootstrap files exist
$bootstrap = __DIR__ . '/bootstrap.php';
$envlocals = __DIR__ . '/env_locals.php';
echo "Looking for bootstrap: $bootstrap => " . (file_exists($bootstrap) ? "FOUND" : "MISSING") . "\n";
echo "Looking for env_locals: $envlocals => " . (file_exists($envlocals) ? "FOUND" : "MISSING") . "\n\n";



function sanitizePrompt(string $s): string
{
    // Ensure valid UTF-8
    $s = mb_convert_encoding($s, 'UTF-8', 'UTF-8');

    // Remove BOM
    $s = preg_replace('/\x{FEFF}/u', '', $s);

    // Remove zero-width chars
    $s = preg_replace('/[\x{200B}-\x{200D}\x{2060}]/u', '', $s);

    // Replace non-breaking spaces with normal spaces
    $s = str_replace("\xC2\xA0", ' ', $s);

    // Remove ASCII control chars except newline/tab
    $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $s);

    // Normalize quotes/dashes (optional but recommended)
    $s = str_replace(
        ["“","”","‘","’","—","–","…"],
        ['"','"',"'", "'", '--','-','...'],
        $s
    );

    return trim($s);
}



try {
    // 5) Require the app bootstrap (wrap in try/catch)
    echo "Including bootstrap.php ...\n";
    require_once $bootstrap;

    echo "Including env_locals.php ...\n";
    require_once $envlocals;

    // 6) Confirm $spw is provided by your bootstrap
    if (!isset($spw)) {
        echo "NOTICE: \$spw is NOT set after bootstrap. Trying to inspect globals...\n";
        // Attempt to inspect commonly available variables
        $vars = get_defined_vars();
        $candidates = array_filter(array_keys($vars), function($k){
            return in_array($k, ['spw','$spw']) || stripos($k, 'spw') !== false;
        });
        echo "Candidate global keys containing 'spw': " . implode(', ', $candidates) . "\n";
        // Continue but expect failures
    } else {
        echo "\$spw FOUND. Inspecting basic methods...\n";
        echo " - getEntityManager exists? " . (method_exists($spw, 'getEntityManager') ? "yes" : "no") . "\n";
        echo " - getFileLogger exists? " . (method_exists($spw, 'getFileLogger') ? "yes" : "no") . "\n";
        echo " - getAIProvider exists? " . (method_exists($spw, 'getAIProvider') ? "yes" : "no") . "\n";
    }

    // 7) Setup services (defensive)
    $em = null;
    if (isset($spw) && method_exists($spw, 'getEntityManager')) {
        $em = $spw->getEntityManager();
    } else {
        echo "Cannot get EntityManager from \$spw. Trying to create minimal DB connection via PDO? (skipping)\n";
        // You can stop here if $spw is missing
    }

    if (!$em) {
        throw new Exception("EntityManager unavailable. Aborting test here.");
    }

    $conn = $em->getConnection();
    $repo = $em->getRepository(App\Entity\GeneratorConfig::class);
    $logger = $spw->getFileLogger();
    $aiProvider = $spw->getAIProvider();
    $validator = new App\Service\Schema\SchemaValidator();
    $normalizer = new App\Service\Schema\ResponseNormalizer();
    $generatorService = new App\Service\GeneratorService($aiProvider, $validator, $normalizer, $logger);

    echo "Services initialized OK.\n";

    // 8) Load a real job from DB (use the next ungenerated one)
    $job = $conn->fetchAssociative("SELECT * FROM rapid_showcase WHERE is_generated = 0 AND is_archived = 0 ORDER BY id ASC LIMIT 1");
    if (!$job) {
        echo "No pending jobs found in rapid_showcase (is_generated = 0 AND is_archived = 0).\n";
        echo "Double-check DB or set a test job and re-run.\n";
        exit(0);
    }
    echo "Found Job ID: {$job['id']} RefCode: {$job['reference_code']} Title: {$job['title']}\n";

    $refCode = $job['reference_code'];
    $title = $job['title'];
    $cat = $job['category'];
    $context = "TITLE: $title\nCATEGORY: $cat\n\nSCENARIO:\n" . $job['description_prompt'];

    echo "Context length: " . strlen($context) . " bytes\n";

    // 9) Find the generator config used by CLI (diagnose same config)
    $configId = $job['generator_config_id'] ?: '446437576e785bbf3d188624dd9794eb';
    $descConfig = $repo->findOneBy(['configId' => $configId]);
    if (!$descConfig) {
        echo "Assigned config $configId not found. Trying fallback config id.\n";
        $descConfig = $repo->findOneBy(['configId' => '446437576e785bbf3d188624dd9794eb']);
    }
    if (!$descConfig) {
        throw new Exception("No generator config available");
    }
    echo "Using Generator Config: " . $descConfig->getTitle() . " (id: " . $configId . ")\n";



$context = sanitizePrompt($context);



    // 10) Run the generation and dump everything
    echo "Calling generatorService->generate() ...\n";
    $descResult = $generatorService->generate($descConfig, [
        'entity_name' => $context,
        'random_seed' => rand(1, 999999)
    ]);

    echo "Generator result object type: " . gettype($descResult) . "\n";

    if (is_object($descResult)) {
        if (method_exists($descResult, 'isSuccess')) {
            echo "isSuccess(): " . ($descResult->isSuccess() ? "TRUE" : "FALSE") . "\n";
        }
        if (method_exists($descResult, 'getData')) {
            $data = $descResult->getData();
            echo "--- getData() (first 4000 chars) ---\n";
            if (is_string($data)) {
                echo substr($data, 0, 4000) . (strlen($data) > 4000 ? "\n...[truncated]\n" : "\n");
            } else {
                echo substr(print_r($data, true), 0, 4000) . (strlen(print_r($data, true)) > 4000 ? "\n...[truncated]\n" : "\n");
            }
        }
        if (method_exists($descResult, 'getError')) {
            echo "--- getError() ---\n";
            var_dump($descResult->getError());
        }
        echo "--- Full dump (short) ---\n";
        $dump = print_r($descResult, true);
        echo substr($dump, 0, 4000) . (strlen($dump) > 4000 ? "\n...[truncated]\n" : "\n");
    } else {
        echo "Result is not an object. Dump:\n";
        var_dump($descResult);
    }

    echo "\n=== TEMP GEN DEBUG END ===\n";

} catch (Throwable $t) {
    echo "\nEXCEPTION: " . get_class($t) . " - " . $t->getMessage() . "\n";
    echo $t->getTraceAsString() . "\n";
    echo "\n=== TEMP GEN DEBUG ABORT ===\n";
    exit(1);
}
