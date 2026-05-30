#!/bin/bash

# ==============================================================================
# SAGE Liplab Client (WebM Version) - Database Audio Mapped
# ==============================================================================

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MYSQL_ARGS=$("$SCRIPT_DIR"/db_name.sh main-conn)

if [ -f "$SCRIPT_DIR/load_root.sh" ]; then
  source "$SCRIPT_DIR/load_root.sh"
fi

if [ -z "$PROJECT_ROOT" ]; then
  echo "ERROR: PROJECT_ROOT is not set."
  exit 1
fi

VIDEOS_DIR="$PROJECT_ROOT/public/videos"
THUMBS_DIR="$VIDEOS_DIR/thumbnails"
# AUDIO_DIR is no longer the sole source of truth, but we keep the var just in case
AUDIO_DIR="$PROJECT_ROOT/public/audio"

mkdir -p "$VIDEOS_DIR"
mkdir -p "$THUMBS_DIR"

VIDEOS_DIR_REL="videos"
THUMBS_DIR_REL="videos/thumbnails"
PYAPI_URL="http://localhost:8009"

MAP_RUN_ID="$1"
ENTITY_ID="$2"
ENTITY_TYPE="${3:-composites}" 

if [ -z "$ENTITY_ID" ]; then
  echo "Usage: $0 MAP_RUN_ID ENTITY_ID [ENTITY_TYPE]"
  exit 1
fi

echo "--- Starting Liplab Generation (WebM) ---"
echo "Entity: $ENTITY_TYPE #$ENTITY_ID"

# Settings
P_FPS=24
P_SENSITIVITY=1.0
P_MAX_WIDTH=1024
P_MAX_HEIGHT=1024

# ==============================================================================
# 1. Fetch Audio from Database Mapping
# ==============================================================================

