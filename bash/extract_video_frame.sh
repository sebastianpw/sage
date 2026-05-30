#!/bin/sh
# bash/extract_video_frame.sh
# Extracts a single high-quality frame from a video at a specific timestamp.
# $1: Input video path
# $2: Output image path
# $3: Timestamp (seconds)

# Try realpath (if available)
if command -v realpath >/dev/null 2>&1; then
    SCRIPT_PATH="$(realpath "$0")"
else
    # Termux-safe fallback
    case "$0" in
        /*) SCRIPT_PATH="$0" ;;
        "") SCRIPT_PATH="$(pwd)/UNKNOWN_SCRIPT" ;;
        *) SCRIPT_PATH="$(pwd)/$0" ;;
    esac
fi

SCRIPT_DIR="$(cd "$(dirname "$SCRIPT_PATH")" && pwd)"
. "$SCRIPT_DIR/bin_lookup.sh"

FFMPEG=$(require_bin ffmpeg)

# Using -ss after -i for frame-accurate extraction (slower but precise)
# -q:v 2 ensures high quality
"$FFMPEG" -y -i "$1" -ss "$3" -vframes 1 -q:v 2 "$2"
