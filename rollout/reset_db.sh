#!/bin/bash
# Reset database and re-import SQL dumps
# Loads credentials dynamically from ../.env.local

set -euo pipefail

# ===============================
# SAGE DEV BANNER - DB RESET
# ===============================

cat << "EOF"
   ___  ___   ___  ___  ____
  / _ \/ __/ / __// __//_  /
 / , _/ _/  _\ \ / _/   / / 
/_/|_/___/ /___//___/  /_/  
EOF

RED='\033[0;31m'
NC='\033[0m' # no color

echo -e "${RED}WARNING: This script will DROP your current databases and re-import them from scratch!${NC}"
echo -e "${RED}All current data will be permanently LOST. For dev containers ONLY!${NC}"
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

# ===============================
# 1. DROP EXISTING DATABASES
# ===============================
echo "Using root shell to drop existing databases..."
# Using the root_pw configured in the main rollout script
mysql -u root -proot_pw -e "DROP DATABASE IF EXISTS \`$MAIN_DB\`;"
mysql -u root -proot_pw -e "DROP DATABASE IF EXISTS \`$SYS_DB\`;"
mysql -u root -proot_pw -e "DROP DATABASE IF EXISTS \`$WN_DB\`;"
echo "Databases dropped successfully."

# ===============================
# 2. RECREATE DATABASES
# ===============================
create_db_if_not_exists() {
    local user="$1"
    local pass="$2"
    local host="$3"
    local port="${4:-3306}"
    local db="$5"
    echo "Ensuring database $db exists..."
    # Fallback to root to recreate them to avoid any privilege issues after dropping
    mysql -u root -proot_pw -e "CREATE DATABASE IF NOT EXISTS \`$db\` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"
}

create_db_if_not_exists "$MAIN_USER" "$MAIN_PASS" "$MAIN_HOST" "$MAIN_PORT" "$MAIN_DB"
create_db_if_not_exists "$SYS_USER" "$SYS_PASS" "$SYS_HOST" "$SYS_PORT" "$SYS_DB"
create_db_if_not_exists "$WN_USER" "$WN_PASS" "$WN_HOST" "$WN_PORT" "$WN_DB"

# ===============================
# 3. IMPORT SQL DUMPS
# ===============================

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

echo "Structure and data imports finished successfully!"

cat << "EOF"
           __          
  ___ ___ / /___ _____ 
 (_-</ -_) __/ // / _ \
/___/\__/\__/\_,_/ .__/
 ___/ /__  ___  /_/    
/ _  / _ \/ _ \/ -_)   
\_,_/\___/_//_/\__/    
                       
EOF

echo "Database Reset complete!"


