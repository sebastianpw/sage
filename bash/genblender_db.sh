#!/bin/bash

# ==============================================================================
# SAGE Blender Worker Client
# 1. Gathers Assets (Images/GLBs) from local storage.
# 2. Uploads them + JSON Config to Tablet (PyAPI).
# 3. Polls for Render Result.
# 4. Saves Video & Updates DB.
# ==============================================================================

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MYSQL_ARGS=$("$SCRIPT_DIR"/db_name.sh main-conn)

# Load Roots
if [ -f "$SCRIPT_DIR/load_root.sh" ]; then
  source "$SCRIPT_DIR/load_root.sh"
fi

if [ -z "$PROJECT_ROOT" ]; then
    echo "ERROR: PROJECT_ROOT not set."
    exit 1
fi

JOB_ID="$1"
if [ -z "$JOB_ID" ]; then
  echo "Usage: $0 JOB_ID"
  exit 1
fi

# -----------------------------
# Config
# -----------------------------
PYAPI_URL=$("$SCRIPT_DIR"/pyapi_echo.sh) # Target: Tablet IP
VIDEOS_DIR="$PROJECT_ROOT/public/videos"
THUMBS_DIR="$VIDEOS_DIR/thumbnails"
mkdir -p "$VIDEOS_DIR" "$THUMBS_DIR"

VIDEOS_REL="videos"
THUMBS_REL="videos/thumbnails"

