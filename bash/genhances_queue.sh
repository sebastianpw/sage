# bash/genhances_queue.sh

#!/bin/bash

# ==============================================================================
# SAGE Frame Enhancement Enqueuer (Fills the map_run_queue)
# Usage: ./genhances_queue.sh [batch_limit] [worker_scope] [entity_type_filter] [limit] [offset] [no_styles] [add_to_prompt]
# ==============================================================================

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MYSQL_ARGS=$("$SCRIPT_DIR"/db_name.sh main-conn)

BATCH_LIMIT="$1"
WORKER_SCOPE="${2:-global}"
ENTITY_TYPE_FILTER="$3"
LIMIT="$4"
OFFSET="$5"
NO_STYLES="${6:-0}"
ADD_TO_PROMPT="$7"

if [ -n "$BATCH_LIMIT" ] && [ "$BATCH_LIMIT" -le 0 ]; then
  BATCH_LIMIT=""
fi

# -----------------------------
# Fetch Global Negative Prompt from DB
# -----------------------------
read -r GLOBAL_NEGATIVE_PROMPT < <(
  mysql $MYSQL_ARGS -N -e \
    "SELECT COALESCE(description,'') FROM prompt_negative_globals WHERE active=1 ORDER BY id DESC LIMIT 1;"
)
GLOBAL_NEGATIVE_PROMPT=${GLOBAL_NEGATIVE_PROMPT:-"nsfw, bad quality, text"}

# -----------------------------
# Fetch all entity types with pending enhancement requests
# -----------------------------
TYPE_QUERY="SELECT entity_type, COUNT(*) FROM frame_enhancements WHERE regenerate_images = 1"

if [ -n "$ENTITY_TYPE_FILTER" ]; then
  SAFE_FILTER=$(echo "$ENTITY_TYPE_FILTER" | sed "s/'/''/g")
  TYPE_QUERY="$TYPE_QUERY AND entity_type = '$SAFE_FILTER'"
fi

TYPE_QUERY="$TYPE_QUERY GROUP BY entity_type ORDER BY entity_type ASC"

mapfile -t ENTITY_TYPE_ROWS < <(mysql $MYSQL_ARGS -N -e "$TYPE_QUERY")

if [ "${#ENTITY_TYPE_ROWS[@]}" -eq 0 ]; then
  echo "No frame enhancements flagged for regeneration."
  exit 0
fi

TOTAL_ENQUEUE_COUNT=0

