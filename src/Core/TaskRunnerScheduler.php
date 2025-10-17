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
