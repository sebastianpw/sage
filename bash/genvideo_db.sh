#!/bin/bash

# ==============================================================================
# SAGE Video Generation Client (Pollinations / Seedance)
# Handles: Animatics (txt2vid & img2vid) and Composites (start/end interpolation)
# ==============================================================================

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MYSQL_ARGS=$("$SCRIPT_DIR"/db_name.sh main-conn)

export PAI_TOKEN=$(cat "$SCRIPT_DIR/../token/.pollinationsaitoken")
export FREEIMAGE_KEY=$(cat "$SCRIPT_DIR/../token/.freeimage_key")

if [ -f "$SCRIPT_DIR/load_root.sh" ]; then
  source "$SCRIPT_DIR/load_root.sh"
fi

# -----------------------------
# Directories
# -----------------------------
VIDEOS_DIR="$PROJECT_ROOT/public/videos"
THUMBS_DIR="$VIDEOS_DIR/thumbnails"
mkdir -p "$VIDEOS_DIR"
mkdir -p "$THUMBS_DIR"

VIDEOS_DIR_REL="videos"
THUMBS_DIR_REL="videos/thumbnails"

# -----------------------------
# Arguments
# -----------------------------
MAP_RUN_ID="$1"
ENTITY_TYPE="$2"
ENTITY_ID="$3"

if [ -z "$ENTITY_ID" ]; then
  echo "Usage: $0 MAP_RUN_ID ENTITY_TYPE ENTITY_ID"
  exit 1
fi

# -----------------------------
# Config
# -----------------------------
#MODEL="seedance"
#MODEL="seedance-pro"
#MODEL="seedance-2.0"

#MODEL="grok-video"
#MODEL="grok-video-pro"


#MODEL="ltx-2"


MODEL="wan"
#MODEL="wan-fast"

#MODEL="p-video"
SEED=$(( (RANDOM << 15) | RANDOM ))
WIDTH=1024
HEIGHT=1024
DURATION=6

# Variables to be populated
PROMPT=""
IMAGE_PARAM="" # Holds the URL(s) for the API

# ==============================================================================
# LOGIC BLOCK: ENTITY SPECIFIC PREPARATION
# ==============================================================================

