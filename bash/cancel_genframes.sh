#!/bin/bash
# save as: stop_genframes.sh

echo "Attempting to stop genframes_fromdb.sh..."

# Method 1: Kill by specific script name
if pkill -f "genframes_fromdb.sh"; then
    echo "✅ genframes_fromdb.sh process terminated"
else
    echo "⚠️  No genframes_fromdb.sh process found by name"
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
REMAINING=$(ps aux | grep -E "genframes_fromdb\.sh" | grep -v grep)
if [ ! -z "$REMAINING" ]; then
    echo "Force killing remaining processes..."
    pkill -9 -f "genframes_fromdb.sh"
fi

echo "Done."
