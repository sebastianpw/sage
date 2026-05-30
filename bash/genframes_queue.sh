# bash/genframes_queue.sh

#!/bin/bash

# ==============================================================================
# SAGE Frame Enqueuer (Fills the map_run_queue)
# Usage: ./genframes_queue.sh prompt_type [limit] [offset] [no_styles] [add_to_prompt] [worker_scope]
# Example: ./genframes_queue.sh sketches "" "" 0 "" manual
# ==============================================================================

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MYSQL_ARGS=$("$SCRIPT_DIR"/db_name.sh main-conn)

PROMPT_TYPE="$1"
LIMIT="$2"
OFFSET="$3"
NO_STYLES="$4"
ADD_TO_PROMPT="$5"
WORKER_SCOPE="${6:-global}"

NO_STYLES=${NO_STYLES:-0}

if [ -z "$PROMPT_TYPE" ]; then
  echo "Usage: $0 prompt_type [limit] [offset] [no_styles] [add_to_prompt] [worker_scope]"
  exit 1
fi

# -----------------------------
# 1. Fetch Entities
# -----------------------------
VIEW_NAME="v_prompts_${PROMPT_TYPE}"

SQL_QUERY="
SELECT id 
FROM $VIEW_NAME 
WHERE regenerate_images = 1
"

declare -a ENTITY_IDS

while IFS=$'\t' read -r id; do
  ENTITY_IDS+=("$id")
done < <(mysql $MYSQL_ARGS -N -e "$SQL_QUERY")

TOTAL_COUNT=${#ENTITY_IDS[@]}

if [ "$TOTAL_COUNT" -eq 0 ]; then
  echo "No '$PROMPT_TYPE' entities flagged for image generation."
  exit 0
fi

# -----------------------------
# 2. Create Map Run
# -----------------------------
MAP_RUN_ID=$(mysql $MYSQL_ARGS -N -e "
    INSERT INTO map_runs (entity_type, note) 
    VALUES ('$PROMPT_TYPE', 'Queued Frame Generation Run ($TOTAL_COUNT items - $WORKER_SCOPE)'); 
    SELECT LAST_INSERT_ID();
")

echo "--------------------------------------------------------"
echo "Queueing Frames Batch Run ($WORKER_SCOPE mode)"
echo "Entity Type: $PROMPT_TYPE"
echo "Map Run ID:  $MAP_RUN_ID"
echo "Total Items: $TOTAL_COUNT"
echo "--------------------------------------------------------"

# -----------------------------
# 3. Build Safe Configuration JSON for Worker
# -----------------------------
PROVIDER_JSON="null"
PRIORITY=0

if [ "$WORKER_SCOPE" = "manual" ]; then
    PRIORITY=1
    read -r EP_ID M_OVER W_OVER H_OVER < <(
        mysql $MYSQL_ARGS -N -e "
            SELECT endpoint_id, COALESCE(model_override,''), COALESCE(width_override,0), COALESCE(height_override,0)
            FROM worker_img_provider_default WHERE scope='manual' LIMIT 1;
        "
    )
    if [ -n "$EP_ID" ] && [ "$EP_ID" != "NULL" ]; then
        PROVIDER_JSON=$(jq -n -c \
            --arg ep "$EP_ID" \
            --arg model "$M_OVER" \
            --arg w "$W_OVER" \
            --arg h "$H_OVER" \
            '{
                endpoint_id: ($ep|tonumber),
                model: (if $model == "" then null else $model end),
                width: (if $w == "0" then null else ($w|tonumber) end),
                height: (if $h == "0" then null else ($h|tonumber) end)
            } | with_entries(select(.value != null))'
        )
    fi
fi

CONFIG_JSON=$(jq -n -c \
  --arg limit "$LIMIT" \
  --arg offset "$OFFSET" \
  --arg no_styles "$NO_STYLES" \
  --arg add_to_prompt "$ADD_TO_PROMPT" \
  --argjson prov "$PROVIDER_JSON" \
  '{
     limit: (if $limit == "" then null else $limit end), 
     offset: (if $offset == "" then null else $offset end), 
     no_styles: (if $no_styles == "" then null else $no_styles end), 
     add_to_prompt: (if $add_to_prompt == "" then null else $add_to_prompt end)
   } 
   | with_entries(select(.value != null))
   | if $prov != null then .provider = $prov else . end')

SQL_CONFIG_JSON=$(echo "$CONFIG_JSON" | sed "s/'/''/g")

# -----------------------------
# 4. Fill the Queue
# -----------------------------
for i in "${!ENTITY_IDS[@]}"; do
  ENTITY_ID="${ENTITY_IDS[$i]}"
  CURRENT_NUM=$((i + 1))
  
  echo "[$CURRENT_NUM / $TOTAL_COUNT] Queueing Frame Generation for $PROMPT_TYPE #$ENTITY_ID..."

  # Insert into the queue and clear the regenerate flag
  mysql $MYSQL_ARGS -e "
      INSERT INTO map_run_queue 
      (map_run_id, entity_type, entity_id, asset_type, status, priority, api_provider_config)
      VALUES 
      ($MAP_RUN_ID, '$PROMPT_TYPE', $ENTITY_ID, 'frames', 'pending', $PRIORITY, IF('$SQL_CONFIG_JSON'='{}', NULL, '$SQL_CONFIG_JSON'));

      UPDATE $PROMPT_TYPE 
      SET active_map_run_id = $MAP_RUN_ID, regenerate_images = 0
      WHERE id = $ENTITY_ID;
  "
done

echo "--------------------------------------------------------"
echo "✓ Successfully queued $TOTAL_COUNT frame generation tasks."
echo "--------------------------------------------------------"

# -----------------------------
# 5. Execute Manual Worker Immediately (If scoped)
# -----------------------------
if [ "$WORKER_SCOPE" = "manual" ]; then
    echo "⚡ Executing Manual Worker to process queue immediately..."
    "$SCRIPT_DIR/genworker_queue.sh" "$TOTAL_COUNT" "manual"
fi