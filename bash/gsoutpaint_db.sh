#!/bin/bash

# ==============================================================================
# SAGE Green Screen Outpaint Client (Synchronous)
# Fetches the 'img2img_frame_id' from the specified entity.
# Calls local Pillow Service to outpaint/green screen.
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
# Configuration
# -----------------------------
PILLOW_API="http://localhost:8009/image/outpaint"

# -----------------------------
# Arguments
# -----------------------------
MAP_RUN_ID="$1"
ENTITY_TYPE="$2"
ENTITY_ID="$3"
WIDTH="$4"
HEIGHT="$5"
POS_X="$6"
POS_Y="$7"
COLOR="$8"

if [ -z "$MAP_RUN_ID" ] || [ -z "$ENTITY_TYPE" ] || [ -z "$ENTITY_ID" ] || [ -z "$WIDTH" ]; then
  echo "Usage: $0 MAP_RUN_ID ENTITY_TYPE ENTITY_ID WIDTH [HEIGHT] [X] [Y] [COLOR]"
  exit 1
fi

# -----------------------------
# 1. Fetch Source Frame ID from Entity Table
# -----------------------------
SOURCE_FRAME_ID=$(mysql $MYSQL_ARGS -N -e "SELECT COALESCE(img2img_frame_id, 0) FROM $ENTITY_TYPE WHERE id=$ENTITY_ID;")

if [ -z "$SOURCE_FRAME_ID" ] || [ "$SOURCE_FRAME_ID" -eq 0 ]; then
  echo "ERROR: Entity $ENTITY_ID has no img2img_frame_id set. Cannot perform outpaint."
  # Clear regenerate flag to prevent loops
  mysql $MYSQL_ARGS -e "UPDATE $ENTITY_TYPE SET regenerate_images=0 WHERE id=$ENTITY_ID;"
  exit 1
fi

# -----------------------------
# 2. Resolve Filename AND Style Metadata
# -----------------------------
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
# 3. DB: Reserve Next Frame Number
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
# 4. Construct cURL Arguments
# -----------------------------
CURL_ARGS=(
  -X POST "$PILLOW_API"
  -F "file=@$ABS_SOURCE_PATH"
  -F "width=$WIDTH"
)

if [ -n "$HEIGHT" ]; then CURL_ARGS+=(-F "height=$HEIGHT"); fi
if [ -n "$POS_X" ]; then CURL_ARGS+=(-F "x=$POS_X"); fi
if [ -n "$POS_Y" ]; then CURL_ARGS+=(-F "y=$POS_Y"); fi
if [ -n "$COLOR" ]; then CURL_ARGS+=(-F "color=$COLOR"); fi

CURL_ARGS+=(--output "$OUTFILE")

# -----------------------------
# 5. Execute Request
# -----------------------------
echo "Processing Entity $ENTITY_ID (Src: $SOURCE_FRAME_ID) -> $OUTFILE"
echo "Params: W:$WIDTH H:${HEIGHT:-Auto} X:${POS_X:-Center} Y:${POS_Y:-Center} C:${COLOR:-#00FF00}"

# Execute cURL
curl -s "${CURL_ARGS[@]}"

# -----------------------------
# 6. Validate Result
# -----------------------------
# Check if file exists and is a valid image (using file command or size)
if [ -s "$OUTFILE" ] && file "$OUTFILE" | grep -q "PNG image data"; then
  echo "✅ Outpaint successful."
else
  echo "❌ Outpaint failed. No valid image produced."
  # Do NOT clear regenerate flag so it can be retried? 
  # Or clear it to stop blocking? Usually clear it to prevent infinite loops.
  mysql $MYSQL_ARGS -e "UPDATE $ENTITY_TYPE SET regenerate_images=0 WHERE id=$ENTITY_ID;"
  exit 1
fi

# -----------------------------
# 7. Database Insert (With Style Inheritance)
# -----------------------------
SAFE_PROMPT="GS Outpaint"
SAFE_STYLE=$(echo "$SRC_STYLE" | sed "s/'/''/g")

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
# 8. Clear Flag
# -----------------------------
mysql $MYSQL_ARGS -e "UPDATE $ENTITY_TYPE SET regenerate_images=0 WHERE id=$ENTITY_ID;"

echo "Frame $FRAME_ID created successfully."
