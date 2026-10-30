#!/bin/bash
# save as: cancel_genworker_queue.sh

echo "Attempting to stop genworker_queue.sh..."

# Method 1: Kill by specific script name
if pkill -f "genworker_queue.sh"; then
    echo "✅ genworker_queue.sh process terminated"
else
    echo "⚠️  No genworker_queue.sh process found by name"
fi

# Method 2: If you have the scheduler PID file
if [ -f "/path/to/scheduler.pid" ]; then   # Adjust path if needed
    SCHEDULER_PID=$(cat /path/to/scheduler.pid)
    echo "Found scheduler PID: $SCHEDULER_PID"
    
    # Kill child processes of the scheduler
    CHILD_PIDS=$(pgrep -P $SCHEDULER_PID)
    if [ ! -z "$CHILD_PIDS" ]; then
        echo "Killing child processes: $CHILD_PIDS"
        kill $CHILD_PIDS 2>/dev/null
        sleep 1
        # Force kill if still running
        kill -9 $CHILD_PIDS 2>/dev/null
        echo "✅ Child processes terminated"
    else
        echo "No child processes found under scheduler"
    fi
fi

# Method 3: Find and kill any remaining processes
REMAINING=$(ps aux | grep -E "genworker_queue\.sh" | grep -v grep)
if [ ! -z "$REMAINING" ]; then
    echo "Force killing remaining processes..."
    pkill -9 -f "genworker_queue.sh"
fi

echo "Done."
