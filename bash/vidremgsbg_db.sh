#!/bin/bash

# ==============================================================================
# SAGE Video Rembg Worker
# Reads from video_enhancements, processes chromakey removal via PyAPI,
# inserts the resulting webm as a new videos row, and maps it back to the
# same animatic as the source video via videos_2_animatics.
# ==============================================================================

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MYSQL_ARGS=$("$SCRIPT_DIR"/db_name.sh main-conn)

# Load project roots
if [ -f "$SCRIPT_DIR/load_root.sh" ]; then
    source "$SCRIPT_DIR/load_root.sh"
fi

if [ -z "$PROJECT_ROOT" ]; then
    echo "ERROR: PROJECT_ROOT not set."
    exit 1
fi

ENHANCEMENT_ID="$1"
MAP_RUN_ID="$2"

if [ -z "$ENHANCEMENT_ID" ] || [ -z "$MAP_RUN_ID" ]; then
    echo "Usage: $0 ENHANCEMENT_ID MAP_RUN_ID"
    exit 1
fi

# Config
ZROK_URL=$("$SCRIPT_DIR"/pyapi_echo.sh)
VIDEOS_DIR="$PROJECT_ROOT/public/videos"
THUMBS_DIR="$VIDEOS_DIR/thumbnails"
mkdir -p "$VIDEOS_DIR"
mkdir -p "$THUMBS_DIR"

VIDEOS_DIR_REL="videos"
THUMBS_DIR_REL="videos/thumbnails"

# Polling Settings
POLL_INTERVAL=5
MAX_POLL_ATTEMPTS=120  # 10 minutes max

