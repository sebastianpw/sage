#!/bin/bash

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
DB_USER="root"
DB_NAME=$("$SCRIPT_DIR/db_name.sh")
MAX_RETRIES=2
RETRY_DELAY=2
POLL_INTERVAL=5
MAX_POLL_ATTEMPTS=12

# -----------------------------
# Freepik API key
# -----------------------------
if [ -z "$FREEPIK_KEY" ]; then
  if [ -f "$SCRIPT_DIR/../token/.freepik_api_key" ]; then
    FREEPIK_KEY=$(cat "$SCRIPT_DIR/../token/.freepik_api_key")
  fi
fi

if [ -z "$FREEPIK_KEY" ]; then
  echo "ERROR: FREEPIK_KEY not set."
  exit 1
fi

# -----------------------------
# Freeimage.host key
# -----------------------------
if [ -z "$FREEIMAGE_KEY" ]; then
  if [ -f "$SCRIPT_DIR/../token/.freeimage_key" ]; then
    export FREEIMAGE_KEY=$(cat "$SCRIPT_DIR/../token/.freeimage_key")
  fi
fi

if [ -z "$FREEIMAGE_KEY" ]; then
  echo "WARNING: FREEIMAGE_KEY not set."
fi

# -----------------------------
# Usage / Arguments
# UPDATED: Added Negative Prompt ($2) and Seed ($3)
# -----------------------------
BASE_PROMPT="$1"
NEGATIVE_PROMPT="$2"    # <--- NEW
SEED_ARG="$3"           # <--- NEW
MAP_RUN_ID="$4"         # Shifted
ENTITY_TYPE="$5"        # Shifted
ENTITY_ID="$6"          # Shifted
LIMIT="$7"              # Shifted
OFFSET="$8"             # Shifted
NO_STYLES="$9"          # Shifted
ADD_TO_PROMPT="${10}"   # Shifted

# -----------------------------
# Default values
# -----------------------------
NO_STYLES=${NO_STYLES:-0}

# -----------------------------
# Validation
# -----------------------------
if [ -z "$BASE_PROMPT" ] || [ -z "$MAP_RUN_ID" ] || [ -z "$ENTITY_TYPE" ] || [ -z "$ENTITY_ID" ]; then
  echo "Usage: $0 \"base prompt\" \"neg prompt\" \"seed\" MAP_RUN_ID \"entity_type\" \"entity_id\" [limit] [offset] [no_styles] [add_to_prompt]"
  exit 1
fi

