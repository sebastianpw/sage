#!/bin/bash
# dumpcode.sh v2.9
# Dumps code files and main DB table structures into a single output file.
# Records the recipe metadata into the system DB.
#
# Usage: ./dumpcode.sh [--name "Recipe Name"] <output_file> <file1> [db:table1] [<file2> ...]
#
#   - Recipe data is stored in the 'sys-conn' database.
#   - `db:tablename` ingredients are dumped from the 'main-conn' database.

# --- Boilerplate and Configuration ---

ORIGINAL_ARGS=("$@")
set -e
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# --- Database Connection Setup ---
if [ ! -f "$SCRIPT_DIR/db_name.sh" ]; then
    echo "ERROR: Database configuration script db_name.sh not found. Aborting."
    exit 1
fi

MYSQL_CHARSET_OPT="--default-character-set=utf8mb4"
echo "Connecting to System DB (for recipe storage)..."
MYSQL_SYS_CONN_ARGS="$("$SCRIPT_DIR"/db_name.sh sys-conn) $MYSQL_CHARSET_OPT"
if [ -z "$MYSQL_SYS_CONN_ARGS" ]; then
    echo "ERROR: Failed to get sys-conn configuration from db_name.sh. Aborting."
    exit 1
fi

echo "Connecting to Main DB (for 'db:' ingredients)..."
MYSQL_MAIN_CONN_ARGS=$("$SCRIPT_DIR"/db_name.sh main-conn)
if [ -z "$MYSQL_MAIN_CONN_ARGS" ]; then
    echo "ERROR: Failed to get main-conn configuration from db_name.sh. Aborting."
    exit 1
fi
MAIN_DB_NAME=$(echo "$MYSQL_MAIN_CONN_ARGS" | grep -oE '[^ ]+$')
MYSQL_MAIN_ADMIN_ARGS="$(echo "$MYSQL_MAIN_CONN_ARGS" | sed "s/ ${MAIN_DB_NAME}$//") $MYSQL_CHARSET_OPT"

# --- Load Project Root & Check Prerequisites ---
if [ -f "$SCRIPT_DIR/load_root.sh" ]; then
  source "$SCRIPT_DIR/load_root.sh"
fi
if [ -z "$PROJECT_ROOT" ]; then
  echo "ERROR: PROJECT_ROOT is not set. Aborting."; exit 1
fi
if ! command -v mysqldump &> /dev/null; then
    echo "ERROR: mysqldump command could not be found. Aborting."; exit 1
fi

# --- Helper Functions (for System DB) ---
function mysql_query_sys() {
  mysql $MYSQL_SYS_CONN_ARGS -N -s -e "$1"
}

function mysql_insert_and_get_id_sys() {
  # This function is safe for SHORT queries only.
  local query="$1; SELECT LAST_INSERT_ID();"
  mysql $MYSQL_SYS_CONN_ARGS -N -s -e "$query" | tail -n 1
}

function mysql_insert_content_and_get_id_sys() {
  # Robust way to handle potentially huge content via stdin.
  # Takes HASH as $1 and RAW CONTENT as $2.
  local hash="$1"
  local content="$2"
  (
    echo -n "INSERT INTO recipe_ingredient_snapshots (content_hash, content) VALUES ('$hash', '"
    echo -n "$content" | sed -e 's/\\/\\\\/g' -e "s/'/''/g"
    echo -n "'); SELECT LAST_INSERT_ID();"
  ) | mysql $MYSQL_SYS_CONN_ARGS -N -s | tail -n 1
}

# --- Argument Parsing ---
RECIPE_NAME=""
if [ "$1" == "--name" ]; then
  if [ -z "$2" ]; then echo "ERROR: --name option requires a value." >&2; exit 1; fi
  RECIPE_NAME="$2"
  shift 2
