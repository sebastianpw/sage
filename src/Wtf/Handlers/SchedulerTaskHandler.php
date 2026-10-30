<?php
// src/Wtf/Handlers/SchedulerTaskHandler.php
// ─────────────────────────────────────────────────────────────────────────────
// WTF — Work The Flow
// Handler: 'scheduler_task'. Bridges a WTF step to the existing SAGE
// PHP scheduler (scheduled_tasks / task_runs / task_locks).
//
// ── CONTEXT INTERPOLATION (The "Wrench" feature) ──
// You can dynamically pass data from previous WTF steps into the CLI args of
// your scheduler tasks using curly braces: {{context_key}}
//
// Example: If Sqlish wrote to `writeback_context_key`: "my_sketches",
// it creates an array: {"min_id": 1, "max_id": 3, "ids": [1,2,3]}.
// You can set your Scheduler Step args to:
//    --target-ids={{my_sketches.ids}} --min={{my_sketches.min_id}}
//
// The handler will resolve the dot-notation, convert arrays to comma-separated
// lists, and safely update the scheduled_tasks args before triggering it.
// ─────────────────────────────────────────────────────────────────────────────
namespace App\Wtf\Handlers;

use App\Wtf\StepHandlerInterface;
use PDO;

class SchedulerTaskHandler implements StepHandlerInterface
{
    private PDO $pdo;

    public function __construct()
    {
        global $spw;
        $this->pdo = $spw->getPDO();
    }

    public function execute(array $step, array $context): array
    {
        $config = $step['config'] ?? [];
        $taskId = (int)($config['scheduled_task_id'] ?? 0);
        $waitMode = $config['wait_mode'] ?? 'trigger_new';
        $argsOverride = $config['args'] ?? null;

        if ($taskId <= 0) {
            return ['success' => false, 'error' => "scheduler_task step config missing 'scheduled_task_id'."];
        }

        $taskStmt = $this->pdo->prepare("SELECT id, name FROM scheduled_tasks WHERE id = ?");
        $taskStmt->execute([$taskId]);
        $task = $taskStmt->fetch(PDO::FETCH_ASSOC);
        if (!$task) {
            return ['success' => false, 'error' => "scheduled_tasks row {$taskId} not found."];
        }

        $pollState = $step['result'] ?? [];
        $watchRunId = $pollState['watch_run_id'] ?? null;
        $baselineRunId = $pollState['baseline_run_id'] ?? null;

        if ($waitMode === 'watch_latest') {
            $run = $this->getLatestRun($taskId);
            if (!$run) {
                return ['success' => false, 'error' => "No task_runs found for scheduled_tasks {$taskId} to watch."];
            }
            return $this->mapRunToResult($task, $run);
        }

        // wait_mode === 'trigger_new' (default)
        if ($watchRunId === null && $baselineRunId === null) {
            $baseline = $this->getMaxRunId($taskId);
            
            // Interpolate dynamic context variables into args using {{key.subkey}}
            if ($argsOverride !== null) {
                $argsOverride = preg_replace_callback('/\{\{([^}]+)\}\}/', function($matches) use ($context) {
                    $keys = explode('.', trim($matches[1]));
                    $val = $context;
                    foreach ($keys as $k) {
                        if (is_array($val) && array_key_exists($k, $val)) {
                            $val = $val[$k];
                        } else {
                            return ''; // Key not found, replace with empty string
                        }
                    }
                    if (is_array($val)) return implode(',', $val);
                    return (string)$val;
                }, $argsOverride);

                $this->pdo->prepare("UPDATE scheduled_tasks SET args = ?, run_now = 1 WHERE id = ?")->execute([$argsOverride, $taskId]);
            } else {
                $this->pdo->prepare("UPDATE scheduled_tasks SET run_now = 1 WHERE id = ?")->execute([$taskId]);
            }

            return [
                'in_progress' => true,
                'summary' => "Triggered scheduler task '{$task['name']}' (id {$taskId}), waiting for it to be picked up…",
                'data' => [
                    'baseline_run_id' => $baseline, 
                    'watch_run_id' => null,
                    'interpolated_args' => $argsOverride // Save for debugging visibility
                ]
            ];
        }

        if ($watchRunId === null) {
            $newRun = $this->getNewestRunAfter($taskId, (int)$baselineRunId);
            if (!$newRun) {
                return [
                    'in_progress' => true,
                    'summary' => "Waiting for scheduler to pick up '{$task['name']}'…",
                    'data' => ['baseline_run_id' => $baselineRunId, 'watch_run_id' => null]
                ];
            }
            $watchRunId = (int)$newRun['id'];
        }

        $run = $this->getRunById((int)$watchRunId);
        if (!$run) {
            return ['success' => false, 'error' => "task_runs row {$watchRunId} disappeared while watching."];
        }

        $result = $this->mapRunToResult($task, $run);
        if (!empty($result['in_progress'])) {
            $result['data']['baseline_run_id'] = $baselineRunId;
            $result['data']['watch_run_id'] = $watchRunId;
        }
        return $result;
    }

    private function mapRunToResult(array $task, array $run): array
    {
        $status = $run['status'];
        $runId = (int)$run['id'];

        if (in_array($status, ['pending', 'running'], true)) {
            return [
                'in_progress' => true,
                'summary' => "Scheduler task '{$task['name']}' run #{$runId} is {$status} (pid: " . ($run['pid'] ?? '—') . ')…',
                'data' => [],
            ];
        }

        if ($status === 'completed') {
            return [
                'success' => true,
                'summary' => "Scheduler task '{$task['name']}' run #{$runId} completed (exit code " . ($run['exit_code'] ?? '0') . ').',
                'data' => [
                    'task_run_id' => $runId,
                    'exit_code' => $run['exit_code'],
                    'stdout_log' => $run['stdout_log'],
                    'stderr_log' => $run['stderr_log'],
                ],
            ];
        }

        return [
            'success' => false,
            'error' => "Scheduler task '{$task['name']}' run #{$runId} ended with status '{$status}' (exit code " . ($run['exit_code'] ?? 'n/a') . '). Check its logs in Scheduler Forge.',
            'data' => [
                'task_run_id' => $runId,
                'exit_code' => $run['exit_code'],
                'stdout_log' => $run['stdout_log'],
                'stderr_log' => $run['stderr_log'],
            ],
        ];
    }

    private function getMaxRunId(int $taskId): int
    {
        $stmt = $this->pdo->prepare("SELECT COALESCE(MAX(id), 0) FROM task_runs WHERE task_id = ?");
        $stmt->execute([$taskId]);
        return (int)$stmt->fetchColumn();
    }

    private function getNewestRunAfter(int $taskId, int $afterId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, pid, status, exit_code, stdout_log, stderr_log, started_at, finished_at
            FROM task_runs WHERE task_id = ? AND id > ? ORDER BY id ASC LIMIT 1
        ");
        $stmt->execute([$taskId, $afterId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function getRunById(int $runId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, pid, status, exit_code, stdout_log, stderr_log, started_at, finished_at
            FROM task_runs WHERE id = ?
        ");
        $stmt->execute([$runId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function getLatestRun(int $taskId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, pid, status, exit_code, stdout_log, stderr_log, started_at, finished_at
            FROM task_runs WHERE task_id = ? ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([$taskId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}