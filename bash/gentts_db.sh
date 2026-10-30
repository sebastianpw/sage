#!/bin/bash
# gentts_db.sh - Pollinations API TTS Worker (MP3 320k)

# ---------------------------------------------------------
# TTS CONFIGURATION
# ---------------------------------------------------------
TTS_MODEL="hexgrad/kokoro-82m"
TTS_VOICE="alloy"
CURL_TIMEOUT=120
# ---------------------------------------------------------

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MYSQL_ARGS=$("$SCRIPT_DIR"/db_name.sh main-conn)

if [ -f "$SCRIPT_DIR/load_root.sh" ]; then source "$SCRIPT_DIR/load_root.sh"; fi
if [ -z "$PROJECT_ROOT" ]; then PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"; fi

API_URL="https://gen.pollinations.ai/v1/audio/speech"

# ---------------------------------------------------------
# Pollinations API Key Resolution
# ---------------------------------------------------------
export PAI_TOKEN=""
if [ -f "$SCRIPT_DIR/../token/.pollinationsaitoken" ]; then
  PAI_TOKEN=$(cat "$SCRIPT_DIR/../token/.pollinationsaitoken")
fi

if [ -z "$PAI_TOKEN" ]; then
  echo "WARNING: PAI_TOKEN not set. Continuing without Auth."
fi

# ---------------------------------------------------------
# Directories & Arguments
# ---------------------------------------------------------
AUDIO_DIR="$PROJECT_ROOT/public/audios"
AUDIO_DIR_REL="/audios"
mkdir -p "$AUDIO_DIR"

TEXT_CONTENT="$1"
MAP_RUN_ID="$2"
ENTITY_TYPE="$3"
ENTITY_ID="$4"

if [ -z "$TEXT_CONTENT" ]; then echo "Error: Empty text content"; exit 1; fi

# Filename Generation (Now using .mp3)
audio_basename=$(mysql $MYSQL_ARGS -N --batch --skip-column-names -e "UPDATE audio_counter SET next_audio = LAST_INSERT_ID(next_audio + 1); SELECT LPAD(LAST_INSERT_ID(), 7, '0');")
FILENAME_ONLY="qwen_clone${audio_basename}.mp3"
OUTFILE="$AUDIO_DIR/$FILENAME_ONLY"
TMP_WAV="${OUTFILE}.wav"

# Request uncompressed WAV from API for best local encoding quality
JSON_FILE=$(mktemp)
jq -n \
  --arg m "$TTS_MODEL" \
  --arg i "$TEXT_CONTENT" \
  --arg v "$TTS_VOICE" \
  '{model: $m, input: $i, voice: $v, response_format: "wav"}' > "$JSON_FILE"

echo "   -> [Worker] Submitting task for Doc $ENTITY_ID (Model: $TTS_MODEL, Voice: $TTS_VOICE)..."

# ---------------------------------------------------------
# Submit synchronous POST request (Saving to TMP_WAV)
# ---------------------------------------------------------
if [ -n "$PAI_TOKEN" ]; then
  HTTP_STATUS=$(curl -m "$CURL_TIMEOUT" -s -w "%{http_code}" -X POST "$API_URL" \
    -H "Authorization: Bearer $PAI_TOKEN" \
    -H "Content-Type: application/json" \
    -d @"$JSON_FILE" \
    -o "$TMP_WAV")
else
  HTTP_STATUS=$(curl -m "$CURL_TIMEOUT" -s -w "%{http_code}" -X POST "$API_URL" \
    -H "Content-Type: application/json" \
    -d @"$JSON_FILE" \
    -o "$TMP_WAV")
fi

rm -f "$JSON_FILE"

# Validate HTTP response code
if [ "$HTTP_STATUS" -ne 200 ]; then
    echo "   -> [Worker] ❌ Task Failed: HTTP $HTTP_STATUS"
    cat "$TMP_WAV" # Output the JSON error if one returns instead of audio
    rm -f "$TMP_WAV"
    exit 1
fi

# ---------------------------------------------------------
# Local FFmpeg Encoding to 320k MP3
# ---------------------------------------------------------
echo "   -> [Worker] Converting to 320k MP3..."
ffmpeg -y -i "$TMP_WAV" -codec:a libmp3lame -b:a 320k "$OUTFILE" </dev/null >/dev/null 2>&1

if [ $? -ne 0 ]; then
    echo "   -> [Worker] ❌ FFmpeg conversion failed!"
    rm -f "$TMP_WAV" "$OUTFILE"
    exit 1
fi

# Clean up temp WAV
rm -f "$TMP_WAV"

FILE_SIZE=$(stat -c%s "$OUTFILE" 2>/dev/null)
echo "   -> [Worker] Success! MP3 saved. Size: $FILE_SIZE bytes."

# ---------------------------------------------------------
# Database Updates
# ---------------------------------------------------------
SAFE_NAME="PolliTTS_${TTS_VOICE}_${ENTITY_ID}"

AUDIO_ID=$(mysql $MYSQL_ARGS -N -e "INSERT INTO audios (filename, name, entity_type, entity_id, map_run_id) VALUES ('$AUDIO_DIR_REL/$FILENAME_ONLY', '$SAFE_NAME', '$ENTITY_TYPE', $ENTITY_ID, $MAP_RUN_ID); SELECT LAST_INSERT_ID();")

mysql $MYSQL_ARGS -e "INSERT INTO audios_2_documentations (from_id, to_id) VALUES ($AUDIO_ID, $ENTITY_ID);"
mysql $MYSQL_ARGS -e "UPDATE documentations SET regenerate_audios=0 WHERE id=$ENTITY_ID;"

echo "   -> [Worker] Database updated. Saved $FILENAME_ONLY"
