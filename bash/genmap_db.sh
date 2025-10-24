#!/bin/bash

# -----------------------------
# Resolve script directory (key fix)
# -----------------------------
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

MYSQL_ARGS=$("$SCRIPT_DIR"/db_name.sh main-conn)

# -----------------------------
# Load token
# -----------------------------
export PAI_TOKEN=$(cat "$SCRIPT_DIR/../token/.pollinationsaitoken")

# -----------------------------
# Load project roots
# -----------------------------
if [ -f "$SCRIPT_DIR/load_root.sh" ]; then
  source "$SCRIPT_DIR/load_root.sh"
fi

[ -z "$FRAMES_ROOT" ] && { echo "ERROR: FRAMES_ROOT not set"; exit 1; }
[ -z "$PROJECT_ROOT" ] && { echo "ERROR: PROJECT_ROOT not set"; exit 1; }

# -----------------------------
# Directories
# -----------------------------
FRAMES_DIR="$FRAMES_ROOT"
mkdir -p "$FRAMES_DIR"
FRAMES_DIR_REL="${FRAMES_ROOT#$PROJECT_ROOT/public/}"

# -----------------------------
# Config
# -----------------------------
CLOUDFLARED_URL="https://c7sr49n1ktm9.share.zrok.io"
DB_USER="root"
DB_NAME=$("$SCRIPT_DIR/db_name.sh")
MAX_RETRIES=1
RETRY_DELAY=2

VALID_TYPES=("pose" "hed" "canny" "midas" "mlsd")

# -----------------------------
# Arguments
# -----------------------------
MAP_RUN_ID="$1"
ENTITY_ID="$2"

[ -z "$MAP_RUN_ID" ] || [ -z "$ENTITY_ID" ] && {
  echo "Usage: $0 MAP_RUN_ID ENTITY_ID"
  exit 1
}

ENTITY_TYPE="controlnet_maps"
MAPPING_TABLE="frames_2_${ENTITY_TYPE}"

# Check mapping table exists
TABLE_EXISTS=$(mysql $MYSQL_ARGS -N -e "SHOW TABLES LIKE '$MAPPING_TABLE';")
[ -z "$TABLE_EXISTS" ] && { echo "Mapping table '$MAPPING_TABLE' does not exist!"; exit 1; }

# -----------------------------
# Fetch img2img source image info
# -----------------------------
read IMG2IMG_FRAME_ID IMG2IMG_PROMPT < <(
  mysql $MYSQL_ARGS -N -e \
    "SELECT COALESCE(img2img_frame_id,0), COALESCE(img2img_prompt,'') FROM $ENTITY_TYPE WHERE id=$ENTITY_ID;"
)

IMG2IMG_FILENAME=""
if [ "$IMG2IMG_FRAME_ID" -gt 0 ]; then
  IMG2IMG_FILENAME=$(mysql $MYSQL_ARGS -N -e "SELECT filename FROM frames WHERE id = $IMG2IMG_FRAME_ID LIMIT 1;" | tr -d '\r')
fi

if [ -n "$IMG2IMG_FILENAME" ]; then
  ABS_PATH="$PROJECT_ROOT/public/$IMG2IMG_FILENAME"
  [ -f "$ABS_PATH" ] && IMG2IMG_FILENAME="$ABS_PATH" || { echo "Source image missing at $ABS_PATH"; exit 1; }
fi

# -----------------------------
# Generate all maps
# -----------------------------
for MAP_TYPE in "${VALID_TYPES[@]}"; do

  frame_basename=$(mysql $MYSQL_ARGS -N -e "
UPDATE frame_counter SET next_frame = LAST_INSERT_ID(next_frame + 1);
SELECT CONCAT('frame', LPAD(LAST_INSERT_ID(), 7, '0'));
")
  outfile="$FRAMES_DIR/$frame_basename.png"
  filename_only="$frame_basename"

  echo "Generating ControlNet map $filename_only of type '$MAP_TYPE'"

  attempt=1
  while [ $attempt -le $MAX_RETRIES ]; do
    curl -s -X POST "$CLOUDFLARED_URL/map/$MAP_TYPE" \
      -F "file=@$IMG2IMG_FILENAME" \
      --output "$outfile"

    ffmpeg -v error -i "$outfile" -f null - 2>/dev/null
    if [ $? -eq 0 ]; then
      echo "✅ Saved valid map: $outfile"

      # Insert into DB
      SAFE_PROMPT="$MAP_TYPE"
      SAFE_STYLE="$MAP_TYPE"
      FRAME_ID=$(mysql $MYSQL_ARGS -N -e "
INSERT INTO frames
  (filename, name, prompt, entity_type, entity_id, style, style_id, map_run_id, img2img_frame_id, img2img_prompt)
VALUES
  ('$FRAMES_DIR_REL/$filename_only.png',
   '$filename_only',
   '$SAFE_PROMPT',
   '$ENTITY_TYPE',
   $ENTITY_ID,
   '$SAFE_STYLE',
   0,
   $MAP_RUN_ID,
   $IMG2IMG_FRAME_ID,
   '$(echo "$IMG2IMG_PROMPT" | sed "s/'/''/g")');
SELECT LAST_INSERT_ID();
")
      [ -n "$FRAME_ID" ] && mysql $MYSQL_ARGS -e \
        "INSERT INTO $MAPPING_TABLE (from_id, to_id) VALUES ($FRAME_ID, $ENTITY_ID);"

      break
    else
      echo "Broken image. Retry $attempt/$MAX_RETRIES..."
      rm -f "$outfile"
      attempt=$((attempt+1))
      sleep $RETRY_DELAY
    fi
  done

  if [ $attempt -gt $MAX_RETRIES ]; then
    echo "❌ Failed to generate valid map after $MAX_RETRIES attempts for type '$MAP_TYPE'."
  fi

done

# -----------------------------
# Clear regenerate flag
# -----------------------------
mysql $MYSQL_ARGS -e "UPDATE $ENTITY_TYPE SET regenerate_images=0 WHERE id=$ENTITY_ID;"
