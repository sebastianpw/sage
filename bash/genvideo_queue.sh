#!/bin/bash

# ==============================================================================
# SAGE Video Generation Queue Worker
# Fetches pending video tasks from map_run_queue and processes them.
# Usage: ./genvideo_queue.sh [limit]
# Example: ./genvideo_queue.sh 4  (Processes 4 videos at a time)
# ==============================================================================

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MYSQL_ARGS=$("$SCRIPT_DIR"/db_name.sh main-conn)

PROCESS_LIMIT="${1:-4}" # Default to 4 per run

export PAI_TOKEN=$(cat "$SCRIPT_DIR/../token/.pollinationsaitoken")
export FREEIMAGE_KEY=$(cat "$SCRIPT_DIR/../token/.freeimage_key")

if [ -f "$SCRIPT_DIR/load_root.sh" ]; then
  source "$SCRIPT_DIR/load_root.sh"
fi

VIDEOS_DIR="$PROJECT_ROOT/public/videos"
THUMBS_DIR="$VIDEOS_DIR/thumbnails"
mkdir -p "$VIDEOS_DIR" "$THUMBS_DIR"

VIDEOS_DIR_REL="videos"
THUMBS_DIR_REL="videos/thumbnails"

# -----------------------------
# 1. Fetch Pending Queue Items
# -----------------------------
QUERY="
  SELECT id, map_run_id, entity_type, entity_id, attempts, max_attempts 
  FROM map_run_queue 
  WHERE status = 'pending' AND asset_type = 'videos' 
  ORDER BY priority DESC, id ASC 
  LIMIT $PROCESS_LIMIT
"

declare -a Q_IDS Q_MAP_RUNS Q_ENTITY_TYPES Q_ENTITY_IDS Q_ATTEMPTS Q_MAX_ATTEMPTS
while IFS=$'\t' read -r q_id m_id e_type e_id att max_att; do
    Q_IDS+=("$q_id")
    Q_MAP_RUNS+=("$m_id")
    Q_ENTITY_TYPES+=("$e_type")
    Q_ENTITY_IDS+=("$e_id")
    Q_ATTEMPTS+=("$att")
    Q_MAX_ATTEMPTS+=("$max_att")
done < <(mysql $MYSQL_ARGS -N -e "$QUERY")

