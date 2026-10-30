<?php
// bash/wtf_advance_runs.php
// ─────────────────────────────────────────────────────────────────────────────
// WTF — Work The Flow
// CLI entry point meant to be run periodically BY THE EXISTING PHP SCHEDULER
// (as a scheduled_tasks row with a short schedule_interval, e.g. 15-30s) —
// NOT a new daemon, NOT a new process-management mechanism. This script does
// one pass and exits, exactly like your other PHP CLI jobs.
//
// What it does: for every wtf_runs row with status='running' AND
// auto_advance=1, call WorkflowExecutor::advance() once. That's it.
//
// v3 CHANGE: WorkflowExecutor no longer takes $pdo as a constructor
// parameter — it pulls it from the global $spw singleton internally. This
// script must still require bootstrap.php + env_locals.php first (as
// before) so that singleton exists, but the constructor call itself is
// now just `new WorkflowExecutor()`. This also fixes a real bug: the
// previous version of this file called `new WorkflowExecutor($pdo)` with
// no $spw, which silently meant HandlerRegistry never registered the
// `wrish_run` handler for this scheduled sweep — so any workflow run
// containing a wrish_run step would have thrown "no handler registered
// for handler_key 'wrish_run'" the moment this scheduled task tried to
// advance it.
//
// Suggested scheduled_tasks row (create via Scheduler Forge or SQL):
//   name:              WTF — Advance Auto-Advance Runs
//   script_path:       php bash/wtf_advance_runs.php
//   schedule_interval: 20   (seconds)
//   require_lock:      1
//   lock_scope:        global   (only one instance of this sweep at a time)
//   max_concurrent_runs: 1
// ─────────────────────────────────────────────────────────────────────────────

require_once __DIR__ . '/../public/bootstrap.php';
require_once __DIR__ . '/../public/env_locals.php';
require_once __DIR__ . '/../src/Wtf/StepHandlerInterface.php';
require_once __DIR__ . '/../src/Wtf/Handlers/ManualHandler.php';
require_once __DIR__ . '/../src/Wtf/Handlers/NoopDelayHandler.php';
require_once __DIR__ . '/../src/Wtf/Handlers/SchedulerTaskHandler.php';
require_once __DIR__ . '/../src/Service/GeneratorRepository.php';
require_once __DIR__ . '/../src/Service/WrishEngine.php';
require_once __DIR__ . '/../src/Wtf/Handlers/WrishRunHandler.php';
require_once __DIR__ . '/../src/Wtf/HandlerRegistry.php';
require_once __DIR__ . '/../src/Wtf/WorkflowExecutor.php';

use App\Wtf\WorkflowExecutor;

global $spw;
$pdo = $spw->getPDO();

$executor = new WorkflowExecutor();

$stmt = $pdo->query("
    SELECT id, user_id, title FROM wtf_runs
    WHERE status = 'running' AND auto_advance = 1
    ORDER BY updated_at ASC
");
$runs = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($runs)) {
    echo "[wtf_advance_runs] No auto-advance running runs found.\n";
    exit(0);
}

echo "[wtf_advance_runs] Found " . count($runs) . " auto-advance run(s) to check.\n";

$advanced = 0;
$errored = 0;

foreach ($runs as $run) {
    $runId = (int)$run['id'];
    $userId = (int)$run['user_id'];
    try {
        $result = $executor->advance($userId, $runId);
        $status = $result['status'] ?? 'unknown';
        echo "[wtf_advance_runs] Run #{$runId} ('{$run['title']}') advanced — now status='{$status}'.\n";
        $advanced++;
    } catch (Exception $e) {
        echo "[wtf_advance_runs] ERROR advancing run #{$runId}: " . $e->getMessage() . "\n";
        $errored++;
    }
}

echo "[wtf_advance_runs] Done. Advanced: {$advanced}, Errors: {$errored}.\n";
exit($errored > 0 ? 1 : 0);
