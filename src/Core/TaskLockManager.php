<?php

namespace App\Core;

use PDO;
use App\Core\SpwBase;

/**
 * TaskLockManager - Provides mutex functionality for scheduled tasks
 *
 * Safe, durable table-based locks with owner tokens and steal/force-release semantics.
 */
class TaskLockManager
{
    private SpwBase $spw;
    private PDO $pdo;
    private SchedulerFileLogger $logger;

    public function __construct()
    {
        $this->spw = SpwBase::getInstance();
        $this->pdo = $this->spw->getPDO();
        $this->logger = $this->spw->getSchedulerFileLogger();
    }

    /**
     * Lightweight UUIDv4 string (36 chars)
     */
    private function uuidV4(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    public function generateLockKey(
        int $taskId,
        string $scope,
        ?string $entityType = null,
        ?int $entityId = null
    ): string {
        switch ($scope) {
            case 'entity':
                if ($entityType && $entityId) {
                    return "task_{$taskId}_entity_{$entityType}_{$entityId}";
                }
                return "task_{$taskId}_global";
            case 'none':
                $this->logger->log('WARNING', ["Lock requested for task with 'none' scope - using global"]);
                return "task_{$taskId}_global";
            case 'global':
            default:
                return "task_{$taskId}_global";
        }
    }

    public function acquireLock(
        int $taskId,
        ?string $entityType = null,
        ?int $entityId = null,
        ?int $runId = null
    ): ?array {
        $stmt = $this->pdo->prepare("
            SELECT require_lock, lock_scope, lock_timeout_minutes, max_concurrent_runs, name
            FROM scheduled_tasks
            WHERE id = :task_id
        ");
        $stmt->execute([':task_id' => $taskId]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$task) {
            $this->logger->log('ERROR', ["Task $taskId not found"]);
            return null;
        }

        if (empty($task['require_lock']) || ($task['lock_scope'] ?? 'global') === 'none') {
            $this->logger->log('DEBUG', ["Task {$task['name']} bypasses locking"]);
            return ['bypass' => true, 'task_id' => $taskId];
        }

        $lockKey = $this->generateLockKey($taskId, $task['lock_scope'], $entityType, $entityId);
        $this->cleanupExpiredLocks();

        $currentLocks = $this->getActiveLockCount($lockKey, $taskId);
        $maxConcurrent = (int)($task['max_concurrent_runs'] ?? 1);
        if ($currentLocks >= $maxConcurrent) {
            $this->logger->log('WARNING', [
                "Cannot acquire lock for task {$task['name']}",
                "lock_key" => $lockKey,
                "current_locks" => $currentLocks,
                "max_concurrent" => $maxConcurrent
            ]);
            return null;
        }

        $ownerToken = $this->uuidV4();
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $expiresAt = $now->modify("+{$task['lock_timeout_minutes']} minutes");

        $this->logger->log('DEBUG', [
            "Attempting lock acquire",
            "task_id" => $taskId,
            "task_name" => $task['name'],
            "lock_key" => $lockKey,
            "run_id" => $runId,
            "max_concurrent" => $maxConcurrent,
            "timeout_minutes" => $task['lock_timeout_minutes']
        ]);

        try {
            $insert = $this->pdo->prepare("
                INSERT INTO task_locks
                (task_id, lock_key, acquired_at, expires_at, run_id, pid, hostname, status, owner_token, last_renewed)
                VALUES
                (:task_id, :lock_key, :acquired_at, :expires_at, :run_id, :pid, :hostname, 'active', :owner_token, :last_renewed)
            ");
            $insert->execute([
                ':task_id' => $taskId,
                ':lock_key' => $lockKey,
                ':acquired_at' => $now->format('Y-m-d H:i:s'),
                ':expires_at' => $expiresAt->format('Y-m-d H:i:s'),
                ':run_id' => $runId,
                ':pid' => getmypid(),
                ':hostname' => gethostname(),
                ':owner_token' => $ownerToken,
                ':last_renewed' => $now->format('Y-m-d H:i:s')
            ]);

            $lockId = (int)$this->pdo->lastInsertId();
            $this->logger->log('INFO', ["Lock acquired (insert) for task {$task['name']}", "lock_id" => $lockId]);

            return [
                'lock_id' => $lockId,
                'task_id' => $taskId,
                'lock_key' => $lockKey,
                'owner_token' => $ownerToken,
                'acquired_at' => $now->format('Y-m-d H:i:s'),
                'expires_at' => $expiresAt->format('Y-m-d H:i:s')
            ];
        } catch (\PDOException $e) {
            if ($e->getCode() !== '23000') {
                throw $e;
            }
        }

        // Steal expired lock
        $update = $this->pdo->prepare("
            UPDATE task_locks
            SET owner_token = :owner_token,
                acquired_at = :acquired_at,
                expires_at = :expires_at,
                run_id = :run_id,
                pid = :pid,
                hostname = :hostname,
                status = 'active',
                last_renewed = :last_renewed
            WHERE lock_key = :lock_key
              AND (status != 'active' OR expires_at <= UTC_TIMESTAMP())
        ");
        $update->execute([
            ':owner_token' => $ownerToken,
            ':acquired_at' => $now->format('Y-m-d H:i:s'),
            ':expires_at' => $expiresAt->format('Y-m-d H:i:s'),
            ':run_id' => $runId,
            ':pid' => getmypid(),
            ':hostname' => gethostname(),
            ':last_renewed' => $now->format('Y-m-d H:i:s'),
            ':lock_key' => $lockKey
        ]);

        if ($update->rowCount() > 0) {
            $stmt = $this->pdo->prepare("SELECT id FROM task_locks WHERE lock_key = :lock_key LIMIT 1");
            $stmt->execute([':lock_key' => $lockKey]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $lockId = (int)$row['id'];

            $this->logger->log('INFO', ["Lock stolen for task {$task['name']}", "lock_id" => $lockId]);
            return [
                'lock_id' => $lockId,
                'task_id' => $taskId,
                'lock_key' => $lockKey,
                'owner_token' => $ownerToken,
                'acquired_at' => $now->format('Y-m-d H:i:s'),
                'expires_at' => $expiresAt->format('Y-m-d H:i:s')
            ];
        }

        $this->logger->log('DEBUG', ["Lock acquisition failed - active lock present", "lock_key" => $lockKey]);
        return null;
    }

    public function releaseLock($lockIdOrInfo, bool $force = false, ?string $ownerToken = null): bool
    {
        if (is_array($lockIdOrInfo) && isset($lockIdOrInfo['bypass'])) {
            return true;
        }

        $lockId = is_array($lockIdOrInfo) ? ($lockIdOrInfo['lock_id'] ?? null) : $lockIdOrInfo;
        if (!$lockId) {
            $this->logger->log('WARNING', ['Attempted to release lock with no ID']);
            return false;
        }

        if ($ownerToken === null && is_array($lockIdOrInfo) && isset($lockIdOrInfo['owner_token'])) {
            $ownerToken = $lockIdOrInfo['owner_token'];
        }

        if ($force) {
            return $this->forceReleaseLock((int)$lockId);
        }

        if ($ownerToken === null || $ownerToken === '') {
            $this->logger->log('WARNING', ["Cannot release lock without owner_token", "lock_id" => $lockId]);
            return false;
        }

        $stmt = $this->pdo->prepare("
            UPDATE task_locks
            SET status = 'released', released_at = UTC_TIMESTAMP()
            WHERE id = :lock_id AND status = 'active' AND owner_token = :owner_token
        ");
        $stmt->execute([':lock_id' => $lockId, ':owner_token' => $ownerToken]);
        $released = $stmt->rowCount() > 0;

        if ($released) {
            $this->logger->log('INFO', ["Lock $lockId released by owner token"]);
        }
        return $released;
    }

    public function forceReleaseLock(int $lockId): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE task_locks
            SET status = 'released', released_at = UTC_TIMESTAMP()
            WHERE id = :id AND status = 'active'
        ");
        $stmt->execute([':id' => $lockId]);
        $released = $stmt->rowCount() > 0;
        if ($released) {
            $this->logger->log('WARNING', ["Force released lock $lockId"]);
        }
        return $released;
    }

    public function renewLock($lockIdOrInfo, int $additionalMinutes = 30, ?string $ownerToken = null): bool
    {
        if (is_array($lockIdOrInfo) && isset($lockIdOrInfo['bypass'])) {
            return true;
        }

        $lockId = is_array($lockIdOrInfo) ? ($lockIdOrInfo['lock_id'] ?? null) : $lockIdOrInfo;
        if (!$lockId) return false;

        if ($ownerToken === null && is_array($lockIdOrInfo) && isset($lockIdOrInfo['owner_token'])) {
            $ownerToken = $lockIdOrInfo['owner_token'];
        }

        if ($ownerToken === null || $ownerToken === '') return false;

        $stmt = $this->pdo->prepare("
            UPDATE task_locks
            SET expires_at = UTC_TIMESTAMP() + INTERVAL :minutes MINUTE,
                last_renewed = UTC_TIMESTAMP()
            WHERE id = :lock_id AND status = 'active' AND owner_token = :owner_token
        ");
        $stmt->execute([':minutes' => $additionalMinutes, ':lock_id' => $lockId, ':owner_token' => $ownerToken]);

        return $stmt->rowCount() > 0;
    }

    public function isLockActive($lockIdOrInfo): bool
    {
        if (is_array($lockIdOrInfo) && isset($lockIdOrInfo['bypass'])) return true;

        $lockId = is_array($lockIdOrInfo) ? ($lockIdOrInfo['lock_id'] ?? null) : $lockIdOrInfo;
        if (!$lockId) return false;

        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM task_locks 
            WHERE id = :lock_id AND status = 'active' AND expires_at > UTC_TIMESTAMP()
        ");
        $stmt->execute([':lock_id' => $lockId]);
        return $stmt->fetchColumn() > 0;
    }

    public function cleanupExpiredLocks(): int
    {
        $stmt = $this->pdo->prepare("
            UPDATE task_locks
            SET status = 'expired'
            WHERE status = 'active' AND expires_at < UTC_TIMESTAMP()
        ");
        $stmt->execute();
        $count = $stmt->rowCount();
        if ($count > 0) $this->logger->log('INFO', ["Cleaned up $count expired locks"]);
        return $count;
    }

    public function forceReleaseTaskLocks(int $taskId): int
    {
        $stmt = $this->pdo->prepare("
            UPDATE task_locks
            SET status = 'released', released_at = UTC_TIMESTAMP()
            WHERE task_id = :task_id AND status = 'active'
        ");
        $stmt->execute([':task_id' => $taskId]);
        $count = $stmt->rowCount();
        if ($count > 0) $this->logger->log('WARNING', ["Force released $count locks for task $taskId"]);
        return $count;
    }

    public function getTaskLocks(int $taskId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT l.*, t.name as task_name
            FROM task_locks l
            JOIN scheduled_tasks t ON l.task_id = t.id
            WHERE l.task_id = :task_id
              AND l.status = 'active'
              AND l.expires_at > UTC_TIMESTAMP()
            ORDER BY l.acquired_at DESC
        ");
        $stmt->execute([':task_id' => $taskId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function ensureLockForRun(
        int $taskId,
        string $lockKey,
        int $runId,
        ?string $ownerToken,
        int $pid,
        string $hostname,
        int $ttlMinutes = 60
    ): bool {
        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $expiresAtExpr = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->modify("+{$ttlMinutes} minutes")->format('Y-m-d H:i:s');

        if ($ownerToken === null || $ownerToken === '') {
            $ownerToken = $this->uuidV4();
        }

        $sql = "
            INSERT INTO task_locks
            (task_id, lock_key, acquired_at, expires_at, run_id, pid, hostname, status, owner_token, last_renewed)
            VALUES
            (:task_id, :lock_key, :acquired_at, :expires_at, :run_id, :pid, :hostname, 'active', :owner_token, :last_renewed)
            ON DUPLICATE KEY UPDATE
                owner_token = VALUES(owner_token),
                acquired_at = VALUES(acquired_at),
                expires_at = VALUES(expires_at),
                run_id = VALUES(run_id),
                pid = VALUES(pid),
                hostname = VALUES(hostname),
                status = 'active',
                last_renewed = VALUES(last_renewed)
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':task_id' => $taskId,
            ':lock_key' => $lockKey,
            ':acquired_at' => $now,
            ':expires_at' => $expiresAtExpr,
            ':run_id' => $runId,
            ':pid' => $pid,
            ':hostname' => $hostname,
            ':owner_token' => $ownerToken,
            ':last_renewed' => $now
        ]);

        $this->logger->log('INFO', [
            "Ensured lock for running PID",
            "task_id" => $taskId,
            "run_id" => $runId,
            "pid" => $pid,
            "lock_key" => $lockKey,
            "owner_token" => substr($ownerToken, 0, 8) . '...'
        ]);
        return true;
    }

    private function getActiveLockCount(string $lockKey, int $taskId): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM task_locks
            WHERE lock_key = :lock_key 
              AND task_id = :task_id 
              AND status = 'active' 
              AND expires_at > UTC_TIMESTAMP()
        ");
        $stmt->execute([':lock_key' => $lockKey, ':task_id' => $taskId]);
        return (int)$stmt->fetchColumn();
    }

    public function parseEntityFromArgs(string $args): array
    {
        $parts = preg_split('/\s+/', trim($args));
        $entityType = $parts[0] ?? null;
        $entityId = isset($parts[1]) && is_numeric($parts[1]) ? (int)$parts[1] : null;
        return ['entity_type' => $entityType, 'entity_id' => $entityId];
    }
}