TOTAL_COUNT=${#Q_IDS[@]}
if [ "$TOTAL_COUNT" -eq 0 ]; then
    echo "No pending video tasks in queue."
    exit 0
fi

echo "========================================================"
echo "Processing $TOTAL_COUNT queued video tasks..."
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
    
    echo ""
    echo "[Task $QUEUE_ID] Starting $ENTITY_TYPE #$ENTITY_ID (MapRun: $MAP_RUN_ID)"
    
    # Mark as processing
    mysql $MYSQL_ARGS -e "UPDATE map_run_queue SET status = 'processing', started_at = NOW() WHERE id = $QUEUE_ID;"

    # Initialize execution variables
    MODEL="wan-fast"
    SEED=$(( (RANDOM << 15) | RANDOM ))
    WIDTH=1024
    HEIGHT=1024
    DURATION=6
    PROMPT=""
    IMAGE_PARAM=""
    ERROR_MSG=""
    
    # --- LOGIC BLOCK: ENTITY SPECIFIC PREPARATION ---
    if [ "$ENTITY_TYPE" == "composites" ]; then
        echo "Mode: Composites (Interpolation)"
        mapfile -t composite_frame_ids < <(mysql $MYSQL_ARGS -N -e "SELECT frame_id FROM composite_frames WHERE composite_id = $ENTITY_ID ORDER BY frame_id ASC LIMIT 2;")
        
        if [ ${#composite_frame_ids[@]} -lt 2 ]; then
            ERROR_MSG="Composites require exactly 2 assigned frames."
        else
            PROMPT=$(mysql $MYSQL_ARGS -N -e "SELECT description FROM composites WHERE id=$ENTITY_ID LIMIT 1;")
            if [ -z "$PROMPT" ] || [ "$PROMPT" == "NULL" ]; then
                 PROMPT=$(mysql $MYSQL_ARGS -N -e "SELECT name FROM composites WHERE id=$ENTITY_ID LIMIT 1;")
            fi

            declare -a uploaded_urls
            for frame_id in "${composite_frame_ids[@]}"; do
                filename=$(mysql $MYSQL_ARGS -N -e "SELECT filename FROM frames WHERE id = $frame_id LIMIT 1;" | tr -d '\r')
                abs_path="$PROJECT_ROOT/public/$filename"
                
                if [ -f "$abs_path" ]; then
                    echo "Uploading frame #$frame_id..."
                    response=$(curl -s -X POST "https://freeimage.host/api/1/upload" -F "key=$FREEIMAGE_KEY" -F "action=upload" -F "source=@$abs_path" -F "format=json")
                    url=$(echo "$response" | jq -r '.image.url')
                    if [ -n "$url" ] && [ "$url" != "null" ]; then
                        uploaded_urls+=("$url")
                    else
                        ERROR_MSG="Error uploading frame $frame_id"
                        break
                    fi
                else
                    ERROR_MSG="File not found: $abs_path"
                    break
                fi
            done
            IMAGE_PARAM=$(IFS=,; echo "${uploaded_urls[*]}")
        fi

    elif [ "$ENTITY_TYPE" == "animatics" ]; then
        echo "Mode: Animatics"
        RAW_DATA=$(mysql $MYSQL_ARGS -N -e "SELECT COALESCE(img2img,0), COALESCE(img2img_frame_id,0), COALESCE(img2img_prompt,''), description, name FROM animatics WHERE id=$ENTITY_ID LIMIT 1;")
        IFS=$'\t' read -r IMG2IMG_FLAG IMG2IMG_FRAME_ID DB_IMG_PROMPT DB_DESC DB_NAME <<< "$RAW_DATA"

        if [ -n "$DB_IMG_PROMPT" ] && [ "$DB_IMG_PROMPT" != "NULL" ] && [ "$DB_IMG_PROMPT" != "" ]; then PROMPT="$DB_IMG_PROMPT";
        elif [ -n "$DB_DESC" ] && [ "$DB_DESC" != "NULL" ] && [ "$DB_DESC" != "" ]; then PROMPT="$DB_DESC";
        else PROMPT="$DB_NAME"; fi

        if [ "$IMG2IMG_FLAG" -eq 1 ] && [ "$IMG2IMG_FRAME_ID" -gt 0 ]; then
            echo "Type: Image-to-Video"
            filename=$(mysql $MYSQL_ARGS -N -e "SELECT filename FROM frames WHERE id = $IMG2IMG_FRAME_ID LIMIT 1;" | tr -d '\r')
            abs_path="$PROJECT_ROOT/public/$filename"

            if [ -f "$abs_path" ]; then
                echo "Uploading source frame #$IMG2IMG_FRAME_ID..."
                response=$(curl -s -X POST "https://freeimage.host/api/1/upload" -F "key=$FREEIMAGE_KEY" -F "action=upload" -F "source=@$abs_path" -F "format=json")
                url=$(echo "$response" | jq -r '.image.url')
                if [ -n "$url" ] && [ "$url" != "null" ]; then
                    IMAGE_PARAM="$url"
                else
                    ERROR_MSG="Error uploading source image."
                fi
            else
                 ERROR_MSG="Source image file missing: $abs_path"
            fi
        else
            echo "Type: Text-to-Video"
            IMAGE_PARAM=""
        fi
    else
        ERROR_MSG="Unsupported Entity Type: $ENTITY_TYPE"
    fi

    # --- GENERATION BLOCK ---
    if [ -z "$ERROR_MSG" ]; then
        SAFE_PROMPT=$(echo "$PROMPT" | tr -d '\n' | tr -d '\r')
        if [ -z "$SAFE_PROMPT" ]; then
            ERROR_MSG="Prompt is empty."
        else
            URL_PROMPT=$(echo -n "$SAFE_PROMPT" | tr -d '?%' | jq -sRr @uri)
            
            video_basename=$(mysql $MYSQL_ARGS -N --batch --skip-column-names -e "
              UPDATE video_counter SET next_video = LAST_INSERT_ID(next_video + 1) ORDER BY next_video DESC LIMIT 1;
              SELECT LPAD(LAST_INSERT_ID(), 7, '0');
            ")
            video_basename="video$video_basename"
            
            OUTFILE="$VIDEOS_DIR/$video_basename.mp4"
            THUMBFILE="$THUMBS_DIR/$video_basename.jpg"
            
            BASE_URL="https://gen.pollinations.ai/video/$URL_PROMPT"
            PARAMS="model=$MODEL&width=$WIDTH&height=$HEIGHT&seed=$SEED&nologo=true&duration=$DURATION"
            [ -n "$IMAGE_PARAM" ] && PARAMS="$PARAMS&image=$IMAGE_PARAM"
            FULL_URL="$BASE_URL?$PARAMS"
            
            echo "Requesting Video from API..."
            curl -s -L -H "Authorization: Bearer $PAI_TOKEN" "$FULL_URL" -o "$OUTFILE"
            
            # Validation
            if [ ! -s "$OUTFILE" ]; then
                ERROR_MSG="Output file is empty."
                rm -f "$OUTFILE"
            else
                FILE_TYPE=$(file -b --mime-type "$OUTFILE")
                if [ "$FILE_TYPE" == "application/json" ]; then
                    ERROR_MSG="API returned JSON error."
                    rm -f "$OUTFILE"
                else
                    ffmpeg -v error -i "$OUTFILE" -f null - 2>/dev/null
                    if [ $? -ne 0 ]; then
                        ERROR_MSG="Generated file is corrupt or invalid video."
                        rm -f "$OUTFILE"
                    fi
                fi
            fi
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
        echo "✓ Video generated successfully: $video_basename.mp4"
        
        # Post-Processing
        ffmpeg -y -i "$OUTFILE" -ss 00:00:00.000 -vframes 1 "$THUMBFILE" >/dev/null 2>&1
        FILE_SIZE=$(stat -c%s "$OUTFILE")
        SQL_PROMPT=$(echo "$SAFE_PROMPT" | sed "s/'/''/g")
        SQL_NAME="$video_basename.mp4"
        
        # Database Insertion
        VIDEO_ID=$(mysql $MYSQL_ARGS -N -e "
            INSERT INTO videos 
            (map_run_id, name, description, url, thumbnail, duration, type, file_size, width, height, created_at)
            VALUES 
            ($MAP_RUN_ID, '$SQL_NAME', '$SQL_PROMPT', '$VIDEOS_DIR_REL/$video_basename.mp4', '$THUMBS_DIR_REL/$video_basename.jpg', $DURATION, 'video/mp4', $FILE_SIZE, $WIDTH, $HEIGHT, NOW());
            SELECT LAST_INSERT_ID();
        ")
        
        # Link in mapping table
        MAPPING_TABLE="videos_2_$ENTITY_TYPE"
        TABLE_EXISTS=$(mysql $MYSQL_ARGS -N -e "SHOW TABLES LIKE '$MAPPING_TABLE';")
        if [ -n "$TABLE_EXISTS" ] && [ -n "$VIDEO_ID" ]; then
            mysql $MYSQL_ARGS -e "INSERT IGNORE INTO $MAPPING_TABLE (from_id, to_id) VALUES ($VIDEO_ID, $ENTITY_ID);"
        fi
        
        # Finalize Queue Row
        mysql $MYSQL_ARGS -e "UPDATE map_run_queue SET status = 'completed', asset_id = $VIDEO_ID, completed_at = NOW(), error_msg = NULL WHERE id = $QUEUE_ID;"
    fi

done

echo "========================================================"
echo "Worker execution complete."
echo "========================================================"
exit 0
