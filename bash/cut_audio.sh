#!/bin/sh
# bash/cut_audio.sh
# Minimal FFmpeg wrapper for cutting audio.
# Usage: sh cut_audio.sh <input> <start> <end> <output>

# --- 1. ROBUST SCRIPT PATH DETECTION ---
# (Logic taken from generate_thumbnail.sh)
if command -v realpath >/dev/null 2>&1; then
    SCRIPT_PATH="$(realpath "$0")"
else
    # Fallback for Termux/PHP-FPM where realpath might be missing
    case "$0" in
        /*) SCRIPT_PATH="$0" ;;
        "") SCRIPT_PATH="$(pwd)/UNKNOWN_SCRIPT" ;;
        *)  SCRIPT_PATH="$(pwd)/$0" ;;
    esac
fi
SCRIPT_DIR="$(cd "$(dirname "$SCRIPT_PATH")" && pwd)"

# --- 2. LOAD BINARY LOOKUP HELPER ---
if [ -f "$SCRIPT_DIR/bin_lookup.sh" ]; then
    . "$SCRIPT_DIR/bin_lookup.sh"
else
    echo "Error: bin_lookup.sh not found in $SCRIPT_DIR" >&2
    exit 1
fi

# --- 3. RESOLVE FFMPEG ---
FFMPEG=$(require_bin ffmpeg)

# --- 4. ARGS & EXECUTION ---
INPUT="$1"
START="$2"
END="$3"
OUTPUT="$4"

if [ -z "$INPUT" ] || [ -z "$OUTPUT" ]; then
    echo "Usage: $0 <input> <start> <end> <output>"
    exit 1
fi

# Execute Cut
# -y: Overwrite output file if it exists
# -v error: Silence output unless there is an error
# -ss: Start timestamp
# -to: End timestamp
"$FFMPEG" -y -v error -i "$INPUT" -ss "$START" -to "$END" "$OUTPUT"
