#!/bin/bash

# ==============================================================================
# SAGE Rembg Wrapper
# Batch processes background removal for entities flagged with regenerate_images=1.
# Delegates the source frame lookup (img2img_frame_id) to the client script.
# ==============================================================================

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MYSQL_ARGS=$("$SCRIPT_DIR"/db_name.sh main-conn)

# Arguments
ENTITY_TYPE="$1"       # e.g., character, anima
QUALITY="${2:-film}"   # fast or film
MODEL="${3:-u2net}"    # u2net or isnet-general-use

if [ -z "$ENTITY_TYPE" ]; then
  echo "Usage: $0 ENTITY_TYPE [QUALITY] [MODEL]"
  exit 1
fi

# -----------------------------
# 1. Create a new map_run
# -----------------------------
MAP_RUN_ID=$(mysql $MYSQL_ARGS -N -e "
INSERT INTO map_runs (entity_type, note) 
VALUES ('$ENTITY_TYPE', 'Background Removal ($QUALITY)'); 
SELECT LAST_INSERT_ID();
")

echo "Created new map_run ID: $MAP_RUN_ID for $ENTITY_TYPE (Background Removal)"

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
  # Usage: ./rembg_db.sh MAP_RUN_ID ENTITY_TYPE ENTITY_ID QUALITY MODEL
  "$SCRIPT_DIR/rembg_db.sh" \
      "$MAP_RUN_ID" \
      "$ENTITY_TYPE" \
      "$ENTITY_ID" \
      "$QUALITY" \
      "$MODEL"

  # Update active_map_run_id for this entity to reflect new history
  mysql $MYSQL_ARGS -e "
  UPDATE $ENTITY_TYPE 
  SET active_map_run_id = '$MAP_RUN_ID'
  WHERE id = '$ENTITY_ID';
  "
done

echo "--------------------------------------------------------"
echo "Batch processing complete."