# -----------------------------
# 1. Fetch Queue Data
# -----------------------------
RAW_DATA=$(mysql $MYSQL_ARGS -N -e "
    SELECT animatic_id, motion_setup_id, flight_data_json 
    FROM motion_render_queue WHERE id=$JOB_ID LIMIT 1")
    
IFS=$'\t' read -r ANIM_ID SETUP_ID FLIGHT_JSON <<< "$RAW_DATA"

if [ -z "$ANIM_ID" ]; then 
    echo "Error: Job #$JOB_ID not found in queue."
    exit 1
fi

echo "Preparing Job #$JOB_ID (Setup #$SETUP_ID)..."

# -----------------------------
# 2. Build Layers JSON & Collect Files
# -----------------------------
SQL_LAYERS="
    SELECT ml.id, ml.role, ml.z_index, ml.layer_config, f.filename, m.filename
    FROM motion_layers ml
    LEFT JOIN frames f ON ml.frame_id = f.id
    LEFT JOIN meshes m ON ml.mesh_id = m.id
    WHERE ml.motion_setup_id = $SETUP_ID
    ORDER BY ml.z_index ASC
"

LAYERS_JSON="[]"
CURL_ARGS=()
HAS_FILES=0
DECLARED_FILES=() 

while IFS=$'\t' read -r lid role z conf frame mesh; do
    [ "$frame" == "NULL" ] && frame=""
    [ "$mesh" == "NULL" ] && mesh=""
    [ "$conf" == "NULL" ] && conf="{}"

    # Determine which file to use
    FILENAME=""
    if [ -n "$frame" ]; then FILENAME="$frame"; fi
    if [ -n "$mesh" ]; then FILENAME="$mesh"; fi

    BASENAME=""
    
    # If file exists locally, add to CURL_ARGS
    if [ -n "$FILENAME" ]; then
        ABS_PATH="$PROJECT_ROOT/public/$FILENAME"
        BASENAME=$(basename "$FILENAME")
        
        if [ -f "$ABS_PATH" ]; then
            # Avoid duplicate uploads
            if [[ ! " ${DECLARED_FILES[*]} " =~ " ${ABS_PATH} " ]]; then
                CURL_ARGS+=("-F" "files=@$ABS_PATH")
                DECLARED_FILES+=("$ABS_PATH")
                HAS_FILES=1
            fi
        else
            echo "Warning: Asset missing locally: $ABS_PATH"
        fi
    fi

    # Construct Layer JSON Item
    ITEM_JSON=$(jq -n \
        --arg id "$lid" \
        --arg role "$role" \
        --arg z "$z" \
        --argjson conf "$conf" \
        --arg fn "$BASENAME" \
        '{id: $id, role: $role, z_index: ($z|tonumber), config: $conf, frame_filename: $fn, mesh_filename: $fn}')
        
    LAYERS_JSON=$(echo "$LAYERS_JSON" | jq --argjson item "$ITEM_JSON" '. + [$item]')

done < <(mysql $MYSQL_ARGS -N -e "$SQL_LAYERS")

# -----------------------------
# 3. Construct Final Payload
# -----------------------------
ENV_CONF=$(mysql $MYSQL_ARGS -N -e "SELECT environment_config FROM motion_setups WHERE id=$SETUP_ID")
[ -z "$ENV_CONF" ] && ENV_CONF="{}"

# Combine into master JSON string
JOB_DATA=$(jq -n \
    --argjson aid "$ANIM_ID" \
    --argjson env "$ENV_CONF" \
    --argjson lays "$LAYERS_JSON" \
    --argjson flight "$FLIGHT_JSON" \
    '{animatic_id: $aid, setup: {environment: $env, layers: $lays}, flight_data: $flight}')

# -----------------------------
# 4. Upload & Trigger
# -----------------------------
echo "Uploading Job #$JOB_ID to Tablet ($PYAPI_URL)..."

RESP=$(curl -s -X POST "$PYAPI_URL/render/blender-async" \
    "${CURL_ARGS[@]}" \
    -F "job_data=$JOB_DATA")

TASK_ID=$(echo "$RESP" | jq -r '.task_id // empty')

if [ -z "$TASK_ID" ]; then
    echo "Failed to start task. Response: $RESP"
    exit 1
fi

echo "Task Started: $TASK_ID. Polling..."

# -----------------------------
# 5. Polling Loop
# -----------------------------
# Generate Unique Filename
video_basename=$(mysql $MYSQL_ARGS -N --batch --skip-column-names -e "
  UPDATE video_counter SET next_video = LAST_INSERT_ID(next_video + 1);
  SELECT LPAD(LAST_INSERT_ID(), 7, '0');
")
VIDEO_FILE="video${video_basename}.mp4"
VIDEO_PATH="$VIDEOS_DIR/$VIDEO_FILE"
THUMB_FILE="video${video_basename}.jpg"
THUMB_PATH="$THUMBS_DIR/$THUMB_FILE"

MAX_RETRIES=120 # 10 Minutes
SUCCESS=0

for (( i=0; i<MAX_RETRIES; i++ )); do
    sleep 5
    
    # Download response
    HTTP_CODE=$(curl -s -o "$VIDEO_PATH" -w "%{http_code}" "$PYAPI_URL/render/status/$TASK_ID")
    
    if [ "$HTTP_CODE" == "200" ]; then
        # Check if it is a valid video file (non-empty)
        if [ -s "$VIDEO_PATH" ]; then
            SUCCESS=1
            echo "✓ Render Received."
            break
        fi
    elif [ "$HTTP_CODE" == "500" ]; then
        echo "✗ Remote Error."
        cat "$VIDEO_PATH" # Contains error JSON
        rm "$VIDEO_PATH"
        exit 1
    elif [ "$HTTP_CODE" == "404" ]; then
        echo "Task lost on server."
        exit 1
    fi
    echo -n "."
done

if [ $SUCCESS -eq 0 ]; then
    echo "Timeout waiting for render."
    rm -f "$VIDEO_PATH"
    exit 1
fi

# -----------------------------
# 6. Post-Process & DB Registration
# -----------------------------

# Generate Thumbnail
if command -v ffmpeg >/dev/null 2>&1; then
    ffmpeg -y -i "$VIDEO_PATH" -ss 00:00:01.000 -vframes 1 -q:v 2 "$THUMB_PATH" >/dev/null 2>&1
fi

# Get Metadata
FILE_SIZE=$(stat -c%s "$VIDEO_PATH")
DURATION=0
if command -v ffprobe >/dev/null 2>&1; then
    DURATION=$(ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 "$VIDEO_PATH" | cut -d. -f1)
fi

# Save to Videos Table
VIDEO_ID=$(mysql $MYSQL_ARGS -N -e "
    INSERT INTO videos (name, description, url, thumbnail, duration, type, file_size, width, height, created_at)
    VALUES (
        '$VIDEO_FILE', 
        'Blender Render (Animatic #$ANIM_ID)', 
        '$VIDEOS_REL/$VIDEO_FILE', 
        '$THUMBS_REL/$THUMB_FILE', 
        '$DURATION', 
        'video/mp4', 
        $FILE_SIZE, 
        1920, 1080, 
        NOW()
    );
    SELECT LAST_INSERT_ID();
")

if [ -n "$VIDEO_ID" ]; then
    # Link to Animatic
    mysql $MYSQL_ARGS -e "INSERT IGNORE INTO videos_2_animatics (from_id, to_id) VALUES ($VIDEO_ID, $ANIM_ID)"
    
    # Update Queue Result
    mysql $MYSQL_ARGS -e "UPDATE motion_render_queue SET result_video_id=$VIDEO_ID WHERE id=$JOB_ID"
    
    echo "Video #$VIDEO_ID registered successfully."
else
    echo "Error registering video in DB."
    exit 1
fi

exit 0
