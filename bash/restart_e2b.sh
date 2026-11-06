#!/bin/bash

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DB_DATADIR=$("$SCRIPT_DIR/db_name.sh" datadir)

echo "🚀 Starting E2B custom boot sequence..."

# 🟢 FIX PERMISSIONS (Prevents mkdir & file_put_contents errors)
echo "Fixing directory permissions..."
mkdir -p /var/www/sage/logs
mkdir -p /var/www/sage/syslogs
mkdir -p /var/www/sage/public/media/magazines

chmod -R 777 /var/www/sage/public || true
chmod -R 777 /var/www/sage/token || true
chmod -R 777 /var/www/sage/logs || true
chmod -R 777 /var/www/sage/syslogs || true
chown -R application:application /var/www/sage || chown -R www-data:www-data /var/www/sage || true

# 1. Start MariaDB
if [ "${DOCKERIZED:-0}" != "1" ]; then
    if ! pgrep -x mariadbd > /dev/null; then
        echo "Starting MariaDB..."
        mariadbd-safe --datadir="$DB_DATADIR" &
        sleep 5
    fi
fi

# 2. Restart PHP Scheduler
echo "Restarting SAGE Scheduler..."
pkill -f "scheduler_start.php" || true
sleep 1
"$SCRIPT_DIR/scheduler_startup.sh" &

# 3. Start Python API
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

echo "✅ E2B custom boot sequence finished. Handing over to supervisord..."