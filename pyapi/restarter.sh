#!/bin/bash

# 1. Move to the directory where this script lives (pyapi)
cd "$(dirname "$0")"

# 2. Define paths
# Venv python inside pyapi
PYTHON_BIN="./venv/bin/python3"
# Sibling syslogs folder
LOG_FILE="../syslogs/restarter.log"

# 3. Ensure sibling log directory exists
mkdir -p "../syslogs"

# 4. Check for existing process
if pgrep -f "restarter.py" > /dev/null; then
    echo "⚠️  Restarter service is already running."
    exit 0
fi

# 5. Run in background (nohup)
#    Redirects stdout/stderr to the sibling log file
nohup $PYTHON_BIN restarter.py > "$LOG_FILE" 2>&1 &

echo "✅ Restarter service started in background on port 8010."
echo "   PID: $!"
echo "   Log: $(realpath $LOG_FILE)"
