<?php
namespace App\Core;

use PDO;

class TaskRunnerScheduler extends AbstractScheduler
{
    protected int $sleepInterval = 1;
    protected TaskLockManager $lockManager;
    protected int $maxTaskRunMinutes = 60;

    public function __construct(int $sleepInterval = 1)
    {
        parent::__construct();
        $this->sleepInterval = $sleepInterval;
        $this->lockManager = new TaskLockManager();
    }

    public function run(): void
    {
        $spw = $this->spw;
        $logger = $spw->getSchedulerFileLogger();
        $logger->log('INFO', ["Scheduler started. Sleep interval: {$this->sleepInterval} seconds"]);

        $heartbeat = new TaskHeartbeat();

        while (true) {
            try {
                // Update heartbeat first
                $heartbeat->updateSchedulerHeartbeat();

                // Check for stale tasks (but DON'T do lock cleanup here - let tracker handle it)
                $heartbeat->checkStaleTasks($this->maxTaskRunMinutes);

                // Process due tasks
                $this->processDueTasks();

                // WTF — Work The Flow: advance any running+auto_advance runs by one
                // step each, once per tick. This intentionally lives here instead of
                // as a scheduled_tasks row: it's a cheap DB-only nudge (no process,
                // no PID, no lock, no per-run log file needed), and it rides the
                // heartbeat loop that already runs unconditionally every tick with
                // zero log noise of its own. If a WTF step happens to trigger a real
                // scheduler_task under the hood, that sub-task still gets its own
                // task_runs row and log files exactly as before — nothing about
                // that path changed. See App\Wtf\WorkflowExecutor::advanceAllAutoRuns().
                $this->advanceWtfRuns();

            } catch (\Throwable $e) {
                $logger->log('ERROR', [
                    "Scheduler loop error",
                    "message" => $e->getMessage(),
                    "trace" => $e->getTraceAsString(),
                ]);
            }

            sleep($this->sleepInterval);
        }
    }

    /**
     * Advance WTF auto-advance runs. Deliberately silent on the common case
     * (nothing to do) — only genuine state transitions inside WorkflowExecutor
     * (step_started, step_done, step_failed, run_paused, run_completed, etc.)
     * produce a wtf_run_events row, and those are visible in the WTF UI itself,
     * not in the scheduler's own log file. This method never writes to the
     * scheduler log — that's the whole point of moving this off a
     * scheduled_tasks row in the first place.
     */
    protected function advanceWtfRuns(): void
    {
        static $classesLoaded = false;

        if (!$classesLoaded) {
            // Lazily require WTF's classes the first time this fires, so a
            // fresh install that hasn't set up WTF at all doesn't pay any
            // cost or risk any fatal include error on every scheduler tick.
            $base = dirname(__DIR__, 2); // project root (src/Core -> project root)
            $files = [
                $base . '/src/Wtf/StepHandlerInterface.php',
                $base . '/src/Wtf/Handlers/ManualHandler.php',
                $base . '/src/Wtf/Handlers/NoopDelayHandler.php',
                $base . '/src/Wtf/Handlers/SchedulerTaskHandler.php',
                $base . '/src/Service/GeneratorRepository.php',
                $base . '/src/Service/WrishEngine.php',
                $base . '/src/Wtf/Handlers/WrishRunHandler.php',
                $base . '/src/Wtf/HandlerRegistry.php',
                $base . '/src/Wtf/WorkflowExecutor.php',
            ];
            foreach ($files as $f) {
                if (file_exists($f)) {
                    require_once $f;
                }
            }
            $classesLoaded = true;
        }

        if (!class_exists('\App\Wtf\WorkflowExecutor')) {
            // WTF not installed on this instance — nothing to do, no error.
            return;
        }

        try {
            $executor = new \App\Wtf\WorkflowExecutor();
            $executor->advanceAllAutoRuns();
        } catch (\Throwable $e) {
            // A genuine problem advancing WTF runs (e.g. a handler class
            // missing) IS worth a log line, since it's not the "nothing to
            // do" common case — but it must never crash the main scheduler
            // loop, since task scheduling is the higher-priority job here.
            $this->spw->getSchedulerFileLogger()->log('ERROR', [
                "WTF advanceAllAutoRuns() failed",
                "message" => $e->getMessage(),
            ]);
        }
    }

