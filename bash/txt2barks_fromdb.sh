#!/bin/bash
# Wrapper: find entities flagged for txt2bark and run txt2bark_db.sh for each
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MYSQL_ARGS=$("$SCRIPT_DIR"/db_name.sh main-conn)

# Default entity type
ENTITY_TYPE="${1:-audio_dialogue_lines}"

# Create a new map_run
MAP_RUN_ID=$(mysql $MYSQL_ARGS -N -e "
INSERT INTO map_runs (entity_type, note)
VALUES ('$ENTITY_TYPE', 'Text-to-audio (Bark / Suno)');
SELECT LAST_INSERT_ID();
")

echo "Created new map_run ID: $MAP_RUN_ID for entity_type: $ENTITY_TYPE"

# Select entities flagged for txt2bark
SQL_QUERY="SELECT id, COALESCE(description, '') FROM $ENTITY_TYPE WHERE regenerate_audios=1"

declare -a ENTITY_IDS
declare -a ENTITY_TEXTS

while IFS=$'\t' read -r id text; do
  ENTITY_IDS+=("$id")
  ENTITY_TEXTS+=("$text")
done < <(mysql $MYSQL_ARGS -N -e "$SQL_QUERY")

TOTAL=${#ENTITY_IDS[@]}
echo "Found $TOTAL entities flagged for txt2bark."

for i in "${!ENTITY_IDS[@]}"; do
  ENTITY_ID="${ENTITY_IDS[$i]}"
  ENTITY_TEXT="${ENTITY_TEXTS[$i]}"
  PROMPT_CLEAN=$(echo "$ENTITY_TEXT" | tr '\n' ' ' | tr '\r' ' ')

  echo "--- Processing [$((i+1))/$TOTAL] Entity ID: $ENTITY_ID ---"

  "$SCRIPT_DIR/txt2bark_db.sh" "$PROMPT_CLEAN" "$MAP_RUN_ID" "$ENTITY_TYPE" "$ENTITY_ID"

  mysql $MYSQL_ARGS -e "
    UPDATE $ENTITY_TYPE
    SET active_map_run_id = '$MAP_RUN_ID'
    WHERE id = '$ENTITY_ID';
  "
done

echo "Done processing txt2bark for '$ENTITY_TYPE'."
