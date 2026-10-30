#!/bin/bash

# ==============================================================================
# SAGE Pollinations AI CLI
# Text, image, audio, video, 3D, embeddings, transcription, uploads and
# account ops via https://gen.pollinations.ai. Self-locating; runs from
# anywhere in the project.
# Usage: ./pai.sh <command> [args...]
#   ./pai.sh help
# ==============================================================================

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Load optional project root helper (defines PROJECT_ROOT, FRAMES_ROOT, ...)
if [ -f "$SCRIPT_DIR/load_root.sh" ]; then
    source "$SCRIPT_DIR/load_root.sh"
fi
[ -z "$PROJECT_ROOT" ] && PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

# DB connection args — loaded once, available for future subcommands.
# pai.sh itself does not currently touch the database.
MYSQL_ARGS=""
if [ -x "$SCRIPT_DIR/db_name.sh" ]; then
    MYSQL_ARGS=$("$SCRIPT_DIR/db_name.sh" main-conn 2>/dev/null || true)
fi

# ------------------------------------------------------------------------------
# Tokens (same convention as genworker_queue.sh)
# ------------------------------------------------------------------------------
TOKEN_FILE="$SCRIPT_DIR/../token/.pollinationsaitoken"
PAI_TOKEN="${POLLINATIONS_API_KEY:-}"
if [ -z "$PAI_TOKEN" ] && [ -r "$TOKEN_FILE" ]; then
    PAI_TOKEN=$(cat "$TOKEN_FILE")
fi
export PAI_TOKEN

FREEIMAGE_KEY=""
if [ -r "$SCRIPT_DIR/../token/.freeimage_key" ]; then
    FREEIMAGE_KEY=$(cat "$SCRIPT_DIR/../token/.freeimage_key")
fi
export FREEIMAGE_KEY

# ------------------------------------------------------------------------------
# Constants
# ------------------------------------------------------------------------------
BASE="https://gen.pollinations.ai"
MEDIA="https://media.pollinations.ai"

CURL_TIMEOUT="${CURL_TIMEOUT:-120}"
GEN_TIMEOUT="${GEN_TIMEOUT:-300}"
STREAM_TIMEOUT="${STREAM_TIMEOUT:-600}"
VIDEO_TIMEOUT="${VIDEO_TIMEOUT:-900}"

DEF_TEXT="openai"
DEF_IMG="black-forest-labs/flux.1-schnell"
DEF_AUD="openai/tts-1"
DEF_VOICE="nova"

AUTH=()
[ -n "$PAI_TOKEN" ] && AUTH=(-H "Authorization: Bearer $PAI_TOKEN")

# ------------------------------------------------------------------------------
# Helpers
# ------------------------------------------------------------------------------
die() { echo "pai.sh: $*" >&2; exit 1; }

need() {
    command -v "$1" >/dev/null 2>&1 || die "missing dependency: $1 (pkg install $1)"
}
need curl
need jq

have_key() { [ -n "$PAI_TOKEN" ]; }

urlenc() { printf '%s' "$1" | jq -sRr @uri; }

# SSE token parser — reads stdin, outputs concatenated token deltas.
sse_tokens() {
    jq -R --unbuffered -jr '
        select(startswith("data:")) |
        sub("^data: ?"; "") |
        select(. != "[DONE]") |
        fromjson? |
        .choices[0].delta.content // empty
    '
}

