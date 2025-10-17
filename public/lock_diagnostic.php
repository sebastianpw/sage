<?php
declare(strict_types=1);

/**
 * Lock Diagnostic Tool
 * 
 * Use this to diagnose why locks are stuck and force-release them if needed
 * 
 * Usage:
 *   php lock_diagnostic.php              - Show all lock info
 *   php lock_diagnostic.php --release    - Auto-release stuck locks
 *   php lock_diagnostic.php --force-all  - Force release ALL active locks (dangerous!)
 */

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

use App\Core\TaskLockManager;
use App\Core\TaskHeartbeat;

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();
$lockManager = new TaskLockManager();
$heartbeat = new TaskHeartbeat();

$autoRelease = in_array('--release', $argv);
$forceAll = in_array('--force-all', $argv);

echo "\n";
echo "╔═══════════════════════════════════════════════════════════════╗\n";
echo "║              LOCK DIAGNOSTIC TOOL                             ║\n";
echo "╚═══════════════════════════════════════════════════════════════╝\n";
echo "\n";

// Get all active locks
$stmt = $pdo->query("
    SELECT 
        tl.id as lock_id,
        tl.task_id,
        tl.lock_key,
        tl.acquired_at,
        tl.expires_at,
        tl.pid,
        tl.run_id,
        tl.status as lock_status,
        st.name as task_name,
        tr.id as run_id,
        tr.status as run_status,
        tr.finished_at,
        TIMESTAMPDIFF(SECOND, tl.acquired_at, NOW()) as age_seconds,
        TIMESTAMPDIFF(SECOND, NOW(), tl.expires_at) as ttl_seconds
    FROM task_locks tl
    LEFT JOIN scheduled_tasks st ON tl.task_id = st.id
    LEFT JOIN task_runs tr ON tl.run_id = tr.id
    WHERE tl.status = 'active'
    ORDER BY tl.acquired_at ASC
");

$locks = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($locks)) {
    echo "✓ No active locks found - system is clean!\n\n";
    exit(0);
}

echo "Found " . count($locks) . " active lock(s)\n";
echo str_repeat("─", 65) . "\n\n";

$stuckLocks = [];

foreach ($locks as $i => $lock) {
    $num = $i + 1;
    echo "Lock #{$num} (ID: {$lock['lock_id']})\n";
    echo str_repeat("─", 65) . "\n";
    echo "  Task: {$lock['task_name']} (ID: {$lock['task_id']})\n";
    echo "  Lock Key: {$lock['lock_key']}\n";
    echo "  PID: " . ($lock['pid'] ?? 'NULL') . "\n";
    echo "  Acquired: {$lock['acquired_at']} (" . formatDuration($lock['age_seconds']) . " ago)\n";
    echo "  Expires: {$lock['expires_at']} (" . formatDuration($lock['ttl_seconds']) . " remaining)\n";
    echo "  Run ID: " . ($lock['run_id'] ?? 'NULL') . "\n";
    
    // Diagnose the lock
    $issues = [];
    $isStuck = false;
    
    // Check 1: PID exists?
    if ($lock['pid']) {
        $pidExists = posix_kill((int)$lock['pid'], 0);
        if (!$pidExists) {
            $issues[] = "⚠️  Process (PID {$lock['pid']}) is DEAD";
            $isStuck = true;
        } else {
            echo "  Process Status: ✓ Running (PID {$lock['pid']})\n";
        }
    } else {
        $issues[] = "⚠️  No PID associated with lock";
    }
    
    // Check 2: Expired?
    if ($lock['ttl_seconds'] < 0) {
        $issues[] = "⚠️  Lock has EXPIRED";
        $isStuck = true;
    }
    
    // Check 3: Associated run finished?
    if ($lock['run_id']) {
        echo "  Associated Run: #{$lock['run_id']} - Status: {$lock['run_status']}\n";
        if (in_array($lock['run_status'], ['completed', 'failed', 'stale'])) {
            $issues[] = "⚠️  Associated task run is FINISHED ({$lock['run_status']})";
            echo "  Run Finished: {$lock['finished_at']}\n";
            $isStuck = true;
        }
    } else {
        $issues[] = "⚠️  No associated run_id";
    }
    
    // Check 4: Very old?
    if ($lock['age_seconds'] > 3600) { // Older than 1 hour
        $issues[] = "⚠️  Lock is very old (" . formatDuration($lock['age_seconds']) . ")";
    }
    
    if (!empty($issues)) {
        echo "\n  ISSUES DETECTED:\n";
        foreach ($issues as $issue) {
            echo "    $issue\n";
        }
        
        if ($isStuck) {
            echo "  → This lock appears to be STUCK!\n";
            $stuckLocks[] = $lock['lock_id'];
        }
    } else {
        echo "  Status: ✓ Lock appears normal\n";
    }
    
    echo "\n";
}

// Summary
echo str_repeat("═", 65) . "\n";
echo "SUMMARY\n";
echo str_repeat("═", 65) . "\n";
echo "Total active locks: " . count($locks) . "\n";
echo "Stuck locks: " . count($stuckLocks) . "\n";

if (!empty($stuckLocks)) {
    echo "\nStuck lock IDs: " . implode(', ', $stuckLocks) . "\n";
}

// Auto-release if requested
if ($autoRelease && !empty($stuckLocks)) {
    echo "\n";
    echo str_repeat("─", 65) . "\n";
    echo "AUTO-RELEASING STUCK LOCKS...\n";
    echo str_repeat("─", 65) . "\n";
    
    foreach ($stuckLocks as $lockId) {
        if ($lockManager->releaseLock($lockId)) {
            echo "✓ Released lock ID: $lockId\n";
        } else {
            echo "✗ Failed to release lock ID: $lockId\n";
        }
    }
    
    echo "\nDone!\n";
}

if ($forceAll) {
    echo "\n";
    echo str_repeat("─", 65) . "\n";
    echo "⚠️  FORCE RELEASING ALL ACTIVE LOCKS...\n";
    echo str_repeat("─", 65) . "\n";
    
    foreach ($locks as $lock) {
        if ($lockManager->releaseLock($lock['lock_id'])) {
            echo "✓ Released lock ID: {$lock['lock_id']}\n";
        } else {
            echo "✗ Failed to release lock ID: {$lock['lock_id']}\n";
        }
    }
    
    echo "\nDone!\n";
}

if (!$autoRelease && !$forceAll && !empty($stuckLocks)) {
    echo "\n";
    echo "To automatically release stuck locks, run:\n";
    echo "  php lock_diagnostic.php --release\n";
    echo "\n";
    echo "To force-release ALL locks (use carefully!), run:\n";
    echo "  php lock_diagnostic.php --force-all\n";
}

echo "\n";

function formatDuration(int $seconds): string
{
    if ($seconds < 0) {
        return abs($seconds) . "s overdue";
    }
    if ($seconds < 60) {
        return $seconds . "s";
    }
    if ($seconds < 3600) {
        return floor($seconds / 60) . "m " . ($seconds % 60) . "s";
    }
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    return "{$hours}h {$minutes}m";
}
