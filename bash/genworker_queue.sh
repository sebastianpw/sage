# bash/genworker_queue.sh

#!/bin/bash

# ==============================================================================
# SAGE Unified Frame Generation Queue Worker
# Fetches ANY pending frame generation tasks (Standard & Enhancements) 
# from map_run_queue and processes them. Excludes video/animatics.
# Usage: ./genworker_queue.sh [limit] [scope]
#   scope: global (default, cron) | manual (ad-hoc from view_queue)
# ==============================================================================

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MYSQL_ARGS=$("$SCRIPT_DIR"/db_name.sh main-conn)

PROCESS_LIMIT="${1:-4}"
WORKER_SCOPE="${2:-global}"

export PAI_TOKEN=$(cat "$SCRIPT_DIR/../token/.pollinationsaitoken")
export FREEIMAGE_KEY=$(cat "$SCRIPT_DIR/../token/.freeimage_key")

if [ -f "$SCRIPT_DIR/load_root.sh" ]; then
  source "$SCRIPT_DIR/load_root.sh"
fi

if [ -z "$FRAMES_ROOT" ] || [ -z "$PROJECT_ROOT" ]; then
  echo "ERROR: Roots not set. Aborting."
  exit 1
fi

FRAMES_DIR="$FRAMES_ROOT"
mkdir -p "$FRAMES_DIR"
FRAMES_DIR_REL="${FRAMES_ROOT#$PROJECT_ROOT/public/}"

# -----------------------------
# 1. Fetch Global Negative Prompt
# -----------------------------
read -r GLOBAL_NEGATIVE_PROMPT < <(
  mysql $MYSQL_ARGS -N -e \
    "SELECT COALESCE(description,'') FROM prompt_negative_globals WHERE active=1 ORDER BY id DESC LIMIT 1;"
)
GLOBAL_NEGATIVE_PROMPT=${GLOBAL_NEGATIVE_PROMPT:-"nsfw, bad quality, text"}

# -----------------------------
# 2. Load Default Provider Config from DB
# -----------------------------
# Reads the active endpoint for the given scope from worker_img_provider_default.
# Falls back to the first enabled endpoint if the scope row is missing.

read -r DEFAULT_ENDPOINT_ID DEFAULT_BASE_URL DEFAULT_PATH_TEMPLATE DEFAULT_HTTP_METHOD DEFAULT_MODEL_OVERRIDE DEFAULT_WIDTH_OVERRIDE DEFAULT_HEIGHT_OVERRIDE < <(
  mysql $MYSQL_ARGS -N -e "
    SELECT
      e.id,
      e.base_url,
      e.path_template,
      e.http_method,
      COALESCE(d.model_override, ''),
      COALESCE(d.width_override, 0),
      COALESCE(d.height_override, 0)
    FROM worker_img_provider_default d
    JOIN worker_img_api_endpoint e ON e.id = d.endpoint_id
    WHERE d.scope = '$WORKER_SCOPE'
      AND e.is_enabled = 1
    LIMIT 1;
  "
)

# Hard fallback if DB tables not yet populated
if [ -z "$DEFAULT_BASE_URL" ]; then
  DEFAULT_BASE_URL="https://gen.pollinations.ai"
  DEFAULT_PATH_TEMPLATE="/image/{prompt}"
  DEFAULT_HTTP_METHOD="GET"
  DEFAULT_MODEL_OVERRIDE=""
  DEFAULT_WIDTH_OVERRIDE=0
  DEFAULT_HEIGHT_OVERRIDE=0
  DEFAULT_ENDPOINT_ID=0
  echo "WARNING: worker_img_provider_default not found for scope '$WORKER_SCOPE'. Using hardcoded fallback."
fi

