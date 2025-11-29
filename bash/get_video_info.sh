#!/bin/sh
# scripts/get_video_info.sh

# This script runs ffprobe to get video metadata in JSON format.
# $1: The full path to the input video file.


# Try realpath (if available)
if command -v realpath >/dev/null 2>&1; then
    SCRIPT_PATH="$(realpath "$0")"
else
    # Termux-safe fallback: use pwd if PHP-FPM erases $0
    case "$0" in
        /*) SCRIPT_PATH="$0" ;;
        "") SCRIPT_PATH="$(pwd)/UNKNOWN_SCRIPT" ;;  # PHP-FPM case
        *) SCRIPT_PATH="$(pwd)/$0" ;;
    esac
fi

SCRIPT_DIR="$(cd "$(dirname "$SCRIPT_PATH")" && pwd)"
. "$SCRIPT_DIR/bin_lookup.sh"

FFPROBE=$(require_bin ffprobe)

"$FFPROBE" -v quiet -print_format json -show_format -show_streams "$1"
