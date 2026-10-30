<?php
// bash/sqlish_run.php
// ─────────────────────────────────────────────────────────────────────────────
// SQLISH — Headless CLI Runner
//
// Drives SqlishEngine::runOnce() from the command line without the UI.
//
// Usage:
//   php bash/sqlish_run.php --template_id=X --source_file=path/to/text.txt
//   php bash/sqlish_run.php --template_id=X --source_text="Raw lore here..."
// ─────────────────────────────────────────────────────────────────────────────
require_once __DIR__ . '/../public/bootstrap.php';
require_once __DIR__ . '/../public/env_locals.php';
require_once __DIR__ . '/../src/Service/GeneratorRepository.php';
require_once __DIR__ . '/../src/Service/SqlishEngine.php';

use App\Service\SqlishEngine;

function argOpt(string $name, $default = null) {
    foreach ($GLOBALS['argv'] as $arg) {
        if (str_starts_with($arg, "--{$name}=")) {
            return substr($arg, strlen("--{$name}="));
        }
    }
    return $default;
}

const DEFAULT_CLI_USER_ID = 1;

$userId = (int)argOpt('user_id', DEFAULT_CLI_USER_ID);
$templateId = (int)argOpt('template_id', 0);
$sourceFile = argOpt('source_file');
$sourceText = argOpt('source_text', '');
$contextRaw = argOpt('context_json', '{}');

if ($templateId <= 0) {
    fwrite(STDERR, "Usage: php sqlish_run.php --template_id=N [--source_text=\"...\"] [--source_file=path] [--context_json=\"{}\"]\n");
    exit(1);
}

if ($sourceFile && file_exists($sourceFile)) {
    $sourceText = file_get_contents($sourceFile);
}

$context = json_decode($contextRaw, true) ?: [];

echo "[sqlish_run] Starting extraction for template {$templateId}...\n";

$engine = new SqlishEngine();

try {
    $run = $engine->runOnce($userId, $templateId, $sourceText, $context, "CLI Run");
} catch (Exception $e) {
    fwrite(STDERR, "[sqlish_run] ERROR: " . $e->getMessage() . "\n");
    exit(1);
}

echo "[sqlish_run] Run #{$run['id']} Finished.\n";
echo "   Status: {$run['status']}\n";
echo "   Written: {$run['items_written']}\n";
echo "   Failed: {$run['items_failed']}\n";

if ($run['status'] === 'failed') {
    echo "   Error: {$run['last_error']}\n";
    exit(1);
}

exit(0);
