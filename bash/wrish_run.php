<?php
// bash/wrish_run.php
// ─────────────────────────────────────────────────────────────────────────────
// WRISH — Headless CLI Runner
//
// Drives WrishEngine::runStep() for a session with no browser involved.
//
// Usage:
//   php bash/wrish_run.php --session_id=abc-123
//   php bash/wrish_run.php --session_id=abc-123 --loop
//   php bash/wrish_run.php --session_id=abc-123 --loop --max_steps=40
//   php bash/wrish_run.php --session_id=abc-123 --new --message="Plan a full season..."
//   php bash/wrish_run.php --session_id=abc-123 --user_id=2   (only if you ever add a 2nd user)
// ─────────────────────────────────────────────────────────────────────────────

require_once __DIR__ . '/../public/bootstrap.php';
require_once __DIR__ . '/../public/env_locals.php';
require_once __DIR__ . '/../src/Service/GeneratorRepository.php';
require_once __DIR__ . '/../src/Service/WrishEngine.php';

use App\Service\WrishEngine;

function argOpt(string $name, $default = null) {
    foreach ($GLOBALS['argv'] as $arg) {
        if (str_starts_with($arg, "--{$name}=")) {
            return substr($arg, strlen("--{$name}="));
        }
    }
    return $default;
}
function argFlag(string $name): bool {
    return in_array("--{$name}", $GLOBALS['argv'], true);
}

const DEFAULT_CLI_USER_ID = 1; // your admin id — same pattern as cli_rapid_gen.php

$userId = (int)argOpt('user_id', DEFAULT_CLI_USER_ID); // override only if ever needed
$sessionId = (string)argOpt('session_id', '');
$isNew = argFlag('new');
$loop = argFlag('loop');
$initialMessage = argOpt('message', null);
$maxSteps = argOpt('max_steps', null);
$autoAdvance = !argFlag('no_auto_advance'); // default true for headless use — a human isn't here to click "proceed"

if (!$sessionId) {
    fwrite(STDERR, "Usage: php wrish_run.php --session_id=SID [--user_id=N] [--new --message=\"...\"] [--loop] [--max_steps=N] [--no_auto_advance]\n");
    exit(1);
}

$engine = new WrishEngine();
global $spw;
$pdo = $spw->getPDO();

// Ensure a chat session row exists if this is a brand new session (the UI
// normally creates this on first send(); a headless first-call needs the
// same row to exist for wrish_conversation's FK-by-convention to make sense).
if ($isNew) {
    $stmt = $pdo->prepare("
        INSERT INTO wrish_chat_sessions (session_id, user_id, title, updated_at)
        VALUES (?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE updated_at = NOW()
    ");
    $stmt->execute([$sessionId, $userId, $initialMessage ? mb_substr($initialMessage, 0, 40) : 'Headless Run']);
}

$run = $engine->startRun($userId, $sessionId, $autoAdvance, $maxSteps !== null ? (int)$maxSteps : null);
echo "[wrish_run] run_id={$run['id']} status={$run['status']} steps_completed={$run['steps_completed']}/{$run['max_steps']}\n";

$settings = [
    'depth' => 'standard',
    'frictionIntensity' => 'medium',
    'autoAdvance' => $autoAdvance,
    'sessionTopic' => '',
    'contextExtra' => '',
];

$stepCount = 0;
$firstCall = true;

do {
    $message = ($firstCall && $initialMessage) ? $initialMessage : null;
    $firstCall = false;

    try {
        $result = $engine->runStep($userId, $sessionId, $message, $settings, (int)$run['id']);
    } catch (Exception $e) {
        fwrite(STDERR, "[wrish_run] ERROR: " . $e->getMessage() . "\n");
        exit(1);
    }

    $stepCount++;
    $run = $result['run'] ?? $engine->getRun($userId, (int)$run['id']);

    echo "[wrish_run] turn_type={$result['turn_type']} run_status={$run['status']} steps={$run['steps_completed']}/{$run['max_steps']}\n";

    if ($result['turn_type'] === 'halted') {
        fwrite(STDERR, "[wrish_run] Run halted: " . ($run['last_error'] ?? 'see wrish_extraction_log') . "\n");
        exit(2);
    }

    if (!in_array($run['status'], ['running'], true)) {
        break; // done, cancelled, paused, or failed_extraction
    }

} while ($loop);

echo "[wrish_run] Finished. Final status: {$run['status']} ({$run['steps_completed']} steps).\n";
exit($run['status'] === 'done' ? 0 : ($run['status'] === 'running' ? 0 : 1));
