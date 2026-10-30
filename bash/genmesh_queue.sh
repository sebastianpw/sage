#!/bin/bash

# ==============================================================================
# SAGE 3D Mesh Generation Queue Worker (Trellis-2 via Pollinations)
# Fetches pending mesh generation tasks from map_run_queue and generates .glb models.
# Usage: ./genmesh_queue.sh [limit]
# ==============================================================================

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MYSQL_ARGS=$("$SCRIPT_DIR"/db_name.sh main-conn)

PROCESS_LIMIT="${1:-2}"
CURL_TIMEOUT=300

export PAI_TOKEN=$(cat "$SCRIPT_DIR/../token/.pollinationsaitoken" 2>/dev/null | tr -d ' \r\n')
export FREEIMAGE_KEY=$(cat "$SCRIPT_DIR/../token/.freeimage_key" 2>/dev/null | tr -d ' \r\n')

if [ -f "$SCRIPT_DIR/load_root.sh" ]; then
  source "$SCRIPT_DIR/load_root.sh"
fi

if [ -z "$PROJECT_ROOT" ]; then
  echo "ERROR: PROJECT_ROOT not set. Aborting."
  exit 1
fi

MODELS_DIR="$PROJECT_ROOT/public/models"
mkdir -p "$MODELS_DIR"

