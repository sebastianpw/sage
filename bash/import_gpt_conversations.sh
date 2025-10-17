#!/bin/bash
# import_gpt_conversations_final.sh
# Stream ChatGPT export and insert conversations + messages into DB
# Usage: ./import_gpt_conversations_final.sh [conversations.json] [BATCH_CONV_COUNT]

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
CONV_FILE="${1:-$SCRIPT_DIR/conversations.json}"
MAX_CONVS="${2:-0}"   # 0 = all
DB_ARGS="$($SCRIPT_DIR/db_name.sh sys-conn || true)"

if [ -z "$DB_ARGS" ]; then
  echo "Could not determine system DB connection arguments via db_name.sh sys-conn" >&2
  exit 1
fi

# check dependencies
for cmd in jq mysql sha1sum; do
  if ! command -v $cmd >/dev/null 2>&1; then
    echo "Please install $cmd" >&2
    exit 1
  fi
done

if [ ! -f "$CONV_FILE" ]; then
  echo "Conversations file not found at $CONV_FILE" >&2
  exit 1
fi

TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT
mkdir -p "$TMP_DIR/failed" "$TMP_DIR/bad_meta"

# SQL escape helper
sql_escape() {
  printf '%s' "$1" | sed "s/'/''/g"
}

to_sql_datetime() {
  local v="$1"
  if [[ -z "$v" || "$v" == "null" || "$v" == "0" ]]; then
    echo "NULL"
    return
  fi

  # strip quotes if jq added them
  v="${v%\"}"
  v="${v#\"}"

  local dt=""
  # integer seconds (10 digits)
  if [[ "$v" =~ ^[0-9]{10}$ ]]; then
    dt=$(date -u -d "@$v" '+%Y-%m-%d %H:%M:%S' 2>/dev/null)
  # milliseconds (13 digits)
  elif [[ "$v" =~ ^[0-9]{13}$ ]]; then
    dt=$(date -u -d "@$((v/1000))" '+%Y-%m-%d %H:%M:%S' 2>/dev/null)
  # floating-point UNIX timestamp
  elif [[ "$v" =~ ^[0-9]+\.[0-9]+$ ]]; then
    dt=$(date -u -d "@${v%%.*}" '+%Y-%m-%d %H:%M:%S' 2>/dev/null)
  else
    dt="$v"
  fi

  if [[ -z "$dt" ]]; then
    echo "NULL"
  else
    # ALWAYS quote the datetime
    echo "'$dt'"
  fi
}




# extract conversations into NDJSON
if [ "$MAX_CONVS" -gt 0 ]; then
  jq -c ".[0:$MAX_CONVS][]" "$CONV_FILE" > "$TMP_DIR/_conversations.ndjson"
else
  jq -c '.[]' "$CONV_FILE" > "$TMP_DIR/_conversations.ndjson"
fi

TOTAL=$(wc -l < "$TMP_DIR/_conversations.ndjson" | tr -d ' ')
echo "Streaming $TOTAL conversations for import..."

conv_count=0
imported_convs=0
imported_msgs=0

while IFS= read -r conv_line; do
  conv_count=$((conv_count+1))

  declared_id=$(printf '%s' "$conv_line" | jq -r '.id // .conversation_id // empty')
  external_id=${declared_id:-$(printf '%s' "$conv_line" | sha1sum | awk '{print $1}')}

  # skip if already exists
  if mysql $DB_ARGS -N -s -e "SELECT 1 FROM gpt_conversations WHERE external_id='$(sql_escape "$external_id")' LIMIT 1;" | grep -q 1; then
    echo "[$conv_count/$TOTAL] SKIP existing conversation $external_id"
    continue
  fi

  title=$(printf '%s' "$conv_line" | jq -r '.title // empty')
  created_at_raw=$(printf '%s' "$conv_line" | jq -r '.create_time // .created_at // .created // empty')
  updated_at_raw=$(printf '%s' "$conv_line" | jq -r '.update_time // .updated_at // .updated // empty')
  model=$(printf '%s' "$conv_line" | jq -r '.model // .default_model_slug // empty')
  meta_json=$(printf '%s' "$conv_line" | jq -c '.')

  if ! printf '%s' "$meta_json" | jq -e '.' >/dev/null 2>&1; then
    echo "[$conv_count/$TOTAL] ERROR: conversation meta invalid JSON -> saved to $TMP_DIR/bad_meta/$external_id.json"
    printf '%s' "$meta_json" > "$TMP_DIR/bad_meta/$external_id.json"
    continue
  fi

  created_q=$(to_sql_datetime "$created_at_raw")
  updated_q=$(to_sql_datetime "$updated_at_raw")

  # insert conversation
  SQL_CONV="$TMP_DIR/insert_conv_${external_id}.sql"
  cat > "$SQL_CONV" <<SQL
