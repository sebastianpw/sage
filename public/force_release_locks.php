<?php
declare(strict_types=1);

/**
 * Emergency Lock Release Tool
 * 
 * Usage:
 *   php force_release_locks.php <task_id>     - Release all locks for a specific task
 *   php force_release_locks.php --all         - Release ALL active locks
 *   php force_release_locks.php --cleanup     - Run comprehensive cleanup
 */

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

use App\Core\TaskLockManager;
use App\Core\TaskHeartbeat;

if (!isset($argv[1])) {
    echo "Usage:\n";
    echo "  php force_release_locks.php <task_id>     - Release locks for task\n";
    echo "  php force_release_locks.php --all         - Release ALL locks\n";
    echo "  php force_release_locks.php --cleanup     - Run cleanup\n";
    exit(1);
}

$spw = \App\Core\SpwBase::getInstance();
$lockManager = new TaskLockManager();
$heartbeat = new TaskHeartbeat();

$arg = $argv[1];

if ($arg === '--cleanup') {
    echo "Running comprehensive lock cleanup...\n";
    
    $heartbeat->checkFinishedTaskLocks();
    $heartbeat->checkOrphanedLocks();
    $lockManager->cleanupExpiredLocks();
    
    echo "✓ Cleanup completed!\n";
    exit(0);
}

if ($arg === '--all') {
    echo "⚠️  WARNING: This will release ALL active locks!\n";
    echo "Are you sure? Type 'yes' to confirm: ";
    
    $handle = fopen("php://stdin", "r");
    $line = trim(fgets($handle));
    fclose($handle);
    
    if ($line !== 'yes') {
        echo "Aborted.\n";
        exit(0);
    }
    
    $pdo = $spw->getPDO();
    $stmt = $pdo->query("
        SELECT id, task_id, lock_key 
        FROM task_locks 
        WHERE status = 'active'
    ");
    $locks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($locks)) {
        echo "No active locks found.\n";
        exit(0);
    }
    
    echo "Releasing " . count($locks) . " locks...\n";
    
    foreach ($locks as $lock) {
        $lockManager->releaseLock($lock['id']);
        echo "✓ Released: {$lock['lock_key']}\n";
    }
    
    echo "\n✓ All locks released!\n";
    exit(0);
}

// Release locks for specific task
$taskId = (int)$arg;

if ($taskId <= 0) {
    echo "Error: Invalid task ID\n";
    exit(1);
}

$pdo = $spw->getPDO();
$stmt = $pdo->prepare("SELECT name FROM scheduled_tasks WHERE id = :id");
$stmt->execute([':id' => $taskId]);
$taskName = $stmt->fetchColumn();

if (!$taskName) {
    echo "Error: Task ID $taskId not found\n";
    exit(1);
}

echo "Task: $taskName (ID: $taskId)\n";
echo "Releasing all locks for this task...\n\n";

$count = $lockManager->forceReleaseTaskLocks($taskId);

if ($count > 0) {
    echo "✓ Released $count lock(s)\n";
} else {
    echo "No active locks found for this task.\n";
}

exit(0);
