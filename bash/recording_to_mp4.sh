#!/bin/sh
# bash/recording_to_mp4.sh
#
# Usage:
#   sh bash/recording_to_mp4.sh /path/input.webm /path/output.mp4
#
# This follows the same invocation style as your thumbnail script:
#   sh 'script.sh' arg1 arg2 ...
#
# It is portable across Termux PHP-FPM and ordinary Linux shells.

if [ $# -lt 2 ]; then
    echo "Usage: $0 INPUT_WEBM OUTPUT_MP4" >&2
    exit 1
fi

INPUT="$1"
OUTPUT="$2"

# Try realpath (if available)
if command -v realpath >/dev/null 2>&1; then
    SCRIPT_PATH="$(realpath "$0")"
else
    case "$0" in
        /*) SCRIPT_PATH="$0" ;;
        "") SCRIPT_PATH="$(pwd)/UNKNOWN_SCRIPT" ;;
        *) SCRIPT_PATH="$(pwd)/$0" ;;
    esac
fi

SCRIPT_DIR="$(cd "$(dirname "$SCRIPT_PATH")" && pwd)"
. "$SCRIPT_DIR/bin_lookup.sh"

FFMPEG="$(require_bin ffmpeg)"

OUT_DIR="$(dirname "$OUTPUT")"
if [ ! -d "$OUT_DIR" ]; then
    mkdir -p "$OUT_DIR" || exit 1
fi

"$FFMPEG" -y \
    -i "$INPUT" \
    -c:v libx264 \
    -preset veryfast \
    -crf 20 \
    -pix_fmt yuv420p \
    -movflags +faststart \
    -c:a aac \
    -b:a 128k \
    "$OUTPUT"
