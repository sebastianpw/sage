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
    local proto host port user pass db
    proto="$(echo $url | grep :// | sed -e's,^\(.*://\).*,\1,g')"
    url="${url/$proto/}"
    userpass="$(echo $url | cut -d@ -f1)"
    hostportdb="$(echo $url | cut -d@ -f2)"
    user="$(echo $userpass | cut -d: -f1)"
    pass="$(echo $userpass | cut -d: -f2)"
    host="$(echo $hostportdb | cut -d: -f1)"
    portdb="$(echo $hostportdb | cut -d/ -f2)"
    port="$(echo $portdb | cut -d/ -f1 | cut -d? -f1)"
    db="$(echo $portdb | cut -d/ -f2 | cut -d? -f1)"
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
