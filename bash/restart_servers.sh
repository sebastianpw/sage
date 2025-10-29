#!/bin/bash

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

DB_DATADIR=$("$SCRIPT_DIR/db_name.sh" datadir)


check_wake_lock() {
    if command -v termux-wake-lock >/dev/null 2>&1; then
        return 0
    else
        return 1
    fi
}


# Stop running PHP built-in servers
pkill -f "php -S" 2>/dev/null

# Stop php-fpm
pkill php-fpm 2>/dev/null

# Stop MariaDB/MySQL servers if not containerized
if [ "${DOCKERIZED:-0}" != "1" ]; then
    pkill -f "mysqld" 2>/dev/null
    pkill -f "mariadbd" 2>/dev/null
fi

# Stop NGINX
pkill nginx 2>/dev/null

# Give processes a moment to fully stop
sleep 2

# Start MariaDB if not containerized
if [ "${DOCKERIZED:-0}" != "1" ]; then
    # ORIGINAL invocation preserved
    mariadbd-safe --datadir="$DB_DATADIR" &
    # Wait a moment to allow MariaDB to initialize
    sleep 10
fi

# Start PHP-FPM in background
php-fpm &

# Start NGINX server
nginx &

# Give servers a moment to initialize
sleep 5

# PHP scheduler startup
"$SCRIPT_DIR/scheduler_startup.sh" &


echo "PHP-FPM, NGINX, and MariaDB restarted in background."
echo "NGINX server running at http://localhost:8080"

sleep 10

"$SCRIPT_DIR/../pyapi/run_server.sh"


check_wake_lock() {
    if command -v termux-wake-lock >/dev/null 2>&1; then
        echo "✅ termux-wake-lock is available"
        return 0
    else
        echo "⚠ termux-wake-lock is not available"
        return 1
    fi
}

if check_wake_lock; then
    termux-wake-lock
    echo "Wake lock acquired!"
else
    "$SCRIPT_DIR/cloudflared_tunnel.sh"
fi


