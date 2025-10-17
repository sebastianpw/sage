#!/bin/bash

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DB_USER="root"
DB_NAME=$("$SCRIPT_DIR/db_name.sh")

# -----------------------------
# Load project roots (must set FRAMES_ROOT and PROJECT_ROOT)
# -----------------------------
if [ -f "$SCRIPT_DIR/load_root.sh" ]; then
  source "$SCRIPT_DIR/load_root.sh"
fi

# Fail if required variables not set
if [ -z "$FRAMES_ROOT" ]; then
  echo "ERROR: FRAMES_ROOT is not set. Please ensure load_root.sh exports FRAMES_ROOT. Aborting."
  exit 1
fi

if [ -z "$PROJECT_ROOT" ]; then
  echo "ERROR: PROJECT_ROOT is not set. Please ensure load_root.sh exports PROJECT_ROOT. Aborting."
  exit 1
fi

# Directories
VIDEO_DIR="$PROJECT_ROOT/public/videos"
THUMBNAIL_DIR="$VIDEO_DIR/thumbnail"

echo "Starting video population process..."
echo "Project root: $PROJECT_ROOT"
echo "Video directory: $VIDEO_DIR"
echo "Thumbnail directory: $THUMBNAIL_DIR"

# Create thumbnail directory if it doesn't exist
mkdir -p "$THUMBNAIL_DIR"

# Check if ffmpeg is available
if ! command -v ffmpeg &> /dev/null; then
    echo "Error: ffmpeg is not installed or not in PATH"
    exit 1
fi

# Check if ffprobe is available
if ! command -v ffprobe &> /dev/null; then
    echo "Error: ffprobe is not installed or not in PATH"
    exit 1
fi

# Safer globbing with nullglob and arrays
shopt -s nullglob
video_files=("$VIDEO_DIR"/*.mp4)

if [ ${#video_files[@]} -eq 0 ]; then
    echo "No MP4 files found in $VIDEO_DIR"
    exit 0
fi

echo "Found ${#video_files[@]} MP4 files"

# Process each MP4 file
for video_file in "${video_files[@]}"; do
    filename=$(basename "$video_file")
    name="${filename%.*}"
    thumbnail_file="$THUMBNAIL_DIR/${name}.jpg"
    
    echo "Processing: $filename"
    
    # Check if video already exists in database
    existing_id=$(mysql -u "$DB_USER" "$DB_NAME" -N -e "
        SELECT id FROM videos WHERE name = '$name'
    ")
    
    if [ -n "$existing_id" ]; then
        echo "  - Already exists in database (ID: $existing_id), skipping"
        continue
    fi
    
    # Initialize thumbnail_sql variable
    thumbnail_sql="NULL"
    
    # Generate thumbnail if it doesn't exist
    if [ ! -f "$thumbnail_file" ]; then
        echo "  - Generating thumbnail..."
        if ffmpeg -i "$video_file" -ss 00:00:01 -vframes 1 -q:v 2 "$thumbnail_file" 2>/dev/null; then
            if [ -f "$thumbnail_file" ]; then
                echo "  - ✓ Thumbnail generated successfully"
                thumbnail_sql="'/videos/thumbnail/${name}.jpg'"
            else
                echo "  - ✗ Thumbnail generation failed"
                thumbnail_sql="NULL"
            fi
        else
            echo "  - ✗ FFmpeg failed to generate thumbnail"
            thumbnail_sql="NULL"
        fi
    else
        echo "  - Thumbnail already exists"
        thumbnail_sql="'/videos/thumbnail/${name}.jpg'"
    fi
    
    # Get video duration using ffprobe
    duration=$(ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 "$video_file" 2>/dev/null | cut -d. -f1)
    duration=${duration:-0}
    
    echo "  - Duration: ${duration}s"
    
    # Prepare SQL values
    video_url="/videos/$filename"
    
    # Insert into database
    echo "  - Adding to database..."
    
    # Use HEREDOC for better SQL formatting
    VIDEO_ID=$(mysql -u "$DB_USER" "$DB_NAME" -N << EOF
INSERT INTO videos 
    (name, url, thumbnail, duration, type) 
VALUES 
    ('$name', '$video_url', $thumbnail_sql, $duration, 'video/mp4');
SELECT LAST_INSERT_ID();
EOF
)
    
    if [ -n "$VIDEO_ID" ]; then
        echo "  - ✓ Successfully added to database (ID: $VIDEO_ID)"
    else
        echo "  - ✗ Failed to add to database"
        # Show the SQL that failed for debugging
        echo "  - Failed SQL: INSERT INTO videos (name, url, thumbnail, duration, type) VALUES ('$name', '$video_url', $thumbnail_sql, $duration, 'video/mp4');"
    fi
done

echo "Video population process completed!"
