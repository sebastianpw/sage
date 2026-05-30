#!/bin/bash

# ==============================================================================
# SAGE Video Enqueuer (Fills the map_run_queue)
# Usage: ./genvideos_queue.sh entity_type [batch_limit]
# Example: ./genvideos_queue.sh animatics 100
# Example: ./genvideos_queue.sh composites 10
# ==============================================================================

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MYSQL_ARGS=$("$SCRIPT_DIR"/db_name.sh main-conn)

ENTITY_TYPE="$1"       # e.g. animatics or composites
BATCH_LIMIT="$2"       # optional

if [ -z "$ENTITY_TYPE" ]; then
  echo "Usage: $0 entity_type [batch_limit]"
  exit 1
fi

# -----------------------------
# 1. Identify correct regeneration column
# -----------------------------
REGEN_COL="regenerate_videos"
if [ "$ENTITY_TYPE" == "composites" ]; then
    REGEN_COL="regenerate_images"
fi

# -----------------------------
# 2. Fetch entities
# -----------------------------
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

# -----------------------------
# 3. Create a new map_run
# -----------------------------
MAP_RUN_ID=$(mysql $MYSQL_ARGS -N -e "
    INSERT INTO map_runs (entity_type, note) 
    VALUES ('$ENTITY_TYPE', 'Queued Video Generation ($TOTAL_COUNT items)'); 
    SELECT LAST_INSERT_ID();
")

echo "--------------------------------------------------------"
echo "Queueing Video Batch Run"
echo "Entity Type: $ENTITY_TYPE"
echo "Map Run ID:  $MAP_RUN_ID"
echo "Total Items: $TOTAL_COUNT"
echo "--------------------------------------------------------"

# -----------------------------
# 4. Fill the Queue
# -----------------------------
for i in "${!IDS[@]}"; do
  ENTITY_ID="${IDS[$i]}"
  ENTITY_NAME="${NAMES[$i]}"
  CURRENT_NUM=$((i + 1))
  
  echo "[$CURRENT_NUM / $TOTAL_COUNT] Queueing $ENTITY_TYPE #$ENTITY_ID ($ENTITY_NAME)..."

  # Insert into the queue, clear the regenerate flag, and update active_map_run_id
  # We clear the flag HERE so the user doesn't accidentally queue it twice while it waits.
  mysql $MYSQL_ARGS -e "
      INSERT INTO map_run_queue 
      (map_run_id, entity_type, entity_id, asset_type, status)
      VALUES 
      ($MAP_RUN_ID, '$ENTITY_TYPE', $ENTITY_ID, 'videos', 'pending');

      UPDATE $ENTITY_TYPE 
      SET active_map_run_id = $MAP_RUN_ID, $REGEN_COL = 0
      WHERE id = $ENTITY_ID;
  "
done

echo "--------------------------------------------------------"
echo "✓ Successfully queued $TOTAL_COUNT tasks."
echo "They will be processed by the genvideo_queue.sh worker."
echo "--------------------------------------------------------"
