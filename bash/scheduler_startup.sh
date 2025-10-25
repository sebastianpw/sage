#!/bin/bash

# Combined Startup Script for Scheduler and Completion Tracker
# Place this in your Termux startup or run it manually

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

source "$SCRIPT_DIR/load_root.sh"

echo "================================================"
echo "Starting SPW Scheduler System"
echo "================================================"

# Stop any existing processes first
echo "Stopping existing scheduler..."
php $SCRIPT_DIR/../public/scheduler_stop.php 2>/dev/null

echo "Stopping existing completion tracker..."
php $SCRIPT_DIR/../public/completion_tracker_stop.php 2>/dev/null

# Wait a moment for processes to fully terminate
sleep 3

# Start the main scheduler
echo "Starting scheduler..."
php $SCRIPT_DIR/../public/scheduler_start.php &

SCHEDULER_PID=$!

sleep 2

# Verify scheduler started
if ps -p $SCHEDULER_PID > /dev/null; then
    echo "✓ Scheduler started successfully (PID: $SCHEDULER_PID)"
else
    echo "✗ Failed to start scheduler"
    exit 1
fi

# Start the completion tracker
echo "Starting completion tracker..."
php $SCRIPT_DIR/../public/completion_tracker_start.php &
TRACKER_PID=$!

sleep 2

# Verify completion tracker started
if ps -p $TRACKER_PID > /dev/null; then
    echo "✓ Completion tracker started successfully (PID: $TRACKER_PID)"
else
    echo "✗ Failed to start completion tracker"
    exit 1
fi

echo "================================================"
echo "SPW Scheduler System Started"
echo "================================================"
echo "Scheduler PID: $SCHEDULER_PID"
echo "Completion Tracker PID: $TRACKER_PID"
echo ""
echo "To stop:"
echo "  php $SCRIPT_DIR/../public/scheduler_stop.php"
echo "  php $SCRIPT_DIR/../public/completion_tracker_stop.php"
echo "================================================"

exit 0