SET NAMES utf8mb4;
START TRANSACTION;
INSERT INTO gpt_conversations (external_id, title, created_at, updated_at, model, meta, message_count)
VALUES ('$(sql_escape "$external_id")', '$(sql_escape "$title")', $created_q, $updated_q, '$(sql_escape "$model")', '$(sql_escape "$meta_json")', 0);
COMMIT;
SQL

  if ! mysql $DB_ARGS < "$SQL_CONV"; then
    echo "[$conv_count/$TOTAL] ERROR inserting conversation $external_id -> saved SQL to $SQL_CONV"
    printf '%s' "$meta_json" > "$TMP_DIR/bad_meta/$external_id.json"
    continue
  fi
  imported_convs=$((imported_convs+1))
  echo "[$conv_count/$TOTAL] Imported conversation $external_id"

  # extract messages
  MSGS_FILE="$TMP_DIR/messages_${external_id}.ndjson"
  jq -c '
    def from_mapping:
      if (.mapping? and (.mapping | type=="object")) then (.mapping|to_entries|map(.value.message)|map(select(.!=null))) else [] end;
    def from_msgs:
      if (.messages? and (.messages|type=="array")) then .messages
      elif (.items? and (.items|type=="array")) then .items
      else [] end;
    (from_mapping + from_msgs)
    | map(. as $m | ($m.create_time // ($m.timestamp? // 0)) as $ct | $m + {create_time: $ct})
    | sort_by(.create_time)
    | .[]
  ' <<<"$conv_line" > "$MSGS_FILE" 2>/dev/null || true

  if [ ! -s "$MSGS_FILE" ]; then
    echo "[$conv_count/$TOTAL] No messages extracted for $external_id"
    continue
  fi

  # batch insert messages
  CHUNK_SIZE=400
  batch_values=""
  batch_count=0
  total_msgs_for_conv=0

  while IFS= read -r msg_line; do
    total_msgs_for_conv=$((total_msgs_for_conv+1))
    role=$(printf '%s' "$msg_line" | jq -r '.author.role // .role // "unknown"')
    content=$(printf '%s' "$msg_line" | jq -r '
      (.content? // .message? // {} ) as $c |
      (($c.parts? // $c.content? // []) | if type=="array" then join("\n") else tostring end // (.text? // .body? // ""))')
    content=$(printf '%s' "$content" | sed ':a;N;$!ba;s/\n/\\n/g')
    created_msg_raw=$(printf '%s' "$msg_line" | jq -r '.create_time // .timestamp // .created_at // empty')
    created_q_msg=$(to_sql_datetime "$created_msg_raw")
    model_msg=$(printf '%s' "$msg_line" | jq -r '.metadata.model_slug // .model // empty')
    raw_escaped=$(printf '%s' "$msg_line" | sed "s/'/''/g")

    batch_values="${batch_values}('$(sql_escape "$external_id")', ${total_msgs_for_conv}, '$(sql_escape "$role")', '$(sql_escape "$content")', '$(sql_escape "$content")', '${raw_escaped}', ${created_q_msg}, '$(sql_escape "$model_msg")'),"
    batch_count=$((batch_count+1))

    if [ "$batch_count" -ge "$CHUNK_SIZE" ]; then
      values_sql=${batch_values%,}
      SQL_FILE="$TMP_DIR/insert_msgs_${external_id}_${total_msgs_for_conv}.sql"
      cat > "$SQL_FILE" <<SQL
SET NAMES utf8mb4;
START TRANSACTION;
INSERT INTO gpt_messages (conversation_external_id, message_index, role, content, content_text, raw_json, created_at, model)
VALUES ${values_sql};
COMMIT;
SQL
      if ! mysql $DB_ARGS < "$SQL_FILE"; then
        echo "[$conv_count/$TOTAL] ERROR inserting messages chunk ending at index ${total_msgs_for_conv} for $external_id"
        cp "$SQL_FILE" "$TMP_DIR/failed/failed_msgs_${external_id}_${total_msgs_for_conv}.sql"
        break
      fi
      imported_msgs=$((imported_msgs + batch_count))
      batch_values=""; batch_count=0
    fi
  done < "$MSGS_FILE"

  # flush remaining
  if [ -n "$batch_values" ]; then
    values_sql=${batch_values%,}
    SQL_FILE="$TMP_DIR/insert_msgs_${external_id}_final.sql"
    cat > "$SQL_FILE" <<SQL
SET NAMES utf8mb4;
START TRANSACTION;
INSERT INTO gpt_messages (conversation_external_id, message_index, role, content, content_text, raw_json, created_at, model)
VALUES ${values_sql};
COMMIT;
SQL
    if mysql $DB_ARGS < "$SQL_FILE"; then
      imported_msgs=$((imported_msgs + batch_count))
    else
      echo "[$conv_count/$TOTAL] ERROR inserting final messages batch for $external_id"
      cp "$SQL_FILE" "$TMP_DIR/failed/failed_msgs_${external_id}_final.sql"
    fi
  fi

  # update conversation message count
  mysql $DB_ARGS -e "UPDATE gpt_conversations 
                     SET message_count = (SELECT COUNT(*) 
                                          FROM gpt_messages 
                                          WHERE conversation_external_id='$(sql_escape "$external_id")') 
                     WHERE external_id='$(sql_escape "$external_id")';"

  echo "[$conv_count/$TOTAL] Inserted $total_msgs_for_conv messages for $external_id"

done < "$TMP_DIR/_conversations.ndjson"

echo "Import complete. Conversations processed: $conv_count, imported: $imported_convs, messages total: $imported_msgs"
