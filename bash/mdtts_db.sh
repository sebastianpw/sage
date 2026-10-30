#!/bin/bash
# mdtts_db.sh - Pollinations API TTS Worker with Smart AI Chunking (MP3 320k)

# ---------------------------------------------------------
# TTS & TEXT AI CONFIGURATION
# ---------------------------------------------------------
TTS_MODEL="hexgrad/kokoro-82m"
TTS_VOICE="af_heart"
TEXT_MODEL="deepseek/deepseek-v4.1-flash"
CURL_TIMEOUT=180      # Timeout for TTS Audio calls
TEXT_CURL_TIMEOUT=600 # Extended timeout for AI splitting 39k+ text
MAX_TTS_CHARS=8500
MAX_CHUNKS_GUARD=50   # Abort if chunking produces an absurd number of parts
MAX_RETRIES=3
# ---------------------------------------------------------

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MYSQL_ARGS=$("$SCRIPT_DIR"/db_name.sh main-conn)

if [ -f "$SCRIPT_DIR/load_root.sh" ]; then source "$SCRIPT_DIR/load_root.sh"; fi
if [ -z "$PROJECT_ROOT" ]; then PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"; fi

TTS_API_URL="https://gen.pollinations.ai/v1/audio/speech"
TEXT_API_URL="https://gen.pollinations.ai/v1/chat/completions"

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

if [ -z "$TEXT_CONTENT" ]; then echo "Empty text"; exit 1; fi

# Get original document name for temporary prefixes
ORIG_NAME=$(mysql $MYSQL_ARGS -N -e "SELECT name FROM documentations WHERE id=$ENTITY_ID LIMIT 1;" | tr -d '\r')
if [ -z "$ORIG_NAME" ] || [ "$ORIG_NAME" = "NULL" ]; then
    ORIG_NAME="Document_$ENTITY_ID"
fi

# ---------------------------------------------------------
# Helper: programmatic paragraph-aware splitter
# Reads full text on stdin, writes NUL-delimited chunks to stdout.
# Splits on blank lines (paragraphs) so no individual chunk exceeds max_len.
# ---------------------------------------------------------
split_text_fallback() {
    python3 -c '
import sys, re
text = sys.stdin.read().strip()
max_len = int(sys.argv[1])
# Split into paragraphs: blank line separates paragraphs.
paragraphs = [p.strip() for p in re.split(r"\n\s*\n", text) if p.strip()]
chunks = []
current = ""
for p in paragraphs:
    # A single paragraph longer than max_len must be hard-split on sentence
    # boundaries (or whitespace) so it does not become an oversized chunk.
    if len(p) > max_len:
        if current:
            chunks.append(current.strip())
            current = ""
        # sentence-ish split
        sentences = re.split(r"(?<=[.!?])\s+", p)
        for s in sentences:
            if len(current) + len(s) + 1 <= max_len:
                current = (current + " " + s).strip() if current else s
            else:
                if current:
                    chunks.append(current.strip())
                # If a single sentence is STILL too long, hard-wrap it.
                while len(s) > max_len:
                    cut = s.rfind(" ", 0, max_len)
                    if cut <= 0:
                        cut = max_len
                    chunks.append(s[:cut].strip())
                    s = s[cut:].strip()
                current = s
        continue
    if len(current) + len(p) + 2 <= max_len:
        current = (current + "\n\n" + p) if current else p
    else:
        if current:
            chunks.append(current.strip())
        current = p
if current:
    chunks.append(current.strip())
for c in chunks:
    sys.stdout.write(c)
    sys.stdout.write("\0")
' "$MAX_TTS_CHARS"
}

