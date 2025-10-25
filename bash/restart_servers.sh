#!/bin/bash

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

DB_DATADIR=$("$SCRIPT_DIR/db_name.sh" datadir)


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

# Termux wake-lock if not running in Docker
if [ "${DOCKERIZED:-0}" != "1" ]; then
    termux-wake-lock
else
    :  # No-op for Docker environment
fi


echo "PHP-FPM, NGINX, and MariaDB restarted in background."
echo "NGINX server running at http://localhost:8080"

sleep 10

"$SCRIPT_DIR/cloudflared_tunnel.sh"


