#!/bin/bash

# ==============================================================================
# SAGE Multiplane Client
# Generates video from Composite Arrangements, Saves to DB, Links to Entity.
# ==============================================================================

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# -----------------------------
# DB & Environment
# -----------------------------
MYSQL_ARGS=$("$SCRIPT_DIR"/db_name.sh main-conn)

if [ -f "$SCRIPT_DIR/load_root.sh" ]; then
  source "$SCRIPT_DIR/load_root.sh"
fi

if [ -z "$PROJECT_ROOT" ]; then
  echo "ERROR: PROJECT_ROOT is not set."
  exit 1
fi

# -----------------------------
# Directories
# -----------------------------
# Videos go into public/videos
VIDEOS_DIR="$PROJECT_ROOT/public/videos"
THUMBS_DIR="$VIDEOS_DIR/thumbnails"

mkdir -p "$VIDEOS_DIR"
mkdir -p "$THUMBS_DIR"

# Relative paths for DB
VIDEOS_DIR_REL="videos"
THUMBS_DIR_REL="videos/thumbnails"

# -----------------------------
# Configuration
# -----------------------------
PYAPI_URL="http://127.0.0.1:8009"

# -----------------------------
# Arguments
# -----------------------------
MAP_RUN_ID="$1"
ENTITY_ID="$2"
ENTITY_TYPE="${3:-composites}" # Default to 'composites'

if [ -z "$ENTITY_ID" ]; then
  echo "Usage: $0 MAP_RUN_ID ENTITY_ID [ENTITY_TYPE]"
  exit 1
fi

echo "--- Starting Multiplane Generation ---"
echo "Entity: $ENTITY_TYPE #$ENTITY_ID"

