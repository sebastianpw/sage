#!/bin/bash

# ==============================================================================
# SAGE Multiplane Batch Wrapper
# Usage: ./genmultiplane_fromdb.sh entity_type [batch_limit]
# Example: ./genmultiplane_fromdb.sh composites 5
# ==============================================================================

# -----------------------------
# Resolve script directory
# -----------------------------
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# -----------------------------
# DB Connection
# -----------------------------
MYSQL_ARGS=$("$SCRIPT_DIR"/db_name.sh main-conn)

# -----------------------------
# Configuration
# -----------------------------
CLIENT_SCRIPT="$SCRIPT_DIR/genmultiplane.sh"

# -----------------------------
# Arguments
# -----------------------------
ENTITY_TYPE="$1"       # e.g. composites
BATCH_LIMIT="$2"       # optional, limit number of records to process

# -----------------------------
# Validation
# -----------------------------
if [ -z "$ENTITY_TYPE" ]; then
  echo "Usage: $0 entity_type [batch_limit]"
  exit 1
fi

if [ ! -f "$CLIENT_SCRIPT" ]; then
  echo "ERROR: Client script not found at $CLIENT_SCRIPT"
  exit 1
fi

# -----------------------------
# 1. Create a new map_run for this batch job
# -----------------------------
MAP_RUN_ID=$(mysql $MYSQL_ARGS -N -e "
    INSERT INTO map_runs (entity_type, note) 
    VALUES ('$ENTITY_TYPE', 'Batch Multiplane Generation'); 
    SELECT LAST_INSERT_ID();
")

echo "--------------------------------------------------------"
echo "Starting Multiplane Batch Run"
echo "Entity Type: $ENTITY_TYPE"
echo "Map Run ID:  $MAP_RUN_ID"
echo "--------------------------------------------------------"

# -----------------------------
# 2. Fetch all entities flagged for regeneration
# -----------------------------
# We assume the entity table has 'id', 'name', and 'regenerate_images'
SQL_QUERY="SELECT id, name FROM $ENTITY_TYPE WHERE regenerate_images=1"

# Apply batch limit if provided
if [ -n "$BATCH_LIMIT" ] && [ "$BATCH_LIMIT" -gt 0 ]; then
  SQL_QUERY="$SQL_QUERY LIMIT $BATCH_LIMIT"
fi

# Load results into arrays to avoid MySQL connection overlap
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
# 3. Process Loop
# -----------------------------
for i in "${!IDS[@]}"; do
  ENTITY_ID="${IDS[$i]}"
  ENTITY_NAME="${NAMES[$i]}"
  
  CURRENT_NUM=$((i + 1))
  
  echo ""
  echo "[$CURRENT_NUM / $TOTAL_COUNT] Processing $ENTITY_TYPE #$ENTITY_ID ($ENTITY_NAME)..."

  # -----------------------------
  # Call the Client Script
  # -----------------------------
  # We pass ENTITY_TYPE as the 3rd argument now, ensuring forward compatibility
  "$CLIENT_SCRIPT" "$MAP_RUN_ID" "$ENTITY_ID" "$ENTITY_TYPE"
  CLIENT_EXIT_CODE=$?

  if [ $CLIENT_EXIT_CODE -eq 0 ]; then
      # -----------------------------
      # Update active_map_run_id on success
      # -----------------------------
      mysql $MYSQL_ARGS -e "
          UPDATE $ENTITY_TYPE 
          SET active_map_run_id = $MAP_RUN_ID
          WHERE id = $ENTITY_ID;
      "
      echo "✓ Success. Updated active_map_run_id."
  else
      echo "✗ Failed to generate $ENTITY_TYPE #$ENTITY_ID (Exit Code: $CLIENT_EXIT_CODE)"
  fi

done

echo ""
echo "--------------------------------------------------------"
echo "Batch Run Complete."
echo "--------------------------------------------------------"