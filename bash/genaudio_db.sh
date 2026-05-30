#!/bin/bash

# ==============================================================================
# SAGE Piper TTS Client (Async/Polling) - Verbose
# ==============================================================================

echo "   [Worker] Starting..."

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

if [ -z "$PROJECT_ROOT" ]; then
   PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
fi

# -----------------------------
# Configuration
# -----------------------------
# 1. Resolve Zrok URL (Dynamic or Default)
ZROK_SCRIPT="$SCRIPT_DIR/pyapi_echo.sh"
if [ -x "$ZROK_SCRIPT" ]; then
    ZROK_URL=$("$ZROK_SCRIPT")
else
    ZROK_URL=""
fi

if [ -z "$ZROK_URL" ]; then
    echo "   [Worker] ⚠️ No Zrok URL found. Defaulting to http://localhost:8009"
    ZROK_URL="http://localhost:8009"
fi

AUDIO_DIR="$PROJECT_ROOT/public/audios"
AUDIO_DIR_REL="/audios"
mkdir -p "$AUDIO_DIR"

# 2. Polling Settings
POLL_INTERVAL=5
MAX_POLL_ATTEMPTS=120
RETRY_DELAY=2
MAX_JOB_RETRIES=1

# -----------------------------
# Arguments
# -----------------------------
TEXT_CONTENT="$1"
MAP_RUN_ID="$2"
ENTITY_TYPE="$3"
ENTITY_ID="$4"
VOICE_MODEL="$5"

if [ -z "$VOICE_MODEL" ]; then
    VOICE_MODEL="en_US-amy-medium"
fi

if [ -z "$TEXT_CONTENT" ] || [ -z "$MAP_RUN_ID" ] || [ -z "$ENTITY_TYPE" ] || [ -z "$ENTITY_ID" ]; then
  echo "   [Worker] ❌ Error: Missing arguments."
  exit 1
fi

# -----------------------------
# DB: Get Next Filename
# -----------------------------
audio_basename=$(mysql $MYSQL_ARGS -N --batch --skip-column-names -e "
  UPDATE audio_counter
  SET next_audio = LAST_INSERT_ID(next_audio + 1);
  SELECT LPAD(LAST_INSERT_ID(), 7, '0');
")
audio_basename="audio$audio_basename"
EXTENSION="wav"
OUTFILE="$AUDIO_DIR/$audio_basename.$EXTENSION"
FILENAME_ONLY="$audio_basename.$EXTENSION"

# -----------------------------
# Prepare JSON Payload
# -----------------------------
JSON_FILE=$(mktemp)
python3 -c "
import json, sys
data = {
    'text': sys.argv[1],
    'model': sys.argv[2]
}
print(json.dumps(data))
" "$TEXT_CONTENT" "$VOICE_MODEL" > "$JSON_FILE"

echo "   [Worker] Target: $ZROK_URL"
echo "   [Worker] File: $FILENAME_ONLY"

# -----------------------------
# Execute Async Job Loop
# -----------------------------
job_attempt=1
SUCCESS=false

while [ $job_attempt -le $MAX_JOB_RETRIES ]; do

  echo "   [Worker] Attempt $job_attempt/$MAX_JOB_RETRIES..."

  # A. Submit Task
  SUBMIT_URL="${ZROK_URL}/voicepool/synthesize"
  
  CREATE_RESP=$(curl -s -X POST "$SUBMIT_URL" \
       -H "Content-Type: application/json" \
       -d @"$JSON_FILE")

  TASK_ID=$(echo "$CREATE_RESP" | jq -r '.task_id // empty')

  if [ -z "$TASK_ID" ]; then
    echo "   [Worker] ❌ Failed to create task. Response: $CREATE_RESP"
    job_attempt=$((job_attempt+1))
    sleep $RETRY_DELAY
    continue
  fi

  echo "   [Worker] Task ID: $TASK_ID. Polling..."

  # B. Poll Status
  POLL_OK=false
  poll_count=0
  STATUS_URL="${ZROK_URL}/voicepool/status/$TASK_ID"

  while [ $poll_count -lt $MAX_POLL_ATTEMPTS ]; do
    sleep $POLL_INTERVAL
    poll_count=$((poll_count+1))

    # Fetch headers (-D -) and body to OUTFILE
    POLL_RESP=$(curl -s -D - "$STATUS_URL" -o "$OUTFILE")
    
    # Parse HTTP Status
    HTTP_STATUS=$(echo "$POLL_RESP" | grep -E '^HTTP' | tail -1 | awk '{print $2}')
    # Parse Content-Type
    CONTENT_TYPE=$(echo "$POLL_RESP" | grep -i '^Content-Type:' | awk '{print $2}')

    if [ "$HTTP_STATUS" == "200" ]; then
      if [[ "$CONTENT_TYPE" == *"audio/wav"* ]]; then
        echo "   [Worker] Poll #$poll_count: COMPLETED."
        POLL_OK=true
        break
      else
        # Still JSON (Pending/Processing)
        CURRENT_STATUS=$(jq -r '.status // "UNKNOWN"' "$OUTFILE" 2>/dev/null)
        
        # --- VERBOSE LOGGING ENABLED HERE ---
        echo "   [Worker] Poll #$poll_count: $CURRENT_STATUS"
        
        if [ "$CURRENT_STATUS" == "FAILED" ]; then
             echo "   [Worker] ❌ Task reported failure."
             break
        fi
      fi
    elif [ "$HTTP_STATUS" == "500" ] || [ "$HTTP_STATUS" == "404" ]; then
      echo "   [Worker] Poll #$poll_count: SERVER ERROR ($HTTP_STATUS)"
      break
    else
        echo "   [Worker] Poll #$poll_count: HTTP $HTTP_STATUS..."
    fi
  done

  # C. Validate Result
  if [ "$POLL_OK" == "true" ] && [ -s "$OUTFILE" ]; then
      # Basic validation
      if command -v ffmpeg &> /dev/null; then
          if ffmpeg -v error -i "$OUTFILE" -f null - 2>/dev/null; then
              SUCCESS=true
              break
          else
              echo "   [Worker] ❌ Invalid WAV header."
          fi
      else
          SUCCESS=true
          break
      fi
  else
      echo "   [Worker] ❌ Polling failed or timed out."
  fi

  job_attempt=$((job_attempt+1))
  sleep $RETRY_DELAY
done

# Cleanup
rm -f "$JSON_FILE"

if [ "$SUCCESS" != "true" ]; then
  echo "   [Worker] Failed after retries."
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
SELECT LAST_INSERT_ID();")

MAPPING_TABLE="audios_2_${ENTITY_TYPE}"
TABLE_EXISTS=$(mysql $MYSQL_ARGS -N -e "SHOW TABLES LIKE '$MAPPING_TABLE';")
if [ -n "$TABLE_EXISTS" ] && [ -n "$AUDIO_ID" ]; then
   mysql $MYSQL_ARGS -e "INSERT INTO $MAPPING_TABLE (from_id, to_id) VALUES ($AUDIO_ID, $ENTITY_ID);"
fi

mysql $MYSQL_ARGS -e "UPDATE $ENTITY_TYPE SET regenerate_audios=0 WHERE id=$ENTITY_ID;"

echo "   [Worker] Saved $FILENAME_ONLY"