# ------------------------------------------------------------------------------
# Helper: Upload Image Chain (Pollinations -> Freeimage -> Litterbox)
# ------------------------------------------------------------------------------
upload_image_chain() {
    local filepath="$1"
    local final_url=""

    # 1. Pollinations
    local polli_resp
    polli_resp=$(curl -m "$CURL_TIMEOUT" -s -X POST "$MEDIA/upload" \
        -H "Authorization: Bearer $PAI_TOKEN" \
        -F "file=@$filepath")
    if [[ "$polli_resp" == http* ]]; then
        final_url="$polli_resp"
    else
        local polli_url
        polli_url=$(echo "$polli_resp" | jq -r '.url // empty' 2>/dev/null)
        if [[ "$polli_url" == http* ]]; then
            final_url="$polli_url"
        fi
    fi

    # 2. Freeimage
    if [ -z "$final_url" ] || [ "$final_url" == "null" ]; then
        local fi_resp fi_url
        fi_resp=$(curl -m "$CURL_TIMEOUT" -s -X POST "https://freeimage.host/api/1/upload" \
            -F "key=$FREEIMAGE_KEY" -F "action=upload" \
            -F "source=@$filepath" -F "format=json")
        fi_url=$(echo "$fi_resp" | jq -r '.image.url' 2>/dev/null)
        if [ -n "$fi_url" ] && [ "$fi_url" != "null" ]; then
            final_url="$fi_url"
        fi
    fi

    # 3. Litterbox
    if [ -z "$final_url" ] || [ "$final_url" == "null" ]; then
        local lb_resp
        lb_resp=$(curl -m "$CURL_TIMEOUT" -s \
            -F "reqtype=fileupload" -F "time=1h" -F "fileToUpload=@$filepath" \
            https://litterbox.catbox.moe/resources/internals/api.php)
        if [[ "$lb_resp" == http* ]]; then
            final_url="$lb_resp"
        fi
    fi

    echo "$final_url"
}

# Resolve a reference argument: pass URLs through, upload local files.
resolve_ref() {
    local ref="$1"
    [ -z "$ref" ] && { echo ""; return; }
    if [[ "$ref" == http://* || "$ref" == https://* ]]; then
        echo "$ref"; return
    fi
    if [ -f "$ref" ]; then
        echo "Uploading reference: $ref" >&2
        local url
        url=$(upload_image_chain "$ref")
        if [ -z "$url" ] || [ "$url" = "null" ]; then
            die "failed to upload reference: $ref"
        fi
        echo "$url"; return
    fi
    die "reference is neither a URL nor an existing file: $ref"
}

# ------------------------------------------------------------------------------
# Extension-based text detection for site deploy
# ------------------------------------------------------------------------------
_is_text() {
    case "${1,,}" in
        *.html|*.htm|*.css|*.js|*.mjs|*.cjs|*.jsx|*.ts|*.tsx|*.json|*.xml|*.svg|\
        *.txt|*.md|*.csv|*.tsv|*.yml|*.yaml|*.map|*.webmanifest|*.rss|*.atom)
            return 0 ;;
        *)  return 1 ;;
    esac
}

# ============================================================
# chat
# ============================================================
cmd_chat() {
    local model="$DEF_TEXT" system="" temperature="" max_tokens="" stream=false json=false prompt=""
    while [ $# -gt 0 ]; do
        case "$1" in
            -m|--model)       model="$2"; shift 2;;
            -s|--system)      system="$2"; shift 2;;
            -t|--temperature) temperature="$2"; shift 2;;
            --max-tokens)     max_tokens="$2"; shift 2;;
            --stream)         stream=true; shift;;
            --json)           json=true; shift;;
            -h|--help)        echo "usage: pai.sh chat [-m model] [-s system] [--stream] [--json] <prompt>"; return;;
            -*)               die "chat: unknown flag $1";;
            *)                prompt="${prompt:+$prompt }$1"; shift;;
        esac
    done
    if [ -z "$prompt" ] && [ ! -t 0 ]; then prompt="$(cat)"; fi
    [ -z "$prompt" ] && die "chat: no prompt"

    local payload
    payload=$(jq -n \
        --arg model "$model" --arg prompt "$prompt" --arg system "$system" \
        --arg temp "$temperature" --arg max_tokens "$max_tokens" \
        --argjson stream "$stream" '
        {
            model: $model,
            messages: (
                (if $system != "" then [{role:"system", content:$system}] else [] end)
                + [{role:"user", content:$prompt}]
            )
        }
        + (if $temp       != "" then {temperature: ($temp|tonumber)}       else {} end)
        + (if $max_tokens != "" then {max_tokens:  ($max_tokens|tonumber)} else {} end)
        + (if $stream     then {stream: true} else {} end)')

    if $stream; then
        local rc=0
        curl -m "$STREAM_TIMEOUT" -N -sS --fail-with-body \
            -H "Content-Type: application/json" "${AUTH[@]}" \
            -d "$payload" "$BASE/v1/chat/completions" \
            | sse_tokens || rc=$?
        echo
        [ "$rc" -ne 0 ] && die "chat: streaming failed (curl exit $rc)"
    else
        local resp rc
        resp=$(curl -m "$CURL_TIMEOUT" -sS --fail-with-body \
            -H "Content-Type: application/json" "${AUTH[@]}" \
            -d "$payload" "$BASE/v1/chat/completions")
        rc=$?
        if [ "$rc" -ne 0 ]; then
            printf '%s\n' "$resp" | jq -r '.error.message // .' >&2 2>/dev/null \
                || printf '%s\n' "$resp" >&2
            die "chat: HTTP $rc"
        fi
        if $json; then
            printf '%s\n' "$resp" | jq .
        else
            printf '%s\n' "$resp" | jq -r '.choices[0].message.content // .error.message // "(empty)"'
        fi
    fi
}

