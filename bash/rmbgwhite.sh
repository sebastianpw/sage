#!/bin/bash
# Usage: ./rmbgwhite.sh <fuzz_percentage> <file_or_pattern ...>

if [ $# -lt 2 ]; then
    echo "Usage: $0 <fuzz_percentage> <file_or_pattern ...>"
    exit 1
fi

# First parameter is fuzz (required)
FUZZ="$1"

# Validate that it's an integer
if ! [[ "$FUZZ" =~ ^[0-9]+$ ]]; then
    echo "Error: fuzz_percentage must be an integer"
    exit 1
fi

# Remaining parameters are files / glob patterns
FILES=("${@:2}")

shopt -s nocaseglob  # case-insensitive file matching

for INPUT in "${FILES[@]}"; do
    # Skip if not a regular file
    [[ ! -f "$INPUT" ]] && continue

    # Only process image files
    [[ ! "$INPUT" =~ \.(jpg|jpeg|png|bmp|tiff|gif)$ ]] && { echo "Skipping non-image: $INPUT"; continue; }

    # Keep original filename, just change extension to .png
    OUTPUT="${INPUT%.*}.png"
    echo "Processing $INPUT -> $OUTPUT"

    convert "$INPUT" -fuzz ${FUZZ}% -transparent white "$OUTPUT"
done

echo "Done!"


