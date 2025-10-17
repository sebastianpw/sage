<?php

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

use App\Core\TaskLockManager;

$lockManager = new TaskLockManager();

// Handle AJAX actions
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'];
    
    if ($action === 'fetch_locks') {
        $stmt = $pdo->query("
            SELECT 
                l.id,
                l.task_id,
                l.lock_key,
                l.acquired_at,
                l.expires_at,
                l.pid,
                l.hostname,
                l.status,
                l.run_id,
                t.name as task_name,
                TIMESTAMPDIFF(SECOND, l.acquired_at, NOW()) as age_seconds,
                TIMESTAMPDIFF(SECOND, NOW(), l.expires_at) as ttl_seconds
            FROM task_locks l
            LEFT JOIN scheduled_tasks t ON l.task_id = t.id
            WHERE l.status = 'active'
            ORDER BY l.acquired_at DESC
        ");
        
        $locks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['locks' => $locks]);
        exit;
    }
    
    if ($action === 'release_lock') {
        $lockId = (int)($_POST['lock_id'] ?? 0);
        if ($lockId > 0) {
            $success = $lockManager->releaseLock($lockId);
            echo json_encode(['success' => $success]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid lock ID']);
        }
        exit;
    }
    
    if ($action === 'force_release_task') {
        $taskId = (int)($_POST['task_id'] ?? 0);
        if ($taskId > 0) {
            $count = $lockManager->forceReleaseTaskLocks($taskId);
            echo json_encode(['success' => true, 'count' => $count]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid task ID']);
        }
        exit;
    }
    
    if ($action === 'cleanup_expired') {
        $count = $lockManager->cleanupExpiredLocks();
        echo json_encode(['success' => true, 'count' => $count]);
        exit;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Task Locks</title>
<?php echo \App\Core\SpwBase::getInstance()->getJquery(); ?>
<link rel="stylesheet" href="/css/toast.css">
<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
.container { max-width: 1400px; margin: 0 auto; }
.header { display: flex; align-items: center; gap: 15px; margin-bottom: 20px; }
.card { background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
.stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px; }
.stat-box { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px; }
.stat-box h3 { margin: 0 0 10px 0; font-size: 14px; opacity: 0.9; }
.stat-box .value { font-size: 32px; font-weight: bold; }
table { border-collapse: collapse; width: 100%; }
th, td { border: 1px solid #ddd; padding: 12px; text-align: left; font-size: 14px; }
th { background: #f8f9fa; font-weight: 600; }
.status-active { color: #28a745; font-weight: bold; }
.status-expired { color: #dc3545; }
.status-released { color: #6c757d; }
.lock-key { font-family: monospace; font-size: 12px; background: #f8f9fa; padding: 2px 6px; border-radius: 3px; }
.ttl-warning { color: #ffc107; }
.ttl-critical { color: #dc3545; font-weight: bold; }
button { padding: 8px 16px; cursor: pointer; border: none; border-radius: 4px; font-size: 14px; }
.btn-danger { background: #dc3545; color: white; }
.btn-primary { background: #007bff; color: white; }
.btn-success { background: #28a745; color: white; }
.btn-sm { padding: 4px 8px; font-size: 12px; }
.empty-state { text-align: center; padding: 40px; color: #6c757d; }
.refresh-info { font-size: 12px; color: #6c757d; margin-left: auto; }
</style>
<?php echo $eruda; ?>
</head>
<body>
<?php require "floatool.php"; ?>

<div class="container">
    <div class="header">
        <a href="scheduler_view.php" title="Back to Scheduler" style="text-decoration: none; font-size: 24px;">⬅️</a>
        <h2 style="margin: 0;">🔒 Task Locks</h2>
        <button id="cleanupBtn" class="btn-primary">Cleanup Expired</button>
        <button id="refreshBtn" class="btn-success">↻ Refresh</button>
        <div class="refresh-info">Auto-refresh: <span id="countdown">10</span>s</div>
    </div>

    <div class="stats" id="statsContainer"></div>

    <div class="card">
        <h3>Active Locks</h3>
        <div id="locksContainer">
            <div class="empty-state">Loading...</div>
        </div>
    </div>
</div>

<div id="toast-container"></div>
<script src="/js/toast.js"></script>

<script>
let autoRefreshInterval;
let countdownInterval;
let countdownSeconds = 10;

function formatDuration(seconds) {
    if (seconds < 60) return seconds + 's';
    if (seconds < 3600) return Math.floor(seconds / 60) + 'm ' + (seconds % 60) + 's';
    return Math.floor(seconds / 3600) + 'h ' + Math.floor((seconds % 3600) / 60) + 'm';
}

function loadLocks() {
    $.post('task_locks_view.php', {action: 'fetch_locks'}, function(data) {
        const locks = data.locks || [];
        
        // Update stats
        const activeLocks = locks.filter(l => l.status === 'active').length;
        const expiringLocks = locks.filter(l => l.ttl_seconds < 300 && l.ttl_seconds > 0).length;
        const uniqueTasks = new Set(locks.map(l => l.task_id)).size;
        
        $('#statsContainer').html(`
            <div class="stat-box">
                <h3>Active Locks</h3>
                <div class="value">${activeLocks}</div>
            </div>
            <div class="stat-box" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                <h3>Expiring Soon</h3>
                <div class="value">${expiringLocks}</div>
            </div>
            <div class="stat-box" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                <h3>Locked Tasks</h3>
                <div class="value">${uniqueTasks}</div>
            </div>
        `);
        
        // Render locks table
        if (locks.length === 0) {
            $('#locksContainer').html('<div class="empty-state">✅ No active locks</div>');
            return;
        }
        
        let html = '<table><thead><tr>';
        html += '<th>Task</th>';
        html += '<th>Lock Key</th>';
        html += '<th>Age</th>';
        html += '<th>TTL</th>';
        html += '<th>PID</th>';
        html += '<th>Run ID</th>';
        html += '<th>Hostname</th>';
        html += '<th>Action</th>';
        html += '</tr></thead><tbody>';
        
        locks.forEach(lock => {
            const ttlClass = lock.ttl_seconds < 60 ? 'ttl-critical' : (lock.ttl_seconds < 300 ? 'ttl-warning' : '');
            
            html += '<tr>';
            html += `<td><strong>${lock.task_name || 'Unknown'}</strong><br><small>#${lock.task_id}</small></td>`;
            html += `<td><span class="lock-key">${lock.lock_key}</span></td>`;
            html += `<td>${formatDuration(lock.age_seconds)}</td>`;
            html += `<td class="${ttlClass}">${lock.ttl_seconds > 0 ? formatDuration(lock.ttl_seconds) : 'Expired'}</td>`;
            html += `<td>${lock.pid || '-'}</td>`;
            html += `<td>${lock.run_id || '-'}</td>`;
            html += `<td>${lock.hostname || '-'}</td>`;
            html += `<td><button class="btn-danger btn-sm releaseBtn" data-lock-id="${lock.id}">Release</button></td>`;
            html += '</tr>';
        });
        
        html += '</tbody></table>';
        $('#locksContainer').html(html);
    }, 'json');
    
    resetCountdown();
}

function resetCountdown() {
    countdownSeconds = 10;
    $('#countdown').text(countdownSeconds);
}

function startAutoRefresh() {
    autoRefreshInterval = setInterval(loadLocks, 10000);
    
    countdownInterval = setInterval(() => {
        countdownSeconds--;
        $('#countdown').text(countdownSeconds);
        if (countdownSeconds <= 0) {
            countdownSeconds = 10;
        }
    }, 1000);
}

$(document).ready(function() {
    loadLocks();
    startAutoRefresh();
    
    $('#refreshBtn').click(function() {
        loadLocks();
        Toast.show('Refreshed', 'info');
    });
    
    $('#cleanupBtn').click(function() {
        $.post('task_locks_view.php', {action: 'cleanup_expired'}, function(data) {
            if (data.success) {
                Toast.show(`Cleaned up ${data.count} expired locks`, 'success');
                loadLocks();
            } else {
                Toast.show('Cleanup failed', 'error');
            }
        }, 'json');
    });
    
    $(document).on('click', '.releaseBtn', function() {
        const lockId = $(this).data('lock-id');
        
        if (!confirm('Are you sure you want to release this lock?')) return;
        
        $.post('task_locks_view.php', {action: 'release_lock', lock_id: lockId}, function(data) {
            if (data.success) {
                Toast.show('Lock released', 'success');
                loadLocks();
            } else {
                Toast.show('Failed to release lock: ' + (data.error || 'Unknown error'), 'error');
            }
        }, 'json');
    });
});
</script>

</body>
</html>
