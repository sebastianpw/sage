#!/bin/bash

# ==============================================================================
# SAGE Video Rembg Worker (Async Polling + WebM Support)
# Handles specific derivate ID processing.
# ==============================================================================

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MYSQL_ARGS=$("$SCRIPT_DIR"/db_name.sh main-conn)

# Load project roots
if [ -f "$SCRIPT_DIR/load_root.sh" ]; then
  source "$SCRIPT_DIR/load_root.sh"
fi

if [ -z "$PROJECT_ROOT" ]; then
  echo "ERROR: Roots not set."
  exit 1
fi

DERIVATE_ID="$1"
if [ -z "$DERIVATE_ID" ]; then
    echo "Usage: $0 DERIVATE_ID"
    exit 1
fi

# Config
ZROK_URL=$("$SCRIPT_DIR"/zrok_echo.sh)
VIDEOS_DIR="$PROJECT_ROOT/public/videos"
THUMBS_DIR="$VIDEOS_DIR/thumbnails"
mkdir -p "$VIDEOS_DIR"
mkdir -p "$THUMBS_DIR"

VIDEOS_DIR_REL="videos"
THUMBS_DIR_REL="videos/thumbnails"

# Polling Settings
POLL_INTERVAL=5
MAX_POLL_ATTEMPTS=120 # 10 minutes max (videos take time)

# 1. Fetch Job Details
read -r SOURCE_VIDEO_ID SOURCE_REL_PATH < <(mysql $MYSQL_ARGS -N -e "
    SELECT d.vid2vid_video_id, v.url 
    FROM derivates d 
    JOIN videos v ON d.vid2vid_video_id = v.id 
    WHERE d.id = $DERIVATE_ID LIMIT 1
")

if [ -z "$SOURCE_VIDEO_ID" ] || [ -z "$SOURCE_REL_PATH" ]; then
    echo "ERROR: Could not find source video info for derivate $DERIVATE_ID"
    mysql $MYSQL_ARGS -e "UPDATE derivates SET regenerate_videos=0 WHERE id=$DERIVATE_ID"
    exit 1
fi

# 2. Locate Source File
SOURCE_FULL_PATH="$PROJECT_ROOT/public/${SOURCE_REL_PATH#/}"
if [ ! -f "$SOURCE_FULL_PATH" ]; then
    echo "ERROR: Source file not found: $SOURCE_FULL_PATH"
    mysql $MYSQL_ARGS -e "UPDATE derivates SET regenerate_videos=0 WHERE id=$DERIVATE_ID"
    exit 1
fi

# 3. Generate Filename (Correct Counter Logic)
# We use ORDER BY next_video DESC LIMIT 1 to ensure we only update the HIGHEST counter
# even if there are multiple rows in the table.
video_basename=$(mysql $MYSQL_ARGS -N --batch --skip-column-names -e "
  UPDATE video_counter
  SET next_video = LAST_INSERT_ID(next_video + 1)
  ORDER BY next_video DESC LIMIT 1;
  SELECT LPAD(LAST_INSERT_ID(), 7, '0');
")
video_basename="video$video_basename"

# Fallback check
if [ "$video_basename" == "video" ]; then
    echo "Error: Could not retrieve next video ID from DB."
    exit 1
fi

NEW_FILENAME="${video_basename}.webm"
NEW_FULL_PATH="$VIDEOS_DIR/$NEW_FILENAME"
NEW_REL_PATH="$VIDEOS_DIR_REL/$NEW_FILENAME"

THUMB_FILENAME="${video_basename}.jpg"
THUMB_FULL_PATH="$THUMBS_DIR/$THUMB_FILENAME"
THUMB_REL_PATH="$THUMBS_DIR_REL/$THUMB_FILENAME"

echo "Target File: $NEW_FILENAME"

# 4. Initiate Async Job
echo "Uploading $SOURCE_FULL_PATH to PyAPI (Async)..."

INIT_RESP=$(curl -s -X POST "$ZROK_URL/video/rembg-async" \
    -F "file=@$SOURCE_FULL_PATH" \
    -F "model=birefnet-general")

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
            # Success
            mv "$RESP_FILE" "$NEW_FULL_PATH"
            echo "Poll #$count: Success! Video downloaded."
            POLL_OK=true
            rm -f "$HDR_FILE"
            break
        else
            # JSON Status
            STATUS=$(jq -r '.status // "UNKNOWN"' "$RESP_FILE")
            echo "Poll #$count: $STATUS..."
        fi
    elif [ "$HTTP_CODE" == "500" ] || [ "$HTTP_CODE" == "404" ]; then
        # Failure
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
    # Fallback to direct ffmpeg
    ffmpeg -y -i "$NEW_FULL_PATH" -ss 00:00:00.000 -vframes 1 -q:v 2 "$THUMB_FULL_PATH" >/dev/null 2>&1
fi

# 7. Register New Video
FILE_SIZE=$(stat -c%s "$NEW_FULL_PATH")
DURATION=0
WIDTH=0
HEIGHT=0
if command -v ffprobe >/dev/null 2>&1; then
    DURATION=$(ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 "$NEW_FULL_PATH" | cut -d. -f1)
    WIDTH=$(ffprobe -v error -select_streams v:0 -show_entries stream=width -of default=noprint_wrappers=1:nokey=1 "$NEW_FULL_PATH")
    HEIGHT=$(ffprobe -v error -select_streams v:0 -show_entries stream=height -of default=noprint_wrappers=1:nokey=1 "$NEW_FULL_PATH")
fi

mysql $MYSQL_ARGS -e "
    INSERT INTO videos (name, description, url, thumbnail, duration, type, file_size, width, height, created_at)
    SELECT 
        '$NEW_FILENAME (No BG)', 
        CONCAT(description, ' - Background Removed'), 
        '$NEW_REL_PATH', 
        '$THUMB_REL_PATH',
        '$DURATION', 
        'video/webm', 
        '$FILE_SIZE', 
        '$WIDTH', 
        '$HEIGHT', 
        NOW() 
    FROM videos WHERE id = $SOURCE_VIDEO_ID;
"
NEW_VIDEO_ID=$(mysql $MYSQL_ARGS -N -e "SELECT LAST_INSERT_ID()")

if [ -z "$NEW_VIDEO_ID" ]; then
    echo "Error inserting into videos table."
    exit 1
fi

# 8. Link to Derivates
mysql $MYSQL_ARGS -e "INSERT IGNORE INTO videos_2_derivates (from_id, to_id) VALUES ($NEW_VIDEO_ID, $DERIVATE_ID);"

# 9. Update Derivate Status
mysql $MYSQL_ARGS -e "
    UPDATE derivates 
    SET regenerate_videos=0, vid2vid_video_filename='$NEW_REL_PATH' 
    WHERE id=$DERIVATE_ID
"

echo "Video processed and linked successfully. New ID: $NEW_VIDEO_ID"