# -----------------------------
# 1. Fetch Global Settings
# -----------------------------
SETTINGS=$(mysql $MYSQL_ARGS -N -e "
    SELECT 
        COALESCE(frames, 60), 
        COALESCE(fps, 30), 
        COALESCE(move_x, 100), 
        COALESCE(move_y, 0), 
        COALESCE(zoom_start, 1.0), 
        COALESCE(zoom_end, 1.05) 
    FROM multiplane_settings 
    WHERE composite_id = $ENTITY_ID LIMIT 1;
")

if [ -z "$SETTINGS" ]; then
    echo "No settings found. Using defaults."
    read P_FRAMES P_FPS P_MOVEX P_MOVEY P_ZOOM_START P_ZOOM_END <<< "60 30 100 0 1.0 1.05"
else
    read P_FRAMES P_FPS P_MOVEX P_MOVEY P_ZOOM_START P_ZOOM_END <<< "$SETTINGS"
fi

# Calculate Duration for DB
DURATION=$(awk "BEGIN {print $P_FRAMES / $P_FPS}")

# -----------------------------
# 2. Fetch Layout Config (JSON)
# -----------------------------
# Gets the most recently updated arrangement for this composite
RAW_LAYOUT_JSON=$(mysql $MYSQL_ARGS -N -e "SELECT layer_config FROM multiplane_arrangements WHERE composite_id = $ENTITY_ID ORDER BY updated_at DESC LIMIT 1;")

if [ -z "$RAW_LAYOUT_JSON" ] || [ "$RAW_LAYOUT_JSON" == "NULL" ]; then
    echo "No arrangement found. Using empty config (Python will use defaults)."
    RAW_LAYOUT_JSON="{}"
fi

# -----------------------------
# 3. Fetch Layers & Construct JSON
# -----------------------------
SQL_LAYERS="
    SELECT 
        f.filename,
        COALESCE(ml.speed, 0.5) as speed,
        f.id as frame_id
    FROM composite_frames cf
    JOIN frames f ON cf.frame_id = f.id
    LEFT JOIN multiplane_layers ml ON (ml.composite_id = cf.composite_id AND ml.frame_id = cf.frame_id)
    WHERE cf.composite_id = $ENTITY_ID
    ORDER BY COALESCE(ml.z_index, 0) ASC;
"

FINAL_CONFIG_JSON="{}"
CURL_FILE_ARGS=()
HAS_FILES=0

while IFS=$'\t' read -r filename speed frame_id; do
    ABS_PATH="$PROJECT_ROOT/public/$filename"
    BASE_NAME=$(basename "$filename")
    
    if [ ! -f "$ABS_PATH" ]; then
        echo "Warning: Layer file missing: $ABS_PATH"
        continue
    fi
    
    HAS_FILES=1
    CURL_FILE_ARGS+=("-F" "files=@$ABS_PATH")
    
    # Merge DB Speed with JSON Arrangement (Arrangement overrides speed if it existed there, but usually it doesn't)
    # We allow the layout to define x,y,scale,rotation,zIndex. We inject 'speed' from DB.
    ITEM_CONFIG=$(echo "$RAW_LAYOUT_JSON" | jq -r --arg fid "$frame_id" --arg speed "$speed" '
        (.[$fid] // {}) + {speed: ($speed | tonumber)}
    ')
    
    # Add to Final JSON Map (Key = Filename)
    FINAL_CONFIG_JSON=$(echo "$FINAL_CONFIG_JSON" | jq --arg fname "$BASE_NAME" --argjson conf "$ITEM_CONFIG" '.[$fname] = $conf')

done < <(mysql $MYSQL_ARGS -N -e "$SQL_LAYERS")

if [ "$HAS_FILES" -eq 0 ]; then
    echo "Error: No valid layers found for Composite #$ENTITY_ID"
    exit 1
fi

# -----------------------------
# 4. Initiate API Job
# -----------------------------
echo "Uploading assets to Python API..."

CREATE_RESP=$(curl -s -X POST "$PYAPI_URL/multiplane/compose-async" \
    "${CURL_FILE_ARGS[@]}" \
    -F "layer_config=$FINAL_CONFIG_JSON" \
    -F "frames=$P_FRAMES" \
    -F "fps=$P_FPS" \
    -F "move_x=$P_MOVEX" \
    -F "move_y=$P_MOVEY" \
    -F "zoom_start=$P_ZOOM_START" \
    -F "zoom_end=$P_ZOOM_END")

TASK_ID=$(echo "$CREATE_RESP" | jq -r '.task_id // empty')

if [ -z "$TASK_ID" ]; then
    echo "API Error: $CREATE_RESP"
    exit 1
fi

echo "Task Queued: $TASK_ID"

# -----------------------------
# 5. Polling Loop
# -----------------------------
# Generate Unique Video Filename using video_counter
video_basename=$(mysql $MYSQL_ARGS -N --batch --skip-column-names -e "
  UPDATE video_counter
  SET next_video = LAST_INSERT_ID(next_video + 1);
  SELECT LPAD(LAST_INSERT_ID(), 7, '0');
")
video_basename="video$video_basename"
VIDEO_FILENAME="$video_basename.mp4"
VIDEO_ABS_PATH="$VIDEOS_DIR/$VIDEO_FILENAME"
THUMB_FILENAME="$video_basename.jpg"
THUMB_ABS_PATH="$THUMBS_DIR/$THUMB_FILENAME"

# Temp headers file for polling
HEADER_FILE="$SCRIPT_DIR/headers_$TASK_ID.txt"

echo "Target File: $VIDEO_FILENAME"
echo "Polling..."

MAX_POLLS=60
POLL_SUCCESS=0

for (( i=1; i<=MAX_POLLS; i++ )); do
    sleep 3
    
    # Download response to target path, dump headers to temp
    curl -s -D "$HEADER_FILE" -o "$VIDEO_ABS_PATH" "$PYAPI_URL/multiplane/status/$TASK_ID"
    
    HTTP_CODE=$(grep "HTTP/" "$HEADER_FILE" | tail -1 | awk '{print $2}')
    CONTENT_TYPE=$(grep -i "Content-Type:" "$HEADER_FILE" | awk '{print $2}')
    
    if [ "$HTTP_CODE" == "200" ]; then
        if [[ "$CONTENT_TYPE" == *"video"* ]]; then
            POLL_SUCCESS=1
            echo "✓ Video received."
            break
        else
            # Still processing (JSON response inside the file we just downloaded)
            # We can optionally read status from $VIDEO_ABS_PATH if we want debug info
            echo -n "."
        fi
    elif [ "$HTTP_CODE" == "500" ] || [ "$HTTP_CODE" == "404" ]; then
        echo ""
        echo "✗ API Failed ($HTTP_CODE)"
        cat "$VIDEO_ABS_PATH" # Contains error json
        rm -f "$VIDEO_ABS_PATH" "$HEADER_FILE"
        exit 1
    fi
done

rm -f "$HEADER_FILE"

if [ "$POLL_SUCCESS" -eq 0 ]; then
    echo ""
    echo "Timed out waiting for video."
    rm -f "$VIDEO_ABS_PATH"
    exit 1
fi

# -----------------------------
# 6. Post-Processing & DB Registration
# -----------------------------

# Generate Thumbnail
if [ -f "$SCRIPT_DIR/generate_thumbnail.sh" ]; then
    echo "Generating thumbnail..."
    "$SCRIPT_DIR/generate_thumbnail.sh" "$VIDEO_ABS_PATH" "$THUMB_ABS_PATH" "0"
else
    echo "Warning: generate_thumbnail.sh not found."
fi

# Determine file size
FILE_SIZE=$(stat -c%s "$VIDEO_ABS_PATH")

# Insert into videos table
echo "Registering in DB..."
VIDEO_ID=$(mysql $MYSQL_ARGS -N -e "
    INSERT INTO videos 
    (name, description, url, thumbnail, duration, type, file_size, width, height, created_at)
    VALUES 
    ('$VIDEO_FILENAME', 
     'Multiplane Animation (Composite #$ENTITY_ID)', 
     '$VIDEOS_DIR_REL/$VIDEO_FILENAME', 
     '$THUMBS_DIR_REL/$THUMB_FILENAME',
     $DURATION,
     'video/mp4',
     $FILE_SIZE,
     1024, 1024,
     NOW()
    );
    SELECT LAST_INSERT_ID();
")

# Link to Composite
if [ -n "$VIDEO_ID" ]; then
    # Check if table exists (it should)
    mysql $MYSQL_ARGS -e "INSERT INTO videos_2_composites (from_id, to_id) VALUES ($VIDEO_ID, $ENTITY_ID);"
    echo "Success! Video #$VIDEO_ID linked to Composite #$ENTITY_ID."
else
    echo "Error registering video in database."
    exit 1
fi

# Clear regeneration flag
mysql $MYSQL_ARGS -e "UPDATE $ENTITY_TYPE SET regenerate_images=0 WHERE id=$ENTITY_ID;"

exit 0
