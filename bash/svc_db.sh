#!/bin/bash
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MYSQL_ARGS=$("$SCRIPT_DIR"/db_name.sh main-conn)

if [ -f "$SCRIPT_DIR/load_root.sh" ]; then source "$SCRIPT_DIR/load_root.sh"; fi

AUDIOS_DIR="$PROJECT_ROOT/public/audios"
mkdir -p "$AUDIOS_DIR"
AUDIOS_DIR_REL="audios" 

ZROK_URL=$("$SCRIPT_DIR/zrok_echo.sh")
POLL_INTERVAL=3
MAX_POLL_ATTEMPTS=60 

MAP_RUN_ID="$1"
ENTITY_ID="$2"

if [ -z "$MAP_RUN_ID" ] || [ -z "$ENTITY_ID" ]; then
  echo "Usage: $0 MAP_RUN_ID ENTITY_ID"
  exit 1
fi

# 1. Fetch Source Information
IFS=$'\t' read -r W2W_FILENAME W2W_ID PITCH_SHIFT IDENTITY_ID < <(mysql $MYSQL_ARGS -N -e "
  SELECT 
    COALESCE(wav2wav_audio_filename, ''), 
    COALESCE(wav2wav_audio_id, 0),
    COALESCE(pitch_shift, 0), 
    COALESCE(audio_voice_identity_id, 0)
  FROM audio_dialogue_lines 
  WHERE id = $ENTITY_ID 
  LIMIT 1;
")

SRC_FILENAME="$W2W_FILENAME"
SRC_AUDIO_ID="$W2W_ID"

if [ -z "$SRC_FILENAME" ] && [ "$SRC_AUDIO_ID" -gt 0 ]; then
  SRC_FILENAME=$(mysql $MYSQL_ARGS -N -e "SELECT filename FROM audios WHERE id = $SRC_AUDIO_ID LIMIT 1")
fi

ABS_SOURCE_PATH="$PROJECT_ROOT/public/$SRC_FILENAME"

if [ -z "$SRC_FILENAME" ] || [ ! -f "$ABS_SOURCE_PATH" ]; then
  echo "ERROR: Source file not found: $ABS_SOURCE_PATH"
  mysql $MYSQL_ARGS -e "UPDATE audio_dialogue_lines SET regenerate_audios=0 WHERE id=$ENTITY_ID;"
  exit 1
fi

# 2. Resolve Model Name
# This must match the folder name in /kaggle/working/svc_models/
MODEL_NAME="Tachibana" # Default to our test model
case "$IDENTITY_ID" in
  1) MODEL_NAME="Tachibana" ;;
  # Add other IDs if you download more models
  *) MODEL_NAME="Tachibana" ;;
esac

# 3. Call API
audio_basename=$(mysql $MYSQL_ARGS -N --batch --skip-column-names -e "UPDATE audio_counter SET next_audio = LAST_INSERT_ID(next_audio + 1); SELECT LPAD(LAST_INSERT_ID(), 7, '0');")
audio_basename="audio$audio_basename"
OUTFILE="$AUDIOS_DIR/$audio_basename.mp3"
FILENAME_ONLY="$audio_basename"

echo "Sending $SRC_FILENAME to SVC (Model: $MODEL_NAME)..."
CREATE_RESP=$(curl -s -X POST "$ZROK_URL/svc/convert-async" \
  -F "file=@$ABS_SOURCE_PATH" \
  -F "model_name=$MODEL_NAME" \
  -F "pitch=$PITCH_SHIFT")

TASK_ID=$(echo "$CREATE_RESP" | jq -r '.task_id // empty')

if [ -z "$TASK_ID" ]; then
  echo "API Error: $CREATE_RESP"
  exit 1
fi

# 4. Poll
SUCCESS=false
count=0
while [ $count -lt $MAX_POLL_ATTEMPTS ]; do
  sleep $POLL_INTERVAL
  count=$((count+1))
  POLL_RESP=$(curl -s -D - "$ZROK_URL/svc/status/$TASK_ID" -o "$OUTFILE")
  HTTP_STATUS=$(echo "$POLL_RESP" | grep -E '^HTTP' | tail -1 | awk '{print $2}')
  
  if [ "$HTTP_STATUS" == "200" ] && [[ $(file --mime-type -b "$OUTFILE") == "audio/mpeg" ]]; then
    SUCCESS=true
    break
  fi
done

if [ "$SUCCESS" != "true" ]; then
  echo "Failed."
  mysql $MYSQL_ARGS -e "UPDATE audio_dialogue_lines SET regenerate_audios=0 WHERE id=$ENTITY_ID;"
  exit 1
fi

# 5. Insert to DB
REL_OUT_PATH="$AUDIOS_DIR_REL/$FILENAME_ONLY.mp3"
SQL_AUDIO_ID="NULL"
if [ "$SRC_AUDIO_ID" -gt 0 ]; then SQL_AUDIO_ID=$SRC_AUDIO_ID; fi

AUDIO_ID=$(mysql $MYSQL_ARGS -N -e "
INSERT INTO audios
  (name, filename, entity_type, entity_id, map_run_id, rvc_model_name, pitch_shift, wav2wav_audio_id, wav2wav_audio_filename)
VALUES
  ('$FILENAME_ONLY', '$REL_OUT_PATH', 'audio_dialogue_lines', $ENTITY_ID, $MAP_RUN_ID, '$MODEL_NAME', $PITCH_SHIFT, $SQL_AUDIO_ID, '$SRC_FILENAME');
SELECT LAST_INSERT_ID();
")

mysql $MYSQL_ARGS -e "INSERT INTO audios_2_audio_dialogue_lines (from_id, to_id) VALUES ($AUDIO_ID, $ENTITY_ID);"
mysql $MYSQL_ARGS -e "UPDATE audio_dialogue_lines SET regenerate_audios=0 WHERE id=$ENTITY_ID;"

echo "Done: $AUDIO_ID"
