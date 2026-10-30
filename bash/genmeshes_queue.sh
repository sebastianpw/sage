#!/bin/bash

# ==============================================================================
# SAGE Mesh Enqueuer (Fills map_run_queue for 3D GLB Generation)
# Usage: ./genmeshes_queue.sh prompt_type [limit] [offset] [resolution] [worker_scope]
# Example: ./genmeshes_queue.sh sketches "" "" medium manual
# ==============================================================================

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MYSQL_ARGS=$("$SCRIPT_DIR"/db_name.sh main-conn)

PROMPT_TYPE="$1"
LIMIT="$2"
OFFSET="$3"
RESOLUTION="${4:-medium}"
WORKER_SCOPE="${5:-global}"

if [ -z "$PROMPT_TYPE" ]; then
  echo "Usage: $0 prompt_type [limit] [offset] [resolution] [worker_scope]"
  exit 1
fi

# -----------------------------
# 1. Ensure regenerate_meshes column exists & Fetch Flagged Entities
# -----------------------------
HAS_REGEN_COL=$(mysql $MYSQL_ARGS -N -e "SHOW COLUMNS FROM \`$PROMPT_TYPE\` LIKE 'regenerate_meshes';")
if [ -z "$HAS_REGEN_COL" ]; then
    echo "Adding 'regenerate_meshes' column to table '$PROMPT_TYPE'..."
    mysql $MYSQL_ARGS -e "ALTER TABLE \`$PROMPT_TYPE\` ADD COLUMN \`regenerate_meshes\` tinyint(1) NOT NULL DEFAULT 0;"
fi

SQL_QUERY="
SELECT id 
FROM \`$PROMPT_TYPE\` 
WHERE regenerate_meshes = 1
ORDER BY id ASC
"

if [ -n "$LIMIT" ] && [ "$LIMIT" -gt 0 ]; then
    OFFSET_VAL=${OFFSET:-0}
    SQL_QUERY="$SQL_QUERY LIMIT $LIMIT OFFSET $OFFSET_VAL"
fi

declare -a ENTITY_IDS
while IFS=$'\t' read -r id; do
  [ -n "$id" ] && ENTITY_IDS+=("$id")
done < <(mysql $MYSQL_ARGS -N -e "$SQL_QUERY")

TOTAL_COUNT=${#ENTITY_IDS[@]}

if [ "$TOTAL_COUNT" -eq 0 ]; then
  echo "No '$PROMPT_TYPE' entities flagged with regenerate_meshes = 1."
  exit 0
fi

# -----------------------------
# 2. Create Map Run
# -----------------------------
MAP_RUN_ID=$(mysql $MYSQL_ARGS -N -e "
    INSERT INTO map_runs (entity_type, note) 
    VALUES ('$PROMPT_TYPE', 'Queued 3D Mesh Generation Run ($TOTAL_COUNT items - $WORKER_SCOPE)'); 
    SELECT LAST_INSERT_ID();
")

echo "--------------------------------------------------------"
echo "Queueing 3D Meshes Batch Run ($WORKER_SCOPE mode)"
echo "Entity Type: $PROMPT_TYPE"
echo "Map Run ID:  $MAP_RUN_ID"
echo "Resolution:  $RESOLUTION"
echo "Total Items: $TOTAL_COUNT"
echo "--------------------------------------------------------"

# -----------------------------
# 3. Build Task Configuration JSON
# -----------------------------
PRIORITY=0
[ "$WORKER_SCOPE" = "manual" ] && PRIORITY=1

CONFIG_JSON=$(jq -n -c \
  --arg res "$RESOLUTION" \
  --arg model "microsoft/trellis-2" \
  '{
     resolution: $res,
     model: $model
   }')

SQL_CONFIG_JSON=$(echo "$CONFIG_JSON" | sed "s/'/''/g")

# -----------------------------
# 4. Fill the Queue
# -----------------------------
for i in "${!ENTITY_IDS[@]}"; do
  ENTITY_ID="${ENTITY_IDS[$i]}"
  CURRENT_NUM=$((i + 1))
  
  echo "[$CURRENT_NUM / $TOTAL_COUNT] Queueing 3D Mesh Generation for $PROMPT_TYPE #$ENTITY_ID..."

  mysql $MYSQL_ARGS -e "
      INSERT INTO map_run_queue 
      (map_run_id, entity_type, entity_id, asset_type, status, priority, api_provider_config)
      VALUES 
      ($MAP_RUN_ID, '$PROMPT_TYPE', $ENTITY_ID, 'meshes', 'pending', $PRIORITY, '$SQL_CONFIG_JSON');

      UPDATE \`$PROMPT_TYPE\` 
      SET active_map_run_id = $MAP_RUN_ID, regenerate_meshes = 0
      WHERE id = $ENTITY_ID;
  "
done

echo "--------------------------------------------------------"
echo "✓ Successfully queued $TOTAL_COUNT mesh generation tasks."
echo "--------------------------------------------------------"

# -----------------------------
# 5. Execute Manual Worker Immediately (If scoped)
# -----------------------------
if [ "$WORKER_SCOPE" = "manual" ]; then
    echo "⚡ Executing Mesh Worker to process queue immediately..."
    "$SCRIPT_DIR/genmesh_queue.sh" "$TOTAL_COUNT"
fi