# 1. Fetch Job Details from video_enhancements
read -r ENTITY_ID VID2VID_VIDEO_ID VID2VID_VIDEO_URL CHROMAKEY_COLOR < <(mysql $MYSQL_ARGS -N -e "
    SELECT entity_id, vid2vid_video_id, vid2vid_video_url, chromakey_color
    FROM video_enhancements
    WHERE id = $ENHANCEMENT_ID
    LIMIT 1
")

if [ -z "$ENTITY_ID" ] || [ -z "$VID2VID_VIDEO_ID" ]; then
    echo "ERROR: Could not find job details for video_enhancements ID $ENHANCEMENT_ID"
    mysql $MYSQL_ARGS -e "UPDATE video_enhancements SET regenerate_videos=0 WHERE id=$ENHANCEMENT_ID"
    exit 1
fi

# Default chromakey color fallback
CHROMAKEY_COLOR="${CHROMAKEY_COLOR:-#00FB00}"

echo "Enhancement ID:  $ENHANCEMENT_ID"
echo "Animatic ID:     $ENTITY_ID"
echo "Source Video ID: $VID2VID_VIDEO_ID"
echo "Chromakey Color: $CHROMAKEY_COLOR"

# 2. Locate Source File
SOURCE_FULL_PATH="$PROJECT_ROOT/public/${VID2VID_VIDEO_URL#/}"
if [ ! -f "$SOURCE_FULL_PATH" ]; then
    echo "ERROR: Source file not found: $SOURCE_FULL_PATH"
    mysql $MYSQL_ARGS -e "UPDATE video_enhancements SET regenerate_videos=0 WHERE id=$ENHANCEMENT_ID"
    exit 1
fi

# 3. Generate Output Filename via counter (mirrors genvideo_db.sh pattern)
video_basename=$(mysql $MYSQL_ARGS -N --batch --skip-column-names -e "
  UPDATE video_counter
  SET next_video = LAST_INSERT_ID(next_video + 1)
  ORDER BY next_video DESC LIMIT 1;
  SELECT LPAD(LAST_INSERT_ID(), 7, '0');
")
video_basename="video$video_basename"

if [ "$video_basename" == "video" ]; then
    echo "ERROR: Could not retrieve next video ID from DB."
    exit 1
fi

NEW_FILENAME="${video_basename}.webm"
NEW_FULL_PATH="$VIDEOS_DIR/$NEW_FILENAME"
NEW_REL_PATH="$VIDEOS_DIR_REL/$NEW_FILENAME"

THUMB_FILENAME="${video_basename}.jpg"
THUMB_FULL_PATH="$THUMBS_DIR/$THUMB_FILENAME"
THUMB_REL_PATH="$THUMBS_DIR_REL/$THUMB_FILENAME"

echo "Target File: $NEW_FILENAME"

# 4. Initiate Async Chromakey Job on PyAPI
echo "Uploading $SOURCE_FULL_PATH to PyAPI (chromakey-async)..."

INIT_RESP=$(curl -s -X POST "$ZROK_URL/video/chromakey-async" \
    -F "file=@$SOURCE_FULL_PATH" \
    -F "color=$CHROMAKEY_COLOR" \
    -F "threshold=0.15" \
    -F "softness=0.05")

TASK_ID=$(echo "$INIT_RESP" | jq -r '.task_id // empty')

if [ -z "$TASK_ID" ] || [ "$TASK_ID" == "null" ]; then
    echo "ERROR: Failed to start task. Response: $INIT_RESP"
    exit 1
fi

echo "Task started: $TASK_ID. Polling for completion..."

# 5. Polling Loop
POLL_OK=false
count=0

while [ $count -lt $MAX_POLL_ATTEMPTS ]; do
    sleep $POLL_INTERVAL
    count=$((count+1))

    RESP_FILE=$(mktemp)
    HDR_FILE=$(mktemp)

    curl -s -D "$HDR_FILE" "$ZROK_URL/video/status/$TASK_ID" -o "$RESP_FILE"

    HTTP_CODE=$(grep -E "^HTTP" "$HDR_FILE" | tail -1 | awk '{print $2}')
    CONTENT_TYPE=$(grep -i "^Content-Type:" "$HDR_FILE" | awk '{print $2}')

    if [ "$HTTP_CODE" == "200" ]; then
        if [[ "$CONTENT_TYPE" == *"video"* ]] || [[ "$CONTENT_TYPE" == *"application/octet-stream"* ]]; then
            mv "$RESP_FILE" "$NEW_FULL_PATH"
            echo "Poll #$count: Success! Video downloaded."
            POLL_OK=true
            rm -f "$HDR_FILE"
            break
        else
            STATUS=$(jq -r '.status // "UNKNOWN"' "$RESP_FILE")
            echo "Poll #$count: $STATUS..."
        fi
    elif [ "$HTTP_CODE" == "500" ] || [ "$HTTP_CODE" == "404" ]; then
        ERR_MSG=$(jq -r '.detail // "Unknown error"' "$RESP_FILE")
        echo "Poll #$count: FAILED - $ERR_MSG"
        rm -f "$RESP_FILE" "$HDR_FILE"
        exit 1
    else
        echo "Poll #$count: HTTP $HTTP_CODE... waiting"
    fi

    rm -f "$RESP_FILE" "$HDR_FILE"
done

if [ "$POLL_OK" != "true" ]; then
    echo "ERROR: Timed out waiting for video processing."
    exit 1
fi

# 6. Generate Thumbnail
echo "Generating thumbnail..."
if [ -f "$SCRIPT_DIR/generate_thumbnail.sh" ]; then
    "$SCRIPT_DIR/generate_thumbnail.sh" "$NEW_FULL_PATH" "$THUMB_FULL_PATH" "0"
else
    ffmpeg -y -i "$NEW_FULL_PATH" -ss 00:00:00.000 -vframes 1 -q:v 2 "$THUMB_FULL_PATH" >/dev/null 2>&1
fi

# 7. Probe Output File
FILE_SIZE=$(stat -c%s "$NEW_FULL_PATH")
DURATION=0
WIDTH=0
HEIGHT=0
if command -v ffprobe >/dev/null 2>&1; then
    DURATION=$(ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 "$NEW_FULL_PATH" | cut -d. -f1)
    WIDTH=$(ffprobe -v error -select_streams v:0 -show_entries stream=width -of default=noprint_wrappers=1:nokey=1 "$NEW_FULL_PATH")
    HEIGHT=$(ffprobe -v error -select_streams v:0 -show_entries stream=height -of default=noprint_wrappers=1:nokey=1 "$NEW_FULL_PATH")
fi

# 8. Insert New Video Row
# Pull name/description from source video for context
NEW_VIDEO_ID=$(mysql $MYSQL_ARGS -N -e "
    INSERT INTO videos
        (map_run_id, name, description, url, thumbnail, duration, type,
         file_size, width, height, created_at)
    SELECT
        $MAP_RUN_ID,
        CONCAT(name, ' [no bg]'),
        CONCAT(COALESCE(description,''), ' - Background Removed'),
        '$NEW_REL_PATH',
        '$THUMB_REL_PATH',
        $DURATION,
        'video/webm',
        $FILE_SIZE,
        $WIDTH,
        $HEIGHT,
        NOW()
    FROM videos
    WHERE id = $VID2VID_VIDEO_ID;
    SELECT LAST_INSERT_ID();
")

if [ -z "$NEW_VIDEO_ID" ] || [ "$NEW_VIDEO_ID" == "0" ]; then
    echo "ERROR: Failed to insert new video row."
    exit 1
fi

echo "New video row inserted: ID $NEW_VIDEO_ID"

# 9. Map Result Back to Same Animatic (core of this whole pipeline)
mysql $MYSQL_ARGS -e "
    INSERT IGNORE INTO videos_2_animatics (from_id, to_id)
    VALUES ($NEW_VIDEO_ID, $ENTITY_ID);
"
echo "Linked Video #$NEW_VIDEO_ID → Animatic #$ENTITY_ID via videos_2_animatics"

# 10. Clear Regenerate Flag on Enhancement Row
mysql $MYSQL_ARGS -e "
    UPDATE video_enhancements
    SET regenerate_videos = 0
    WHERE id = $ENHANCEMENT_ID
"

echo "Done. New video: $NEW_FILENAME  |  Enhancement #$ENHANCEMENT_ID cleared."