# ============================================================
# image
# ============================================================
cmd_image() {
    local model="$DEF_IMG" out="" width="" height="" seed="" ref="" prompt=""
    while [ $# -gt 0 ]; do
        case "$1" in
            -m|--model)  model="$2"; shift 2;;
            -o|--out)    out="$2"; shift 2;;
            --width)     width="$2"; shift 2;;
            --height)    height="$2"; shift 2;;
            --seed)      seed="$2"; shift 2;;
            -i|--image)  ref="$2"; shift 2;;
            -h|--help)   echo "usage: pai.sh image [-m model] [-o out.png] [--width N] [--height N] [--seed N] [-i ref.png|url] <prompt>"; return;;
            -*)          die "image: unknown flag $1";;
            *)           prompt="${prompt:+$prompt }$1"; shift;;
        esac
    done
    [ -z "$prompt" ] && die "image: no prompt"

    # Resolve reference (uploads local file via chain)
    if [ -n "$ref" ]; then
        ref=$(resolve_ref "$ref")
    fi

    local qs="model=$(urlenc "$model")"
    [ -n "$width"  ] && qs="$qs&width=$width"
    [ -n "$height" ] && qs="$qs&height=$height"
    [ -n "$seed"   ] && qs="$qs&seed=$seed"
    [ -n "$ref"    ] && qs="$qs&image=$(urlenc "$ref")"

    local url="$BASE/image/$(urlenc "$prompt")?${qs}"
    [ -z "$out" ] && out="image_$(date +%s).png"

    echo "→ $url" >&2
    curl -m "$GEN_TIMEOUT" -sS --fail-with-body "${AUTH[@]}" "$url" -o "$out" \
        || die "image: download failed"
    echo "saved: $out" >&2
}

# ============================================================
# audio (TTS)
# ============================================================
cmd_audio() {
    local model="$DEF_AUD" voice="$DEF_VOICE" out="" format="mp3" text=""
    while [ $# -gt 0 ]; do
        case "$1" in
            -m|--model)  model="$2"; shift 2;;
            --voice)     voice="$2"; shift 2;;
            -o|--out)    out="$2"; shift 2;;
            --format)    format="$2"; shift 2;;
            -h|--help)   echo "usage: pai.sh audio [--voice nova] [-m model] [-o out.mp3] <text>"; return;;
            -*)          die "audio: unknown flag $1";;
            *)           text="${text:+$text }$1"; shift;;
        esac
    done
    [ -z "$text" ] && die "audio: no text"
    [ -z "$out"  ] && out="speech_$(date +%s).${format}"

    local payload
    payload=$(jq -n --arg m "$model" --arg i "$text" --arg v "$voice" --arg f "$format" \
        '{model:$m, input:$i, voice:$v, response_format:$f}')

    curl -m "$GEN_TIMEOUT" -sS --fail-with-body -X POST "$BASE/v1/audio/speech" \
        -H "Content-Type: application/json" "${AUTH[@]}" \
        -d "$payload" -o "$out" || die "audio: request failed"
    echo "saved: $out" >&2
}

