<?php
// bash/splash_run.php
require_once __DIR__ . '/../public/bootstrap.php';
require_once __DIR__ . '/../public/env_locals.php';
require_once __DIR__ . '/../src/Service/GeneratorRepository.php';
require_once __DIR__ . '/../src/Service/SplashEngine.php';

use App\Service\SplashEngine;

function argOpt(string $name, $default = null) {
    foreach ($GLOBALS['argv'] as $arg) {
        if (str_starts_with($arg, "--{$name}=")) return substr($arg, strlen("--{$name}="));
    }
    return $default;
}

$userId = (int)argOpt('user_id', 1);
$templateId = (int)argOpt('template_id', 0);
$sourceFile = argOpt('source_file');
$sourceText = argOpt('source_text', '');

if ($templateId <= 0) {
    fwrite(STDERR, "Usage: php splash_run.php --template_id=N [--source_text=\"...\"] [--source_file=path]\n");
    exit(1);
}

if ($sourceFile && file_exists($sourceFile)) $sourceText = file_get_contents($sourceFile);

echo "[splash_run] Starting Splash Writers' Room for template {$templateId}...\n";

$engine = new SplashEngine();
try {
    $run = $engine->startRun($userId, $templateId, $sourceText, "CLI Run");
    while ($run['status'] === 'running') {
        echo "   -> Executing next role...\n";
        $run = $engine->runNextRole($userId, $run['id']);
    }
} catch (Exception $e) {
    fwrite(STDERR, "[splash_run] ERROR: " . $e->getMessage() . "\n");
    exit(1);
}

echo "[splash_run] Run #{$run['id']} Finished.\n";
echo "   Status: {$run['status']}\n";

if ($run['status'] === 'failed') {
    echo "   Error: {$run['last_error']}\n";
    exit(1);
}

exit(0);
