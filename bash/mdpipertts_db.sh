#!/bin/bash
# mdtts_db.sh - Worker with Verbose Polling

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MYSQL_ARGS=$("$SCRIPT_DIR"/db_name.sh main-conn)

if [ -f "$SCRIPT_DIR/load_root.sh" ]; then source "$SCRIPT_DIR/load_root.sh"; fi
if [ -z "$PROJECT_ROOT" ]; then PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"; fi

ZROK_SCRIPT="$SCRIPT_DIR/pyapi_echo.sh"
if [ -x "$ZROK_SCRIPT" ]; then ZROK_URL=$("$ZROK_SCRIPT"); else ZROK_URL="http://localhost:8009"; fi

AUDIO_DIR="$PROJECT_ROOT/public/audios"
AUDIO_DIR_REL="/audios"
mkdir -p "$AUDIO_DIR"

TEXT_CONTENT="$1"
MAP_RUN_ID="$2"
ENTITY_TYPE="$3"
ENTITY_ID="$4"
VOICE_MODEL="$5"

if [ -z "$TEXT_CONTENT" ]; then echo "Empty text"; exit 1; fi

# Filename
audio_basename=$(mysql $MYSQL_ARGS -N --batch --skip-column-names -e "UPDATE audio_counter SET next_audio = LAST_INSERT_ID(next_audio + 1); SELECT LPAD(LAST_INSERT_ID(), 7, '0');")
FILENAME_ONLY="audio${audio_basename}.wav"
OUTFILE="$AUDIO_DIR/$FILENAME_ONLY"

# JSON
JSON_FILE=$(mktemp)
python3 -c "import json, sys; print(json.dumps({'text': sys.argv[1], 'model': sys.argv[2]}))" "$TEXT_CONTENT" "$VOICE_MODEL" > "$JSON_FILE"

# Submit
SUBMIT_URL="${ZROK_URL}/voicepool/synthesize"
CREATE_RESP=$(curl -s -X POST "$SUBMIT_URL" -H "Content-Type: application/json" -d @"$JSON_FILE")
TASK_ID=$(echo "$CREATE_RESP" | jq -r '.task_id // empty')

if [ -z "$TASK_ID" ]; then echo "   [DocWorker] ❌ Task Create Failed."; rm "$JSON_FILE"; exit 1; fi

echo "   [DocWorker] Task $TASK_ID for Doc $ENTITY_ID (Model: $VOICE_MODEL)..."

# Poll
POLL_OK=false
count=0
STATUS_URL="${ZROK_URL}/voicepool/status/$TASK_ID"

while [ $count -lt 500 ]; do
    sleep 5
    count=$((count+1))
    
    # Capture headers and body
    curl -s -D "$JSON_FILE.headers" "$STATUS_URL" -o "$OUTFILE"
    
    # Check Content-Type for Audio
    TYPE=$(grep -i "Content-Type" "$JSON_FILE.headers")
    
    if [[ "$TYPE" == *"audio/wav"* ]]; then
        echo "   [DocWorker] Poll #$count: COMPLETE (WAV received)."
        POLL_OK=true
        break
    else
        # Parse JSON status
        STATUS=$(jq -r '.status // "UNKNOWN"' "$OUTFILE" 2>/dev/null)
        echo "   [DocWorker] Poll #$count: $STATUS"
        
        if [ "$STATUS" == "FAILED" ]; then 
            break
        fi
    fi
done

rm "$JSON_FILE" "$JSON_FILE.headers"

if [ "$POLL_OK" != "true" ]; then 
    echo "   [DocWorker] ❌ Failed/Timeout."
    exit 1
fi

# Save
SAFE_NAME="Doc_Audio_${ENTITY_ID}"
AUDIO_ID=$(mysql $MYSQL_ARGS -N -e "INSERT INTO audios (filename, name, entity_type, entity_id, map_run_id) VALUES ('$AUDIO_DIR_REL/$FILENAME_ONLY', '$SAFE_NAME', '$ENTITY_TYPE', $ENTITY_ID, $MAP_RUN_ID); SELECT LAST_INSERT_ID();")
mysql $MYSQL_ARGS -e "INSERT INTO audios_2_documentations (from_id, to_id) VALUES ($AUDIO_ID, $ENTITY_ID);"
mysql $MYSQL_ARGS -e "UPDATE documentations SET regenerate_audios=0 WHERE id=$ENTITY_ID;"

echo "   [DocWorker] Saved $FILENAME_ONLY"