# ============================================================
# video
# ============================================================
cmd_video() {
    local model="google/veo-3.1-fast" duration="4" out="" ref="" prompt=""
    while [ $# -gt 0 ]; do
        case "$1" in
            -m|--model)    model="$2"; shift 2;;
            --duration)    duration="$2"; shift 2;;
            -o|--out)      out="$2"; shift 2;;
            -i|--image)    ref="$2"; shift 2;;
            -h|--help)     echo "usage: pai.sh video [-m model] [--duration 4] [-i start.jpg|url] [-o out.mp4] <prompt>"; return;;
            -*)            die "video: unknown flag $1";;
            *)             prompt="${prompt:+$prompt }$1"; shift;;
        esac
    done
    [ -z "$prompt" ] && die "video: no prompt"
    [ -z "$out"    ] && out="video_$(date +%s).mp4"

    if [ -n "$ref" ]; then
        ref=$(resolve_ref "$ref")
    fi

    local qs="model=$(urlenc "$model")&duration=$duration"
    [ -n "$ref" ] && qs="$qs&image=$(urlenc "$ref")"
    local url="$BASE/video/$(urlenc "$prompt")?${qs}"

    echo "→ $url" >&2
    curl -m "$VIDEO_TIMEOUT" -sS --fail-with-body "${AUTH[@]}" "$url" -o "$out" \
        || die "video: download failed"
    echo "saved: $out" >&2
}

# ============================================================
# 3d
# ============================================================
cmd_3d() {
    local model="microsoft/trellis-2" resolution="low" image="" out="" prompt=""
    while [ $# -gt 0 ]; do
        case "$1" in
            -m|--model)    model="$2"; shift 2;;
            --resolution)  resolution="$2"; shift 2;;
            -i|--image)    image="$2"; shift 2;;
            -o|--out)      out="$2"; shift 2;;
            -h|--help)     echo "usage: pai.sh 3d [-m model] [--resolution low|medium|high] -i ref.jpg|url [-o out.glb]"; return;;
            -*)            die "3d: unknown flag $1";;
            *)             prompt="${prompt:+$prompt }$1"; shift;;
        esac
    done
    [ -z "$image" ] && [ -z "$prompt" ] && die "3d: need --image or a prompt"
    [ -z "$out"   ] && out="model_$(date +%s).glb"

    if [ -n "$image" ]; then
        image=$(resolve_ref "$image")
    fi

    local qs="model=$(urlenc "$model")&resolution=$resolution"
    [ -n "$image" ] && qs="$qs&image=$(urlenc "$image")"
    local url="$BASE/3d/$(urlenc "${prompt:-no_prompt}")?${qs}"

    echo "→ $url" >&2
    curl -m "$VIDEO_TIMEOUT" -sS --fail-with-body "${AUTH[@]}" "$url" -o "$out" \
        || die "3d: download failed"
    echo "saved: $out" >&2
}

# ============================================================
# embed
# ============================================================
cmd_embed() {
    local model="openai/text-embedding-3-small" dims="" input=""
    while [ $# -gt 0 ]; do
        case "$1" in
            -m|--model) model="$2"; shift 2;;
            --dims)     dims="$2"; shift 2;;
            -h|--help)  echo "usage: pai.sh embed [-m model] [--dims 512] <text>"; return;;
            -*)         die "embed: unknown flag $1";;
            *)          input="${input:+$input }$1"; shift;;
        esac
    done
    if [ -z "$input" ] && [ ! -t 0 ]; then input="$(cat)"; fi
    [ -z "$input" ] && die "embed: no input"

    local payload
    payload=$(jq -n --arg m "$model" --arg i "$input" --arg d "$dims" '
        {model:$m, input:$i} + (if $d != "" then {dimensions:($d|tonumber)} else {} end)')

    curl -m "$GEN_TIMEOUT" -sS --fail-with-body -X POST "$BASE/v1/embeddings" \
        -H "Content-Type: application/json" "${AUTH[@]}" \
        -d "$payload" \
    | jq '{model, usage, dims: (.data[0].embedding|length), preview: .data[0].embedding[0:4]}'
}