# -----------------------------
# Build Safe Configuration JSON for Worker
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
# Process each entity type separately
# -----------------------------
for TYPE_ROW in "${ENTITY_TYPE_ROWS[@]}"; do
  IFS=$'\t' read -r ENTITY_TYPE FLAGGED_COUNT <<< "$TYPE_ROW"

  if [ -z "$ENTITY_TYPE" ]; then
    continue
  fi

  SAFE_ENTITY_TYPE=$(echo "$ENTITY_TYPE" | sed "s/'/''/g")

  # -----------------------------
  # Fetch all enhancement rows for this entity type
  # -----------------------------
  SQL_QUERY="
    SELECT CONCAT_WS(CHAR(31),
      id,
      description,
      COALESCE(prompt_negative, ''),
      COALESCE(seed, ''),
      entity_id,
      COALESCE(img2img_frame_id, 0)
    )
    FROM frame_enhancements
    WHERE regenerate_images = 1
      AND entity_type = '$SAFE_ENTITY_TYPE'
    ORDER BY id ASC
  "

  if [ -n "$BATCH_LIMIT" ] && [ "$BATCH_LIMIT" -gt 0 ]; then
    SQL_QUERY="$SQL_QUERY LIMIT $BATCH_LIMIT"
  fi

  declare -a ENHANCE_IDS=()
  declare -a PROMPTS=()
  declare -a NEGATIVES=()
  declare -a SEEDS=()
  declare -a ORIG_ENTITY_IDS=()
  declare -a SOURCE_FRAME_IDS=()

  while IFS=$'\x1f' read -r id prompt neg seed entity_id img2img_id; do
    ENHANCE_IDS+=("$id")
    PROMPTS+=("$prompt")
    NEGATIVES+=("$neg")
    SEEDS+=("$seed")
    ORIG_ENTITY_IDS+=("$entity_id")
    SOURCE_FRAME_IDS+=("$img2img_id")
  done < <(mysql $MYSQL_ARGS -N -e "$SQL_QUERY")

  TOTAL=${#ENHANCE_IDS[@]}

  if [ "$TOTAL" -eq 0 ]; then
    echo "No '$ENTITY_TYPE' flagged for regeneration."
    continue
  fi

  # -----------------------------
  # Create a dedicated map run for this entity type
  # -----------------------------
  MAP_RUN_ID=$(mysql $MYSQL_ARGS -N -e "
    INSERT INTO map_runs (entity_type, note)
    VALUES ('$SAFE_ENTITY_TYPE', 'Queued Frame Enhancement ($TOTAL items - $WORKER_SCOPE)');
    SELECT LAST_INSERT_ID();
  ")

  echo "--------------------------------------------------------"
  echo "Queueing Frame Enhancement Batch Run ($WORKER_SCOPE mode)"
  echo "Entity Type: $ENTITY_TYPE"
  echo "Map Run ID:  $MAP_RUN_ID"
  echo "Total Items: $TOTAL"
  echo "--------------------------------------------------------"

  # -----------------------------
  # Fill the Queue
  # -----------------------------
  for i in "${!ENHANCE_IDS[@]}"; do
    ENHANCE_ID="${ENHANCE_IDS[$i]}"
    ENTITY_PROMPT="${PROMPTS[$i]}"
    ENTITY_SPECIFIC_NEGATIVE="${NEGATIVES[$i]}"
    ENTITY_SEED="${SEEDS[$i]}"
    ORIG_ENTITY_ID="${ORIG_ENTITY_IDS[$i]}"
    SOURCE_FRAME_ID="${SOURCE_FRAME_IDS[$i]}"
    CURRENT_NUM=$((i + 1))

    echo "[$CURRENT_NUM / $TOTAL] Queueing ENHANCE_ID=$ENHANCE_ID (For $ENTITY_TYPE ID: $ORIG_ENTITY_ID)..."
    echo "   -> Prompt: ${ENTITY_PROMPT:0:50}..."
    echo "   -> Source Frame ID: $SOURCE_FRAME_ID"

    # Insert into the queue, clear the regenerate flag, and update active_map_run_id
    mysql $MYSQL_ARGS -e "
      INSERT INTO map_run_queue
      (map_run_id, entity_type, entity_id, asset_type, status, priority, api_provider_config)
      VALUES
      ($MAP_RUN_ID, 'frame_enhancements', $ENHANCE_ID, 'frames', 'pending', $PRIORITY, IF('$SQL_CONFIG_JSON'='{}', NULL, '$SQL_CONFIG_JSON'));

      UPDATE frame_enhancements
      SET active_map_run_id = $MAP_RUN_ID, regenerate_images = 0
      WHERE id = $ENHANCE_ID;
    "

    TOTAL_ENQUEUE_COUNT=$((TOTAL_ENQUEUE_COUNT + 1))
  done
done

echo "--------------------------------------------------------"
echo "✓ Successfully queued $TOTAL_ENQUEUE_COUNT tasks."
echo "--------------------------------------------------------"

# -----------------------------
# Execute Manual Worker Immediately (If scoped)
# -----------------------------
if [ "$WORKER_SCOPE" = "manual" ]; then
    echo "⚡ Executing Manual Worker to process queue immediately..."
    "$SCRIPT_DIR/genworker_queue.sh" "$TOTAL_ENQUEUE_COUNT" "manual"
fi