# ---------------------------------------------------------
# STEP 1: Smart AI Chunking
# ---------------------------------------------------------
TEXT_LEN=${#TEXT_CONTENT}
declare -a CHUNKS

if [ "$TEXT_LEN" -gt "$MAX_TTS_CHARS" ]; then
    echo "   [DocWorker] Text is $TEXT_LEN chars (exceeds $MAX_TTS_CHARS limit)."
    echo "   [DocWorker] Asking AI ($TEXT_MODEL) to gracefully insert ===SPLIT=== markers..."

    AI_JSON=$(mktemp)
    jq -n \
      --arg m "$TEXT_MODEL" \
      --arg i "$TEXT_CONTENT" \
      '{
        model: $m,
        messages: [
          {
            role: "system",
            content: "You are a precise text splitting tool. The user provides a long text. Your ONLY job is to return the EXACT same text, but insert the exact string \"===SPLIT===\" at logical narrative breaks (e.g., between paragraphs) so that no individual section exceeds 8000 characters. DO NOT summarize. DO NOT alter the original text. DO NOT add introductory or concluding remarks. Just output the text with the split markers. Please use as little splits as possible to stay within limit and also stays consistent regarding narrative structure."
          },
          {role: "user", content: $i}
        ]
      }' > "$AI_JSON"

    if [ -n "$PAI_TOKEN" ]; then
        AI_RESP=$(curl -m "$TEXT_CURL_TIMEOUT" -s -X POST "$TEXT_API_URL" -H "Authorization: Bearer $PAI_TOKEN" -H "Content-Type: application/json" -d @"$AI_JSON")
    else
        AI_RESP=$(curl -m "$TEXT_CURL_TIMEOUT" -s -X POST "$TEXT_API_URL" -H "Content-Type: application/json" -d @"$AI_JSON")
    fi
    rm -f "$AI_JSON"

    SPLIT_TEXT=$(echo "$AI_RESP" | jq -r '.choices[0].message.content // empty')

    # If the AI request timed out or returned empty, gracefully fallback to a
    # standard programmatic paragraph-aware split.
    if [ -z "$SPLIT_TEXT" ]; then
        echo "   [DocWorker] ⚠️ AI returned empty (possible timeout). Falling back to safe programmatic narrative split..."
        mapfile -t -d '' CHUNKS < <(split_text_fallback <<< "$TEXT_CONTENT")
    # If the AI returned the text but ignored the instruction to insert ===SPLIT===
    elif [[ "$SPLIT_TEXT" != *"===SPLIT==="* && ${#SPLIT_TEXT} -gt $MAX_TTS_CHARS ]]; then
        echo "   [DocWorker] ⚠️ AI forgot to insert ===SPLIT=== markers! Forcing safe programmatic split..."
        mapfile -t -d '' CHUNKS < <(split_text_fallback <<< "$TEXT_CONTENT")
    else
        # Parse the split text into our bash array using NUL-delimited records.
        # NOTE: must be NUL-delimited because each chunk contains embedded
        # newlines. A plain `mapfile -t` on newline-separated output would
        # explode one paragraph-rich chunk into hundreds of bogus entries.
        mapfile -t -d '' CHUNKS < <(python3 -c '
import sys
text = sys.stdin.read()
for c in text.split("===SPLIT==="):
    c = c.strip()
    if c:
        sys.stdout.write(c)
        sys.stdout.write("\0")
' <<< "$SPLIT_TEXT")
    fi
else
    # No splitting required
    CHUNKS=("$TEXT_CONTENT")
fi

TOTAL_CHUNKS=${#CHUNKS[@]}
echo "   [DocWorker] Process will be executed in $TOTAL_CHUNKS part(s)."

# ---------------------------------------------------------
# Sanity guard: if chunking produced an absurd number of parts, abort.
# This is a strong indicator that something upstream is misbehaving.
# ---------------------------------------------------------
if [ "$TOTAL_CHUNKS" -gt "$MAX_CHUNKS_GUARD" ]; then
    echo "   [DocWorker] ❌ Chunking produced $TOTAL_CHUNKS parts (guard=$MAX_CHUNKS_GUARD). Aborting." >&2
    exit 1
fi

# ---------------------------------------------------------
# STEP 2: Process TTS for each chunk
# ---------------------------------------------------------
declare -a MERGE_LIST
declare -a TEMP_DOC_IDS
declare -a TEMP_AUDIO_IDS

for i in "${!CHUNKS[@]}"; do
    CHUNK_IDX=$((i+1))
    CHUNK_TEXT="${CHUNKS[$i]}"

    p_basename=$(mysql $MYSQL_ARGS -N --batch --skip-column-names -e "UPDATE audio_counter SET next_audio = LAST_INSERT_ID(next_audio + 1); SELECT LPAD(LAST_INSERT_ID(), 7, '0');")
    P_FILENAME_ONLY="audio${p_basename}.mp3"
    P_OUTFILE="$AUDIO_DIR/$P_FILENAME_ONLY"
    P_TMP_WAV="${P_OUTFILE}.wav"

    # Handle Temp DB insertions for multi-part processing
    if [ "$TOTAL_CHUNKS" -gt 1 ]; then
        TEMP_NAME="pt${CHUNK_IDX}_${TOTAL_CHUNKS}_${ORIG_NAME}"
        echo "   [DocWorker] Creating temp doc: $TEMP_NAME"

        SAFE_TEMP_NAME=$(echo "$TEMP_NAME" | sed "s/'/''/g")
        SAFE_CHUNK_TEXT=$(echo "$CHUNK_TEXT" | sed "s/'/''/g")

        T_DOC_ID=$(mysql $MYSQL_ARGS -N -e "INSERT INTO documentations (name, content) VALUES ('$SAFE_TEMP_NAME', '$SAFE_CHUNK_TEXT'); SELECT LAST_INSERT_ID();")
        TEMP_DOC_IDS+=("$T_DOC_ID")

        TARGET_DOC_ID="$T_DOC_ID"
        SAFE_AUDIO_NAME="Doc_Audio_Temp_${T_DOC_ID}"
    else
        TARGET_DOC_ID="$ENTITY_ID"
        SAFE_AUDIO_NAME="Doc_Audio_${ENTITY_ID}"
    fi

    # Build JSON Request safely
    TTS_JSON=$(mktemp)
    jq -n \
      --arg m "$TTS_MODEL" \
      --arg in "$CHUNK_TEXT" \
      --arg v "$TTS_VOICE" \
      '{model: $m, input: $in, voice: $v, response_format: "wav"}' > "$TTS_JSON"

    # --- HTTP Request with 3-Attempt Retry Logic ---
    attempt=1
    success=false
    while [ $attempt -le $MAX_RETRIES ]; do
        echo "   [DocWorker] Submitting TTS Request for Part $CHUNK_IDX/$TOTAL_CHUNKS (Attempt $attempt)..."

        if [ -n "$PAI_TOKEN" ]; then
            HTTP_STATUS=$(curl -m "$CURL_TIMEOUT" -s -w "%{http_code}" -X POST "$TTS_API_URL" -H "Authorization: Bearer $PAI_TOKEN" -H "Content-Type: application/json" -d @"$TTS_JSON" -o "$P_TMP_WAV")
        else
            HTTP_STATUS=$(curl -m "$CURL_TIMEOUT" -s -w "%{http_code}" -X POST "$TTS_API_URL" -H "Content-Type: application/json" -d @"$TTS_JSON" -o "$P_TMP_WAV")
        fi

        if [ "$HTTP_STATUS" -eq 200 ]; then
            success=true
            break
        else
            echo "   [DocWorker] ⚠️ Attempt $attempt failed (HTTP $HTTP_STATUS)."
            attempt=$((attempt+1))
            if [ $attempt -le $MAX_RETRIES ]; then
                sleep 3 # Wait 3 seconds before trying again
            fi
        fi
    done

    if [ "$success" = false ]; then
        echo "   [DocWorker] ❌ Task Failed for Part $CHUNK_IDX after $MAX_RETRIES attempts."
        rm -f "$P_TMP_WAV" "$TTS_JSON"
        exit 1
    fi
    rm -f "$TTS_JSON"

    # FFmpeg encode to MP3
    echo "   [DocWorker] Converting Part $CHUNK_IDX to 320k MP3..."
    ffmpeg -y -i "$P_TMP_WAV" -codec:a libmp3lame -b:a 320k "$P_OUTFILE" </dev/null >/dev/null 2>&1

    if [ $? -ne 0 ]; then
        echo "   [DocWorker] ❌ FFmpeg failed on Part $CHUNK_IDX!"
        rm -f "$P_TMP_WAV" "$P_OUTFILE"
        exit 1
    fi
    rm -f "$P_TMP_WAV"

    # Save Temp / Final Audio to DB depending on chunking state
    T_AUDIO_ID=$(mysql $MYSQL_ARGS -N -e "INSERT INTO audios (filename, name, entity_type, entity_id, map_run_id) VALUES ('$AUDIO_DIR_REL/$P_FILENAME_ONLY', '$SAFE_AUDIO_NAME', '$ENTITY_TYPE', $TARGET_DOC_ID, $MAP_RUN_ID); SELECT LAST_INSERT_ID();")
    mysql $MYSQL_ARGS -e "INSERT INTO audios_2_documentations (from_id, to_id) VALUES ($T_AUDIO_ID, $TARGET_DOC_ID);"

    MERGE_LIST+=("$P_OUTFILE")
    if [ "$TOTAL_CHUNKS" -gt 1 ]; then
        TEMP_AUDIO_IDS+=("$T_AUDIO_ID")
    fi

    sleep 2
done

# ---------------------------------------------------------
# STEP 3: Merge and Finalize (if chunking occurred)
# ---------------------------------------------------------
if [ "$TOTAL_CHUNKS" -gt 1 ]; then
    echo "   [DocWorker] Merging $TOTAL_CHUNKS parts into final MP3..."

    f_basename=$(mysql $MYSQL_ARGS -N --batch --skip-column-names -e "UPDATE audio_counter SET next_audio = LAST_INSERT_ID(next_audio + 1); SELECT LPAD(LAST_INSERT_ID(), 7, '0');")
    F_FILENAME_ONLY="audio${f_basename}.mp3"
    FINAL_MP3="$AUDIO_DIR/$F_FILENAME_ONLY"

    # Build FFmpeg concat manifest
    CONCAT_FILE=$(mktemp)
    for mp3 in "${MERGE_LIST[@]}"; do
        echo "file '$mp3'" >> "$CONCAT_FILE"
    done

    # Concat files natively
    ffmpeg -y -f concat -safe 0 -i "$CONCAT_FILE" -c copy "$FINAL_MP3" </dev/null >/dev/null 2>&1
    rm -f "$CONCAT_FILE"

    # Insert Final File into DB attached to Original Entity
    SAFE_FINAL_NAME="Doc_Audio_${ENTITY_ID}"
    FINAL_AUDIO_ID=$(mysql $MYSQL_ARGS -N -e "INSERT INTO audios (filename, name, entity_type, entity_id, map_run_id) VALUES ('$AUDIO_DIR_REL/$F_FILENAME_ONLY', '$SAFE_FINAL_NAME', '$ENTITY_TYPE', $ENTITY_ID, $MAP_RUN_ID); SELECT LAST_INSERT_ID();")
    mysql $MYSQL_ARGS -e "INSERT INTO audios_2_documentations (from_id, to_id) VALUES ($FINAL_AUDIO_ID, $ENTITY_ID);"

    # ---------------------------------------------------------
    # STEP 4: Cleanup Temporary Database Rows & Files
    # ---------------------------------------------------------
    echo "   [DocWorker] Cleaning up temporary database records and files..."

    for t_doc in "${TEMP_DOC_IDS[@]}"; do
        mysql $MYSQL_ARGS -e "DELETE FROM documentations WHERE id=$t_doc;"
    done

    for t_aud in "${TEMP_AUDIO_IDS[@]}"; do
        mysql $MYSQL_ARGS -e "DELETE FROM audios_2_documentations WHERE from_id=$t_aud;"
        mysql $MYSQL_ARGS -e "DELETE FROM audios WHERE id=$t_aud;"
    done

    for tmp_mp3 in "${MERGE_LIST[@]}"; do
        rm -f "$tmp_mp3"
    done

    echo "   [DocWorker] Successfully generated and merged $F_FILENAME_ONLY"
else
    echo "   [DocWorker] Successfully generated $P_FILENAME_ONLY"
fi

mysql $MYSQL_ARGS -e "UPDATE documentations SET regenerate_audios=0 WHERE id=$ENTITY_ID;"