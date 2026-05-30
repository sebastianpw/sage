#!/bin/bash

# ==============================================================================
# SAGE Rembg Client (Async/Polling + Map Runs + Style Inheritance)
# Fetches the 'img2img_frame_id' from the specified entity.
# Removes background via Zrok API.
# Saves result as a new frame, inheriting STYLE and STYLE_ID from the source.
# ==============================================================================

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

if [ -z "$FRAMES_ROOT" ] || [ -z "$PROJECT_ROOT" ]; then
  echo "ERROR: Roots not set. Check load_root.sh."
  exit 1
fi

# -----------------------------
# Directories
# -----------------------------
FRAMES_DIR="$FRAMES_ROOT"
mkdir -p "$FRAMES_DIR"
FRAMES_DIR_REL="${FRAMES_ROOT#$PROJECT_ROOT/public/}"

# -----------------------------
# Temp Dir & JPG Conversion Logic
# -----------------------------
TMP_DIR="${PROJECT_ROOT%/}/temp"
mkdir -p "$TMP_DIR"
TMP_FILES=()

cleanup_tmp() {
  for f in "${TMP_FILES[@]}"; do [ -f "$f" ] && rm -f "$f"; done
}
trap cleanup_tmp EXIT

convert_to_png() {
  local inp="$1"
  local out="$2"
  echo "Converting $inp -> $out"
  if command -v magick >/dev/null 2>&1; then magick "$inp" -colorspace sRGB -alpha remove -strip "$out" 2>&1 || return $?; return 0; fi
  if command -v convert >/dev/null 2>&1; then convert "$inp" -colorspace sRGB -alpha remove -strip "$out" 2>&1 || return $?; return 0; fi
  if command -v ffmpeg >/dev/null 2>&1; then ffmpeg -y -loglevel error -i "$inp" -vf "format=rgba" "$out" 2>&1 || return $?; return 0; fi
  return 10
}

# -----------------------------
# Configuration
# -----------------------------
ZROK_URL=$("$SCRIPT_DIR/zrok_echo.sh")

# Async Polling Config
POLL_INTERVAL=3          
MAX_POLL_ATTEMPTS=20     
RETRY_DELAY=2            
MAX_JOB_RETRIES=2

# -----------------------------
# Arguments
# -----------------------------
MAP_RUN_ID="$1"
ENTITY_TYPE="$2"
ENTITY_ID="$3"
QUALITY="${4:-film}"      # fast or film
#MODEL="${5:-u2net}"       # u2net or isnet-general-use
#MODEL="${5:-birefnet-general}"
MODEL="birefnet-general"

if [ -z "$MAP_RUN_ID" ] || [ -z "$ENTITY_TYPE" ] || [ -z "$ENTITY_ID" ]; then
  echo "Usage: $0 MAP_RUN_ID ENTITY_TYPE ENTITY_ID [QUALITY] [MODEL]"
  exit 1
fi

# -----------------------------
# 1. Fetch Source Frame ID from Entity Table
# -----------------------------
SOURCE_FRAME_ID=$(mysql $MYSQL_ARGS -N -e "SELECT COALESCE(img2img_frame_id, 0) FROM $ENTITY_TYPE WHERE id=$ENTITY_ID;")

if [ -z "$SOURCE_FRAME_ID" ] || [ "$SOURCE_FRAME_ID" -eq 0 ]; then
  echo "ERROR: Entity $ENTITY_ID has no img2img_frame_id set. Cannot perform background removal."
  # Clear regenerate flag to prevent loops
  mysql $MYSQL_ARGS -e "UPDATE $ENTITY_TYPE SET regenerate_images=0 WHERE id=$ENTITY_ID;"
  exit 1
fi

# -----------------------------
# 2. Resolve Filename AND Style Metadata
# -----------------------------
# We fetch filename, style (string), and style_id (int) from the source frame.
# We use COALESCE to handle NULLs safely.
# IFS=$'\t' ensures we split columns correctly even if style name has spaces.

