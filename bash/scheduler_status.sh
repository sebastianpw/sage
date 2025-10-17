#!/bin/bash

# System Status Check Script
# Shows the current status of scheduler and completion tracker

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd
)"

echo "================================================"
echo "SPW Scheduler System Status"
echo "================================================"

# Check Scheduler
echo ""
echo "--- Scheduler ---"
SCHEDULER_PIDS=$(pgrep -f 'scheduler_start.php')
if [ -z "$SCHEDULER_PIDS" ]; then
    echo "Status: ✗ NOT RUNNING"
else
    echo "Status: ✓ RUNNING"
    echo "PIDs: $SCHEDULER_PIDS"
    
    # Check PID file
    if [ -f "$SCRIPT_DIR/scheduler.pid" ]; then
        PID_FILE_PID=$(cat "$SCRIPT_DIR/scheduler.pid")
        echo "PID File: $PID_FILE_PID"
    fi
    
    # Show last heartbeat
    HEARTBEAT=$(php -r "require '$SCRIPT_DIR/../public/bootstrap.php'; \$hb = new \App\Core\TaskHeartbeat(); echo \$hb->getLastHeartbeat() ?? 'Never';" 2>/dev/null)
    echo "Last Heartbeat: $HEARTBEAT"
fi

# Check Completion Tracker
echo ""
echo "--- Completion Tracker ---"
TRACKER_PIDS=$(pgrep -f 'completion_tracker_start.php')
if [ -z "$TRACKER_PIDS" ]; then
    echo "Status: ✗ NOT RUNNING"
else
    echo "Status: ✓ RUNNING"
    echo "PIDs: $TRACKER_PIDS"
    
    # Check PID file
    if [ -f "$SCRIPT_DIR/completion_tracker.pid" ]; then
        PID_FILE_PID=$(cat "$SCRIPT_DIR/completion_tracker.pid")
        echo "PID File: $PID_FILE_PID"
    fi
fi

# Check active locks
echo ""
echo "--- Active Locks ---"
ACTIVE_LOCKS=$(php -r "
require '$SCRIPT_DIR/../public/bootstrap.php';
\$pdo = \App\Core\SpwBase::getInstance()->getPDO();
\$stmt = \$pdo->query('SELECT COUNT(*) FROM task_locks WHERE status = \"active\"');
echo \$stmt->fetchColumn();
" 2>/dev/null)
echo "Active Locks: ${ACTIVE_LOCKS:-0}"

# Check running tasks
echo ""
echo "--- Running Tasks ---"
RUNNING_TASKS=$(php -r "
require '$SCRIPT_DIR/../public/bootstrap.php';
\$pdo = \App\Core\SpwBase::getInstance()->getPDO();
\$stmt = \$pdo->query('SELECT COUNT(*) FROM task_runs WHERE status IN (\"pending\", \"running\")');
echo \$stmt->fetchColumn();
" 2>/dev/null)
echo "Running Tasks: ${RUNNING_TASKS:-0}"

# Show recent task completions
echo ""
echo "--- Recent Completions (Last 5) ---"
php -r "
require '$SCRIPT_DIR/../public/bootstrap.php';
\$pdo = \App\Core\SpwBase::getInstance()->getPDO();
\$stmt = \$pdo->query('
    SELECT st.name, tr.finished_at, tr.status, tr.exit_code 
    FROM task_runs tr
    JOIN scheduled_tasks st ON tr.task_id = st.id
    WHERE tr.finished_at IS NOT NULL
    ORDER BY tr.finished_at DESC
    LIMIT 5
');
while (\$row = \$stmt->fetch(PDO::FETCH_ASSOC)) {
    printf(\"  %s | %s | %s (exit: %s)\n\", 
        \$row['finished_at'], 
        str_pad(\$row['name'], 30), 
        \$row['status'], 
        \$row['exit_code'] ?? 'N/A'
    );
}
" 2>/dev/null

echo ""
echo "================================================"
echo "Log Files:"
echo "  Scheduler: $SCRIPT_DIR/../logs/scheduler_$(date +%Y-%m-%d).log"
echo "  General: $SCRIPT_DIR/../logs/$(date +%Y-%m-%d).log"
echo "================================================"