# ============================================================
# transcribe
# ============================================================
cmd_transcribe() {
    local file="" model="openai/whisper-large-v3"
    while [ $# -gt 0 ]; do
        case "$1" in
            -m|--model) model="$2"; shift 2;;
            -h|--help)  echo "usage: pai.sh transcribe [-m model] <audio.mp3>"; return;;
            -*)         die "transcribe: unknown flag $1";;
            *)          file="$1"; shift;;
        esac
    done
    [ -z "$file" ] && die "transcribe: no file"
    [ -r "$file" ] || die "transcribe: cannot read $file"

    curl -m "$GEN_TIMEOUT" -sS --fail-with-body -X POST "$BASE/v1/audio/transcriptions" \
        "${AUTH[@]}" \
        -F "file=@${file}" \
        -F "model=${model}" \
    | jq -r '.text // .'
}

# ============================================================
# models
# ============================================================
cmd_models() {
    local kind="text" query="" limit="" source=""
    while [ $# -gt 0 ]; do
        case "$1" in
            --type)     kind="$2"; shift 2;;
            -q|--query) query="$2"; shift 2;;
            --limit)    limit="$2"; shift 2;;
            --source)   source="$2"; shift 2;;
            -h|--help)  echo "usage: pai.sh models [--type text|image|audio|video|3d|embedding] [-q query] [--limit N] [--source official|community]"; return;;
            -*)         die "models: unknown flag $1";;
            *)          die "models: unexpected arg $1";;
        esac
    done

    local path
    case "$kind" in
        text)      path="text/models";;
        image)     path="image/models";;
        video)     path="video/models";;
        audio)     path="audio/models";;
        embedding) path="embeddings/models";;
        3d|three)  path="3d/models";;
        *)         path="models";;
    esac

    local qs=()
    [ -n "$query"  ] && qs+=("query=$(urlenc "$query")")
    [ -n "$limit"  ] && qs+=("limit=$limit")
    [ -n "$source" ] && qs+=("source=$source")
    local qstr=""
    [ ${#qs[@]} -gt 0 ] && qstr="?$(IFS='&'; echo "${qs[*]}")"

    curl -m "$CURL_TIMEOUT" -sS "$BASE/${path}${qstr}" \
    | jq -r '(.data // .)[] | "\(.id // .name)\t\(.category // .type // "")"' \
    | column -t -s $'\t' 2>/dev/null || true
}

# ============================================================
# balance
# ============================================================
cmd_balance() {
    have_key || die "balance: no API key (need token file or POLLINATIONS_API_KEY)"
    curl -m "$CURL_TIMEOUT" -sS --fail-with-body "${AUTH[@]}" \
        "$BASE/account/balance" | jq .
}

# ============================================================
# upload — Pollinations -> Freeimage -> Litterbox chain
# ============================================================
cmd_upload() {
    local file="${1:-}"
    [ -z "$file" ] && die "usage: pai.sh upload <file>"
    [ -r "$file" ] || die "upload: cannot read $file"

    local url
    url=$(upload_image_chain "$file")
    if [ -n "$url" ] && [ "$url" != "null" ]; then
        echo "$url"
    else
        die "upload: all providers failed"
    fi
}

# ============================================================
# site — deploy only (documented PUT endpoint)
#
#   pai.sh site deploy <name>                         # project-root/dist
#   pai.sh site deploy <name> <dir>                   # explicit folder
#   pai.sh site deploy <name> --file <path>           # single file
#   pai.sh site deploy <name> --file <path> --as <n>  # single file, renamed
# ============================================================
cmd_site() {
    local action="${1:-}"; shift || true
    case "$action" in
        deploy)
            local name="" dir="" single="" asname=""
            while [ $# -gt 0 ]; do
                case "$1" in
                    --file|-f) single="$2"; shift 2;;
                    --as)      asname="$2"; shift 2;;
                    -h|--help)
                        echo "usage: pai.sh site deploy <name> [dir]"
                        echo "       pai.sh site deploy <name> --file <path> [--as <remote-name>]"
                        return;;
                    -*)        die "site deploy: unknown flag $1";;
                    *)
                        if   [ -z "$name" ]; then name="$1"
                        elif [ -z "$dir"  ]; then dir="$1"
                        else die "site deploy: unexpected arg $1"
                        fi
                        shift;;
                esac
            done
            [ -n "$name" ] || die "usage: pai.sh site deploy <name> [dir] | <name> --file <path> [--as <remote-name>]"
            have_key || die "site deploy: needs an API key with 'machines' permission"

            # ------------------------------------------------------------------
            # Single-file mode
            # ------------------------------------------------------------------
            if [ -n "$single" ]; then
                [ -r "$single" ] || die "site deploy: cannot read $single"
                [ -f "$single" ] || die "site deploy: not a regular file: $single"

                [ -z "$asname" ] && asname="$(basename "$single")"

                if [ "$asname" != "index.html" ]; then
                    echo "WARNING: remote filename is '$asname', not 'index.html'." >&2
                    echo "         https://${name}.pollinations.app/ will 404 unless" >&2
                    echo "         index.html also exists. Use --as index.html to rename." >&2
                fi

                local payload
                payload=$(jq -Rs --arg f "$asname" \
                    '{files:[{file:$f, data:.}]}' "$single")

                echo "Deploying single file: $single → $asname" >&2
                curl -m "$GEN_TIMEOUT" -sS --fail-with-body \
                    -X PUT "$BASE/account/sites/${name}" \
                    -H "Authorization: Bearer $PAI_TOKEN" \
                    -H "Content-Type: application/json" \
                    --data-binary "$payload" | jq .
                return
            fi

            # ------------------------------------------------------------------
            # Folder mode (default: $PROJECT_ROOT/dist)
            # ------------------------------------------------------------------
            dir="${dir:-$PROJECT_ROOT/dist}"
            [ -d "$dir"            ] || die "site deploy: not a directory: $dir"
            [ -f "$dir/index.html" ] || die "site deploy: missing $dir/index.html"

            local files_json
            files_json=$(
                cd "$dir" || exit 1
                find . -type f -not -path './.*' -print0 \
                | while IFS= read -r -d '' f; do
                    f="${f#./}"
                    local enc="" data
                    if _is_text "$f"; then
                        data=$(jq -Rs . < "$f")
                    else
                        data=$(base64 -w0 "$f" | jq -Rs .)
                        enc="base64"
                    fi
                    jq -nc \
                        --arg file "$f" --argjson data "$data" --arg enc "$enc" \
                        '{file:$file, data:$data}
                         + (if $enc != "" then {encoding:$enc} else {} end)'
                done | jq -s '.'
            )
            [ "$files_json" = "[]" ] && die "site deploy: no files found in $dir"

            local payload
            payload=$(jq -n --argjson files "$files_json" '{files:$files}')

            echo "Deploying folder: $dir" >&2
            curl -m "$GEN_TIMEOUT" -sS --fail-with-body -X PUT "$BASE/account/sites/${name}" \
                -H "Authorization: Bearer $PAI_TOKEN" \
                -H "Content-Type: application/json" \
                --data-binary "$payload" | jq .
            ;;
        *)
            die "site: unknown action '$action' (use: deploy)"
            ;;
    esac
}