# ------------------------------------------------------------------------------
# Helper: Upload Image Chain (Pollinations -> Freeimage -> Litterbox)
# ------------------------------------------------------------------------------
upload_image_chain() {
    local filepath="$1"
    local final_url=""
    
    # 1. Pollinations Media Upload
    local polli_resp
    polli_resp=$(curl -m 60 -s -X POST "https://media.pollinations.ai/upload" -H "Authorization: Bearer $PAI_TOKEN" -F "file=@$filepath")
    if [[ "$polli_resp" == http* ]]; then
        final_url="$polli_resp"
    else
        local polli_url
        polli_url=$(echo "$polli_resp" | jq -r '.url // empty' 2>/dev/null)
        if [[ "$polli_url" == http* ]]; then
            final_url="$polli_url"
        fi
    fi
    
    # 2. Freeimage Host
    if [ -z "$final_url" ] || [ "$final_url" == "null" ]; then
        local fi_resp fi_url
        fi_resp=$(curl -m 60 -s -X POST "https://freeimage.host/api/1/upload" -F "key=$FREEIMAGE_KEY" -F "action=upload" -F "source=@$filepath" -F "format=json")
        fi_url=$(echo "$fi_resp" | jq -r '.image.url' 2>/dev/null)
        if [ -n "$fi_url" ] && [ "$fi_url" != "null" ]; then
            final_url="$fi_url"
        fi
    fi
    
    # 3. Litterbox Fallback
    if [ -z "$final_url" ] || [ "$final_url" == "null" ]; then
        local lb_resp
        lb_resp=$(curl -m 60 -s -F "reqtype=fileupload" -F "time=1h" -F "fileToUpload=@$filepath" https://litterbox.catbox.moe/resources/internals/api.php)
        if [[ "$lb_resp" == http* ]]; then
            final_url="$lb_resp"
        fi
    fi
    
    echo "$final_url"
}

# -----------------------------
# 1. Atomically Claim Pending Tasks
# -----------------------------
WORKER_CLAIM_ID="mesh_worker_$$_${RANDOM}"

mysql $MYSQL_ARGS -e "
  UPDATE map_run_queue 
  SET status = 'processing', 
      started_at = NOW(), 
      error_msg = '$WORKER_CLAIM_ID'
  WHERE status = 'pending' AND asset_type = 'meshes'
  ORDER BY priority DESC, id ASC 
  LIMIT $PROCESS_LIMIT;
"

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
    echo "No pending mesh tasks in queue."
    exit 0
fi

echo "========================================================"
echo "Processing $TOTAL_COUNT queued 3D mesh tasks..."
echo "========================================================"

# -----------------------------
# 2. Process Loop
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
    echo "[Task $QUEUE_ID] Starting 3D Mesh Generation for $ENTITY_TYPE #$ENTITY_ID (MapRun: $MAP_RUN_ID)"

    RESOLUTION=$(echo "$CONFIG_JSON" | jq -r '.resolution // "high"')
    MODEL=$(echo "$CONFIG_JSON" | jq -r '.model // "microsoft/trellis-2"')

    ERROR_MSG=""
    SOURCE_IMAGE_PATH=""

    # -----------------------------
    # 3. Resolve Entity Source Image
    # -----------------------------
    HAS_IMG2IMG_COL=$(mysql $MYSQL_ARGS -N -e "SHOW COLUMNS FROM \`$ENTITY_TYPE\` LIKE 'img2img_frame_id';")
    if [ -n "$HAS_IMG2IMG_COL" ]; then
        IMG_FRAME_ID=$(mysql $MYSQL_ARGS -N -e "SELECT COALESCE(img2img_frame_id, 0) FROM \`$ENTITY_TYPE\` WHERE id = $ENTITY_ID LIMIT 1;")
        if [ "$IMG_FRAME_ID" -gt 0 ]; then
            FRAME_REL=$(mysql $MYSQL_ARGS -N -e "SELECT filename FROM frames WHERE id = $IMG_FRAME_ID LIMIT 1;")
            [ -n "$FRAME_REL" ] && [ -f "$PROJECT_ROOT/public/$FRAME_REL" ] && SOURCE_IMAGE_PATH="$PROJECT_ROOT/public/$FRAME_REL"
        fi
    fi

    if [ -z "$SOURCE_IMAGE_PATH" ]; then
        MAPPING_TABLE="frames_2_${ENTITY_TYPE}"
        HAS_MAP_TBL=$(mysql $MYSQL_ARGS -N -e "SHOW TABLES LIKE '$MAPPING_TABLE';")
        if [ -n "$HAS_MAP_TBL" ]; then
            FRAME_REL=$(mysql $MYSQL_ARGS -N -e "
                SELECT f.filename 
                FROM frames f 
                JOIN \`$MAPPING_TABLE\` m ON f.id = m.from_id 
                WHERE m.to_id = $ENTITY_ID 
                ORDER BY f.id DESC LIMIT 1;
            ")
            [ -n "$FRAME_REL" ] && [ -f "$PROJECT_ROOT/public/$FRAME_REL" ] && SOURCE_IMAGE_PATH="$PROJECT_ROOT/public/$FRAME_REL"
        fi
    fi

    if [ -z "$SOURCE_IMAGE_PATH" ]; then
        FRAME_REL=$(mysql $MYSQL_ARGS -N -e "
            SELECT filename 
            FROM frames 
            WHERE entity_type = '$ENTITY_TYPE' AND entity_id = $ENTITY_ID 
            ORDER BY id DESC LIMIT 1;
        ")
        [ -n "$FRAME_REL" ] && [ -f "$PROJECT_ROOT/public/$FRAME_REL" ] && SOURCE_IMAGE_PATH="$PROJECT_ROOT/public/$FRAME_REL"
    fi

    if [ -z "$SOURCE_IMAGE_PATH" ]; then
        ERROR_MSG="No source frame found for $ENTITY_TYPE #$ENTITY_ID to generate 3D model from."
    else
        echo "Source image resolved: $SOURCE_IMAGE_PATH"
        
        # -----------------------------
        # 4. Upload to obtain public URL
        # -----------------------------
        echo "Uploading source image to obtain public URL..."
        PUBLIC_IMAGE_URL=$(upload_image_chain "$SOURCE_IMAGE_PATH")

        if [ -z "$PUBLIC_IMAGE_URL" ] || [ "$PUBLIC_IMAGE_URL" == "null" ]; then
            ERROR_MSG="Failed to upload source frame through upload chain."
        else
            echo "Public image URL: $PUBLIC_IMAGE_URL"

            # -----------------------------
            # 5. Call Pollinations Trellis-2
            # -----------------------------
            TIMESTAMP=$(date +%Y%m%d_%H%M%S)
            BASENAME="mesh_${TIMESTAMP}_${ENTITY_TYPE}_${ENTITY_ID}"
            OUTFILE="$MODELS_DIR/${BASENAME}.glb"
            DB_FILENAME="models/${BASENAME}.glb"

            ENC_IMG=$(echo -n "$PUBLIC_IMAGE_URL" | jq -sRr @uri)
            ENC_MODEL=$(echo -n "$MODEL" | jq -sRr @uri)
            REQ_URL="https://gen.pollinations.ai/3d/no_prompt_for_trellis_needed?model=${ENC_MODEL}&resolution=${RESOLUTION}&key=${PAI_TOKEN}&image=${ENC_IMG}"

            echo "Requesting 3D Model ($MODEL, resolution=$RESOLUTION)..."

            attempt=1
            MAX_RETRIES=2
            gen_success=false

            while [ $attempt -le $MAX_RETRIES ]; do
                # Fetch with location-trusted and decompression enabled
                curl -m "$CURL_TIMEOUT" -s -L --location-trusted --compressed \
                    -H "Authorization: Bearer $PAI_TOKEN" \
                    "$REQ_URL" \
                    -o "$OUTFILE"

                # Check if the endpoint returned a plain text URL
                FIRST_BYTES=$(head -c 8 "$OUTFILE" 2>/dev/null)
                if [[ "$FIRST_BYTES" == http* ]]; then
                    DL_URL=$(cat "$OUTFILE" | tr -d ' \r\n')
                    echo "Received direct 3D URL: $DL_URL. Downloading binary..."
                    curl -m "$CURL_TIMEOUT" -s -L --location-trusted "$DL_URL" -o "$OUTFILE"
                fi

                # Check if the endpoint returned a JSON payload with a URL
                if [[ "$FIRST_BYTES" == "{"* ]]; then
                    EXTRACTED_URL=$(jq -r '.url // .data[0].url // empty' "$OUTFILE" 2>/dev/null)
                    if [[ "$EXTRACTED_URL" == http* ]]; then
                        echo "Extracted URL from JSON: $EXTRACTED_URL. Downloading binary..."
                        curl -m "$CURL_TIMEOUT" -s -L --location-trusted "$EXTRACTED_URL" -o "$OUTFILE"
                    fi
                fi

                # Validate binary glTF header (magic bytes: "glTF", size > 1024)
                MAGIC=$(head -c 4 "$OUTFILE" 2>/dev/null)
                FILESIZE=$(wc -c < "$OUTFILE" 2>/dev/null || echo 0)

                if [ "$MAGIC" = "glTF" ] && [ "$FILESIZE" -gt 1024 ]; then
                    echo "✓ Valid GLB received ($FILESIZE bytes): $OUTFILE"
                    gen_success=true
                    break
                else
                    SNIPPET=$(head -c 250 "$OUTFILE" 2>/dev/null | tr '\n' ' ')
                    echo "✗ Unexpected response (magic: '$MAGIC', size: $FILESIZE bytes)."
                    echo "  Response preview: $SNIPPET"
                    attempt=$((attempt + 1))
                    [ $attempt -le $MAX_RETRIES ] && sleep 5
                fi
            done

            if [ "$gen_success" = false ]; then
                ERROR_MSG="Failed to obtain valid glTF binary after $MAX_RETRIES attempts."
            else
                # -----------------------------
                # 6. Insert into meshes table
                # -----------------------------
                ENTITY_NAME=$(mysql $MYSQL_ARGS -N -e "SELECT name FROM \`$ENTITY_TYPE\` WHERE id = $ENTITY_ID LIMIT 1;" 2>/dev/null)
                [ -z "$ENTITY_NAME" ] && ENTITY_NAME="$ENTITY_TYPE #$ENTITY_ID"
                SAFE_NAME=$(echo "$ENTITY_NAME" | sed "s/'/''/g")

                MESH_ID=$(mysql $MYSQL_ARGS -N -e "
                    INSERT INTO meshes (map_run_id, name, filename, entity_type, entity_id, created_at)
                    VALUES ($MAP_RUN_ID, '$SAFE_NAME', '$DB_FILENAME', '$ENTITY_TYPE', $ENTITY_ID, NOW());
                    SELECT LAST_INSERT_ID();
                ")

                echo "✓ Created SAGE Mesh #$MESH_ID (Filename: $DB_FILENAME)"

                # -----------------------------
                # 7. Relational Link Mappings
                # -----------------------------
                if [ "$ENTITY_TYPE" = "animatics" ]; then
                    mysql $MYSQL_ARGS -e "
                        INSERT IGNORE INTO animatic_meshes (animatic_id, mesh_id, created_at)
                        VALUES ($ENTITY_ID, $MESH_ID, NOW());
                    "
                fi

                MAP_TABLE="meshes_2_${ENTITY_TYPE}"
                HAS_TABLE=$(mysql $MYSQL_ARGS -N -e "SHOW TABLES LIKE '$MAP_TABLE';")
                if [ -n "$HAS_TABLE" ]; then
                    mysql $MYSQL_ARGS -e "
                        INSERT IGNORE INTO \`$MAP_TABLE\` (from_id, to_id)
                        VALUES ($MESH_ID, $ENTITY_ID);
                    "
                fi
            fi
        fi
    fi

    # -----------------------------
    # 8. Queue Task Resolution
    # -----------------------------
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
        FINAL_ASSET_ID="${MESH_ID:-NULL}"
        mysql $MYSQL_ARGS -e "UPDATE map_run_queue SET status = 'completed', asset_id = $FINAL_ASSET_ID, completed_at = NOW(), error_msg = NULL WHERE id = $QUEUE_ID;"
        echo "✓ Task #$QUEUE_ID completed successfully (Asset ID: $FINAL_ASSET_ID)."
    fi

done

echo "========================================================"
echo "Mesh queue worker execution complete."
echo "========================================================"
exit 0