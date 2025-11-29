#!/bin/sh
# scripts/generate_thumbnail.sh

# This script generates a thumbnail from a video file.
# $1: The full path to the input video file.
# $2: The full path for the output thumbnail image.
# $3: The time offset (in seconds) to grab the thumbnail from.


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

FFMPEG=$(require_bin ffmpeg)

"$FFMPEG" -i "$1" -ss "$3" -vframes 1 -vf "scale=320:-1" "$2"
