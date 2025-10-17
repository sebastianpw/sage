#!/bin/bash

if [ -z "$1" ]; then
  echo "Usage: $0 <file.png | folder>"
  exit 1
fi

INPUT="$1"

convert_file() {
  local FILE="$1"
  BASENAME="${FILE%.*}"
  OUTPUT="${BASENAME}.jpg"
  echo "Converting $FILE -> $OUTPUT"
  ffmpeg -y -i "$FILE" -q:v 3 "$OUTPUT"
}

if [ -d "$INPUT" ]; then
  # It's a directory → loop through PNGs
  for FILE in "$INPUT"/*.png "$INPUT"/*.PNG; do
    [ -e "$FILE" ] || continue
    convert_file "$FILE"
  done
  echo "All PNGs converted in $INPUT"

elif [ -f "$INPUT" ]; then
  # It's a single file
  convert_file "$INPUT"

else
  echo "Error: $INPUT is neither a file nor a directory"
  exit 1
fi