# Load default params for this endpoint (model, width, height, nologo etc.)
declare -A DEFAULT_PARAMS
if [ -n "$DEFAULT_ENDPOINT_ID" ] && [ "$DEFAULT_ENDPOINT_ID" -gt 0 ]; then
  while IFS=$'\t' read -r pk pv; do
    [ -n "$pk" ] && DEFAULT_PARAMS["$pk"]="$pv"
  done < <(mysql $MYSQL_ARGS -N -e "
    SELECT param_key, COALESCE(default_value_text,'')
    FROM worker_img_api_endpoint_param
    WHERE endpoint_id = $DEFAULT_ENDPOINT_ID
      AND is_enabled = 1
      AND location = 'query'
    ORDER BY sort_order ASC;
  ")
fi

# Apply scope-level overrides from worker_img_provider_default
[ -n "$DEFAULT_MODEL_OVERRIDE" ]  && DEFAULT_PARAMS['model']="$DEFAULT_MODEL_OVERRIDE"
[ "$DEFAULT_WIDTH_OVERRIDE" -gt 0 ]  && DEFAULT_PARAMS['width']="$DEFAULT_WIDTH_OVERRIDE"
[ "$DEFAULT_HEIGHT_OVERRIDE" -gt 0 ] && DEFAULT_PARAMS['height']="$DEFAULT_HEIGHT_OVERRIDE"

# Ensure sane defaults if params table is empty
[ -z "${DEFAULT_PARAMS['model']}"  ] && DEFAULT_PARAMS['model']="flux"
[ -z "${DEFAULT_PARAMS['width']}"  ] && DEFAULT_PARAMS['width']="1024"
[ -z "${DEFAULT_PARAMS['height']}" ] && DEFAULT_PARAMS['height']="1024"
[ -z "${DEFAULT_PARAMS['nologo']}" ] && DEFAULT_PARAMS['nologo']="true"

echo "Provider: $DEFAULT_BASE_URL$DEFAULT_PATH_TEMPLATE (model: ${DEFAULT_PARAMS['model']}, ${DEFAULT_PARAMS['width']}x${DEFAULT_PARAMS['height']})"

# -----------------------------
# 3. Atomically Claim Pending Queue Items
# -----------------------------
# We create a unique token for this specific worker instance
WORKER_CLAIM_ID="worker_$$_${RANDOM}"

# Atomically mark the batch as 'processing' so no other concurrent worker can grab them
mysql $MYSQL_ARGS -e "
  UPDATE map_run_queue 
  SET status = 'processing', 
      started_at = NOW(), 
      error_msg = '$WORKER_CLAIM_ID'
  WHERE status = 'pending' AND asset_type = 'frames'
  ORDER BY priority DESC, id ASC 
  LIMIT $PROCESS_LIMIT;
"

# Fetch EXACTLY the rows we just successfully claimed
QUERY="
  SELECT id, map_run_id, entity_type, entity_id, attempts, max_attempts, api_provider_config
  FROM map_run_queue 
  WHERE status = 'processing' AND error_msg = '$WORKER_CLAIM_ID'
  ORDER BY priority DESC, id ASC 
"

declare -a Q_IDS Q_MAP_RUNS Q_ENTITY_TYPES Q_ENTITY_IDS Q_ATTEMPTS Q_MAX_ATTEMPTS Q_CONFIGS
while IFS=$'\t' read -r q_id m_id e_type e_id att max_att conf; do
    Q_IDS+=("$q_id")
    Q_MAP_RUNS+=("$m_id")
    Q_ENTITY_TYPES+=("$e_type")
    Q_ENTITY_IDS+=("$e_id")
    Q_ATTEMPTS+=("$att")
    Q_MAX_ATTEMPTS+=("$max_att")
    Q_CONFIGS+=("$conf")
done < <(mysql $MYSQL_ARGS -N -e "$QUERY")

TOTAL_COUNT=${#Q_IDS[@]}
if [ "$TOTAL_COUNT" -eq 0 ]; then
    echo "No pending frame tasks in queue."
    exit 0
fi

echo "========================================================"
echo "Processing $TOTAL_COUNT queued frame tasks..."
echo "========================================================"

# -----------------------------
# 4. Process Loop
# -----------------------------
for i in "${!Q_IDS[@]}"; do
    QUEUE_ID="${Q_IDS[$i]}"
    MAP_RUN_ID="${Q_MAP_RUNS[$i]}"
    ENTITY_TYPE="${Q_ENTITY_TYPES[$i]}"
    ENTITY_ID="${Q_ENTITY_IDS[$i]}"
    ATTEMPTS="${Q_ATTEMPTS[$i]}"
    MAX_ATTEMPTS="${Q_MAX_ATTEMPTS[$i]}"
    CONFIG_JSON="${Q_CONFIGS[$i]}"
    
    echo ""
    echo "[Task $QUEUE_ID] Starting: $ENTITY_TYPE #$ENTITY_ID (MapRun: $MAP_RUN_ID)"

    # ------------------------------------------------------------------
    # 4a. Resolve effective provider config for this specific queue item
    # Per-job overrides live in api_provider_config JSON under the key
    # "provider": { "endpoint_id": N, "model": "...", "width": N, "height": N }
    # If absent, the scope-level defaults loaded above are used.
    # ------------------------------------------------------------------
    JOB_ENDPOINT_ID="$DEFAULT_ENDPOINT_ID"
    JOB_BASE_URL="$DEFAULT_BASE_URL"
    JOB_PATH_TEMPLATE="$DEFAULT_PATH_TEMPLATE"
    JOB_HTTP_METHOD="$DEFAULT_HTTP_METHOD"
    declare -A JOB_PARAMS
    for k in "${!DEFAULT_PARAMS[@]}"; do JOB_PARAMS["$k"]="${DEFAULT_PARAMS[$k]}"; done

    if [ -n "$CONFIG_JSON" ] && [ "$CONFIG_JSON" != "NULL" ]; then
        PROV_ENDPOINT_ID=$(echo "$CONFIG_JSON" | jq -r '.provider.endpoint_id // empty')
        PROV_MODEL=$(echo "$CONFIG_JSON"       | jq -r '.provider.model       // empty')
        PROV_WIDTH=$(echo "$CONFIG_JSON"       | jq -r '.provider.width       // empty')
        PROV_HEIGHT=$(echo "$CONFIG_JSON"      | jq -r '.provider.height      // empty')

        # If this job specifies a different endpoint, reload base URL + params
        if [ -n "$PROV_ENDPOINT_ID" ] && [ "$PROV_ENDPOINT_ID" != "$JOB_ENDPOINT_ID" ]; then
            read -r JOB_BASE_URL JOB_PATH_TEMPLATE JOB_HTTP_METHOD < <(
                mysql $MYSQL_ARGS -N -e "
                    SELECT base_url, path_template, http_method
                    FROM worker_img_api_endpoint
                    WHERE id = $PROV_ENDPOINT_ID AND is_enabled = 1
                    LIMIT 1;
                "
            )
            if [ -n "$JOB_BASE_URL" ]; then
                JOB_ENDPOINT_ID="$PROV_ENDPOINT_ID"
                unset JOB_PARAMS
                declare -A JOB_PARAMS
                while IFS=$'\t' read -r pk pv; do
                    [ -n "$pk" ] && JOB_PARAMS["$pk"]="$pv"
                done < <(mysql $MYSQL_ARGS -N -e "
                    SELECT param_key, COALESCE(default_value_text,'')
                    FROM worker_img_api_endpoint_param
                    WHERE endpoint_id = $JOB_ENDPOINT_ID
                      AND is_enabled = 1
                      AND location = 'query'
                    ORDER BY sort_order ASC;
                ")
            else
                # Endpoint not found / disabled — keep defaults
                JOB_BASE_URL="$DEFAULT_BASE_URL"
                JOB_PATH_TEMPLATE="$DEFAULT_PATH_TEMPLATE"
                JOB_HTTP_METHOD="$DEFAULT_HTTP_METHOD"
                echo "WARNING: Requested endpoint_id=$PROV_ENDPOINT_ID not found. Using default."
            fi
        fi

        # Apply per-job param overrides
        [ -n "$PROV_MODEL"  ] && JOB_PARAMS['model']="$PROV_MODEL"
        [ -n "$PROV_WIDTH"  ] && JOB_PARAMS['width']="$PROV_WIDTH"
        [ -n "$PROV_HEIGHT" ] && JOB_PARAMS['height']="$PROV_HEIGHT"
    fi

    echo "  → endpoint: $JOB_BASE_URL$JOB_PATH_TEMPLATE  model=${JOB_PARAMS['model']}  ${JOB_PARAMS['width']}x${JOB_PARAMS['height']}"

    # ------------------------------------------------------------------
    # 4b. Parse task-level config (limit/offset/no_styles/add_to_prompt)
    # These come from the same api_provider_config JSON, top-level keys,
    # exactly as before.
    # ------------------------------------------------------------------
    LIMIT=$(echo "$CONFIG_JSON" | jq -r '.limit')
    [ "$LIMIT" = "null" ] && LIMIT=""
    LIMIT=${LIMIT:-0}

    OFFSET=$(echo "$CONFIG_JSON" | jq -r '.offset')
    [ "$OFFSET" = "null" ] && OFFSET=""
    OFFSET=${OFFSET:-0}

    NO_STYLES=$(echo "$CONFIG_JSON" | jq -r '.no_styles')
    [ "$NO_STYLES" = "null" ] || [ -z "$NO_STYLES" ] && NO_STYLES=0

    ADD_TO_PROMPT=$(echo "$CONFIG_JSON" | jq -r '.add_to_prompt')
    [ "$ADD_TO_PROMPT" = "null" ] && ADD_TO_PROMPT=""

    # -----------------------------
    # 5. Routing logic: Enhancement vs Standard
    # -----------------------------
    IS_ENHANCEMENT=false
    if [ "$ENTITY_TYPE" = "frame_enhancements" ]; then
        IS_ENHANCEMENT=true
    fi

    ERROR_MSG=""
    FORCED_IMG2IMG_ID=""
    ENTITY_DEPTH2IMG="0"
    MAPPING_ENTITY_TYPE=""
    MAPPING_ENTITY_ID=""
    ORIG_ENTITY_DESC=""

    if [ "$IS_ENHANCEMENT" = true ]; then
        RAW_DATA=$(mysql $MYSQL_ARGS -N -e "
          SELECT CONCAT_WS(CHAR(31), 
            description, 
            COALESCE(prompt_negative, ''), 
            COALESCE(seed, ''), 
            entity_type, 
            entity_id, 
            COALESCE(img2img_frame_id, 0),
            COALESCE(depth2img, 0)
          )
          FROM frame_enhancements 
          WHERE id=$ENTITY_ID LIMIT 1;
        ")
        
        if [ -z "$RAW_DATA" ]; then
            ERROR_MSG="Enhancement record not found"
        else
            IFS=$'\x1f' read -r ENTITY_PROMPT ENTITY_SPECIFIC_NEGATIVE ENTITY_SEED MAPPING_ENTITY_TYPE MAPPING_ENTITY_ID FORCED_IMG2IMG_ID ENTITY_DEPTH2IMG <<< "$RAW_DATA"
            ORIG_ENTITY_DESC=$(mysql $MYSQL_ARGS -N -e "SELECT description FROM $MAPPING_ENTITY_TYPE WHERE id = $MAPPING_ENTITY_ID LIMIT 1;")
            if [ -z "$ORIG_ENTITY_DESC" ]; then ORIG_ENTITY_DESC="$ENTITY_PROMPT"; fi
        fi
    else
        VIEW_NAME="v_prompts_${ENTITY_TYPE}"
        RAW_DATA=$(mysql $MYSQL_ARGS -N -e "
          SELECT CONCAT_WS(CHAR(31), 
            COALESCE(prompt, ''), 
            COALESCE(prompt_negative, ''), 
            COALESCE(seed, '')
          )
          FROM $VIEW_NAME
          WHERE id=$ENTITY_ID LIMIT 1;
        ")
        
        if [ -z "$RAW_DATA" ]; then
            ERROR_MSG="Entity record not found in view $VIEW_NAME"
        else
            IFS=$'\x1f' read -r ENTITY_PROMPT ENTITY_SPECIFIC_NEGATIVE ENTITY_SEED <<< "$RAW_DATA"
            MAPPING_ENTITY_TYPE="$ENTITY_TYPE"
            MAPPING_ENTITY_ID="$ENTITY_ID"
        fi
    fi

    # Fast-fail if data missing
    if [ -n "$ERROR_MSG" ]; then
        mysql $MYSQL_ARGS -e "UPDATE map_run_queue SET status = 'failed', error_msg = '$ERROR_MSG', completed_at = NOW() WHERE id = $QUEUE_ID;"
        echo "✗ Task Failed: $ERROR_MSG"
        continue
    fi

    if [ -n "$ADD_TO_PROMPT" ]; then FULL_PROMPT="$ENTITY_PROMPT $ADD_TO_PROMPT"; else FULL_PROMPT="$ENTITY_PROMPT"; fi
    if [ -n "$ENTITY_SPECIFIC_NEGATIVE" ]; then COMBINED_NEGATIVE_PROMPT="$ENTITY_SPECIFIC_NEGATIVE, $GLOBAL_NEGATIVE_PROMPT"; else COMBINED_NEGATIVE_PROMPT="$GLOBAL_NEGATIVE_PROMPT"; fi

    BASE_PROMPT="$FULL_PROMPT"
    NEGATIVE_PROMPT="$COMBINED_NEGATIVE_PROMPT"
    SEED_ARG="$ENTITY_SEED"

    MAX_RETRIES=3
    RETRY_DELAY=3

    mapfile -t styles < <(mysql $MYSQL_ARGS -N -e "SELECT id, prompt FROM v_styles_helper;")
    TOTAL_STYLES=${#styles[@]}

    START=$((OFFSET > 0 ? OFFSET - 1 : 0))
    END=$((LIMIT > 0 ? START + LIMIT - 1 : TOTAL_STYLES - 1))
    [ "$END" -ge "$TOTAL_STYLES" ] && END=$((TOTAL_STYLES - 1))

    MAPPING_TABLE="frames_2_${MAPPING_ENTITY_TYPE}"
    TABLE_EXISTS=$(mysql $MYSQL_ARGS -N -e "SHOW TABLES LIKE '$MAPPING_TABLE';")

    if [ -z "$TABLE_EXISTS" ]; then
        ERROR_MSG="Mapping table '$MAPPING_TABLE' does not exist."
    else
        # -----------------------------
        # 6. Image References Assembly
        # -----------------------------
        IMG2IMG_FILENAME=""
        IMG2IMG_PROMPT=""
        DB_IMG2IMG_FRAME_ID="NULL"

        # Resolve primary img2img (handling standard vs depth2img routing)
        if [ -n "$FORCED_IMG2IMG_ID" ] && [ "$FORCED_IMG2IMG_ID" -gt 0 ]; then
            IMG2IMG_FRAME_ID="$FORCED_IMG2IMG_ID"
            
            TARGET_COLUMN="filename"
            [ "$ENTITY_DEPTH2IMG" = "1" ] && TARGET_COLUMN="depth_map_filename"

            IMG2IMG_FILENAME=$(mysql $MYSQL_ARGS -N -e "SELECT $TARGET_COLUMN FROM frames WHERE id = $IMG2IMG_FRAME_ID LIMIT 1;" | tr -d '\r')
        else
            read IMG2IMG_FLAG IMG2IMG_FRAME_ID IMG2IMG_PROMPT ENTITY_DEPTH2IMG < <(
              mysql $MYSQL_ARGS -N -e \
                "SELECT COALESCE(img2img,0), COALESCE(img2img_frame_id,0), COALESCE(img2img_prompt,''), COALESCE(depth2img,0) FROM $MAPPING_ENTITY_TYPE WHERE id=$MAPPING_ENTITY_ID;"
            )
            if [ "$IMG2IMG_FRAME_ID" -gt 0 ]; then
              TARGET_COLUMN="filename"
              [ "$ENTITY_DEPTH2IMG" = "1" ] && TARGET_COLUMN="depth_map_filename"

              IMG2IMG_FILENAME=$(mysql $MYSQL_ARGS -N -e "SELECT $TARGET_COLUMN FROM frames WHERE id = $IMG2IMG_FRAME_ID LIMIT 1;" | tr -d '\r')
            fi
        fi

        # Protect against explicit NULL returns from MySQL
        if [ "$IMG2IMG_FILENAME" = "NULL" ]; then
            IMG2IMG_FILENAME=""
        fi

        if [ -n "$IMG2IMG_FILENAME" ]; then
            ABS_PATH="$PROJECT_ROOT/public/$IMG2IMG_FILENAME"
            if [ -f "$ABS_PATH" ]; then
                IMG2IMG_FILENAME="$ABS_PATH"
                DB_IMG2IMG_FRAME_ID="$IMG2IMG_FRAME_ID"
            else
                echo "WARNING: img2img source file not found at $ABS_PATH."
                IMG2IMG_FILENAME=""
                DB_IMG2IMG_FRAME_ID="NULL"
            fi
        fi

        # Upload primary image
        PRIMARY_IMAGE_URL=""
        if [ -n "$IMG2IMG_FILENAME" ] && [ -f "$IMG2IMG_FILENAME" ]; then
            echo "Uploading source image to Freeimage.host..."
            response=$(curl -s -X POST "https://freeimage.host/api/1/upload" -F "key=$FREEIMAGE_KEY" -F "action=upload" -F "source=@$IMG2IMG_FILENAME" -F "format=json")
            PRIMARY_IMAGE_URL=$(echo "$response" | jq -r '.image.url')
            if [ -n "$PRIMARY_IMAGE_URL" ] && [ "$PRIMARY_IMAGE_URL" != "null" ]; then
                echo "Uploaded source image URL: $PRIMARY_IMAGE_URL"
            else
                echo "Freeimage upload failed, falling back to litterbox..."
                litter_response=$(curl -s -F "reqtype=fileupload" -F "time=1h" -F "fileToUpload=@$IMG2IMG_FILENAME" https://litterbox.catbox.moe/resources/internals/api.php)
                if [[ "$litter_response" == http* ]]; then
                    PRIMARY_IMAGE_URL="$litter_response"
                    echo "Uploaded source image URL (fallback): $PRIMARY_IMAGE_URL"
                else
                    ERROR_MSG="Failed to upload primary source image."
                    PRIMARY_IMAGE_URL=""
                fi
            fi
        fi

        # Check for enhancements assigned frames
        ADDITIONAL_IMAGE_URLS=""
        if [ "$IS_ENHANCEMENT" = true ]; then
            mapfile -t enhancement_frame_ids < <(mysql $MYSQL_ARGS -N -e "SELECT frame_id FROM frame_enhancement_frames WHERE frame_enhancement_id = $ENTITY_ID ORDER BY frame_id ASC;")
            if [ ${#enhancement_frame_ids[@]} -gt 0 ]; then
                echo "Uploading additional assigned enhancement frames..."
                uploaded_extra_urls=()
                for extra_frame_id in "${enhancement_frame_ids[@]}"; do
                    extra_frame_filename=$(mysql $MYSQL_ARGS -N -e "SELECT filename FROM frames WHERE id = $extra_frame_id LIMIT 1;" | tr -d '\r')
                    if [ -n "$extra_frame_filename" ] && [ "$extra_frame_filename" != "NULL" ]; then
                        extra_frame_abs_path="$PROJECT_ROOT/public/$extra_frame_filename"
                        if [ -f "$extra_frame_abs_path" ]; then
                            response=$(curl -s -X POST "https://freeimage.host/api/1/upload" -F "key=$FREEIMAGE_KEY" -F "action=upload" -F "source=@$extra_frame_abs_path" -F "format=json")
                            extra_frame_url=$(echo "$response" | jq -r '.image.url')
                            if [ -n "$extra_frame_url" ] && [ "$extra_frame_url" != "null" ]; then
                                uploaded_extra_urls+=("$extra_frame_url")
                            else
                                litter_response=$(curl -s -F "reqtype=fileupload" -F "time=1h" -F "fileToUpload=@$extra_frame_abs_path" https://litterbox.catbox.moe/resources/internals/api.php)
                                if [[ "$litter_response" == http* ]]; then
                                    uploaded_extra_urls+=("$litter_response")
                                fi
                            fi
                        fi
                    fi
                done
                if [ ${#uploaded_extra_urls[@]} -gt 0 ]; then ADDITIONAL_IMAGE_URLS=$(IFS=,; echo "${uploaded_extra_urls[*]}"); fi
            fi
        fi

        # Check for composites assigned frames
        COMPOSITE_IMAGE_URLS=""
        if [ "$MAPPING_ENTITY_TYPE" = "composites" ]; then
            mapfile -t composite_frame_ids < <(mysql $MYSQL_ARGS -N -e "SELECT frame_id FROM composite_frames WHERE composite_id = $MAPPING_ENTITY_ID ORDER BY frame_id ASC;")
            if [ ${#composite_frame_ids[@]} -gt 0 ]; then
                echo "Uploading composite assigned frames..."
                uploaded_comp_urls=()
                for comp_frame_id in "${composite_frame_ids[@]}"; do
                    comp_frame_filename=$(mysql $MYSQL_ARGS -N -e "SELECT filename FROM frames WHERE id = $comp_frame_id LIMIT 1;" | tr -d '\r')
                    if [ -n "$comp_frame_filename" ] && [ "$comp_frame_filename" != "NULL" ]; then
                        comp_frame_abs_path="$PROJECT_ROOT/public/$comp_frame_filename"
                        if [ -f "$comp_frame_abs_path" ]; then
                            response=$(curl -s -X POST "https://freeimage.host/api/1/upload" -F "key=$FREEIMAGE_KEY" -F "action=upload" -F "source=@$comp_frame_abs_path" -F "format=json")
                            comp_frame_url=$(echo "$response" | jq -r '.image.url')
                            if [ -n "$comp_frame_url" ] && [ "$comp_frame_url" != "null" ]; then
                                uploaded_comp_urls+=("$comp_frame_url")
                            else
                                litter_response=$(curl -s -F "reqtype=fileupload" -F "time=1h" -F "fileToUpload=@$comp_frame_abs_path" https://litterbox.catbox.moe/resources/internals/api.php)
                                if [[ "$litter_response" == http* ]]; then
                                    uploaded_comp_urls+=("$litter_response")
                                fi
                            fi
                        fi
                    fi
                done
                if [ ${#uploaded_comp_urls[@]} -gt 0 ]; then COMPOSITE_IMAGE_URLS=$(IFS=,; echo "${uploaded_comp_urls[*]}"); fi
            fi
        fi

        # Final IMAGE_URL resolution based on hierarchy
        IMAGE_URL=""
        if [ "$IS_ENHANCEMENT" = true ]; then
            if [ -n "$PRIMARY_IMAGE_URL" ] && [ -n "$ADDITIONAL_IMAGE_URLS" ]; then IMAGE_URL="$PRIMARY_IMAGE_URL,$ADDITIONAL_IMAGE_URLS"
            elif [ -n "$PRIMARY_IMAGE_URL" ]; then IMAGE_URL="$PRIMARY_IMAGE_URL"
            elif [ -n "$ADDITIONAL_IMAGE_URLS" ]; then IMAGE_URL="$ADDITIONAL_IMAGE_URLS"
            fi
        elif [ "$MAPPING_ENTITY_TYPE" = "composites" ]; then
            if [ -n "$COMPOSITE_IMAGE_URLS" ]; then IMAGE_URL="$COMPOSITE_IMAGE_URLS"
            elif [ -n "$PRIMARY_IMAGE_URL" ]; then IMAGE_URL="$PRIMARY_IMAGE_URL"
            fi
        else
            IMAGE_URL="$PRIMARY_IMAGE_URL"
        fi
        
        FRAME_ID=""

        # ------------------------------------------------------------------
        # 7. Generation Block — URL assembled from DB provider config
        # ------------------------------------------------------------------
        if [ -z "$ERROR_MSG" ]; then
            for ((j=START; j<=END; j++)); do
                row="${styles[j]}"
                style_id=$(echo "$row" | awk '{print $1}')
                style=$(echo "$row" | cut -d' ' -f2-)

                prompt="$BASE_PROMPT"
                [ "$NO_STYLES" -eq 0 ] && prompt="$prompt, $style"
                [ -n "$ADD_TO_PROMPT" ] && prompt="$prompt $ADD_TO_PROMPT"
                [ -n "$IMG2IMG_PROMPT" ] && [ -n "$IMAGE_URL" ] && prompt="$prompt $IMG2IMG_PROMPT"

                # DB saving logic: Enhancements save mapped entity description, normal saves generated prompt
                if [ "$IS_ENHANCEMENT" = true ]; then
                    db_prompt_text="$ORIG_ENTITY_DESC"
                    [ "$NO_STYLES" -eq 0 ] && db_prompt_text="$db_prompt_text, $style"
                else
                    db_prompt_text="$prompt"
                fi

                frame_basename=$(mysql $MYSQL_ARGS -N --batch --skip-column-names -e "
                  UPDATE frame_counter SET next_frame = LAST_INSERT_ID(next_frame + 1);
                  SELECT LPAD(LAST_INSERT_ID(), 7, '0');
                ")
                frame_basename="frame$frame_basename"
                outfile="$FRAMES_DIR/$frame_basename.jpg"
                filename_only="$frame_basename"

                url_prompt=$(echo -n "$prompt" | tr -d '?%' | jq -sRr @uri)
                url_negative=$(echo -n "$NEGATIVE_PROMPT" | tr -d '?%' | jq -sRr @uri)

                echo "Generating image $filename_only [Style ID: $style_id]"
                
                if [ -n "$SEED_ARG" ] && [ "$SEED_ARG" -ne 0 ]; then
                    SEED="$SEED_ARG"
                else
                    SEED=$(( (RANDOM << 15) | RANDOM ))
                fi

                # ----------------------------------------------------------
                # Build request URL from DB provider config
                # Substitutes {prompt} in path_template, then appends params.
                # Runtime params (negative_prompt, seed, image) are always
                # injected here regardless of what's in the params table.
                # ----------------------------------------------------------
                REQ_PATH="${JOB_PATH_TEMPLATE/\{prompt\}/$url_prompt}"
                
                # Assemble query string from JOB_PARAMS
                QUERY_STRING=""
                for pk in "${!JOB_PARAMS[@]}"; do
                    pv="${JOB_PARAMS[$pk]}"
                    # Skip image param here — handled below
                    [ "$pk" = "image" ] && continue
                    [ -z "$pv" ] && continue
                    [ -n "$QUERY_STRING" ] && QUERY_STRING="${QUERY_STRING}&"
                    QUERY_STRING="${QUERY_STRING}${pk}=$(echo -n "$pv" | jq -sRr @uri)"
                done

                # Always inject runtime params
                [ -n "$QUERY_STRING" ] && QUERY_STRING="${QUERY_STRING}&"
                QUERY_STRING="${QUERY_STRING}nologo=true&negative_prompt=${url_negative}&seed=${SEED}"

                # Append image URL if present
                if [ -n "$IMAGE_URL" ]; then
                    enc_img=$(echo -n "$IMAGE_URL" | jq -sRr @uri)
                    QUERY_STRING="${QUERY_STRING}&image=${enc_img}"
                fi

                FULL_URL="${JOB_BASE_URL}${REQ_PATH}?${QUERY_STRING}"

                attempt=1
                gen_success=false
                while [ $attempt -le $MAX_RETRIES ]; do
                    curl -s -L -H "Authorization: Bearer $PAI_TOKEN" \
                      "$FULL_URL" \
                      -o "$outfile"

                    ffmpeg -v error -i "$outfile" -f null - 2>/dev/null
                    if [ $? -eq 0 ]; then
                        echo "Saved valid image: $outfile"
                        
                        SAFE_NEG_PROMPT=$(echo "$NEGATIVE_PROMPT" | sed "s/'/''/g")
                        SAFE_DB_PROMPT=$(echo "$db_prompt_text" | sed "s/'/''/g")
                        SAFE_STYLE=$(echo "$style" | sed "s/'/''/g")
                        
                        SAFE_IMG2IMG_PROMPT="NULL"
                        [ -n "$IMG2IMG_PROMPT" ] && SAFE_IMG2IMG_PROMPT="'$(echo "$IMG2IMG_PROMPT" | sed "s/'/''/g")'"

                        SAFE_MODEL="NULL"
                        [ -n "${JOB_PARAMS['model']}" ] && SAFE_MODEL="'$(echo "${JOB_PARAMS['model']}" | sed "s/'/''/g")'"

                        FRAME_ID=$(mysql $MYSQL_ARGS -N -e "
                          INSERT INTO frames
                            (filename, name, prompt, prompt_negative, seed, entity_type, entity_id, style, style_id, map_run_id, model, img2img_frame_id, img2img_prompt)
                          VALUES
                            ('$FRAMES_DIR_REL/$filename_only.jpg',
                             '$filename_only',
                             '$SAFE_DB_PROMPT',
                             '$SAFE_NEG_PROMPT',
                             $SEED,
                             '$MAPPING_ENTITY_TYPE',
                             $MAPPING_ENTITY_ID,
                             '$SAFE_STYLE',
                             $style_id,
                             $MAP_RUN_ID,
                             $SAFE_MODEL,
                             $DB_IMG2IMG_FRAME_ID,
                             $SAFE_IMG2IMG_PROMPT
                          );
                          SELECT LAST_INSERT_ID();")

                        [ -n "$FRAME_ID" ] && mysql $MYSQL_ARGS -e \
                          "INSERT INTO $MAPPING_TABLE (from_id, to_id) VALUES ($FRAME_ID, $MAPPING_ENTITY_ID);"

                        gen_success=true
                        break
                    else
                        echo "Broken image. Retry $attempt/$MAX_RETRIES..."
                        rm -f "$outfile"
                        attempt=$((attempt+1))
                        sleep $RETRY_DELAY
                    fi
                done
                if [ "$gen_success" = false ]; then
                   ERROR_MSG="Failed to generate image after $MAX_RETRIES attempts."
                   break
                fi
            done
        fi
    fi

    # --- RESOLUTION & QUEUE UPDATE ---
    # The error_msg claim token is cleanly overwritten here
    if [ -n "$ERROR_MSG" ]; then
        echo "✗ Task Failed: $ERROR_MSG"
        ATTEMPTS=$((ATTEMPTS + 1))
        SAFE_ERROR=$(echo "$ERROR_MSG" | sed "s/'/''/g")
        
        if [ "$ATTEMPTS" -ge "$MAX_ATTEMPTS" ]; then
            mysql $MYSQL_ARGS -e "UPDATE map_run_queue SET status = 'failed', error_msg = '$SAFE_ERROR', completed_at = NOW() WHERE id = $QUEUE_ID;"
            echo "Max attempts reached. Marked as failed."
        else
            mysql $MYSQL_ARGS -e "UPDATE map_run_queue SET status = 'pending', attempts = $ATTEMPTS, error_msg = '$SAFE_ERROR' WHERE id = $QUEUE_ID;"
            echo "Requeued for retry ($ATTEMPTS/$MAX_ATTEMPTS)."
        fi
    else
        echo "✓ Generation complete."
        FINAL_ASSET_ID="${FRAME_ID:-NULL}"
        mysql $MYSQL_ARGS -e "UPDATE map_run_queue SET status = 'completed', asset_id = $FINAL_ASSET_ID, completed_at = NOW(), error_msg = NULL WHERE id = $QUEUE_ID;"
    fi

done

echo "========================================================"
echo "Worker execution complete."
echo "========================================================"
exit 0