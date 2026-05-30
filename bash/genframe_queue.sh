#!/bin/bash

# ==============================================================================
# SAGE Frame Generation Queue Worker
# Fetches pending frame generation tasks from map_run_queue and processes them.
# Usage: ./genframe_queue.sh [limit]
# ==============================================================================

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MYSQL_ARGS=$("$SCRIPT_DIR"/db_name.sh main-conn)

PROCESS_LIMIT="${1:-4}"

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
# 2. Fetch Pending Queue Items
# -----------------------------
# We specifically avoid frame_enhancements to leave them for the other worker
QUERY="
  SELECT id, map_run_id, entity_type, entity_id, attempts, max_attempts, api_provider_config
  FROM map_run_queue 
  WHERE status = 'pending' AND entity_type != 'frame_enhancements' AND asset_type = 'frames'
  ORDER BY priority DESC, id ASC 
  LIMIT $PROCESS_LIMIT
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
    echo "No pending frame generation tasks in queue."
    exit 0
fi

echo "========================================================"
echo "Processing $TOTAL_COUNT queued frame generation tasks..."
echo "========================================================"

# -----------------------------
# 3. Process Loop
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
    echo "[Task $QUEUE_ID] Starting Frame Generation for $ENTITY_TYPE #$ENTITY_ID (MapRun: $MAP_RUN_ID)"
    
    # Mark as processing
    mysql $MYSQL_ARGS -e "UPDATE map_run_queue SET status = 'processing', started_at = NOW() WHERE id = $QUEUE_ID;"

    # Configuration parsing
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

    # Fetch original entity data safely using the view (same as old logic)
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
        mysql $MYSQL_ARGS -e "UPDATE map_run_queue SET status = 'failed', error_msg = 'Entity record not found in view', completed_at = NOW() WHERE id = $QUEUE_ID;"
        echo "✗ Task Failed: Entity record not found in view $VIEW_NAME."
        continue
    fi

    IFS=$'\x1f' read -r ENTITY_PROMPT ENTITY_SPECIFIC_NEGATIVE ENTITY_SEED <<< "$RAW_DATA"

    if [ -n "$ADD_TO_PROMPT" ]; then FULL_PROMPT="$ENTITY_PROMPT $ADD_TO_PROMPT"; else FULL_PROMPT="$ENTITY_PROMPT"; fi
    if [ -n "$ENTITY_SPECIFIC_NEGATIVE" ]; then COMBINED_NEGATIVE_PROMPT="$ENTITY_SPECIFIC_NEGATIVE, $GLOBAL_NEGATIVE_PROMPT"; else COMBINED_NEGATIVE_PROMPT="$GLOBAL_NEGATIVE_PROMPT"; fi

    BASE_PROMPT="$FULL_PROMPT"
    NEGATIVE_PROMPT="$COMBINED_NEGATIVE_PROMPT"
    SEED_ARG="$ENTITY_SEED"

    ERROR_MSG=""
    USE_TURBO=false 
    MAX_RETRIES=3
    RETRY_DELAY=3

    mapfile -t styles < <(mysql $MYSQL_ARGS -N -e "SELECT id, prompt FROM v_styles_helper;")
    TOTAL_STYLES=${#styles[@]}

    START=$((OFFSET > 0 ? OFFSET - 1 : 0))
    END=$((LIMIT > 0 ? START + LIMIT - 1 : TOTAL_STYLES - 1))
    [ "$END" -ge "$TOTAL_STYLES" ] && END=$((TOTAL_STYLES - 1))

    MAPPING_TABLE="frames_2_${ENTITY_TYPE}"
    TABLE_EXISTS=$(mysql $MYSQL_ARGS -N -e "SHOW TABLES LIKE '$MAPPING_TABLE';")

    if [ -z "$TABLE_EXISTS" ]; then
        ERROR_MSG="Mapping table '$MAPPING_TABLE' does not exist."
    else
        # Fetch img2img info
        read IMG2IMG_FLAG IMG2IMG_FRAME_ID IMG2IMG_PROMPT < <(
          mysql $MYSQL_ARGS -N -e \
            "SELECT COALESCE(img2img,0), COALESCE(img2img_frame_id,0), COALESCE(img2img_prompt,'') FROM $ENTITY_TYPE WHERE id=$ENTITY_ID;"
        )
        IMG2IMG_FLAG=${IMG2IMG_FLAG:-0}
        IMG2IMG_FRAME_ID=${IMG2IMG_FRAME_ID:-0}
        IMG2IMG_PROMPT=${IMG2IMG_PROMPT:-''}
        IMG2IMG_FILENAME=""

        if [ "$IMG2IMG_FRAME_ID" -gt 0 ]; then
            IMG2IMG_FILENAME=$(mysql $MYSQL_ARGS -N -e "SELECT filename FROM frames WHERE id = $IMG2IMG_FRAME_ID LIMIT 1;" | tr -d '\r')
        fi

        if [ -n "$IMG2IMG_FILENAME" ]; then
            ABS_PATH="$PROJECT_ROOT/public/$IMG2IMG_FILENAME"
            if [ -f "$ABS_PATH" ]; then
                IMG2IMG_FILENAME="$ABS_PATH"
            else
                echo "WARNING: img2img source file not found at $ABS_PATH. Falling back to txt2img."
                IMG2IMG_FILENAME=""
                IMG2IMG_FRAME_ID=0
            fi
        fi

        COMPOSITE_IMAGE_URLS=""
        if [ "$ENTITY_TYPE" = "composites" ]; then
            echo "Entity is composites. Checking for assigned frames..."
            mapfile -t composite_frame_ids < <(mysql $MYSQL_ARGS -N -e \
                "SELECT frame_id FROM composite_frames WHERE composite_id = $ENTITY_ID ORDER BY frame_id ASC;")
            
            if [ ${#composite_frame_ids[@]} -gt 0 ]; then
                echo "Found ${#composite_frame_ids[@]} assigned frame(s) for composite ID $ENTITY_ID"
                uploaded_urls=()
                for frame_id in "${composite_frame_ids[@]}"; do
                    frame_filename=$(mysql $MYSQL_ARGS -N -e \
                        "SELECT filename FROM frames WHERE id = $frame_id LIMIT 1;" | tr -d '\r')
                    if [ -n "$frame_filename" ]; then
                        frame_abs_path="$PROJECT_ROOT/public/$frame_filename"
                        if [ -f "$frame_abs_path" ]; then
                            echo "Uploading composite frame $frame_id ($frame_filename) to Freeimage.host..."
                            response=$(curl -s -X POST "https://freeimage.host/api/1/upload" \
                                -F "key=$FREEIMAGE_KEY" \
                                -F "action=upload" \
                                -F "source=@$frame_abs_path" \
                                -F "format=json")
                            frame_url=$(echo "$response" | jq -r '.image.url')
                            if [ -n "$frame_url" ] && [ "$frame_url" != "null" ]; then
                                echo "Uploaded frame $frame_id: $frame_url"
                                uploaded_urls+=("$frame_url")
                            else
                                echo "WARNING: Failed to upload frame $frame_id."
                            fi
                        fi
                    fi
                done
                if [ ${#uploaded_urls[@]} -gt 0 ]; then
                    COMPOSITE_IMAGE_URLS=$(IFS=,; echo "${uploaded_urls[*]}")
                fi
            fi
        fi

        if [ -n "$COMPOSITE_IMAGE_URLS" ]; then
            IMAGE_URL="$COMPOSITE_IMAGE_URLS"
            echo "Using composite assigned frames for img2img"
        elif [ -n "$IMG2IMG_FILENAME" ] && [ -f "$IMG2IMG_FILENAME" ]; then
            echo "Uploading local source image $IMG2IMG_FILENAME to Freeimage.host..."
            response=$(curl -s -X POST "https://freeimage.host/api/1/upload" \
                -F "key=$FREEIMAGE_KEY" \
                -F "action=upload" \
                -F "source=@$IMG2IMG_FILENAME" \
                -F "format=json")
            IMAGE_URL=$(echo "$response" | jq -r '.image.url')
            if [ -z "$IMAGE_URL" ] || [ "$IMAGE_URL" = "null" ]; then
                ERROR_MSG="Failed to upload source image."
            else
                echo "Uploaded source image URL: $IMAGE_URL"
            fi
        else
            IMAGE_URL=""
            IMG2IMG_FILENAME=""
        fi

        FRAME_ID=""

        # --- GENERATION BLOCK ---
        if [ -z "$ERROR_MSG" ]; then
            for ((j=START; j<=END; j++)); do
                row="${styles[j]}"
                style_id=$(echo "$row" | awk '{print $1}')
                style=$(echo "$row" | cut -d' ' -f2-)

                prompt="$BASE_PROMPT"
                [ "$NO_STYLES" -eq 0 ] && prompt="$prompt, $style"
                [ -n "$ADD_TO_PROMPT" ] && prompt="$prompt $ADD_TO_PROMPT"
                [ -n "$IMG2IMG_PROMPT" ] && [ -n "$IMAGE_URL" ] && prompt="$prompt $IMG2IMG_PROMPT"

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

                attempt=1
                gen_success=false
                while [ $attempt -le $MAX_RETRIES ]; do
                    if [ -n "$IMAGE_URL" ]; then
                        curl -s -L -H "Authorization: Bearer $PAI_TOKEN" \
                          "https://gen.pollinations.ai/image/$url_prompt?model=wan-image&image=$IMAGE_URL&width=1024&height=1024&nologo=true&negative_prompt=$url_negative&seed=$SEED" \
                          -o "$outfile"
                    else
                        model_param=""
                        [ "$USE_TURBO" = true ] && model_param="&model=turbo"
                        curl -s -L -H "Authorization: Bearer $PAI_TOKEN" \
                          "https://gen.pollinations.ai/image/$url_prompt?model=wan-image&width=1024&height=1024&nologo=true&negative_prompt=$url_negative&seed=$SEED$model_param" \
                          -o "$outfile"
                    fi

                    ffmpeg -v error -i "$outfile" -f null - 2>/dev/null
                    if [ $? -eq 0 ]; then
                        echo "Saved valid image: $outfile"
                        
                        SAFE_NEG_PROMPT=$(echo "$NEGATIVE_PROMPT" | sed "s/'/''/g")
                        SAFE_PROMPT=$(echo "$prompt" | sed "s/'/''/g")
                        SAFE_STYLE=$(echo "$style" | sed "s/'/''/g")
                        
                        DB_IMG2IMG_FRAME_ID="NULL"
                        [ "$IMG2IMG_FRAME_ID" -gt 0 ] && DB_IMG2IMG_FRAME_ID="$IMG2IMG_FRAME_ID"
                        DB_IMG2IMG_PROMPT="NULL"
                        [ -n "$IMG2IMG_PROMPT" ] && DB_IMG2IMG_PROMPT="'$(echo "$IMG2IMG_PROMPT" | sed "s/'/''/g")'"

                        FRAME_ID=$(mysql $MYSQL_ARGS -N -e "
                          INSERT INTO frames
                            (filename, name, prompt, prompt_negative, seed, entity_type, entity_id, style, style_id, map_run_id, img2img_frame_id, img2img_prompt)
                          VALUES
                            ('$FRAMES_DIR_REL/$filename_only.jpg',
                             '$filename_only',
                             '$SAFE_PROMPT',
                             '$SAFE_NEG_PROMPT',
                             $SEED,
                             '$ENTITY_TYPE',
                             $ENTITY_ID,
                             '$SAFE_STYLE',
                             $style_id,
                             $MAP_RUN_ID,
                             $DB_IMG2IMG_FRAME_ID,
                             $DB_IMG2IMG_PROMPT
                          );
                          SELECT LAST_INSERT_ID();")

                        [ -n "$FRAME_ID" ] && mysql $MYSQL_ARGS -e \
                          "INSERT INTO $MAPPING_TABLE (from_id, to_id) VALUES ($FRAME_ID, $ENTITY_ID);"

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
        echo "✓ Frame generated successfully."
        FINAL_ASSET_ID="${FRAME_ID:-NULL}"
        mysql $MYSQL_ARGS -e "UPDATE map_run_queue SET status = 'completed', asset_id = $FINAL_ASSET_ID, completed_at = NOW(), error_msg = NULL WHERE id = $QUEUE_ID;"
    fi

done

echo "========================================================"
echo "Worker execution complete."
echo "========================================================"
exit 0