# Query to get the filename from the audios table mapped via composite_audios
# We order by audio_id DESC to get the most recently added audio if multiple exist
DB_AUDIO_FILENAME=$(mysql $MYSQL_ARGS -N -e "
    SELECT a.filename
    FROM composite_audios ca
    JOIN audios a ON ca.audio_id = a.id
    WHERE ca.composite_id = $ENTITY_ID
    ORDER BY ca.audio_id DESC
    LIMIT 1;
")

if [ -z "$DB_AUDIO_FILENAME" ]; then
    echo "Error: No audio mapped to composite #$ENTITY_ID in database."
    exit 1
fi

# Construct the absolute path. 
# We assume the DB filename is relative to the 'public' folder (document root).
AUDIO_FILE="$PROJECT_ROOT/public/$DB_AUDIO_FILENAME"

echo "Audio Source: $DB_AUDIO_FILENAME"

if [ ! -f "$AUDIO_FILE" ]; then
    echo "Error: Mapped audio file not found on disk."
    echo "Expected at: $AUDIO_FILE"
    exit 1
fi

# ==============================================================================
# 2. Fetch Visual Layers
# ==============================================================================

SQL_LAYERS="
    SELECT f.filename
    FROM composite_frames cf
    JOIN frames f ON cf.frame_id = f.id
    WHERE cf.composite_id = $ENTITY_ID
    ORDER BY f.filename ASC;
"

CURL_FILE_ARGS=()
HAS_FILES=0

while IFS=$'\t' read -r filename; do
    ABS_PATH="$PROJECT_ROOT/public/$filename"
    if [ ! -f "$ABS_PATH" ]; then
        continue
    fi
    HAS_FILES=1
    CURL_FILE_ARGS+=("-F" "files=@$ABS_PATH")
done < <(mysql $MYSQL_ARGS -N -e "$SQL_LAYERS")

if [ "$HAS_FILES" -eq 0 ]; then
    echo "Error: No valid frame files found."
    exit 1
fi

# ==============================================================================
# 3. Upload and Process
# ==============================================================================

echo "Uploading to API..."
CREATE_RESP=$(curl -s -X POST "$PYAPI_URL/liplab/process-async" \
    "${CURL_FILE_ARGS[@]}" \
    -F "audio=@$AUDIO_FILE" \
    -F "fps=$P_FPS" \
    -F "sensitivity=$P_SENSITIVITY" \
    -F "max_width=$P_MAX_WIDTH" \
    -F "max_height=$P_MAX_HEIGHT")

TASK_ID=$(echo "$CREATE_RESP" | jq -r '.task_id // empty')

if [ -z "$TASK_ID" ]; then
    echo "API Error: $CREATE_RESP"
    exit 1
fi

echo "Task Queued: $TASK_ID"

# ==============================================================================
# 4. Polling
# ==============================================================================

video_basename=$(mysql $MYSQL_ARGS -N --batch --skip-column-names -e "
  UPDATE video_counter
  SET next_video = LAST_INSERT_ID(next_video + 1);
  SELECT LPAD(LAST_INSERT_ID(), 7, '0');
")
video_basename="video$video_basename"
# Change extension to .webm
VIDEO_FILENAME="$video_basename.webm" 
VIDEO_ABS_PATH="$VIDEOS_DIR/$VIDEO_FILENAME"
THUMB_FILENAME="$video_basename.jpg"
THUMB_ABS_PATH="$THUMBS_DIR/$THUMB_FILENAME"

HEADER_FILE="$SCRIPT_DIR/headers_$TASK_ID.txt"

MAX_POLLS=60
POLL_SUCCESS=0
POLL_INTERVAL=10

for (( i=1; i<=MAX_POLLS; i++ )); do
    sleep $POLL_INTERVAL
    curl -s -D "$HEADER_FILE" -o "$VIDEO_ABS_PATH" "$PYAPI_URL/liplab/status/$TASK_ID"
    HTTP_CODE=$(grep "HTTP/" "$HEADER_FILE" | tail -1 | awk '{print $2}')
    CONTENT_TYPE=$(grep -i "Content-Type:" "$HEADER_FILE" | awk '{print $2}')
    
    if [ "$HTTP_CODE" == "200" ]; then
        if [[ "$CONTENT_TYPE" == *"video"* ]]; then
            POLL_SUCCESS=1
            echo "✓ Video received."
            break
        else
            echo -n "."
        fi
    elif [ "$HTTP_CODE" == "500" ] || [ "$HTTP_CODE" == "404" ]; then
        echo ""
        echo "✗ API Failed"
        cat "$VIDEO_ABS_PATH"
        rm -f "$VIDEO_ABS_PATH" "$HEADER_FILE"
        exit 1
    fi
done

rm -f "$HEADER_FILE"

if [ "$POLL_SUCCESS" -eq 0 ]; then
    echo "Timeout."
    rm -f "$VIDEO_ABS_PATH"
    exit 1
fi

# ==============================================================================
# 5. Thumbnail & Registration
# ==============================================================================

# Thumbnail
if [ -f "$SCRIPT_DIR/generate_thumbnail.sh" ]; then
    "$SCRIPT_DIR/generate_thumbnail.sh" "$VIDEO_ABS_PATH" "$THUMB_ABS_PATH" "0"
fi

FILE_SIZE=$(stat -c%s "$VIDEO_ABS_PATH")

# Register
VIDEO_ID=$(mysql $MYSQL_ARGS -N -e "
    INSERT INTO videos 
    (name, description, url, thumbnail, duration, type, file_size, width, height, created_at)
    VALUES 
    ('$VIDEO_FILENAME', 
     'Liplab Animation (WebM)', 
     '$VIDEOS_DIR_REL/$VIDEO_FILENAME', 
     '$THUMBS_DIR_REL/$THUMB_FILENAME',
     0,
     'video/webm',
     $FILE_SIZE,
     $P_MAX_WIDTH, $P_MAX_HEIGHT,
     NOW()
    );
    SELECT LAST_INSERT_ID();
")

if [ -n "$VIDEO_ID" ]; then
    mysql $MYSQL_ARGS -e "INSERT INTO videos_2_composites (from_id, to_id) VALUES ($VIDEO_ID, $ENTITY_ID);"
    echo "Success! Video #$VIDEO_ID registered."
else
    echo "Error registering DB."
    exit 1
fi

mysql $MYSQL_ARGS -e "UPDATE $ENTITY_TYPE SET regenerate_images=0 WHERE id=$ENTITY_ID;"

exit 0
