#!/bin/bash
# Initial rollout database import
# Loads credentials dynamically from ../.env.local
# Also ensures databases exist

set -e  # stop on errors

# Load .env.local variables
ENV_FILE="$(dirname "$0")/../.env.local"
if [ ! -f "$ENV_FILE" ]; then
    echo ".env.local not found at $ENV_FILE"
    exit 1
fi

export $(grep -v '^#' "$ENV_FILE" | xargs)

# Parse DATABASE_URL and DATABASE_SYS_URL
parse_url() {
    local url="$1"
    # remove protocol
    url="${url#*://}"
    local userpass="${url%@*}"
    local hostportdb="${url#*@}"

    local user="${userpass%%:*}"
    local pass="${userpass#*:}"

    local hostport="${hostportdb%%/*}"
    local db_and_params="${hostportdb#*/}"

    local host="${hostport%%:*}"
    local port="${hostport#*:}"
    [ "$port" = "$host" ] && port="3306"

    local db="${db_and_params%%\?*}"

    echo "$user" "$pass" "$host" "$port" "$db"
}

read MAIN_USER MAIN_PASS MAIN_HOST MAIN_PORT MAIN_DB <<< $(parse_url "$DATABASE_URL")
read SYS_USER SYS_PASS SYS_HOST SYS_PORT SYS_DB <<< $(parse_url "$DATABASE_SYS_URL")

# Function to create database if it doesn't exist
create_db_if_not_exists() {
    local user="$1"
    local pass="$2"
    local host="$3"
    local port="${4:-3306}"
    local db="$5"
    echo "Ensuring database $db exists..."
    mysql -h "$host" -P "$port" -u "$user" -p"$pass" -e "CREATE DATABASE IF NOT EXISTS \`$db\` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"
}








# Check if MariaDB is installed
if ! command -v mariadb &> /dev/null && ! command -v mysql &> /dev/null; then
    echo "MariaDB not found, installing..."
    apt update
    apt install -y mariadb-server
else
    echo "MariaDB is already installed."
fi


apt install ffmpeg jq


# Start MariaDB manually if not already running
if ! pgrep -x "mariadbd" > /dev/null; then
    echo "Starting MariaDB server..."
    mariadbd --skip-networking=0 --skip-grant-tables=0 --user=mysql &
    sleep 5  # give it a moment to start
else
    echo "MariaDB already running."
fi






# Set root password and remove default insecure users/databases

mariadb -u root <<SQL
ALTER USER 'root'@'localhost' IDENTIFIED BY 'root_pw';
DELETE FROM mysql.user WHERE User='';
DROP DATABASE IF EXISTS test;
FLUSH PRIVILEGES;
CREATE USER IF NOT EXISTS 'adminer'@'%' IDENTIFIED BY 'root_pw';
GRANT ALL PRIVILEGES ON *.* TO 'adminer'@'%' WITH GRANT OPTION;
FLUSH PRIVILEGES;
SQL




# Install phpMyAdmin
PHP_MYADMIN_DIR="$(dirname "$0")/../phpmyadmin"

if [ ! -d "$PHP_MYADMIN_DIR" ]; then
    echo "Installing phpMyAdmin..."
    composer create-project phpmyadmin/phpmyadmin "$PHP_MYADMIN_DIR" --no-dev --prefer-dist
fi

echo "Database rollout and phpMyAdmin installation complete."

# Create symbolic links
ln -s /var/www/sage/phpmyadmin /var/www/sage/public/admin
ln -s /var/www/sage/config/config.inc.php /var/www/sage/phpmyadmin/config.inc.php
chmod 755 /var/www/sage/phpmyadmin/config.inc.php
chmod 755 /var/www/sage/config/config.inc.php




# Create main and system databases if needed
create_db_if_not_exists "$MAIN_USER" "$MAIN_PASS" "$MAIN_HOST" "$MAIN_PORT" "$MAIN_DB"
create_db_if_not_exists "$SYS_USER" "$SYS_PASS" "$SYS_HOST" "$SYS_PORT" "$SYS_DB"

# Import main database
echo "Importing main database $MAIN_DB..."
mysql -h "$MAIN_HOST" -P "${MAIN_PORT:-3306}" -u "$MAIN_USER" -p"$MAIN_PASS" "$MAIN_DB" < "$(dirname "$0")/init_main.sql"

# Import system database
echo "Importing system database $SYS_DB..."
mysql -h "$SYS_HOST" -P "${SYS_PORT:-3306}" -u "$SYS_USER" -p"$SYS_PASS" "$SYS_DB" < "$(dirname "$0")/init_sys.sql"

echo "Rollout complete."
