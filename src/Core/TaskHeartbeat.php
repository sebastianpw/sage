<?php

namespace App\Core;

use App\Core\SpwBase;

class TaskHeartbeat
{
    private SpwBase $spw;

    public function __construct()
    {
        $this->spw = SpwBase::getInstance();
    }

    /**
     * Update the scheduler heartbeat timestamp
     */
    public function updateSchedulerHeartbeat(): void
    {
        $pdo = $this->spw->getPDO();
        $now = date('Y-m-d H:i:s');

        $stmt = $pdo->prepare("
            INSERT INTO scheduler_heartbeat (id, last_seen)
            VALUES (1, :now)
            ON DUPLICATE KEY UPDATE last_seen = :now
        ");
        $stmt->execute([':now' => $now]);
    }

    /**
     * Check for tasks that have been running too long and mark them as stale.
     * Also releases their locks.
     *
     * @param int $maxRunMinutes Maximum allowed runtime before considering task stale
     */
    public function checkStaleTasks(int $maxRunMinutes = 60): void
    {
        $pdo = $this->spw->getPDO();
        $logger = $this->spw->getSchedulerFileLogger();

        // Find stale running tasks
        $stmt = $pdo->prepare("
            SELECT id, task_id, lock_id, pid
            FROM task_runs
            WHERE status IN ('pending', 'running')
              AND started_at < (NOW() - INTERVAL :minutes MINUTE)
        ");
        $stmt->execute([':minutes' => $maxRunMinutes]);
        $staleTasks = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($staleTasks)) {
            return;
        }

        foreach ($staleTasks as $task) {
            // Mark task as stale
            $stmt = $pdo->prepare("
                UPDATE task_runs
                SET finished_at = NOW(), 
                    exit_code = -1,
                    status = 'stale'
                WHERE id = :id
            ");
            $stmt->execute([':id' => $task['id']]);

            // Release associated lock if exists
            if (!empty($task['lock_id'])) {
                $stmt = $pdo->prepare("
                    UPDATE task_locks
                    SET status = 'expired'
                    WHERE id = :lock_id
                ");
                $stmt->execute([':lock_id' => $task['lock_id']]);
            }

            // Log each stale task
            $logger->log('WARNING', [
                "Marked stale task",
                "run_id" => $task['id'],
                "task_id" => $task['task_id'],
                "pid" => $task['pid'],
                "lock_id" => $task['lock_id'] ?? null
            ]);
        }

        // Summary log
        $logger->log('INFO', [
            "Stale task cleanup completed",
            "stale_count" => count($staleTasks)
        ]);
    }

    /**
     * Check for orphaned locks (locks whose PIDs no longer exist)
     * This catches the case where tasks finish but locks remain
     */
    public function checkOrphanedLocks(): void
    {
        $pdo = $this->spw->getPDO();
        $logger = $this->spw->getSchedulerFileLogger();

        // Get all active locks with PIDs
        $stmt = $pdo->query("
            SELECT id, task_id, pid, lock_key, acquired_at
            FROM task_locks
            WHERE status = 'active'
            AND pid IS NOT NULL
        ");
        $activeLocks = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $orphanedCount = 0;

        foreach ($activeLocks as $lock) {
            $pid = (int)$lock['pid'];
            
            // Check if process is still running
            if ($pid > 0 && !posix_kill($pid, 0)) {
                // Process is dead, release the lock
                $stmt = $pdo->prepare("
                    UPDATE task_locks
                    SET status = 'expired'
                    WHERE id = :id
                ");
                $stmt->execute([':id' => $lock['id']]);

                $orphanedCount++;

                $logger->log('WARNING', [
                    "Released orphaned lock",
                    "lock_id" => $lock['id'],
                    "task_id" => $lock['task_id'],
                    "pid" => $pid,
                    "lock_key" => $lock['lock_key']
                ]);
            }
        }

        if ($orphanedCount > 0) {
            $logger->log('INFO', [
                "Orphaned lock cleanup completed",
                "released_count" => $orphanedCount
            ]);
        }
    }

    /**
     * Check for locks whose associated task_run has finished
     * This is the most important check for stuck locks!
     */
    public function checkFinishedTaskLocks(): void
    {
        $pdo = $this->spw->getPDO();
        $logger = $this->spw->getSchedulerFileLogger();

        // Find locks whose associated run is finished
        $stmt = $pdo->query("
            SELECT 
                tl.id as lock_id,
                tl.task_id,
                tl.pid,
                tl.lock_key,
                tl.run_id,
                tr.status as run_status,
                tr.finished_at
            FROM task_locks tl
            LEFT JOIN task_runs tr ON tl.run_id = tr.id
            WHERE tl.status = 'active'
            AND (
                tr.status IN ('completed', 'failed', 'stale')
                OR tr.id IS NULL
            )
        ");
        
        $finishedLocks = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($finishedLocks)) {
            return;
        }

        $logger->log('INFO', [
            "Found finished task locks to release",
            "count" => count($finishedLocks)
        ]);

        foreach ($finishedLocks as $lock) {
            // Release the lock
            $stmt = $pdo->prepare("
                UPDATE task_locks
                SET status = 'released'
                WHERE id = :id
            ");
            $success = $stmt->execute([':id' => $lock['lock_id']]);

            $logger->log('INFO', [
                "Released lock for finished task",
                "lock_id" => $lock['lock_id'],
                "run_id" => $lock['run_id'],
                "run_status" => $lock['run_status'] ?? 'NULL',
                "finished_at" => $lock['finished_at'] ?? 'NULL',
                "success" => $success
            ]);
        }

        $logger->log('INFO', [
            "Finished task locks cleanup completed",
            "released_count" => count($finishedLocks)
        ]);
    }

    /**
     * Get the last heartbeat timestamp
     *
     * @return string|null
     */
    public function getLastHeartbeat(): ?string
    {
        $pdo = $this->spw->getPDO();
        $stmt = $pdo->query("SELECT last_seen FROM scheduler_heartbeat WHERE id = 1 LIMIT 1");
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row['last_seen'] ?? null;
    }

    /**
     * Check if scheduler is considered alive
     *
     * @param int $timeoutSeconds
     * @return bool
     */
    public function isSchedulerAlive(int $timeoutSeconds = 120): bool
    {
        $last = $this->getLastHeartbeat();
        if ($last === null) {
            return false;
        }

        return (strtotime($last) + $timeoutSeconds) >= time();
    }

    /**
     * Mark a task run as completed
     *
     * @param int $runId
     * @param int $exitCode
     * @return bool
     */
    public function markTaskCompleted(int $runId, int $exitCode = 0): bool
    {
        $pdo = $this->spw->getPDO();

        $status = ($exitCode === 0) ? 'completed' : 'failed';

        $stmt = $pdo->prepare("
            UPDATE task_runs
            SET finished_at = NOW(),
                exit_code = :exit_code,
                status = :status
            WHERE id = :id
        ");

        return $stmt->execute([
            ':id' => $runId,
            ':exit_code' => $exitCode,
            ':status' => $status
        ]);
    }

    /**
     * Get running task count for a specific task
     *
     * @param int $taskId
     * @return int
     */
    public function getRunningTaskCount(int $taskId): int
    {
        $pdo = $this->spw->getPDO();

        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM task_runs
            WHERE task_id = :task_id
              AND status IN ('pending', 'running')
        ");
        $stmt->execute([':task_id' => $taskId]);

        return (int)$stmt->fetchColumn();
    }

    /**
     * Comprehensive lock cleanup - runs all lock checks
     * Call this regularly to prevent stuck locks
     */
    public function comprehensiveLockCleanup(): void
    {
        // Check for locks of finished tasks (most important!)
        $this->checkFinishedTaskLocks();
        
        // Check for orphaned locks (dead PIDs)
        $this->checkOrphanedLocks();
        
        // Check for expired locks by timeout
        $this->cleanupExpiredLocksByTimeout();
    }

    /**
     * Clean up locks that have exceeded their expiration time
     */
    private function cleanupExpiredLocksByTimeout(): void
    {
        $pdo = $this->spw->getPDO();
        $logger = $this->spw->getSchedulerFileLogger();

        $stmt = $pdo->query("
            SELECT id, task_id, lock_key, expires_at
            FROM task_locks
            WHERE status = 'active'
            AND expires_at < NOW()
        ");
        
        $expiredLocks = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($expiredLocks)) {
            return;
        }

        foreach ($expiredLocks as $lock) {
            $stmt = $pdo->prepare("
                UPDATE task_locks
                SET status = 'expired'
                WHERE id = :id
            ");
            $stmt->execute([':id' => $lock['id']]);

            $logger->log('WARNING', [
                "Lock expired by timeout",
                "lock_id" => $lock['id'],
                "task_id" => $lock['task_id'],
                "lock_key" => $lock['lock_key'],
                "expires_at" => $lock['expires_at']
            ]);
        }

        $logger->log('INFO', [
            "Timeout-based lock cleanup completed",
            "expired_count" => count($expiredLocks)
        ]);
    }
}
