#!/bin/bash
# Dedicated boot script for Docker environments.
# This leaves restart_servers.sh untouched for live Codespace users.

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DB_DATADIR=$("$SCRIPT_DIR/db_name.sh" datadir)

echo "Starting Docker Container Boot Sequence..."

# 1. Stop any zombie processes
pkill -f "php -S" 2>/dev/null
pkill php-fpm 2>/dev/null
pkill -f "mysqld" 2>/dev/null
pkill -f "mariadbd" 2>/dev/null
pkill nginx 2>/dev/null
sleep 2

# 2. Start MariaDB
echo "Starting MariaDB..."
mariadbd-safe --datadir="$DB_DATADIR" &
sleep 10

# 3. Start PHP-FPM and NGINX
echo "Starting Web Servers..."
php-fpm &
nginx &
sleep 5

# 4. Start PHP scheduler
"$SCRIPT_DIR/scheduler_startup.sh" &
echo "PHP-FPM, NGINX, and MariaDB restarted in background."

# 5. Start PyAPI and Chroma
"$SCRIPT_DIR/../pyapi/run_server.sh" &
"$SCRIPT_DIR/../chroma-server/run_chroma.sh" &
sleep 10

# 6. Handle Kaggle JSON permissions
KAGGLE_DIR="$SCRIPT_DIR/../token/.kaggle"
KAGGLE_FILE="$KAGGLE_DIR/kaggle.json"
mkdir -p "$KAGGLE_DIR"
chmod 777 "$KAGGLE_DIR"

php <<PHP_CODE
<?php
\$kaggleFile = '$KAGGLE_FILE';
if (file_exists(\$kaggleFile)) {
    \$json = file_get_contents(\$kaggleFile);
    if (json_decode(\$json) === null) { \$json = '{}'; }
} else {
    \$json = '{}';
}
file_put_contents(\$kaggleFile, \$json);
chmod(\$kaggleFile, 0777);
echo "Kaggle JSON restored at \$kaggleFile\n";
?>
PHP_CODE

echo "======================================================="
echo "✅ All Docker services started! Container is fully ready."
echo "======================================================="

# Ensure log directories and files exist so tail doesn't crash
mkdir -p /var/www/sage/logs
mkdir -p /var/log/nginx
touch /var/www/sage/logs/error.log
touch /var/log/nginx/error.log

# 7. Keep the container alive forever and stream logs to stdout
tail -f /var/www/sage/logs/error.log /var/log/nginx/error.log /dev/null

