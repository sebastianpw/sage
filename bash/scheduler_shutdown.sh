#!/bin/bash

# Combined Shutdown Script for Scheduler and Completion Tracker

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

echo "================================================"
echo "Stopping SPW Scheduler System"
echo "================================================"

# Stop scheduler
echo "Stopping scheduler..."
php $SCRIPT_DIR/../public/scheduler_stop.php

# Stop completion tracker
echo "Stopping completion tracker..."
php $SCRIPT_DIR/../public/completion_tracker_stop.php

sleep 2

# Verify everything stopped
SCHEDULER_RUNNING=$(pgrep -f 'scheduler_start.php' | wc -l)
TRACKER_RUNNING=$(pgrep -f 'completion_tracker_start.php' | wc -l)

if [ $SCHEDULER_RUNNING -eq 0 ] && [ $TRACKER_RUNNING -eq 0 ]; then
    echo "================================================"
    echo "✓ All processes stopped successfully"
    echo "================================================"
else
    echo "================================================"
    echo "⚠ Warning: Some processes may still be running"
    echo "Scheduler processes: $SCHEDULER_RUNNING"
    echo "Tracker processes: $TRACKER_RUNNING"
    echo "================================================"
    echo "Force kill with: killall -9 php"
fi