IFS=$'\t' read -r SOURCE_FILENAME SRC_STYLE SRC_STYLE_ID < <(mysql $MYSQL_ARGS -N -e "
  SELECT 
    filename, 
    COALESCE(style, ''), 
    COALESCE(style_id, 'NULL') 
  FROM frames 
  WHERE id = $SOURCE_FRAME_ID 
  LIMIT 1;
")

if [ -z "$SOURCE_FILENAME" ]; then
  echo "ERROR: Frame ID $SOURCE_FRAME_ID (from entity) not found in frames table."
  exit 1
fi

ABS_SOURCE_PATH="$PROJECT_ROOT/public/$SOURCE_FILENAME"
if [ ! -f "$ABS_SOURCE_PATH" ]; then
  echo "ERROR: Source file missing at $ABS_SOURCE_PATH"
  exit 1
fi

# -----------------------------
# 3. Prepare Upload Path (JPG Workaround)
# -----------------------------
UPLOAD_PATH="$ABS_SOURCE_PATH"

if [[ "$ABS_SOURCE_PATH" =~ \.(jpg|jpeg|JPG|JPEG)$ ]]; then
  echo "Detected JPEG source. Applying temp conversion to PNG..."
  TEMP_PNG="$TMP_DIR/rembg_src_${ENTITY_ID}_$$.png"
  
  convert_to_png "$ABS_SOURCE_PATH" "$TEMP_PNG"
  if [ $? -eq 0 ] && [ -f "$TEMP_PNG" ]; then
    UPLOAD_PATH="$TEMP_PNG"
    TMP_FILES+=("$TEMP_PNG")
    echo "Temporary PNG created at $UPLOAD_PATH"
  else
    echo "WARNING: Conversion failed. Attempting upload of original JPG."
  fi
fi

# -----------------------------
# 4. DB: Reserve Next Frame Number
# -----------------------------
frame_basename=$(mysql $MYSQL_ARGS -N --batch --skip-column-names -e "
  UPDATE frame_counter
  SET next_frame = LAST_INSERT_ID(next_frame + 1);
  SELECT LPAD(LAST_INSERT_ID(), 7, '0');
")
frame_basename="frame$frame_basename"
OUTFILE="$FRAMES_DIR/$frame_basename.png"
FILENAME_ONLY="$frame_basename"

# -----------------------------
# 5. Execute Async Job Loop
# -----------------------------
echo "Processing Entity $ENTITY_ID (Src: $SOURCE_FRAME_ID) -> $OUTFILE [Mode: $QUALITY]"

job_attempt=1
SUCCESS=false

while [ $job_attempt -le $MAX_JOB_RETRIES ]; do
  
  # A. Post Task
  echo "Posting async request (Attempt $job_attempt)..."
  
  CREATE_RESP=$(curl -s -X POST "$ZROK_URL/remove-bg-async" \
    -F "file=@$UPLOAD_PATH" \
    -F "quality=$QUALITY" \
    -F "model=$MODEL" \
    -F "output=rgba")

  # Extract Task ID
  TASK_ID=$(echo "$CREATE_RESP" | jq -r '.task_id // empty')

  if [ -z "$TASK_ID" ]; then
    echo "Failed to create task. Response: $CREATE_RESP"
    job_attempt=$((job_attempt+1))
    sleep $RETRY_DELAY
    continue
  fi

  echo "Task created: $TASK_ID. Polling..."

  # B. Polling Loop
  POLL_OK=false
  poll_count=0

  while [ $poll_count -lt $MAX_POLL_ATTEMPTS ]; do
    sleep $POLL_INTERVAL
    poll_count=$((poll_count+1))

    POLL_RESP=$(curl -s -D - "$ZROK_URL/status/$TASK_ID" -o "$OUTFILE")
    
    HTTP_STATUS=$(echo "$POLL_RESP" | grep -E '^HTTP' | tail -1 | awk '{print $2}')
    CONTENT_TYPE=$(echo "$POLL_RESP" | grep -i '^Content-Type:' | awk '{print $2}')

    if [ "$HTTP_STATUS" == "200" ]; then
      if [[ "$CONTENT_TYPE" == *"image/png"* ]]; then
        echo "Poll #$poll_count: COMPLETED. Image downloaded."
        POLL_OK=true
        break
      else
        STATUS=$(jq -r '.status // "UNKNOWN"' "$OUTFILE")
        echo "Poll #$poll_count: $STATUS"
      fi
    elif [ "$HTTP_STATUS" == "500" ] || [ "$HTTP_STATUS" == "404" ]; then
      echo "Poll #$poll_count: FAILED (HTTP $HTTP_STATUS)"
      POLL_OK=false
      break
    else
      echo "Poll #$poll_count: HTTP $HTTP_STATUS... waiting"
    fi
  done

  # C. Validate Result
  if [ "$POLL_OK" == "true" ]; then
    if ffmpeg -v error -i "$OUTFILE" -f null - 2>/dev/null; then
      SUCCESS=true
      break 
    else
      echo "❌ Downloaded file is corrupt. Retrying job..."
    fi
  else
    echo "❌ Polling timed out or task failed."
  fi

  job_attempt=$((job_attempt+1))
  sleep $RETRY_DELAY
done

if [ "$SUCCESS" != "true" ]; then
  echo "Failed after $MAX_JOB_RETRIES job attempts."
  mysql $MYSQL_ARGS -e "UPDATE $ENTITY_TYPE SET regenerate_images=0 WHERE id=$ENTITY_ID;"
  exit 1
fi

# -----------------------------
# 6. Database Insert (With Style Inheritance)
# -----------------------------
SAFE_PROMPT="Background Removal ($QUALITY)"

# Sanitize Style string (escape single quotes)
SAFE_STYLE=$(echo "$SRC_STYLE" | sed "s/'/''/g")

# Note: SRC_STYLE_ID is handled as a number or NULL keyword from step 2

FRAME_ID=$(mysql $MYSQL_ARGS -N -e "
INSERT INTO frames
  (filename, name, prompt, entity_type, entity_id, img2img_frame_id, map_run_id, style, style_id)
VALUES
  ('$FRAMES_DIR_REL/$FILENAME_ONLY.png',
   '$FILENAME_ONLY',
   '$SAFE_PROMPT',
   '$ENTITY_TYPE',
   $ENTITY_ID,
   $SOURCE_FRAME_ID,
   $MAP_RUN_ID,
   '$SAFE_STYLE',
   $SRC_STYLE_ID
);
SELECT LAST_INSERT_ID();")

MAPPING_TABLE="frames_2_${ENTITY_TYPE}"
TABLE_EXISTS=$(mysql $MYSQL_ARGS -N -e "SHOW TABLES LIKE '$MAPPING_TABLE';")

if [ -n "$TABLE_EXISTS" ] && [ -n "$FRAME_ID" ]; then
  mysql $MYSQL_ARGS -e "INSERT INTO $MAPPING_TABLE (from_id, to_id) VALUES ($FRAME_ID, $ENTITY_ID);"
fi

# -----------------------------
# 7. Clear Flag
# -----------------------------
mysql $MYSQL_ARGS -e "UPDATE $ENTITY_TYPE SET regenerate_images=0 WHERE id=$ENTITY_ID;"

echo "Frame $FRAME_ID created successfully."
