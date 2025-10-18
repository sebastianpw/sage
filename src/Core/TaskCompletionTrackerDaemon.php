<?php
namespace App\Core;

use PDO;

/**
 * TaskCompletionTrackerDaemon
 * 
 * Continuously monitors running tasks, checks their PIDs, and marks them as completed
 * when they finish. Also renews locks for running tasks and releases locks for completed tasks.
 * 
 * This runs as a daemon alongside the main scheduler.
 */
class TaskCompletionTrackerDaemon extends AbstractScheduler
{
    protected int $sleepInterval = 30; // default check interval (seconds)
    protected TaskLockManager $lockManager;
    protected TaskHeartbeat $heartbeat;

    public function __construct(int $sleepInterval = 1)
    {
        parent::__construct();
        $this->sleepInterval = $sleepInterval;
        $this->lockManager = new TaskLockManager();
        $this->heartbeat = new TaskHeartbeat();
    }

    public function run(): void
    {
        $spw = $this->spw;
        $logger = $spw->getFileLogger();
        $logger->log('INFO', ["Task Completion Tracker started. Check interval: {$this->sleepInterval} seconds"]);

        while (true) {
            try {
                // First: run comprehensive lock cleanup (finished-run locks, orphaned locks, expired locks)
                $this->heartbeat->comprehensiveLockCleanup();

                // Defensive: handle runs referring to missing scheduled_tasks (would be hidden by JOIN)
                $this->cleanupOrphanedTaskRuns();

                // Then inspect running tasks and renew/ensure locks or finalize runs
                $this->checkRunningTasks();

                // Periodic housekeeping
                $this->cleanupOldRecords();
            } catch (\Throwable $e) {
                $logger->log('ERROR', [
                    "Completion tracker error",
                    "message" => $e->getMessage(),
                    "trace" => $e->getTraceAsString(),
                ]);
            }

            sleep($this->sleepInterval);
        }
    }

    /**
     * Query DB for running/pending task runs and process each.
     */
    protected function checkRunningTasks(): void
    {
        $pdo = $this->spw->getPDO();
        $logger = $this->spw->getFileLogger();

        // Select fields we need: run info + schedule config for lock_key generation & TTL
        $stmt = $pdo->query("
            SELECT 
                tr.id as run_id,
                tr.task_id,
                tr.pid,
                tr.lock_id,
                tr.lock_owner_token,
                tr.started_at,
                tr.stdout_log,
                tr.stderr_log,
                tr.entity_type,
                tr.entity_id,
                st.name as task_name,
                st.lock_scope,
                st.lock_timeout_minutes
            FROM task_runs tr
            JOIN scheduled_tasks st ON tr.task_id = st.id
            WHERE tr.status IN ('pending', 'running')
            ORDER BY tr.started_at ASC
        ");

        $runningTasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($runningTasks)) {
            return;
        }

        $logger->log('DEBUG', ["Checking " . count($runningTasks) . " running tasks for pid & lock renewal"]);

        foreach ($runningTasks as $task) {
            try {
                $this->checkTaskStatus($task);
            } catch (\Throwable $e) {
                $logger->log('ERROR', [
                    "Error checking task status",
                    "run_id" => $task['run_id'] ?? null,
                    "message" => $e->getMessage(),
                    "trace" => $e->getTraceAsString(),
                ]);
            }
        }
    }

