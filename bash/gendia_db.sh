#!/bin/bash

# ==============================================================================
# WORKER: gendia_db.sh (Pollinations AI TTS Client)
# ==============================================================================

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MYSQL_ARGS=$("$SCRIPT_DIR"/db_name.sh main-conn)

# -----------------------------
# Load project roots
# -----------------------------
if [ -f "$SCRIPT_DIR/load_root.sh" ]; then
  source "$SCRIPT_DIR/load_root.sh"
fi

if [ -z "$PROJECT_ROOT" ]; then
   PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
fi

# -----------------------------
# Load Pollinations Token
# -----------------------------
if [ -f "$SCRIPT_DIR/../token/.pollinationsaitoken" ]; then
  export PAI_TOKEN=$(cat "$SCRIPT_DIR/../token/.pollinationsaitoken")
fi

# -----------------------------
# Configuration
# -----------------------------
AUDIO_DIR="$PROJECT_ROOT/public/audios"
AUDIO_DIR_REL="/audios"
mkdir -p "$AUDIO_DIR" 2>/dev/null

MAX_RETRIES=3
RETRY_DELAY=2

# -----------------------------
# Arguments
# -----------------------------
TEXT_CONTENT="$1"
MAP_RUN_ID="$2"
ENTITY_TYPE="$3"
ENTITY_ID="$4"
VOICE_NAME="$5"

if [ -z "$VOICE_NAME" ]; then
    VOICE_NAME="alloy"
fi

if [ -z "$TEXT_CONTENT" ] || [ -z "$MAP_RUN_ID" ] || [ -z "$ENTITY_TYPE" ] || [ -z "$ENTITY_ID" ]; then
  echo "   [Worker] Error: Missing arguments."
  exit 1
fi

# -----------------------------
# DB: Get Next Filename
# -----------------------------
audio_basename=$(mysql $MYSQL_ARGS -N --batch --skip-column-names -e "
  UPDATE audio_counter
  SET next_audio = LAST_INSERT_ID(next_audio + 1);
  SELECT LPAD(LAST_INSERT_ID(), 7, '0');
" 2>/dev/null)

if [ -z "$audio_basename" ]; then
    echo "   [Worker] Error: DB generation failed."
    exit 1
fi

audio_basename="audio$audio_basename"
EXTENSION="mp3" # Can be changed to 'wav' if your engine requires strictly wavs
OUTFILE="$AUDIO_DIR/$audio_basename.$EXTENSION"
FILENAME_ONLY="$audio_basename.$EXTENSION"

echo "   [Worker] File: $FILENAME_ONLY"

# -----------------------------
# URL Encode Prompt
# -----------------------------
if ! command -v jq &> /dev/null; then
    echo "   [Worker] Error: 'jq' missing."
    exit 1
fi

# Clean prompt
CLEAN_PROMPT=$(echo "$TEXT_CONTENT" | tr -d '?%')
url_prompt=$(echo -n "$CLEAN_PROMPT" | jq -sRr @uri)

# -----------------------------
# Execute Generation Loop
# -----------------------------
attempt=1
SUCCESS=false

while [ $attempt -le $MAX_RETRIES ]; do

  # Build Pollinations endpoint with ?voice= (for TTS) and ?response_format=
  POLLINATIONS_URL="https://gen.pollinations.ai/audio/$url_prompt?voice=$VOICE_NAME&response_format=$EXTENSION"
  
  # A. Generate Audio
  # -s (silent), -L (follow redirect), --fail (no output on error)
  if [ -n "$PAI_TOKEN" ]; then
    curl -s -L --fail -H "Authorization: Bearer $PAI_TOKEN" "$POLLINATIONS_URL" -o "$OUTFILE"
  else
    curl -s -L --fail "$POLLINATIONS_URL" -o "$OUTFILE"
  fi

  # B. Validate Result
  if [ -s "$OUTFILE" ]; then
      # Check for FFmpeg (silence stderr with 2>/dev/null)
      if command -v ffmpeg &> /dev/null; then
          if ffmpeg -v error -i "$OUTFILE" -f null - 2>/dev/null; then
              SUCCESS=true
              break
          else
              # Invalid/Corrupted Audio, silent retry
              rm -f "$OUTFILE"
          fi
      else
          # No ffmpeg installed to validate, assume success
          SUCCESS=true
          break
      fi
  else
      # Empty file, retry
      rm -f "$OUTFILE"
  fi

  attempt=$((attempt+1))
  sleep $RETRY_DELAY
done

if [ "$SUCCESS" != "true" ]; then
  echo "   [Worker] Failed."
  exit 1
fi

# -----------------------------
# Database Insert
# -----------------------------
SAFE_NAME=$(echo "$FILENAME_ONLY" | sed "s/'/''/g")

AUDIO_ID=$(mysql $MYSQL_ARGS -N -e "
INSERT INTO audios
  (filename, name, entity_type, entity_id, map_run_id)
VALUES
  ('$AUDIO_DIR_REL/$FILENAME_ONLY',
   '$SAFE_NAME',
   '$ENTITY_TYPE',
   $ENTITY_ID,
   $MAP_RUN_ID
);
SELECT LAST_INSERT_ID();" 2>/dev/null)

MAPPING_TABLE="audios_2_${ENTITY_TYPE}"
TABLE_EXISTS=$(mysql $MYSQL_ARGS -N -e "SHOW TABLES LIKE '$MAPPING_TABLE';" 2>/dev/null)

if [ -n "$TABLE_EXISTS" ] && [ -n "$AUDIO_ID" ]; then
   mysql $MYSQL_ARGS -e "INSERT INTO $MAPPING_TABLE (from_id, to_id) VALUES ($AUDIO_ID, $ENTITY_ID);" 2>/dev/null
fi

mysql $MYSQL_ARGS -e "UPDATE $ENTITY_TYPE SET regenerate_audios=0 WHERE id=$ENTITY_ID;" 2>/dev/null

echo "   [Worker] Saved."
