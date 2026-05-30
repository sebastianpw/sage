#!/bin/bash

# Usage: ./gen_depth_maps_batch.sh [BATCH_SIZE]
# Example: ./gen_depth_maps_batch.sh 10

# -----------------------------
# Resolve script directory
# -----------------------------
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MYSQL_ARGS=$("$SCRIPT_DIR"/db_name.sh main-conn)

# -----------------------------
# Load project roots
# -----------------------------
if [ -f "$SCRIPT_DIR/load_root.sh" ]; then
  source "$SCRIPT_DIR/load_root.sh"
fi

[ -z "$PROJECT_ROOT" ] && { echo "ERROR: PROJECT_ROOT not set"; exit 1; }

# -----------------------------
# Config
# -----------------------------
BATCH_SIZE=${1:-100}
ZROK_URL=$("$SCRIPT_DIR/pyapi_echo.sh")
MAX_RETRIES=1
RETRY_DELAY=2
MAP_TYPE="midas"

echo "======================================================"
echo "Starting Depth Map Batch Run (Batch Size: $BATCH_SIZE)"
echo "API Endpoint: $ZROK_URL"
echo "======================================================"

# -----------------------------
# Total count for progress indicator
# -----------------------------
TOTAL_COUNT=$(mysql $MYSQL_ARGS -N -e "
  SELECT COUNT(*) 
  FROM frames 
  WHERE depth_map_filename IS NULL 
    AND filename IS NOT NULL 
    AND filename != ''
")

CURRENT=0

# -----------------------------
# Query frames needing depth maps
# -----------------------------
SQL_QUERY="
  SELECT id, filename 
  FROM frames 
  WHERE depth_map_filename IS NULL 
    AND filename IS NOT NULL 
    AND filename != ''
  ORDER BY id DESC 
  LIMIT $BATCH_SIZE
"

mysql $MYSQL_ARGS -N -e "$SQL_QUERY" | while IFS=$'\t' read -r FRAME_ID SOURCE_FILENAME; do
  CURRENT=$((CURRENT+1))
  echo "[ $CURRENT / $BATCH_SIZE ]"

  # Clean up carriage returns from DB output just in case
  SOURCE_FILENAME=$(echo "$SOURCE_FILENAME" | tr -d '\r')
  ABS_SOURCE_PATH="$PROJECT_ROOT/public/$SOURCE_FILENAME"
  
  if [ ! -f "$ABS_SOURCE_PATH" ]; then
    echo "⚠️ Skipping Frame $FRAME_ID: Source image missing at $ABS_SOURCE_PATH"
    continue
  fi

  # -----------------------------
  # Generate new file metadata
  # -----------------------------
  REL_DIR=$(dirname "$SOURCE_FILENAME")
  ABS_DIR="$PROJECT_ROOT/public/$REL_DIR"
  mkdir -p "$ABS_DIR"

  frame_basename=$(mysql $MYSQL_ARGS -N -e "
    UPDATE frame_counter SET next_frame = LAST_INSERT_ID(next_frame + 1);
    SELECT CONCAT('frame', LPAD(LAST_INSERT_ID(), 7, '0'));
  ")

  outfile="$ABS_DIR/${frame_basename}.png"
  rel_outfile="$REL_DIR/${frame_basename}.png"

  echo "Processing Frame $FRAME_ID -> Generating Depth Map: $rel_outfile"

  # -----------------------------
  # Call PyAPI and validate
  # -----------------------------
  attempt=1
  success=0
  while [ $attempt -le $MAX_RETRIES ]; do
    curl -s -X POST "$ZROK_URL/map/$MAP_TYPE" \
      -F "file=@$ABS_SOURCE_PATH" \
      --output "$outfile"

    # Validate output with ffmpeg
    ffmpeg -v error -i "$outfile" -f null - 2>/dev/null
    if [ $? -eq 0 ]; then
      echo "✅ Saved valid depth map: $outfile"
      
      # Update the original frame to point to the new depth map
      mysql $MYSQL_ARGS -e "
        UPDATE frames 
        SET depth_map_filename = '$rel_outfile' 
        WHERE id = $FRAME_ID;
      "
      success=1
      break
    else
      echo "❌ Broken image returned. Retry $attempt/$MAX_RETRIES..."
      rm -f "$outfile"
      attempt=$((attempt+1))
      sleep $RETRY_DELAY
    fi
  done

  if [ $success -eq 0 ]; then
    echo "⚠️ Failed to generate valid map after $MAX_RETRIES attempts for frame $FRAME_ID."
  fi

  echo "------------------------------------------------------"
done

echo "Batch processing complete."
