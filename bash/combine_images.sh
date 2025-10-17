#!/bin/bash

# Usage check
if [ "$#" -lt 5 ]; then
  echo "Usage: $0 <output_file> <max_width> <max_height> <mode:1=side-by-side,2=top-to-bottom> <image1> <image2> [image3 ... imageN]"
  exit 1
fi

# Parameters
OUTPUT="$1"
MAX_WIDTH="$2"
MAX_HEIGHT="$3"
MODE="$4"
shift 4  # Remove first 4 parameters so $@ contains only input images

# Determine append mode
if [ "$MODE" -eq 1 ]; then
  APPEND_MODE="+append"   # Side by side
elif [ "$MODE" -eq 2 ]; then
  APPEND_MODE="-append"   # Top to bottom
else
  echo "Invalid mode: $MODE. Use 1 for side-by-side, 2 for top-to-bottom."
  exit 1
fi

# Combine images
TMP_OUTPUT="$(mktemp).jpg"
magick "$@" $APPEND_MODE "$TMP_OUTPUT"

# Resize to fit within max dimensions while preserving aspect ratio
magick "$TMP_OUTPUT" -resize "${MAX_WIDTH}x${MAX_HEIGHT}>" "$OUTPUT"

# Clean up temporary file
rm "$TMP_OUTPUT"

echo "Combined image saved as $OUTPUT (max ${MAX_WIDTH}x${MAX_HEIGHT})"


