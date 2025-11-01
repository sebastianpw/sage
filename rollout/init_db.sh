#!/bin/bash
# Initial rollout database import
# Loads credentials dynamically from ../.env.local
# Also ensures databases exist

set -euo pipefail

# ===============================
# SAGE DEV BANNER
# ===============================

cat << "EOF"
   _______  _________
  / __/ _ |/ ___/ __/
 _\ \/ __ / (_ / _/
/___/_/ |_\___/___/
  / _ \/ __/ | / /
 / // / _/ | |/ /
/____/___/ |___/
EOF

RED='\033[0;31m'
NC='\033[0m' # no color

echo -e "${RED}WARNING: This script will install/uninstall packages and is for GitHub Codespaces / dev containers ONLY!${NC}"
echo -e "${RED}Do NOT run on a production server unless you know what you are doing.${NC}"
echo ""

# Ask for explicit confirmation (accept y/Y too)
read -p "Type YES or y to continue: " confirm
confirm_lower=$(echo "$confirm" | tr '[:upper:]' '[:lower:]')
if [ "$confirm_lower" != "yes" ] && [ "$confirm_lower" != "y" ]; then
  echo "Aborted."
  exit 1
fi


# ===============================
# Alright let's go...
# ===============================


SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DB_DATADIR=$("$SCRIPT_DIR/../bash/db_name.sh" datadir)

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
    DEBIAN_FRONTEND=noninteractive apt install -y mariadb-server
else
    echo "MariaDB is already installed."
fi

# install packages
DEBIAN_FRONTEND=noninteractive apt install -y ffmpeg jq python3-venv python3-pip


# Install venv PyAPI with kaggle service



# Setup Kaggle FastAPI module venv
PYAPI_DIR="$SCRIPT_DIR/../pyapi"

cd "$PYAPI_DIR" || { echo "Could not cd into $PYAPI_DIR"; exit 1; }

if [ ! -d "venv" ]; then
    echo "Creating Python venv for FastAPI Kaggle wrapper..."
    python3 -m venv venv
    "$PYAPI_DIR/venv/bin/pip" install -r requirements.txt
    echo "venv created and dependencies installed."
else
    echo "Python venv already exists. Skipping setup."
fi


# add kaggle config location
export KAGGLE_CONFIG_DIR="$SCRIPT_DIR/../token/.kaggle"


# install zrok
curl -LO https://github.com/openziti/zrok/releases/download/v1.1.10/zrok_1.1.10_linux_amd64.tar.gz

tar -xzf zrok_1.1.10_linux_amd64.tar.gz

mv zrok /usr/local/bin/
chmod +x /usr/local/bin/zrok


# install cloudflared for exposing codespace nginx
("$SCRIPT_DIR/init_cloudflared.sh")


# wait a while
sleep 10

# Start MariaDB manually if not already running
if ! pgrep -x "mariadbd" > /dev/null; then
    echo "Starting MariaDB server..."
    
    mariadbd-safe --datadir="$DB_DATADIR" &
    # Wait a moment to allow MariaDB to initialize
    sleep 10
    
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
    composer create-project phpmyadmin/phpmyadmin "$PHP_MYADMIN_DIR" \
        --no-dev --prefer-dist 2>/dev/null || true
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


cat << "EOF"
           __          
  ___ ___ / /___ _____ 
 (_-</ -_) __/ // / _ \
/___/\__/\__/\_,_/ .__/
 ___/ /__  ___  /_/    
/ _  / _ \/ _ \/ -_)   
\_,_/\___/_//_/\__/    
                       
EOF


echo "Rollout complete. (Re)Starting servers. Fasten your seat belt."

("$SCRIPT_DIR/../bash/restart_servers.sh")


