#!/bin/bash

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DB_DATADIR=$("$SCRIPT_DIR/../bash/db_name.sh" datadir)

# Load .env.local variables
ENV_FILE="$(dirname "$0")/../.env.local"
if [ ! -f "$ENV_FILE" ]; then
    echo ".env.local not found at $ENV_FILE"
    exit 1
fi

export $(grep -v '^#' "$ENV_FILE" | xargs)

# Parse DATABASE_WORDNET_URL
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

# PARSE WORDNET DATABASE URL
read WN_USER WN_PASS WN_HOST WN_PORT WN_DB <<< $(parse_url "$DATABASE_WORDNET_URL")

# Create WordNet database if it doesn't exist
create_db_if_not_exists "$WN_USER" "$WN_PASS" "$WN_HOST" "$WN_PORT" "$WN_DB"

# Import WordNet database
echo "Importing WordNet database $WN_DB..."

mysql -h "$WN_HOST" -P "${WN_PORT:-3306}" -u "$WN_USER" -p"$WN_PASS" "$WN_DB" < "$SCRIPT_DIR/init_wordnet_structure.sql"
mysql -h "$WN_HOST" -P "${WN_PORT:-3306}" -u "$WN_USER" -p"$WN_PASS" "$WN_DB" < "$SCRIPT_DIR/init_wordnet_data.sql"

echo "Wordnet import finished."