# ============================================================
# doctor / help / version
# ============================================================
cmd_doctor() {
    echo "script dir:      $SCRIPT_DIR"
    echo "project root:    $PROJECT_ROOT"
    echo "load_root.sh:    $([ -f "$SCRIPT_DIR/load_root.sh" ] && echo "present" || echo "not found (using fallback)")"
    echo "db_name.sh:      $([ -x "$SCRIPT_DIR/db_name.sh" ] && echo "present" || echo "not found")"
    echo "mysql args:      $([ -n "$MYSQL_ARGS" ] && echo "loaded" || echo "not loaded")"
    echo "token file:      $TOKEN_FILE"
    if have_key; then
        local masked="${PAI_TOKEN:0:7}…${PAI_TOKEN: -4}"
        local source="file"
        [ -n "${POLLINATIONS_API_KEY:-}" ] && source="env (POLLINATIONS_API_KEY)"
        echo "api key:         $masked (source: $source)"
    else
        echo "api key:         MISSING"
    fi
    echo "freeimage key:   $([ -n "$FREEIMAGE_KEY" ] && echo "loaded" || echo "not loaded (upload fallback skipped)")"
    echo "timeouts:        CURL=$CURL_TIMEOUT GEN=$GEN_TIMEOUT STREAM=$STREAM_TIMEOUT VIDEO=$VIDEO_TIMEOUT"
    for d in curl jq base64 column; do
        if command -v "$d" >/dev/null 2>&1; then
            echo "dep:             $d ✓"
        else
            echo "dep:             $d ✗ (pkg install $d)"
        fi
    done
}