fi
if [ $# -lt 2 ]; then
  echo "Usage: $0 [--name \"Recipe Name\"] output_file file1|db:table1 [file2|db:table2 ...]"
  exit 1
fi
OUTPUT_FILE_ARG="$1"
shift
INGREDIENT_ARGS=("$@")
if [ -z "$RECIPE_NAME" ]; then
  RECIPE_NAME=$(basename "$OUTPUT_FILE_ARG")
fi

# --- Rerun Command Generation ---
RERUN_COMMAND="./$(realpath --relative-to="$PWD" "${BASH_SOURCE[0]}")"
for arg in "${ORIGINAL_ARGS[@]}"; do
    if [[ "$arg" == *" "* ]]; then RERUN_COMMAND+=" \"$arg\""; else RERUN_COMMAND+=" $arg"; fi
done

# --- Pre-flight Checks ---
for arg in "${INGREDIENT_ARGS[@]}"; do
  if [[ "$arg" != db:* && ! -f "$arg" ]]; then echo "ERROR: Input file not found: $arg"; exit 1; fi
done
cd "$PROJECT_ROOT"
OUTPUT_FILE_REL=$(realpath --relative-to="$PROJECT_ROOT" "$OLDPWD/$OUTPUT_FILE_ARG")
cd "$OLDPWD" > /dev/null
if [ -f "$OUTPUT_FILE_ARG" ]; then
  read -p "Warning: $OUTPUT_FILE_ARG exists! Overwrite? (y/N) " RESP
  if [[ ! "$RESP" =~ ^[yY] ]]; then echo "Cancelled."; exit 0; fi
fi

echo "--- Starting Recipe Creation ---"
# ... (rest of the script is identical until the main loop)

# --- Database Operations (using System DB connection) ---
echo "Step 1: Checking for recipe group '$RECIPE_NAME' in System DB..."
GROUP_ID=$(mysql_query_sys "SELECT id FROM recipe_groups WHERE name = '$RECIPE_NAME';")
if [ -z "$GROUP_ID" ]; then
  echo " > Group not found. Creating..."
  GROUP_ID=$(mysql_insert_and_get_id_sys "INSERT INTO recipe_groups (name) VALUES ('$RECIPE_NAME')")
else
  echo " > Found group with ID: $GROUP_ID"
fi

echo "Step 2: Creating new recipe instance in System DB..."
RERUN_COMMAND_SQL=$(echo "$RERUN_COMMAND" | sed "s/'/''/g")
RECIPE_ID=$(mysql_insert_and_get_id_sys "INSERT INTO recipes (recipe_group_id, output_filename, rerun_command) VALUES ($GROUP_ID, '$OUTPUT_FILE_REL', '$RERUN_COMMAND_SQL')")
echo " > Created recipe instance with ID: $RECIPE_ID"

echo "Step 3: Processing ingredients..."
DISPLAY_ORDER=0
for arg in "${INGREDIENT_ARGS[@]}"; do
  CONTENT_HASH=""
  SOURCE_ID="$arg"
  if [[ "$arg" == db:* ]]; then
    TABLE_NAME=${arg#db:}
    echo " > Processing DB table '$TABLE_NAME' from Main DB..."
    CONTENT=$(mysqldump $MYSQL_MAIN_ADMIN_ARGS --no-data --compact "$MAIN_DB_NAME" "$TABLE_NAME" 2>/dev/null)
    if [ -z "$CONTENT" ]; then
        echo "ERROR: Failed to dump table '$TABLE_NAME' from Main DB." >&2
        mysql $MYSQL_SYS_CONN_ARGS -e "DELETE FROM recipes WHERE id = $RECIPE_ID;" # Rollback
        echo "Recipe creation aborted and rolled back."; exit 1
    fi
    CONTENT_HASH=$(echo -n "$CONTENT" | sha256sum | awk '{print $1}')
  else
    abs_path=$(realpath "$arg")
    rel_path=$(realpath --relative-to="$PROJECT_ROOT" "$abs_path")
    echo " > Processing file: $rel_path"
    CONTENT_HASH=$(sha256sum "$abs_path" | awk '{print $1}')
    SOURCE_ID="$rel_path"
  fi

  SNAPSHOT_ID=$(mysql_query_sys "SELECT id FROM recipe_ingredient_snapshots WHERE content_hash = '$CONTENT_HASH';")
  if [ -z "$SNAPSHOT_ID" ]; then
    echo "   - New version detected. Inserting into System DB snapshots."
    
    if [[ "$arg" != db:* ]]; then
      # For files, read directly from the file path
      # --- SCOPE FIX: Removed the 'local' keyword ---
      file_content=$(cat "$abs_path")
      SNAPSHOT_ID=$(mysql_insert_content_and_get_id_sys "$CONTENT_HASH" "$file_content")
    else
      # For DB content, use the variable we already have
      SNAPSHOT_ID=$(mysql_insert_content_and_get_id_sys "$CONTENT_HASH" "$CONTENT")
    fi

  else
    echo "   - Existing version found. Re-using snapshot ID: $SNAPSHOT_ID."
  fi

  mysql_query_sys "INSERT INTO recipe_ingredients (recipe_id, snapshot_id, source_filename, display_order) VALUES ($RECIPE_ID, $SNAPSHOT_ID, '$SOURCE_ID', $DISPLAY_ORDER);"
  echo "   - Linked to recipe."

  DISPLAY_ORDER=$((DISPLAY_ORDER + 1))
done

# --- File Generation ---
echo "Step 4: Generating output file '$OUTPUT_FILE_ARG'..."
# ... (rest of the script is identical)
> "$OUTPUT_FILE_ARG"
{
  echo "# To regenerate this exact file, run the following command from the project root:"
  echo "# $RERUN_COMMAND"
  echo ""
  echo "# Ingredients included in this recipe:"
  for arg in "${INGREDIENT_ARGS[@]}"; do echo "# - $arg"; done
  echo ""
  echo "# ------------------------------------------------------------------------------"
  echo ""
} >> "$OUTPUT_FILE_ARG"
for ((i=0; i<${#INGREDIENT_ARGS[@]}; i++)); do
  ARG="${INGREDIENT_ARGS[$i]}"
  if [[ "$ARG" == db:* ]]; then
    TABLE_NAME=${ARG#db:}
    echo "db:$TABLE_NAME" >> "$OUTPUT_FILE_ARG"; echo "" >> "$OUTPUT_FILE_ARG"
    mysqldump $MYSQL_MAIN_ADMIN_ARGS --no-data --compact "$MAIN_DB_NAME" "$TABLE_NAME" >> "$OUTPUT_FILE_ARG"
  else
    echo "$ARG" >> "$OUTPUT_FILE_ARG"; echo "" >> "$OUTPUT_FILE_ARG"
    cat "$ARG" >> "$OUTPUT_FILE_ARG"
  fi
  if [ $i -lt $((${#INGREDIENT_ARGS[@]}-1)) ]; then
    NEXT_ARG="${INGREDIENT_ARGS[$((i+1))]}"
    echo -e "\n\n==== NEXT: $NEXT_ARG ====\n\n" >> "$OUTPUT_FILE_ARG"
  fi
done
DOWNLOADS="$HOME/Download"
if [ -d "$DOWNLOADS" ]; then
    cp "$OUTPUT_FILE_ARG" "$DOWNLOADS/"; echo "Dumped and copied to $DOWNLOADS"
else
    echo "Dumped to $OUTPUT_FILE_ARG"
fi

echo "--- Recipe saved successfully! ---"

