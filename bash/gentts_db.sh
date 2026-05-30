#!/bin/bash
# gentts_db.sh - Qwen3-TTS Async Clone Worker

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MYSQL_ARGS=$("$SCRIPT_DIR"/db_name.sh main-conn)

if [ -f "$SCRIPT_DIR/load_root.sh" ]; then source "$SCRIPT_DIR/load_root.sh"; fi
if [ -z "$PROJECT_ROOT" ]; then PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"; fi

# Determine API URL
ZROK_SCRIPT="$SCRIPT_DIR/zrok_echo.sh"
if [ -x "$ZROK_SCRIPT" ]; then API_URL=$("$ZROK_SCRIPT"); else API_URL="http://localhost:8009"; fi

AUDIO_DIR="$PROJECT_ROOT/public/audios"
AUDIO_DIR_REL="/audios"
mkdir -p "$AUDIO_DIR"

# Arguments
TEXT_CONTENT="$1"
MAP_RUN_ID="$2"
ENTITY_TYPE="$3"
ENTITY_ID="$4"
REF_AUDIO="$5" # e.g. "hal-9000.wav"
REF_TEXT="$6"  # Transcript of the ref audio

if [ -z "$TEXT_CONTENT" ]; then echo "Error: Empty text content"; exit 1; fi

# Generate output filename
audio_basename=$(mysql $MYSQL_ARGS -N --batch --skip-column-names -e "UPDATE audio_counter SET next_audio = LAST_INSERT_ID(next_audio + 1); SELECT LPAD(LAST_INSERT_ID(), 7, '0');")
FILENAME_ONLY="qwen_clone${audio_basename}.wav"
OUTFILE="$AUDIO_DIR/$FILENAME_ONLY"
JSON_FILE=$(mktemp)
HEADER_FILE=$(mktemp)

# ---------------------------------------------------------
# 1. Create Task (POST /qwen3tts/clone)
# ---------------------------------------------------------
echo "   -> [Worker] Submitting task ($REF_AUDIO)..."

# Construct JSON payload
# Note: We pass ref_text. If it is empty string in bash, Python receives "" and handles it.
python3 -c "import json, sys; print(json.dumps({
    'text': sys.argv[1], 
    'ref_audio': sys.argv[2], 
    'ref_text': sys.argv[3], 
    'language': 'auto'
}))" "$TEXT_CONTENT" "$REF_AUDIO" "$REF_TEXT" > "$JSON_FILE"

CREATE_RESP=$(curl -s -X POST "${API_URL}/qwen3tts/clone" \
    -H "Content-Type: application/json" \
    -d @"$JSON_FILE")

# Extract Task ID
TASK_ID=$(echo "$CREATE_RESP" | jq -r '.task_id // empty')

if [ -z "$TASK_ID" ] || [ "$TASK_ID" == "null" ]; then
    echo "   -> [Worker] ❌ Task Creation Failed: $CREATE_RESP"
    rm "$JSON_FILE" "$HEADER_FILE"
    exit 1
fi

echo "   -> [Worker] Task Created: $TASK_ID. Polling..."

# ---------------------------------------------------------
# 2. Poll Status (GET /qwen3tts/status/{id})
# ---------------------------------------------------------
POLL_OK=false
MAX_RETRIES=600 # 20 mins
count=0

while [ $count -lt $MAX_RETRIES ]; do
    sleep 2
    count=$((count+1))
    
    # Check Status
    curl -s -D "$HEADER_FILE" "${API_URL}/qwen3tts/status/$TASK_ID" -o "$OUTFILE"
    
    CTYPE=$(grep -i "Content-Type" "$HEADER_FILE")
    
    if [[ "$CTYPE" == *"audio/wav"* ]]; then
        echo "   -> [Worker] Poll #$count: COMPLETE. Audio received."
        POLL_OK=true
        break
    else
        STATUS=$(jq -r '.status // "UNKNOWN"' "$OUTFILE" 2>/dev/null)
        ERROR=$(jq -r '.error // empty' "$OUTFILE" 2>/dev/null)
        
        if [ $((count % 5)) -eq 0 ]; then
            echo "   -> [Worker] Poll #$count: $STATUS"
        fi

        if [ "$STATUS" == "FAILED" ]; then
            echo "   -> [Worker] ❌ Task Failed: $ERROR"
            break
        fi
    fi
done

rm "$JSON_FILE" "$HEADER_FILE"

if [ "$POLL_OK" != "true" ]; then
    echo "   -> [Worker] ❌ Timed out or failed."
    rm -f "$OUTFILE"
    exit 1
fi

# ---------------------------------------------------------
# 3. Database Updates
# ---------------------------------------------------------
FILE_SIZE=$(stat -c%s "$OUTFILE" 2>/dev/null)
echo "   -> [Worker] Success! Size: $FILE_SIZE bytes."

REF_NAME=$(basename "$REF_AUDIO" .wav)
SAFE_NAME="Qwen3_${REF_NAME}_${ENTITY_ID}"

AUDIO_ID=$(mysql $MYSQL_ARGS -N -e "INSERT INTO audios (filename, name, entity_type, entity_id, map_run_id) VALUES ('$AUDIO_DIR_REL/$FILENAME_ONLY', '$SAFE_NAME', '$ENTITY_TYPE', $ENTITY_ID, $MAP_RUN_ID); SELECT LAST_INSERT_ID();")

mysql $MYSQL_ARGS -e "INSERT INTO audios_2_documentations (from_id, to_id) VALUES ($AUDIO_ID, $ENTITY_ID);"
mysql $MYSQL_ARGS -e "UPDATE documentations SET regenerate_audios=0 WHERE id=$ENTITY_ID;"

echo "   -> [Worker] Database updated."