    protected function processDueTasks(): void
    {
        $pdo = $this->spw->getPDO();

        $stmt = $pdo->query("
            SELECT 
                id, name, `order`, script_path, args, 
                schedule_time, schedule_interval, schedule_dow, 
                last_run, active, description, created_at, 
                updated_at, run_now,
                max_concurrent_runs, lock_timeout_minutes, 
                require_lock, lock_scope
            FROM scheduled_tasks
            WHERE active = 1 OR run_now = 1
        ");

        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($tasks as $task) {
            if ($this->isTaskDue($task)) {
                $this->runTask($task);
            }
        }
    }

    protected function isTaskDue(array $task): bool
    {
        // Check run_now flag first
        if (!empty($task['run_now'])) {
            return true;
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        // Task must be active
        if ((int)($task['active'] ?? 0) !== 1) {
            return false;
        }

        // Day-of-week check
        $dowAllowed = array_map('intval', explode(',', $task['schedule_dow'] ?? '0,1,2,3,4,5,6'));
        $today = (int)$now->format('w');

        if (!in_array($today, $dowAllowed, true)) {
            return false;
        }

        $lastRun = !empty($task['last_run']) 
            ? new \DateTimeImmutable($task['last_run'], new \DateTimeZone('UTC')) 
            : null;

        // Schedule time check
        if (!empty($task['schedule_time'])) {
            $scheduleTime = \DateTimeImmutable::createFromFormat('H:i:s', $task['schedule_time'], new \DateTimeZone('UTC'));
            $scheduleDateTime = $now->setTime(
                (int)$scheduleTime->format('H'), 
                (int)$scheduleTime->format('i'), 
                (int)$scheduleTime->format('s')
            );

            if ($lastRun === null || $lastRun->format('Y-m-d') < $now->format('Y-m-d')) {
                if ($now >= $scheduleDateTime) {
                    return true;
                }
            }
        }

        // Schedule interval check
        if (!empty($task['schedule_interval'])) {
            $intervalSeconds = (int)$task['schedule_interval'];

            if ($lastRun === null) {
                return true;
            }

            $diffSeconds = $now->getTimestamp() - $lastRun->getTimestamp();

            if ($diffSeconds >= $intervalSeconds) {
                return true;
            }
        }

        return false;
    }

    protected function runTask(array $task): void
    {
        $spw = $this->spw;
        $pdo = $spw->getPDO();
        $logger = $spw->getSchedulerFileLogger();

        $taskId = (int)$task['id'];
        $script = trim($task['script_path'] ?? '');
        $args = trim($task['args'] ?? '');

        if ($script === '') {
            $logger->log('ERROR', ["Task $taskId has no script defined"]);
            return;
        }

        // Resolve script path
        if (!str_starts_with($script, '/')) {
            $script = rtrim($spw->getProjectPath(), '/') . '/' . ltrim($script, '/');
        }

        $realScript = realpath($script);
        if ($realScript === false || !file_exists($realScript)) {
            $logger->log('ERROR', ["Task $taskId script not found: $script"]);
            return;
        }

        // Parse entity information for entity-scoped locks
        $entityInfo = $this->lockManager->parseEntityFromArgs($args);
        $entityType = $entityInfo['entity_type'];
        $entityId = $entityInfo['entity_id'];

        // FIX: Create task_runs entry FIRST, then acquire lock with proper runId
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $startedAt = $now->format('Y-m-d H:i:s');

        // Prepare log files
        $logDir = $spw->getProjectPath() . '/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }

        // Create temporary run entry to get runId
        $stmt = $pdo->prepare("
            INSERT INTO task_runs 
            (task_id, started_at, status, entity_type, entity_id)
            VALUES 
            (:task_id, :started_at, 'pending', :entity_type, :entity_id)
        ");
        $stmt->execute([
            ':task_id' => $taskId,
            ':started_at' => $startedAt,
            ':entity_type' => $entityType,
            ':entity_id' => $entityId
        ]);
        $runId = (int)$pdo->lastInsertId();

        // NOW acquire lock with proper runId
        $lock = $this->lockManager->acquireLock(
            $taskId,
            $entityType,
            $entityId,
            $runId  // FIX: Pass actual runId
        );

        if ($lock === null) {
            // Failed to acquire lock - delete the pending run
            $stmt = $pdo->prepare("DELETE FROM task_runs WHERE id = :id");
            $stmt->execute([':id' => $runId]);
            
            $logger->log('WARNING', [
                "Task {$task['name']} skipped - could not acquire lock",
                "entity_type" => $entityType,
                "entity_id" => $entityId,
                "run_id" => $runId
            ]);
            return;
        }

        // Update run with lock information
        $stmt = $pdo->prepare("
            UPDATE task_runs 
            SET lock_id = :lock_id, lock_owner_token = :lock_owner_token
            WHERE id = :id
        ");
        $stmt->execute([
            ':lock_id' => $lock['lock_id'] ?? null,
            ':lock_owner_token' => $lock['owner_token'] ?? null,
            ':id' => $runId
        ]);

        // Resolve inline PHP calls in args
        if (str_contains($args, '$(php ')) {
            $args = preg_replace_callback(
                '/\$\((php [^)]+)\)/',
                fn($matches) => trim(shell_exec($matches[1])),
                $args
            );
        }

        // Setup log files using runId for uniqueness
        $stdoutLog = "$logDir/task_run_{$runId}_out.log";
        $stderrLog = "$logDir/task_run_{$runId}_err.log";
        $exitCodeLog = "$logDir/task_run_{$runId}_exit.log";

        // Update task_runs with log paths
        $stmt = $pdo->prepare("
            UPDATE task_runs 
            SET stdout_log = :stdout_log, stderr_log = :stderr_log
            WHERE id = :id
        ");
        $stmt->execute([
            ':stdout_log' => $stdoutLog,
            ':stderr_log' => $stderrLog,
            ':id' => $runId
        ]);

        // Build command to capture exit code
        $cmdArray = array_merge([$realScript], $args === '' ? [] : preg_split('/\s+/', $args));
        $cmd = implode(' ', array_map('escapeshellarg', $cmdArray));
        $wrappedCmd = "($cmd); echo \$? > " . escapeshellarg($exitCodeLog);
        $fullCmd = "nohup bash -c " . escapeshellarg($wrappedCmd) . " > " . escapeshellarg($stdoutLog) . " 2> " . escapeshellarg($stderrLog) . " & echo $!";

        $logger->log('INFO', [
            "Running Task {$task['name']}",
            "command" => $fullCmd,
            "run_id" => $runId,
            "lock_id" => $lock['lock_id'] ?? 'none',
            "owner_token" => $lock['owner_token'] ?? null
        ]);

        // Execute and get PID
        $pid = (int)shell_exec($fullCmd);

        if ($pid <= 0) {
            $logger->log('ERROR', [
                "Failed to start task - no PID",
                "task_name" => $task['name'],
                "run_id" => $runId
            ]);
            
            // Clean up failed run
            $stmt = $pdo->prepare("UPDATE task_runs SET status = 'failed', exit_code = -1 WHERE id = :id");
            $stmt->execute([':id' => $runId]);
            
            // Release lock
            if (isset($lock['lock_id'])) {
                $this->lockManager->releaseLock($lock['lock_id'], true);
            }
            return;
        }

        $finishedAt = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        // Update task_runs with PID and mark as running
        $stmt = $pdo->prepare("
            UPDATE task_runs
            SET pid = :pid,
                finished_at = :finished_at,
                status = 'running'
            WHERE id = :id
        ");
        $stmt->execute([
            ':pid' => $pid,
            ':finished_at' => $finishedAt,
            ':id' => $runId,
        ]);

        // Update scheduled_tasks.last_run
        $stmt = $pdo->prepare("UPDATE scheduled_tasks SET last_run = :last_run WHERE id = :id");
        $stmt->execute([
            ':last_run' => $finishedAt,
            ':id' => $taskId,
        ]);

        // Clear run_now flag
        $stmt = $pdo->prepare("UPDATE scheduled_tasks SET run_now = 0 WHERE id = :id");
        $stmt->execute([':id' => $taskId]);

        $logger->log('INFO', [
            "Task {$task['name']} dispatched successfully",
            "run_id" => $runId,
            "pid" => $pid,
            "lock_id" => $lock['lock_id'] ?? 'none',
            "owner_token" => $lock['owner_token'] ?? null
        ]);
    }
}