if [ "$ENTITY_TYPE" == "composites" ]; then
    # ---------------------------------------------------------
    # COMPOSITES: Interpolation (Start Frame -> End Frame)
    # ---------------------------------------------------------
    echo "Mode: Composites (Interpolation)"
    
    # Fetch first 2 assigned frames
    mapfile -t composite_frame_ids < <(mysql $MYSQL_ARGS -N -e \
        "SELECT frame_id FROM composite_frames WHERE composite_id = $ENTITY_ID ORDER BY frame_id ASC LIMIT 2;")
    
    if [ ${#composite_frame_ids[@]} -lt 2 ]; then
        echo "ERROR: Composites require exactly 2 assigned frames for start/end interpolation. Found: ${#composite_frame_ids[@]}"
        exit 1
    fi

    # Fetch Name/Description for Prompt
    # Using specific read logic to handle spaces correctly
    PROMPT=$(mysql $MYSQL_ARGS -N -e "SELECT description FROM composites WHERE id=$ENTITY_ID LIMIT 1;")
    if [ -z "$PROMPT" ] || [ "$PROMPT" == "NULL" ]; then
         PROMPT=$(mysql $MYSQL_ARGS -N -e "SELECT name FROM composites WHERE id=$ENTITY_ID LIMIT 1;")
    fi

    # Upload Frames
    declare -a uploaded_urls
    
    for frame_id in "${composite_frame_ids[@]}"; do
        filename=$(mysql $MYSQL_ARGS -N -e "SELECT filename FROM frames WHERE id = $frame_id LIMIT 1;" | tr -d '\r')
        abs_path="$PROJECT_ROOT/public/$filename"
        
        if [ -f "$abs_path" ]; then
            echo "Uploading frame #$frame_id..."
            response=$(curl -s -X POST "https://freeimage.host/api/1/upload" \
                -F "key=$FREEIMAGE_KEY" \
                -F "action=upload" \
                -F "source=@$abs_path" \
                -F "format=json")
            
            url=$(echo "$response" | jq -r '.image.url')
            if [ -n "$url" ] && [ "$url" != "null" ]; then
                uploaded_urls+=("$url")
            else
                echo "Error uploading frame $frame_id"
                exit 1
            fi
        else
            echo "File not found: $abs_path"
            exit 1
        fi
    done

    # Join URLs with comma for Pollinations
    IMAGE_PARAM=$(IFS=,; echo "${uploaded_urls[*]}")
    echo "Source Images: $IMAGE_PARAM"

elif [ "$ENTITY_TYPE" == "animatics" ]; then
    # ---------------------------------------------------------
    # ANIMATICS: Txt2Vid OR Img2Vid
    # ---------------------------------------------------------
    echo "Mode: Animatics"

    # Read Animatic Data
    # IFS=$'\t' ensures spaces in text don't break the variables
    RAW_DATA=$(mysql $MYSQL_ARGS -N -e \
        "SELECT COALESCE(img2img,0), COALESCE(img2img_frame_id,0), COALESCE(img2img_prompt,''), description, name 
         FROM animatics WHERE id=$ENTITY_ID LIMIT 1;")

    IFS=$'\t' read -r IMG2IMG_FLAG IMG2IMG_FRAME_ID DB_IMG_PROMPT DB_DESC DB_NAME <<< "$RAW_DATA"

    # Determine Prompt: Priority -> img2img_prompt > description > name
    if [ -n "$DB_IMG_PROMPT" ] && [ "$DB_IMG_PROMPT" != "NULL" ] && [ "$DB_IMG_PROMPT" != "" ]; then
        PROMPT="$DB_IMG_PROMPT"
    elif [ -n "$DB_DESC" ] && [ "$DB_DESC" != "NULL" ] && [ "$DB_DESC" != "" ]; then
        PROMPT="$DB_DESC"
    else
        PROMPT="$DB_NAME"
    fi

    echo "Raw Prompt extracted: '$PROMPT'"

    # Check Img2Vid
    if [ "$IMG2IMG_FLAG" -eq 1 ] && [ "$IMG2IMG_FRAME_ID" -gt 0 ]; then
        echo "Type: Image-to-Video"
        
        filename=$(mysql $MYSQL_ARGS -N -e "SELECT filename FROM frames WHERE id = $IMG2IMG_FRAME_ID LIMIT 1;" | tr -d '\r')
        abs_path="$PROJECT_ROOT/public/$filename"

        if [ -f "$abs_path" ]; then
            echo "Uploading source frame #$IMG2IMG_FRAME_ID..."
            response=$(curl -s -X POST "https://freeimage.host/api/1/upload" \
                -F "key=$FREEIMAGE_KEY" \
                -F "action=upload" \
                -F "source=@$abs_path" \
                -F "format=json")
            
            url=$(echo "$response" | jq -r '.image.url')
            if [ -n "$url" ] && [ "$url" != "null" ]; then
                IMAGE_PARAM="$url"
            else
                echo "Error uploading source image."
                exit 1
            fi
        else
             echo "Source image file missing: $abs_path"
             exit 1
        fi
    else
        echo "Type: Text-to-Video"
        IMAGE_PARAM=""
    fi
else
    echo "Unsupported Entity Type: $ENTITY_TYPE"
    exit 1
fi

# Clean Prompt
SAFE_PROMPT=$(echo "$PROMPT" | tr -d '\n' | tr -d '\r')

if [ -z "$SAFE_PROMPT" ]; then
    echo "Error: Prompt is empty."
    exit 1
fi

# URL ENCODING (FIXED: Added tr -d '?%' to match frames script)
URL_PROMPT=$(echo -n "$SAFE_PROMPT" | tr -d '?%' | jq -sRr @uri)

echo "Encoded Prompt: $URL_PROMPT"

# ==============================================================================
# FILENAME GENERATION (Safe Version)
# ==============================================================================

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

OUTFILE="$VIDEOS_DIR/$video_basename.mp4"
THUMBFILE="$THUMBS_DIR/$video_basename.jpg"
echo "Target: $OUTFILE"

# ==============================================================================
# GENERATION EXECUTION
# ==============================================================================

# Construct API Call
BASE_URL="https://gen.pollinations.ai/video/$URL_PROMPT"
PARAMS="model=$MODEL&width=$WIDTH&height=$HEIGHT&seed=$SEED&nologo=true&duration=$DURATION"

if [ -n "$IMAGE_PARAM" ]; then
    PARAMS="$PARAMS&image=$IMAGE_PARAM"
fi

FULL_URL="$BASE_URL?$PARAMS"

echo "Requesting Video from Pollinations..."
# echo "URL: $FULL_URL"

# Execute Curl
curl -s -L -H "Authorization: Bearer $PAI_TOKEN" "$FULL_URL" -o "$OUTFILE"

# Validate Output
if [ ! -s "$OUTFILE" ]; then
    echo "Error: Output file is empty."
    rm -f "$OUTFILE"
    exit 1
fi

# Check if it is actually a JSON error masquerading as a file
FILE_TYPE=$(file -b --mime-type "$OUTFILE")
if [ "$FILE_TYPE" == "application/json" ]; then
    echo "Error: API returned JSON error."
    cat "$OUTFILE"
    rm -f "$OUTFILE"
    exit 1
fi

# Final Validation with FFmpeg
ffmpeg -v error -i "$OUTFILE" -f null - 2>/dev/null
if [ $? -ne 0 ]; then
    echo "Error: Generated file is corrupt or invalid video."
    rm -f "$OUTFILE"
    exit 1
fi

echo "Video generated successfully: $video_basename.mp4"

# ==============================================================================
# POST PROCESSING
# ==============================================================================

# Generate Thumbnail
echo "Generating thumbnail..."
ffmpeg -y -i "$OUTFILE" -ss 00:00:00.000 -vframes 1 "$THUMBFILE" >/dev/null 2>&1

FILE_SIZE=$(stat -c%s "$OUTFILE")

# DB Insert
echo "Saving to Database..."

# Escape strings for SQL
SQL_PROMPT=$(echo "$SAFE_PROMPT" | sed "s/'/''/g")
SQL_NAME="$video_basename.mp4"

# Insert into videos
VIDEO_ID=$(mysql $MYSQL_ARGS -N -e "
    INSERT INTO videos 
    (map_run_id, name, description, url, thumbnail, duration, type, file_size, width, height, created_at)
    VALUES 
    ($MAP_RUN_ID,
     '$SQL_NAME', 
     '$SQL_PROMPT', 
     '$VIDEOS_DIR_REL/$video_basename.mp4', 
     '$THUMBS_DIR_REL/$video_basename.jpg',
     $DURATION,
     'video/mp4',
     $FILE_SIZE,
     $WIDTH, $HEIGHT,
     NOW()
    );
    SELECT LAST_INSERT_ID();
")

if [ -z "$VIDEO_ID" ]; then
    echo "Error inserting into videos table."
    exit 1
fi

# Mapping Table Insert
MAPPING_TABLE="videos_2_$ENTITY_TYPE"

# Check if mapping table exists (safety check)
TABLE_EXISTS=$(mysql $MYSQL_ARGS -N -e "SHOW TABLES LIKE '$MAPPING_TABLE';")
if [ -n "$TABLE_EXISTS" ]; then
    mysql $MYSQL_ARGS -e "INSERT INTO $MAPPING_TABLE (from_id, to_id) VALUES ($VIDEO_ID, $ENTITY_ID);"
    echo "Linked Video #$VIDEO_ID to $ENTITY_TYPE #$ENTITY_ID"
else
    echo "Warning: Mapping table $MAPPING_TABLE does not exist. Link skipped."
fi

# Clear Regenerate Flag
REGEN_COL="regenerate_videos"
[ "$ENTITY_TYPE" == "composites" ] && REGEN_COL="regenerate_images"

mysql $MYSQL_ARGS -e "UPDATE $ENTITY_TYPE SET $REGEN_COL=0 WHERE id=$ENTITY_ID;"

exit 0
