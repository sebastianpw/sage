#!/bin/bash
# Initial rollout database import
# Loads credentials dynamically from ../.env.local
# Also ensures databases exist

set -euo pipefail

# ← [Point 1] Never write pip downloads to cache (applies to any pip invocation)
export PIP_NO_CACHE_DIR=1
export PIP_DISABLE_PIP_VERSION_CHECK=1

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

# ← [Point 4] Hoist PYAPI_DIR so the trap can reference it, and install cleanup trap
PYAPI_DIR="$SCRIPT_DIR/../pyapi"

cleanup() {
  local exit_code=$?
  echo "🧹 Cleanup…"

  # pip cache (both the venv's and the global user cache)
  "$PYAPI_DIR/venv/bin/pip" cache purge 2>/dev/null || true
  rm -rf "$HOME/.cache/pip"            2>/dev/null || true
  rm -rf /root/.cache/pip              2>/dev/null || true

  # apt caches
  apt-get clean                        2>/dev/null || true
  rm -rf /var/lib/apt/lists/*          2>/dev/null || true
  rm -rf /var/cache/apt/archives/*.deb 2>/dev/null || true

  # python bytecode
  find "$PYAPI_DIR" -type d -name '__pycache__' -prune -exec rm -rf {} + 2>/dev/null || true
  find "$PYAPI_DIR" -type f -name '*.pyc' -delete 2>/dev/null || true

  # downloaded artifacts that slipped through
  rm -f "$SCRIPT_DIR/../zrok_"*.tar.gz 2>/dev/null || true
  rm -f "$SCRIPT_DIR/zrok_"*.tar.gz    2>/dev/null || true
  rm -f ./zrok_*.tar.gz                2>/dev/null || true

  # logs (safe: only truncate, don't delete)
  find /var/log -type f -exec truncate -s 0 {} \; 2>/dev/null || true

  echo "Cleanup done (exit=$exit_code)."
  exit "$exit_code"
}
trap cleanup EXIT

DB_DATADIR=$("$SCRIPT_DIR/../bash/db_name.sh" datadir)

# Load .env.local variables
ENV_FILE="$(dirname "$0")/../.env.local"
if [ ! -f "$ENV_FILE" ]; then
    echo ".env.local not found at $ENV_FILE"
    exit 1
fi

export $(grep -v '^#' "$ENV_FILE" | xargs)

# Parse DATABASE_URL, DATABASE_SYS_URL and DATABASE_WORDNET_URL
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
read WN_USER WN_PASS WN_HOST WN_PORT WN_DB <<< $(parse_url "$DATABASE_WORDNET_URL")

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
    DEBIAN_FRONTEND=noninteractive apt install -y --no-install-recommends mariadb-server   # ← [Point 3]
else
    echo "MariaDB is already installed."
fi

# install packages
DEBIAN_FRONTEND=noninteractive apt install -y --no-install-recommends ffmpeg jq python3-venv python3-pip   # ← [Point 3]

# ← [Point 3] Drop apt caches to keep image small
apt-get clean
rm -rf /var/lib/apt/lists/* /var/cache/apt/archives/*.deb



# Install venv PyAPI with kaggle service



# Setup Kaggle FastAPI module venv
# ← [Point 4] PYAPI_DIR now defined above; keep cd here
cd "$PYAPI_DIR" || { echo "Could not cd into $PYAPI_DIR"; exit 1; }

if [ ! -d "venv" ]; then
    echo "Creating Python venv for FastAPI Kaggle wrapper..."
    python3 -m venv venv
    "$PYAPI_DIR/venv/bin/pip" install --no-cache-dir -r requirements.txt   # ← [Point 1]
    echo "venv created and dependencies installed."
else
    echo "Python venv already exists. Skipping setup."
fi


# ensure kaggle token config dir exists
KAGGLE_TOKEN_DIR="$SCRIPT_DIR/../token/.kaggle"

mkdir -p "$KAGGLE_TOKEN_DIR"

# set owner for PHP / nginx
#chown www-data:www-data "$KAGGLE_TOKEN_DIR"
# TODO: in prod assign proper user and rights
chmod 777 "$KAGGLE_TOKEN_DIR" 2>/dev/null || true

# export kaggle config location
export KAGGLE_CONFIG_DIR="$KAGGLE_TOKEN_DIR"


# install zrok
# ← [Point 2] Pipe directly to tar — no tarball ever written to disk
curl -sSL https://github.com/openziti/zrok/releases/download/v1.1.10/zrok_1.1.10_linux_amd64.tar.gz \
  | tar -xz -C /usr/local/bin/ zrok
chmod +x /usr/local/bin/zrok

# install chroma vector db
("$SCRIPT_DIR/init_chroma.sh")

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
PHP_MYADMIN_DIR="$SCRIPT_DIR/../phpmyadmin"
PHP_MYADMIN_VERSION="5.2.1"

# Function to create symlinks
create_symlinks() {
    echo "Creating symbolic links…"
    ln -sfn "$PHP_MYADMIN_DIR" /var/www/sage/public/admin
    ln -sfn /var/www/sage/config/config.inc.php "$PHP_MYADMIN_DIR/config.inc.php"
    chmod 755 "$PHP_MYADMIN_DIR/config.inc.php"
    chmod 755 /var/www/sage/config/config.inc.php
}

# Install phpMyAdmin
if [ ! -d "$PHP_MYADMIN_DIR" ] || [ -z "$(ls -A "$PHP_MYADMIN_DIR" 2>/dev/null)" ]; then
    echo "Downloading full phpMyAdmin release (all-languages)…"
    rm -rf "$PHP_MYADMIN_DIR"
    mkdir -p "$PHP_MYADMIN_DIR"

    curl -L "https://files.phpmyadmin.net/phpMyAdmin/${PHP_MYADMIN_VERSION}/phpMyAdmin-${PHP_MYADMIN_VERSION}-all-languages.tar.gz" \
         | tar xz --strip-components=1 -C "$PHP_MYADMIN_DIR"
fi

# Create symlinks
create_symlinks

echo "phpMyAdmin installation complete."



# Create main and system databases if needed
create_db_if_not_exists "$MAIN_USER" "$MAIN_PASS" "$MAIN_HOST" "$MAIN_PORT" "$MAIN_DB"
create_db_if_not_exists "$SYS_USER" "$SYS_PASS" "$SYS_HOST" "$SYS_PORT" "$SYS_DB"
create_db_if_not_exists "$WN_USER" "$WN_PASS" "$WN_HOST" "$WN_PORT" "$WN_DB"


# Import main database
echo "Importing structure for main database $MAIN_DB..."
mysql -h "$MAIN_HOST" -P "${MAIN_PORT:-3306}" -u "$MAIN_USER" -p"$MAIN_PASS" "$MAIN_DB" < "$SCRIPT_DIR/init_main_structure.sql"

echo "Importing data for main database $MAIN_DB..."
mysql -h "$MAIN_HOST" -P "${MAIN_PORT:-3306}" -u "$MAIN_USER" -p"$MAIN_PASS" "$MAIN_DB" < "$SCRIPT_DIR/init_main_data.sql"

echo "Importing documentations for main database $MAIN_DB..."
mysql -h "$MAIN_HOST" -P "${MAIN_PORT:-3306}" -u "$MAIN_USER" -p"$MAIN_PASS" --default-character-set=utf8mb4 "$MAIN_DB" < "$SCRIPT_DIR/sage_main_docs.sql"

echo "Importing dictionaries for main database $MAIN_DB..."
mysql -h "$MAIN_HOST" -P "${MAIN_PORT:-3306}" -u "$MAIN_USER" -p"$MAIN_PASS" "$MAIN_DB" < "$SCRIPT_DIR/sage_main_dict.sql"

# Import system database
echo "Importing structure for system database $SYS_DB..."
mysql -h "$SYS_HOST" -P "${SYS_PORT:-3306}" -u "$SYS_USER" -p"$SYS_PASS" "$SYS_DB" < "$SCRIPT_DIR/init_sys_structure.sql"

echo "Importing data for system database $SYS_DB..."
mysql -h "$SYS_HOST" -P "${SYS_PORT:-3306}" -u "$SYS_USER" -p"$SYS_PASS" "$SYS_DB" < "$SCRIPT_DIR/init_sys_data.sql"

# Import WordNet database
echo "Importing structure for WordNet database $WN_DB..."
mysql -h "$WN_HOST" -P "${WN_PORT:-3306}" -u "$WN_USER" -p"$WN_PASS" "$WN_DB" < "$SCRIPT_DIR/init_wordnet_structure.sql"

echo "Importing data for WordNet database $WN_DB..."
mysql -h "$WN_HOST" -P "${WN_PORT:-3306}" -u "$WN_USER" -p"$WN_PASS" "$WN_DB" < "$SCRIPT_DIR/init_wordnet_data.sql"

echo "Structure and data imports finished."

# ← Remove imported SQL files to shrink image (typically a few hundred MB)
rm -f "$SCRIPT_DIR"/*.sql



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