    /**
     * Check a single task run: if PID alive -> renew/ensure lock; if dead -> finalize and release lock.
     *
     * @param array $task row from DB
     */
    protected function checkTaskStatus(array $task): void
    {
        $pdo = $this->spw->getPDO();
        $logger = $this->spw->getFileLogger();

        $pid = (int)($task['pid'] ?? 0);
        $runId = (int)$task['run_id'];
        $lockId = isset($task['lock_id']) && $task['lock_id'] !== null ? (int)$task['lock_id'] : null;
        $ownerToken = $task['lock_owner_token'] ?? null;
        $taskName = $task['task_name'] ?? 'unknown';
        $lockScope = $task['lock_scope'] ?? 'global';
        $entityType = $task['entity_type'] ?? null;
        $entityId = isset($task['entity_id']) && $task['entity_id'] !== '' ? (int)$task['entity_id'] : null;
        $lockTimeoutMinutes = (int)($task['lock_timeout_minutes'] ?? 60);

        if ($pid <= 0) {
            $logger->log('WARNING', [
                "Task run $runId has no PID, marking as failed",
                "task_name" => $taskName
            ]);
            $this->markTaskFailed($runId, $lockId, -2);
            return;
        }

        // Use robust PID alive check that treats zombies as dead
        $isRunning = $this->isPidAlive($pid);

        // If process is alive, we must ensure the lock remains active (renew or re-create)
        if ($isRunning) {
            $lockKey = $this->lockManager->generateLockKey($task['task_id'], $lockScope, $entityType, $entityId);

            // If lock id exists and is active try to renew it (prefer owner token)
            if ($lockId && $this->lockManager->isLockActive($lockId)) {
                $renewed = $this->lockManager->renewLock($lockId, $lockTimeoutMinutes, $ownerToken ?? null);
                if ($renewed) {
                    $logger->log('DEBUG', [
                        "Renewed lock for running task",
                        "run_id" => $runId,
                        "lock_id" => $lockId,
                        "owner_token" => $ownerToken,
                        "ttl_minutes" => $lockTimeoutMinutes
                    ]);
                    return; // lock renewed successfully; nothing else to do
                }

                // If renewal failed, fall through to ensureLockForRun
                $logger->log('WARNING', [
                    "Failed to renew lock (owner mismatch or missing) - will ensure lock row",
                    "run_id" => $runId,
                    "lock_id" => $lockId,
                    "owner_token" => $ownerToken
                ]);
            }

            // Ensure or create lock row for the running PID (trusted tracker does this)
            $this->lockManager->ensureLockForRun(
                $task['task_id'],
                $lockKey,
                $runId,
                $ownerToken,
                $pid,
                gethostname(),
                max(1, $lockTimeoutMinutes)
            );

            $logger->log('INFO', [
                "Ensured/created lock row for running process",
                "run_id" => $runId,
                "task" => $taskName,
                "pid" => $pid,
                "lock_key" => $lockKey
            ]);

            return;
        }

        // If the process is not running, mark as completed/failed accordingly
        $exitCode = $this->determineExitCode($task);
        $status = ($exitCode === 0) ? 'completed' : 'failed';

        $logger->log('INFO', [
            "Task run $runId completed",
            "task_name" => $taskName,
            "pid" => $pid,
            "exit_code" => $exitCode,
            "status" => $status
        ]);

        // Update task_runs
        $stmt = $pdo->prepare("
            UPDATE task_runs
            SET finished_at = NOW(),
                exit_code = :exit_code,
                status = :status
            WHERE id = :id
        ");
        $stmt->execute([
            ':id' => $runId,
            ':exit_code' => $exitCode,
            ':status' => $status
        ]);

        // Debug: ensure update succeeded
        try {
            $updatedRows = $stmt->rowCount();
            $err = $stmt->errorInfo();
            $logger->log('DEBUG', [
                "Updated task_runs rows",
                "run_id" => $runId,
                "rows_updated" => $updatedRows,
                "error" => $err
            ]);
        } catch (\Throwable $e) {
            $logger->log('ERROR', ["Failed to inspect update result", "run_id" => $runId, "message" => $e->getMessage()]);
        }

        // Update log file sizes
        $this->updateLogSizes($runId, $task['stdout_log'] ?? '', $task['stderr_log'] ?? '');

        // Release lock (tracker is trusted -> force release to ensure cleanup)
        if ($lockId) {
            $released = false;
            try {
                $released = $this->lockManager->releaseLock($lockId, true);
                $logger->log('INFO', [
                    "Lock release for completed task",
                    "lock_id" => $lockId,
                    "run_id" => $runId,
                    "success" => $released
                ]);
            } catch (\Throwable $e) {
                $logger->log('ERROR', [
                    "Error releasing lock for run",
                    "run_id" => $runId,
                    "lock_id" => $lockId,
                    "message" => $e->getMessage()
                ]);
            }

            if (!$released) {
                // Fallback: force release via lock manager
                try {
                    $this->lockManager->forceReleaseLock((int)$lockId);
                } catch (\Throwable $e) {
                    $logger->log('ERROR', [
                        "Fallback forceReleaseLock failed",
                        "lock_id" => $lockId,
                        "message" => $e->getMessage()
                    ]);
                }
            }
        }

        // Update execution stats
        $this->updateExecutionStats($task['task_id'], $status, $task['started_at']);
    }

    /**
     * Mark a run as failed and release lock (force)
     */
    protected function markTaskFailed(int $runId, ?int $lockId, int $exitCode): void
    {
        $pdo = $this->spw->getPDO();
        $logger = $this->spw->getFileLogger();

        $stmt = $pdo->prepare("
            UPDATE task_runs
            SET finished_at = NOW(),
                exit_code = :exit_code,
                status = 'failed'
            WHERE id = :id
        ");
        $stmt->execute([
            ':id' => $runId,
            ':exit_code' => $exitCode
        ]);

        // Debug: ensure update succeeded
        try {
            $updatedRows = $stmt->rowCount();
            $err = $stmt->errorInfo();
            $logger->log('DEBUG', [
                "markTaskFailed updated task_runs",
                "run_id" => $runId,
                "rows_updated" => $updatedRows,
                "error" => $err
            ]);
        } catch (\Throwable $e) {
            $logger->log('ERROR', ["Failed to inspect markTaskFailed update", "run_id" => $runId, "message" => $e->getMessage()]);
        }

        if ($lockId) {
            try {
                $this->lockManager->releaseLock($lockId, true);
            } catch (\Throwable $e) {
                $this->spw->getFileLogger()->log('ERROR', [
                    "Failed to force-release lock in markTaskFailed",
                    "lock_id" => $lockId,
                    "message" => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Determine exit code heuristically (preferred: dedicated exit code file; fallback: stderr content).
     *
     * @param array $task
     * @return int
     */
    protected function determineExitCode(array $task): int
    {
        $runId = (int)($task['run_id'] ?? 0);
        $logDir = $this->spw->getProjectPath() . '/logs';
        $exitCodeLog = "$logDir/task_run_{$runId}_exit.log";

        // Preferred method: read dedicated exit code file
        if (file_exists($exitCodeLog)) {
            $content = trim(@file_get_contents($exitCodeLog));
            if ($content !== '') {
                $exitCode = (int)$content;
                @unlink($exitCodeLog);
                return $exitCode;
            }
        }

        // Fallback heuristic: check stderr contents
        $exitCode = 0; // assume success by default

        if (!empty($task['stderr_log']) && file_exists($task['stderr_log'])) {
            $stderrSize = filesize($task['stderr_log']);
            if ($stderrSize > 0) {
                $stderr = @file_get_contents($task['stderr_log']);
                $lowerStderr = strtolower($stderr);
                if (strpos($lowerStderr, 'error') !== false ||
                    strpos($lowerStderr, 'fatal') !== false ||
                    strpos($lowerStderr, 'failed') !== false ||
                    strpos($lowerStderr, 'exception') !== false) {
                    $exitCode = 1;
                }
            }
        }

        return $exitCode;
    }

    /**
     * Update stored stdout/stderr byte counters for a run
     */
    protected function updateLogSizes(int $runId, string $stdoutLog, string $stderrLog): void
    {
        $pdo = $this->spw->getPDO();
        $logger = $this->spw->getFileLogger();

        $bytesOut = (!empty($stdoutLog) && file_exists($stdoutLog)) ? filesize($stdoutLog) : 0;
        $bytesErr = (!empty($stderrLog) && file_exists($stderrLog)) ? filesize($stderrLog) : 0;

        $stmt = $pdo->prepare("
            UPDATE task_runs
            SET bytes_out = :bytes_out,
                bytes_err = :bytes_err
            WHERE id = :id
        ");
        $stmt->execute([
            ':id' => $runId,
            ':bytes_out' => $bytesOut,
            ':bytes_err' => $bytesErr
        ]);

        // Debug: ensure update succeeded
        try {
            $updatedRows = $stmt->rowCount();
            $err = $stmt->errorInfo();
            $logger->log('DEBUG', [
                "updateLogSizes updated task_runs",
                "run_id" => $runId,
                "rows_updated" => $updatedRows,
                "error" => $err
            ]);
        } catch (\Throwable $e) {
            $logger->log('ERROR', ["Failed to inspect updateLogSizes result", "run_id" => $runId, "message" => $e->getMessage()]);
        }
    }

    /**
     * Update per-task execution statistics for the date of the run
     */
    protected function updateExecutionStats(int $taskId, string $status, string $startedAt): void
    {
        $pdo = $this->spw->getPDO();
        $date = date('Y-m-d', strtotime($startedAt));
        $duration = time() - strtotime($startedAt);

        // Check if stats record exists for today
        $stmt = $pdo->prepare("
            SELECT id, total_runs, successful_runs, failed_runs, stale_runs,
                   avg_duration_seconds, max_duration_seconds
            FROM task_execution_stats
            WHERE task_id = :task_id AND date = :date
        ");
        $stmt->execute([':task_id' => $taskId, ':date' => $date]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($stats) {
            // Update existing record
            $totalRuns = $stats['total_runs'] + 1;
            $successfulRuns = $stats['successful_runs'] + ($status === 'completed' ? 1 : 0);
            $failedRuns = $stats['failed_runs'] + ($status === 'failed' ? 1 : 0);
            $staleRuns = $stats['stale_runs'] + ($status === 'stale' ? 1 : 0);

            $currentAvg = (float)$stats['avg_duration_seconds'];
            $newAvg = $currentAvg !== 0.0
                ? (($currentAvg * $stats['total_runs']) + $duration) / $totalRuns
                : $duration;

            $maxDuration = max((int)$stats['max_duration_seconds'], $duration);

            $stmt = $pdo->prepare("
                UPDATE task_execution_stats
                SET total_runs = :total_runs,
                    successful_runs = :successful_runs,
                    failed_runs = :failed_runs,
                    stale_runs = :stale_runs,
                    avg_duration_seconds = :avg_duration,
                    max_duration_seconds = :max_duration
                WHERE id = :id
            ");
            $stmt->execute([
                ':total_runs' => $totalRuns,
                ':successful_runs' => $successfulRuns,
                ':failed_runs' => $failedRuns,
                ':stale_runs' => $staleRuns,
                ':avg_duration' => $newAvg,
                ':max_duration' => $maxDuration,
                ':id' => $stats['id']
            ]);
        } else {
            // Insert new record
            $stmt = $pdo->prepare("
                INSERT INTO task_execution_stats
                (task_id, date, total_runs, successful_runs, failed_runs, stale_runs,
                 avg_duration_seconds, max_duration_seconds)
                VALUES
                (:task_id, :date, 1, :successful, :failed, :stale, :duration, :duration)
            ");
            $stmt->execute([
                ':task_id' => $taskId,
                ':date' => $date,
                ':successful' => ($status === 'completed' ? 1 : 0),
                ':failed' => ($status === 'failed' ? 1 : 0),
                ':stale' => ($status === 'stale' ? 1 : 0),
                ':duration' => $duration
            ]);
        }
    }

    /**
     * Cleanup old completed task runs and old lock rows.
     * Runs at most once per hour.
     */
    protected function cleanupOldRecords(): void
    {
        static $lastCleanup = 0;
        $now = time();

        // Only cleanup once per hour
        if ($now - $lastCleanup < 3600) {
            return;
        }

        $pdo = $this->spw->getPDO();
        $logger = $this->spw->getFileLogger();

        // Cleanup old completed task runs (keep last 30 days)
        $stmt = $pdo->query("
            DELETE FROM task_runs
            WHERE status IN ('completed', 'failed', 'stale')
            AND finished_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $deletedRuns = $stmt->rowCount();

        // Cleanup old locks (keep last 7 days)
        $stmt = $pdo->query("
            DELETE FROM task_locks
            WHERE status IN ('released', 'expired')
            AND acquired_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        $deletedLocks = $stmt->rowCount();

        if ($deletedRuns > 0 || $deletedLocks > 0) {
            $logger->log('INFO', [
                "Cleanup completed",
                "deleted_runs" => $deletedRuns,
                "deleted_locks" => $deletedLocks
            ]);
        }

        $lastCleanup = $now;
    }

    /**
     * Return true if PID is alive (and not a zombie). Uses /proc when available
     * and falls back to posix_kill when possible. Treats zombies as NOT alive.
     */
    protected function isPidAlive(int $pid): bool
    {
        if ($pid <= 0) return false;

        $logger = $this->spw->getFileLogger();

        // Prefer /proc if available (more reliable inside containers)
        $procStatus = "/proc/{$pid}/status";
        if (file_exists($procStatus)) {
            $content = @file_get_contents($procStatus);
            if ($content !== false) {
                // Look for "State:\tX" line
                if (preg_match('/^State:\s+([A-Z])/m', $content, $m)) {
                    $state = $m[1];
                    if ($state === 'Z') {
                        // Zombie / defunct -> treat as dead (parent didn't reap)
                        $logger->log('DEBUG', [
                            "PID $pid is a zombie (State=Z). Treating as not running."
                        ]);
                        return false;
                    }
                    // any other state (R,S,D,T) count as running
                    return true;
                }
            }
        }

        // Fallback: posix_kill(...,0) where available
        if (function_exists('posix_kill')) {
            try {
                if (@posix_kill($pid, 0)) {
                    return true;
                }
            } catch (\Throwable $e) {
                $logger->log('ERROR', [
                    "posix_kill error checking PID $pid",
                    "message" => $e->getMessage()
                ]);
            }
        }

        // Last resort: check if /proc/$pid exists (already covered), else assume not alive
        return file_exists("/proc/{$pid}");
    }

    /**
     * Find task_runs that reference missing scheduled_tasks and mark them failed/stale.
     * This is defensive: when JOIN in checkRunningTasks hides those rows entirely.
     */
    protected function cleanupOrphanedTaskRuns(): void
    {
        $pdo = $this->spw->getPDO();
        $logger = $this->spw->getFileLogger();

        $stmt = $pdo->query("
            SELECT tr.id AS run_id, tr.task_id, tr.pid, tr.started_at
            FROM task_runs tr
            LEFT JOIN scheduled_tasks st ON tr.task_id = st.id
            WHERE st.id IS NULL
              AND tr.status IN ('pending','running')
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($rows)) return;

        foreach ($rows as $r) {
            $logger->log('WARNING', [
                "Orphaned task_run found (no scheduled_tasks row) - marking failed",
                "run_id" => $r['run_id'], "task_id" => $r['task_id'], "pid" => $r['pid']
            ]);
            // Choose exitCode -3 to indicate orphaned config / missing scheduled_task
            $this->markTaskFailed((int)$r['run_id'], null, -3);
        }
    }
}