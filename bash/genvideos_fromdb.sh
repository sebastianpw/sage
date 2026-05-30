#!/bin/bash

# ==============================================================================
# SAGE Video Batch Wrapper
# Usage: ./genvideos_fromdb.sh entity_type [batch_limit]
# Example: ./genvideos_fromdb.sh animatics 5
# Example: ./genvideos_fromdb.sh composites 1
# ==============================================================================

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MYSQL_ARGS=$("$SCRIPT_DIR"/db_name.sh main-conn)
CLIENT_SCRIPT="$SCRIPT_DIR/genvideo_db.sh"

ENTITY_TYPE="$1"       # e.g. animatics or composites
BATCH_LIMIT="$2"       # optional

if [ -z "$ENTITY_TYPE" ]; then
  echo "Usage: $0 entity_type [batch_limit]"
  exit 1
fi

if [ ! -f "$CLIENT_SCRIPT" ]; then
  echo "ERROR: Client script not found at $CLIENT_SCRIPT"
  exit 1
fi

# -----------------------------
# 1. Create a new map_run
# -----------------------------
MAP_RUN_ID=$(mysql $MYSQL_ARGS -N -e "
    INSERT INTO map_runs (entity_type, note) 
    VALUES ('$ENTITY_TYPE', 'Batch Video Generation (Seedance)'); 
    SELECT LAST_INSERT_ID();
")

echo "--------------------------------------------------------"
echo "Starting Video Batch Run"
echo "Entity Type: $ENTITY_TYPE"
echo "Map Run ID:  $MAP_RUN_ID"
echo "--------------------------------------------------------"

# -----------------------------
# 2. Identify correct regeneration column
# -----------------------------
# Animatics uses 'regenerate_videos', Composites uses 'regenerate_images' (based on your schema)
# We will check the entity type to decide.
REGEN_COL="regenerate_videos"
if [ "$ENTITY_TYPE" == "composites" ]; then
    REGEN_COL="regenerate_images"
fi

# -----------------------------
# 3. Fetch entities
# -----------------------------
# We fetch ID and Description (or Name) to use as the base prompt
# Note: For Animatics, we prioritize img2img_prompt if it exists, handled in client, 
# but here we just need the list.

SQL_QUERY="SELECT id, name FROM $ENTITY_TYPE WHERE $REGEN_COL=1"

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
  echo "No '$ENTITY_TYPE' flagged for regeneration."
  exit 0
fi

echo "Found $TOTAL_COUNT items to process."

# -----------------------------
# 4. Process Loop
# -----------------------------
for i in "${!IDS[@]}"; do
  ENTITY_ID="${IDS[$i]}"
  ENTITY_NAME="${NAMES[$i]}"
  CURRENT_NUM=$((i + 1))
  
  echo ""
  echo "[$CURRENT_NUM / $TOTAL_COUNT] Processing $ENTITY_TYPE #$ENTITY_ID ($ENTITY_NAME)..."

  # Call the Client Script
  # Format: ./genvideo_db.sh MAP_RUN_ID ENTITY_TYPE ENTITY_ID
  "$CLIENT_SCRIPT" "$MAP_RUN_ID" "$ENTITY_TYPE" "$ENTITY_ID"
  CLIENT_EXIT_CODE=$?

  if [ $CLIENT_EXIT_CODE -eq 0 ]; then
      # Update active_map_run_id on success
      mysql $MYSQL_ARGS -e "
          UPDATE $ENTITY_TYPE 
          SET active_map_run_id = $MAP_RUN_ID
          WHERE id = $ENTITY_ID;
      "
      echo "✓ Success. Updated active_map_run_id."
  else
      echo "✗ Failed."
  fi

done

echo "--------------------------------------------------------"
echo "Batch Run Complete."
echo "--------------------------------------------------------"