# -----------------------------
# Load styles
# -----------------------------
mapfile -t styles < <(mysql $MYSQL_ARGS -N -e "SELECT id, prompt FROM v_styles_helper;")
TOTAL_STYLES=${#styles[@]}

START=$((OFFSET > 0 ? OFFSET - 1 : 0))
END=$((LIMIT > 0 ? START + LIMIT - 1 : TOTAL_STYLES - 1))
[ "$END" -ge "$TOTAL_STYLES" ] && END=$((TOTAL_STYLES - 1))

# -----------------------------
# Determine mapping table
# -----------------------------
MAPPING_TABLE="frames_2_${ENTITY_TYPE}"
TABLE_EXISTS=$(mysql $MYSQL_ARGS -N -e "SHOW TABLES LIKE '$MAPPING_TABLE';")
[ -z "$TABLE_EXISTS" ] && { echo "Mapping table '$MAPPING_TABLE' does not exist!"; exit 1; }

# -----------------------------
# Fetch img2img info
# -----------------------------
read IMG2IMG_FLAG IMG2IMG_FRAME_ID IMG2IMG_PROMPT < <(
  mysql $MYSQL_ARGS -N -e \
    "SELECT COALESCE(img2img,0), COALESCE(img2img_frame_id,0), COALESCE(img2img_prompt,'') FROM $ENTITY_TYPE WHERE id=$ENTITY_ID;"
)
IMG2IMG_FLAG=${IMG2IMG_FLAG:-0}
IMG2IMG_FRAME_ID=${IMG2IMG_FRAME_ID:-0}
IMG2IMG_PROMPT=${IMG2IMG_PROMPT:-''}
IMG2IMG_FILENAME=""

if [ "$IMG2IMG_FRAME_ID" -gt 0 ]; then
  IMG2IMG_FILENAME=$(mysql $MYSQL_ARGS -N -e "SELECT filename FROM frames WHERE id = $IMG2IMG_FRAME_ID LIMIT 1;" | tr -d '\r')
fi

# -----------------------------
# Resolve absolute path for img2img
# -----------------------------
if [ -n "$IMG2IMG_FILENAME" ]; then
    ABS_PATH="$PROJECT_ROOT/public/$IMG2IMG_FILENAME"
    if [ -f "$ABS_PATH" ]; then
        IMG2IMG_FILENAME="$ABS_PATH"
    else
        echo "WARNING: img2img source file not found at $ABS_PATH. Falling back to txt2img."
        IMG2IMG_FILENAME=""
        IMG2IMG_FRAME_ID=0
    fi
fi

# -----------------------------
# Composite frames handling
# -----------------------------
COMPOSITE_IMAGE_URLS=""
if [ "$ENTITY_TYPE" = "composites" ]; then
  echo "Entity is composites. Checking for assigned frames..."
  mapfile -t composite_frame_ids < <(mysql $MYSQL_ARGS -N -e \
    "SELECT frame_id FROM composite_frames WHERE composite_id = $ENTITY_ID ORDER BY frame_id ASC;")

  if [ ${#composite_frame_ids[@]} -gt 0 ]; then
    echo "Found ${#composite_frame_ids[@]} assigned frame(s) for composite ID $ENTITY_ID"
    declare -a uploaded_urls

    for frame_id in "${composite_frame_ids[@]}"; do
      frame_filename=$(mysql $MYSQL_ARGS -N -e \
        "SELECT filename FROM frames WHERE id = $frame_id LIMIT 1;" | tr -d '\r')

      if [ -n "$frame_filename" ]; then
        frame_abs_path="$PROJECT_ROOT/public/$frame_filename"
        if [ -f "$frame_abs_path" ]; then
          echo "Uploading composite frame $frame_id ($frame_filename) to Freeimage.host..."
          response=$(curl -s -X POST "https://freeimage.host/api/1/upload" \
            -F "key=$FREEIMAGE_KEY" \
            -F "action=upload" \
            -F "source=@$frame_abs_path" \
            -F "format=json")
          frame_url=$(echo "$response" | jq -r '.image.url')

          if [ -n "$frame_url" ] && [ "$frame_url" != "null" ]; then
            echo "Uploaded frame $frame_id: $frame_url"
            uploaded_urls+=("$frame_url")
          fi
        fi
      fi
    done

    if [ ${#uploaded_urls[@]} -gt 0 ]; then
      COMPOSITE_IMAGE_URLS=$(IFS=,; echo "${uploaded_urls[*]}")
      echo "Composite image URLs: $COMPOSITE_IMAGE_URLS"
    fi
  else
    echo "No assigned frames found for composite ID $ENTITY_ID"
  fi
fi

# -----------------------------
# Set up IMAGE_URL for img2img
# -----------------------------
if [ -n "$COMPOSITE_IMAGE_URLS" ]; then
  IMAGE_URL="$COMPOSITE_IMAGE_URLS"
  echo "Using composite assigned frames for img2img"
elif [ -n "$IMG2IMG_FILENAME" ] && [ -f "$IMG2IMG_FILENAME" ]; then
  echo "Uploading local source image $IMG2IMG_FILENAME to Freeimage.host..."
  response=$(curl -s -X POST "https://freeimage.host/api/1/upload" \
    -F "key=$FREEIMAGE_KEY" \
    -F "action=upload" \
    -F "source=@$IMG2IMG_FILENAME" \
    -F "format=json")
  IMAGE_URL=$(echo "$response" | jq -r '.image.url')
  if [ -z "$IMAGE_URL" ] || [ "$IMAGE_URL" = "null" ]; then
    echo "Failed to upload source image. Response: $response"
    exit 1
  fi
  echo "Uploaded source image URL: $IMAGE_URL"
else
  IMAGE_URL=""
  IMG2IMG_FILENAME=""
fi

# -----------------------------
# Helper: build JSON array for reference_images
# -----------------------------
build_refs_json() {
  local in="$1"
  if [ -z "$in" ]; then
    echo '[]'
    return
  fi
  printf '%s' "$in" | jq -R -s -c 'split(",") | map(gsub("^\\s+|\\s+$";""))'
}

# -----------------------------
# Generate frames (main loop)
# -----------------------------
for ((i=START; i<=END; i++)); do
  row="${styles[i]}"
  style_id=$(echo "$row" | awk '{print $1}')
  style=$(echo "$row" | cut -d' ' -f2-)

  # Build prompt
  prompt="$BASE_PROMPT"
  [ "$NO_STYLES" -eq 0 ] && prompt="$prompt, $style"
  [ -n "$ADD_TO_PROMPT" ] && prompt="$prompt $ADD_TO_PROMPT"
  [ -n "$IMG2IMG_PROMPT" ] && [ -n "$IMAGE_URL" ] && prompt="$prompt $IMG2IMG_PROMPT"

  # -----------------------------
  # Determine Seed (Passed or Random)
  # -----------------------------
  if [ -n "$SEED_ARG" ] && [ "$SEED_ARG" -ne 0 ]; then
    SEED=$((SEED_ARG)) # Ensure it's treated as number
  else
    SEED=$(( (RANDOM << 15) | RANDOM ))
  fi
  echo "Seed: $SEED"

  # -----------------------------
  # Thread-safe next frame basename (DB-driven)
  # -----------------------------
  frame_basename=$(mysql $MYSQL_ARGS -N --batch --skip-column-names -e "
    UPDATE frame_counter
    SET next_frame = LAST_INSERT_ID(next_frame + 1);
    SELECT LPAD(LAST_INSERT_ID(), 7, '0');
  ")
  frame_basename="frame$frame_basename"
  outfile="$FRAMES_DIR/$frame_basename.jpg"
  filename_only="$frame_basename"

  echo "Generating image $filename_only for prompt: $prompt [style_id=$style_id]"

  attempt=1
  while [ $attempt -le $MAX_RETRIES ]; do

    # -----------------------------
    # Freepik async request
    # -----------------------------
    refs_json=$(build_refs_json "$IMAGE_URL")

    # Build JSON Body: Include seed and negative_prompt
    # Using jq to safely build the JSON object
    if [ "$refs_json" = "[]" ]; then
      body=$(jq -n \
        --arg p "$prompt" \
        --arg np "$NEGATIVE_PROMPT" \
        '{prompt: $p, negative_prompt: $np}')
    else
      body=$(jq -n \
        --arg p "$prompt" \
        --arg np "$NEGATIVE_PROMPT" \
        --argjson refs "$refs_json" \
        '{prompt: $p, negative_prompt: $np, reference_images: $refs}')
    fi

    # POST to create a task
    echo "Posting generation request to Freepik..."
    create_resp=$(curl -s -X POST "https://api.freepik.com/v1/ai/gemini-2-5-flash-image-preview" \
      -H "x-freepik-api-key: $FREEPIK_KEY" \
      -H "Content-Type: application/json" \
      -d "$body")

    TASK_ID=$(printf '%s' "$create_resp" | jq -r '.data.task_id // empty')
    
    if [ -z "$TASK_ID" ]; then
      echo "Failed to create Freepik task. Response: $create_resp"
      attempt=$((attempt+1))
      sleep $RETRY_DELAY
      continue
    fi

    echo "Task created: $TASK_ID"
    POLL_OK=false
    poll_count=0

    # Poll loop
    while [ $poll_count -lt $MAX_POLL_ATTEMPTS ]; do
      sleep $POLL_INTERVAL
      poll_count=$((poll_count+1))

      poll_resp=$(curl -s -H "x-freepik-api-key: $FREEPIK_KEY" \
        "https://api.freepik.com/v1/ai/gemini-2-5-flash-image-preview/$TASK_ID")

      status=$(printf '%s' "$poll_resp" | jq -r '.data.status // empty')
      echo "Poll #$poll_count: status=$status"

      if [ "$status" = "COMPLETED" ]; then
        image_url=$(printf '%s' "$poll_resp" | jq -r '.data.generated[0] // empty')
        if [ -z "$image_url" ]; then
          echo "No image URL in COMPLETED response"
          break
        fi

        echo "Downloading image from: $image_url"
        curl -s -L "$image_url" -o "$outfile"
        POLL_OK=true
        break
      elif [ "$status" = "FAILED" ]; then
        echo "Freepik task failed. Response: $poll_resp"
        POLL_OK=false
        break
      fi
    done

    if [ "$POLL_OK" != "true" ] && [ $poll_count -ge $MAX_POLL_ATTEMPTS ]; then
      echo "Max polls reached. Giving up on this attempt."
      POLL_OK=false
    fi

    # Validate image
    if [ "$POLL_OK" = true ]; then
      ffmpeg -v error -i "$outfile" -f null - 2>/dev/null
      if [ $? -eq 0 ]; then
        echo "Saved valid image: $outfile"

        # Insert frame into DB
        SAFE_NEG_PROMPT=$(echo "$NEGATIVE_PROMPT" | sed "s/'/''/g")
        SAFE_PROMPT=$(echo "$prompt" | sed "s/'/''/g")
        SAFE_STYLE=$(echo "$style" | sed "s/'/''/g")
        DB_IMG2IMG_FRAME_ID="NULL"
        [ "$IMG2IMG_FRAME_ID" -gt 0 ] && DB_IMG2IMG_FRAME_ID="$IMG2IMG_FRAME_ID"
        DB_IMG2IMG_PROMPT="NULL"
        [ -n "$IMG2IMG_PROMPT" ] && DB_IMG2IMG_PROMPT="'$(echo "$IMG2IMG_PROMPT" | sed "s/'/''/g")'"

        # Added: seed and prompt_negative
        FRAME_ID=$(mysql $MYSQL_ARGS -N -e "
INSERT INTO frames
  (filename, name, prompt, prompt_negative, seed, entity_type, entity_id, style, style_id, map_run_id, img2img_frame_id, img2img_prompt)
VALUES
  ('$FRAMES_DIR_REL/$filename_only.jpg',
   '$filename_only',
   '$SAFE_PROMPT',
   '$SAFE_NEG_PROMPT',
   $SEED,
   '$ENTITY_TYPE',
   $ENTITY_ID,
   '$SAFE_STYLE',
   $style_id,
   $MAP_RUN_ID,
   $DB_IMG2IMG_FRAME_ID,
   $DB_IMG2IMG_PROMPT
);
SELECT LAST_INSERT_ID();")

        [ -n "$FRAME_ID" ] && mysql $MYSQL_ARGS -e \
          "INSERT INTO $MAPPING_TABLE (from_id, to_id) VALUES ($FRAME_ID, $ENTITY_ID);"

        break
      else
        echo "Broken/invalid image. Retry $attempt/$MAX_RETRIES..."
        rm -f "$outfile"
        attempt=$((attempt+1))
        sleep $RETRY_DELAY
      fi
    else
      echo "Polling or generation failed. Retry $attempt/$MAX_RETRIES..."
      attempt=$((attempt+1))
      sleep $RETRY_DELAY
    fi
  done

  if [ $attempt -gt $MAX_RETRIES ]; then
    echo "Failed to generate valid image after $MAX_RETRIES attempts."
  fi
done

# Clear regenerate flag
mysql $MYSQL_ARGS -e "UPDATE $ENTITY_TYPE SET regenerate_images=0 WHERE id=$ENTITY_ID;"

echo "Freepik generation script finished."
