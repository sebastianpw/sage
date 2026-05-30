#!/bin/bash

# ==============================================================================
# SAGE Liplab Batch Wrapper
# Usage: ./genlips_fromdb.sh composites [batch_limit]
# ==============================================================================

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MYSQL_ARGS=$("$SCRIPT_DIR"/db_name.sh main-conn)
CLIENT_SCRIPT="$SCRIPT_DIR/genlip_db.sh"

ENTITY_TYPE="${1:-composites}"
BATCH_LIMIT="$2"

if [ ! -f "$CLIENT_SCRIPT" ]; then
  echo "ERROR: Client script not found at $CLIENT_SCRIPT"
  exit 1
fi

# 1. Create Map Run
MAP_RUN_ID=$(mysql $MYSQL_ARGS -N -e "
    INSERT INTO map_runs (entity_type, note) 
    VALUES ('$ENTITY_TYPE', 'Batch Liplab Generation'); 
    SELECT LAST_INSERT_ID();
")

echo "--------------------------------------------------------"
echo "Starting Liplab Batch Run ($MAP_RUN_ID)"
echo "--------------------------------------------------------"

# 2. Fetch Entities
# Assumes 'regenerate_images=1' is the trigger. 
# Or you could add a specific column 'regenerate_liplab=1' if you want to separate triggers.
SQL_QUERY="SELECT id, name FROM $ENTITY_TYPE WHERE regenerate_images=1"

if [ -n "$BATCH_LIMIT" ] && [ "$BATCH_LIMIT" -gt 0 ]; then
  SQL_QUERY="$SQL_QUERY LIMIT $BATCH_LIMIT"
fi

declare -a IDS
declare -a NAMES

while IFS=$'\t' read -r id name; do
  IDS+=("$id")
  NAMES+=("$name")
done < <(mysql $MYSQL_ARGS -N -e "$SQL_QUERY")

TOTAL_COUNT=${#IDS[@]}

if [ "$TOTAL_COUNT" -eq 0 ]; then
  echo "No items flagged for regeneration."
  exit 0
fi

# 3. Process Loop
for i in "${!IDS[@]}"; do
  ENTITY_ID="${IDS[$i]}"
  ENTITY_NAME="${NAMES[$i]}"
  CURRENT_NUM=$((i + 1))
  
  echo ""
  echo "[$CURRENT_NUM / $TOTAL_COUNT] Processing #$ENTITY_ID ($ENTITY_NAME)..."

  "$CLIENT_SCRIPT" "$MAP_RUN_ID" "$ENTITY_ID" "$ENTITY_TYPE"
  CLIENT_EXIT_CODE=$?

  if [ $CLIENT_EXIT_CODE -eq 0 ]; then
      mysql $MYSQL_ARGS -e "
          UPDATE $ENTITY_TYPE 
          SET active_map_run_id = $MAP_RUN_ID
          WHERE id = $ENTITY_ID;
      "
      echo "✓ Success."
  else
      echo "✗ Failed."
  fi
done

echo "Batch Run Complete."
