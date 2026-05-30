#!/bin/bash
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MYSQL_ARGS=$("$SCRIPT_DIR"/db_name.sh main-conn)

ENTITY_TYPE="audio_dialogue_lines" 

# Create map run
MAP_RUN_ID=$(mysql $MYSQL_ARGS -N -e "INSERT INTO map_runs (entity_type, note) VALUES ('$ENTITY_TYPE', 'RVC Voice Conversion'); SELECT LAST_INSERT_ID();")
echo "Created new map_run ID: $MAP_RUN_ID"

# Fetch IDs
SQL_QUERY="SELECT id FROM $ENTITY_TYPE WHERE regenerate_audios=1 AND wav2wav=1"
declare -a ENTITY_IDS
while IFS=$'\t' read -r id; do ENTITY_IDS+=("$id"); done < <(mysql $MYSQL_ARGS -N -e "$SQL_QUERY")

# Process
for i in "${!ENTITY_IDS[@]}"; do
  ENTITY_ID="${ENTITY_IDS[$i]}"
  echo "--- Processing Audio Line $ENTITY_ID ---"
  "$SCRIPT_DIR/svc_db.sh" "$MAP_RUN_ID" "$ENTITY_ID"
  
  mysql $MYSQL_ARGS -e "UPDATE $ENTITY_TYPE SET active_map_run_id = '$MAP_RUN_ID' WHERE id = '$ENTITY_ID';"
done
