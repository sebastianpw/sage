#!/bin/bash

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DB_DATADIR=$("$SCRIPT_DIR/db_name.sh" datadir)

echo "🚀 Starting E2B custom boot sequence..."

# 1. Start MariaDB (if not already running via systemd)
if [ "${DOCKERIZED:-0}" != "1" ]; then
    if ! pgrep -x mariadbd > /dev/null; then
        echo "Starting MariaDB..."
        mariadbd-safe --datadir="$DB_DATADIR" &
        sleep 5
    else
        echo "MariaDB is already running."
    fi
fi

# 2. Restart PHP Scheduler (Kill old, start new)
echo "Restarting SAGE Scheduler..."
pkill -f "scheduler_start.php" || true
sleep 1
"$SCRIPT_DIR/scheduler_startup.sh" &

# 3. Start Python API in background (run_server.sh handles killing the old one)
(
    cd "$SCRIPT_DIR/../pyapi" || exit
    bash run_server.sh >> "$SCRIPT_DIR/../syslogs/pyapi_boot.log" 2>&1
) &

# 4. Kaggle Setup
KAGGLE_DIR="$SCRIPT_DIR/../token/.kaggle"
KAGGLE_FILE="$KAGGLE_DIR/kaggle.json"
mkdir -p "$KAGGLE_DIR"
chmod 777 "$KAGGLE_DIR"
if [ ! -f "$KAGGLE_FILE" ]; then echo "{}" > "$KAGGLE_FILE"; fi
chmod 777 "$KAGGLE_FILE"

# 5. NGINX and PHP-FPM are intentionally skipped! Supervisord handles them.

echo "✅ E2B custom boot sequence finished. Handing over to supervisord..."