cmd_help() {
    cat <<'EOF'
pai.sh — SAGE Pollinations AI CLI

usage: ./pai.sh <command> [args...]

commands:
  chat [-m model] [-s system] [--stream] [--json] <prompt>
        Send a message. Streams tokens with --stream. Reads prompt from stdin if piped.
  image [-m model] [-o out.png] [--width N] [--height N] [--seed N] [-i ref.png|url] <prompt>
        Generate or edit an image. -i accepts a local file (auto-uploaded) or URL.
  audio [--voice nova] [-m model] [-o out.mp3] <text>
        Text-to-speech. Voices: alloy echo fable onyx nova shimmer ash ballad coral sage verse ...
  video [-m model] [--duration 4] [-i start.jpg|url] [-o out.mp4] <prompt>
        Generate a video.
  3d [-m model] [--resolution low|medium|high] -i ref.jpg|url [-o out.glb]
        Generate a 3D model (GLB).
  embed [-m model] [--dims 512] <text>
        Create embeddings. Prints dimensions + preview.
  transcribe [-m model] <audio.mp3>
        Speech-to-text.
  upload <file>
        Upload via Pollinations -> Freeimage -> Litterbox chain. Prints URL.
  models [--type text|image|audio|video|3d|embedding] [-q query] [--limit N] [--source official|community]
        List models from the live catalog.
  balance
        Show account Pollen balance.
  site deploy <name> [dir]
  site deploy <name> --file <path> [--as <remote-name>]
        Deploy a static folder (default: <project root>/dist) or a single
        file to https://<name>.pollinations.app
  doctor
        Show script location, project root, tokens, and dependency status.
  help | version

env:
  POLLINATIONS_API_KEY   overrides the token file if set
  CURL_TIMEOUT           base timeout (default 120s)
  GEN_TIMEOUT            generation timeout (default 300s)
  STREAM_TIMEOUT         chat streaming timeout (default 600s)
  VIDEO_TIMEOUT          video / 3d timeout (default 900s)
EOF
}

cmd_version() { echo "pai.sh 0.2.1"; }

# ==============================================================================
# Dispatch
# ==============================================================================
main() {
    local cmd="${1:-help}"; shift || true
    case "$cmd" in
        chat|ask|c)          cmd_chat "$@";;
        image|img|i)         cmd_image "$@";;
        audio|tts|a)         cmd_audio "$@";;
        video|vid|v)         cmd_video "$@";;
        3d|model3d)          cmd_3d "$@";;
        embed|embeddings|e)  cmd_embed "$@";;
        transcribe|stt)      cmd_transcribe "$@";;
        upload|up)           cmd_upload "$@";;
        models|ls)           cmd_models "$@";;
        balance|bal)         cmd_balance "$@";;
        site)                cmd_site "$@";;
        doctor|diag)         cmd_doctor "$@";;
        version|-V|--version) cmd_version;;
        help|-h|--help)      cmd_help;;
        *)                   die "unknown command '$cmd' (run: pai.sh help)";;
    esac
}

main "$@"