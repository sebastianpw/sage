#!/bin/bash
# Worker: generate audio from text via Bark (Suno) and store result in DB
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MYSQL_ARGS=$("$SCRIPT_DIR"/db_name.sh main-conn)

# load project root if present
if [ -f "$SCRIPT_DIR/load_root.sh" ]; then source "$SCRIPT_DIR/load_root.sh"; fi

AUDIOS_DIR="${PROJECT_ROOT:-$(dirname "$SCRIPT_DIR")}/public/audios"
mkdir -p "$AUDIOS_DIR"
AUDIOS_DIR_REL="audios"

ZROK_URL=$("$SCRIPT_DIR"/zrok_echo.sh)

# Polling / timing
POLL_INTERVAL=5
MAX_POLL_ATTEMPTS=90

# ---------------------------
# Bark inference defaults
# ---------------------------
VOICE_PRESET="v2/en_speaker_6"
TEMPERATURE=0.7
# ---------------------------

TEXT_CONTENT="$1"
MAP_RUN_ID="$2"
ENTITY_TYPE="$3"
ENTITY_ID="$4"

if [ -z "$TEXT_CONTENT" ] || [ -z "$MAP_RUN_ID" ] || [ -z "$ENTITY_TYPE" ] || [ -z "$ENTITY_ID" ]; then
  echo "Usage: $0 \"text content\" MAP_RUN_ID ENTITY_TYPE ENTITY_ID"
  exit 1
fi

# Obtain thread-safe audio basename
audio_basename=$(mysql $MYSQL_ARGS -N --batch --skip-column-names -e "
  UPDATE audio_counter
  SET next_audio = LAST_INSERT_ID(next_audio + 1);
  SELECT LPAD(LAST_INSERT_ID(), 7, '0');
")
audio_basename="audio$audio_basename"
EXTENSION="wav"
OUTFILE="$AUDIOS_DIR/$audio_basename.$EXTENSION"
FILENAME_ONLY="$audio_basename.$EXTENSION"

echo "Generating txt->bark: $FILENAME_ONLY"
echo "Prompt (preview): ${TEXT_CONTENT:0:160}"
echo "voice=$VOICE_PRESET temperature=$TEMPERATURE"

# Build curl request
curl_args=(
  -s -X POST "$ZROK_URL/bark-generate-async"
  --form-string "text=$TEXT_CONTENT"
  -F "voice_preset=$VOICE_PRESET"
  -F "temperature=$TEMPERATURE"
)

CREATE_RESP=$(curl "${curl_args[@]}")
TASK_ID=$(echo "$CREATE_RESP" | jq -r '.task_id // empty')

if [ -z "$TASK_ID" ]; then
  echo "API Error on create: $CREATE_RESP"
  mysql $MYSQL_ARGS -e "
    UPDATE $ENTITY_TYPE SET regenerate_audios=0 WHERE id=$ENTITY_ID;
  "
  exit 1
fi

echo "Task created: $TASK_ID. Polling..."

SUCCESS=false
count=0
TMP_OUT="$OUTFILE.tmp"
rm -f "$TMP_OUT"

while [ $count -lt $MAX_POLL_ATTEMPTS ]; do
  sleep $POLL_INTERVAL
  count=$((count+1))

  POLL_HEADERS=$(mktemp)
  curl -s -D "$POLL_HEADERS" "$ZROK_URL/status/$TASK_ID" -o "$TMP_OUT"
  HTTP_STATUS=$(grep -E '^HTTP' "$POLL_HEADERS" | tail -1 | awk '{print $2}')
  MIME_TYPE=$(file --mime-type -b "$TMP_OUT" 2>/dev/null || echo "")

  echo "[poll $count] http=$HTTP_STATUS mime=$MIME_TYPE"

  if [ "$HTTP_STATUS" == "200" ] && echo "$MIME_TYPE" | grep -qi '^audio/'; then
    mv -f "$TMP_OUT" "$OUTFILE"
    SUCCESS=true
    rm -f "$POLL_HEADERS"
    break
  fi

  if [ "$HTTP_STATUS" == "500" ]; then
    echo "Server error:"
    sed -n '1,200p' "$TMP_OUT" || true
  fi

  rm -f "$POLL_HEADERS"
done

if [ "$SUCCESS" != "true" ]; then
  echo "Failed to generate Bark audio for entity $ENTITY_ID"
  mysql $MYSQL_ARGS -e "
    UPDATE $ENTITY_TYPE SET regenerate_audios=0 WHERE id=$ENTITY_ID;
  "
  rm -f "$TMP_OUT"
  exit 1
fi

# Optional validation
if command -v ffmpeg >/dev/null 2>&1; then
  ffmpeg -v error -i "$OUTFILE" -f null - 2>/dev/null || {
    echo "ffmpeg validation failed"
    exit 1
  }
fi

REL_OUT_PATH="$AUDIOS_DIR_REL/$FILENAME_ONLY"
SAFE_NAME=$(echo "$audio_basename" | sed "s/'/''/g")

AUDIO_ID=$(mysql $MYSQL_ARGS -N -e "
INSERT INTO audios
  (name, filename, entity_type, entity_id, map_run_id)
VALUES
  ('$SAFE_NAME', '$REL_OUT_PATH', '$ENTITY_TYPE', $ENTITY_ID, $MAP_RUN_ID);
SELECT LAST_INSERT_ID();
")

MAPPING_TABLE="audios_2_${ENTITY_TYPE}"
TABLE_EXISTS=$(mysql $MYSQL_ARGS -N -e "SHOW TABLES LIKE '$MAPPING_TABLE';")

if [ -n "$TABLE_EXISTS" ] && [ -n "$AUDIO_ID" ]; then
  mysql $MYSQL_ARGS -e "
    INSERT INTO $MAPPING_TABLE (from_id, to_id)
    VALUES ($AUDIO_ID, $ENTITY_ID);
  "
fi

mysql $MYSQL_ARGS -e "
  UPDATE $ENTITY_TYPE SET regenerate_audios=0 WHERE id=$ENTITY_ID;
"

echo "Done: inserted Bark audio id $AUDIO_ID -> $REL_OUT_PATH"
