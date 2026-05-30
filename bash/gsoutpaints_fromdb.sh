#!/bin/bash

# ==============================================================================
# SAGE Green Screen Outpaint Wrapper
# Batch processes outpainting for entities flagged with regenerate_images=1.
# Delegates the source frame lookup (img2img_frame_id) to the client script.
# ==============================================================================

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MYSQL_ARGS=$("$SCRIPT_DIR"/db_name.sh main-conn)

# Arguments
ENTITY_TYPE="$1"       # e.g., character, anima
WIDTH="${2:-1024}"     # Default width
HEIGHT="$3"            # Optional height
POS_X="$4"             # Optional X
POS_Y="$5"             # Optional Y
COLOR="${6:-#00FF00}"  # Default Green

if [ -z "$ENTITY_TYPE" ]; then
  echo "Usage: $0 ENTITY_TYPE [WIDTH] [HEIGHT] [X] [Y] [COLOR]"
  exit 1
fi

# -----------------------------
# 1. Create a new map_run
# -----------------------------
NOTE="GS Outpaint (${WIDTH}px"
if [ -n "$HEIGHT" ]; then NOTE="${NOTE}x${HEIGHT}"; fi
NOTE="${NOTE})"

MAP_RUN_ID=$(mysql $MYSQL_ARGS -N -e "
INSERT INTO map_runs (entity_type, note) 
VALUES ('$ENTITY_TYPE', '$NOTE'); 
SELECT LAST_INSERT_ID();
")

echo "Created new map_run ID: $MAP_RUN_ID for $ENTITY_TYPE ($NOTE)"

# -----------------------------
# 2. Fetch Entities
# -----------------------------
# We only need the ID. The client script will check if img2img_frame_id exists.
SQL_QUERY="SELECT id FROM $ENTITY_TYPE WHERE regenerate_images=1"

declare -a ENTITY_IDS

while IFS=$'\t' read -r id; do
  ENTITY_IDS+=("$id")
done < <(mysql $MYSQL_ARGS -N -e "$SQL_QUERY")

# -----------------------------
# 3. Process Loop
# -----------------------------
for i in "${!ENTITY_IDS[@]}"; do
  ENTITY_ID="${ENTITY_IDS[$i]}"
  
  echo "--------------------------------------------------------"
  echo "Processing Entity $ENTITY_ID [$ENTITY_TYPE]"

  # Call the Client Script
  "$SCRIPT_DIR/gsoutpaint_db.sh" \
      "$MAP_RUN_ID" \
      "$ENTITY_TYPE" \
      "$ENTITY_ID" \
      "$WIDTH" \
      "$HEIGHT" \
      "$POS_X" \
      "$POS_Y" \
      "$COLOR"

  # Update active_map_run_id for this entity to reflect new history
  mysql $MYSQL_ARGS -e "
  UPDATE $ENTITY_TYPE 
  SET active_map_run_id = '$MAP_RUN_ID'
  WHERE id = '$ENTITY_ID';
  "
done

echo "--------------------------------------------------------"
echo "Batch processing complete."
