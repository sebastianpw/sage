#!/bin/bash
# Usage:
# ./combine_images.sh background.jpg output.png char1.png,x1,y1,scale1 char2.png,x2,y2,scale2 ...

if [ "$#" -lt 3 ]; then
  echo "Usage: $0 background output char1.png,x,y,scale [char2.png,x,y,scale ...]"
  exit 1
fi

BACKGROUND="$1"
OUTPUT="$2"
shift 2

# Start building the convert command
CMD=("convert" "$BACKGROUND")

for ARG in "$@"; do
  IFS=',' read -r IMG X Y SCALE <<< "$ARG"
  
  if [ -z "$IMG" ] || [ -z "$X" ] || [ -z "$Y" ] || [ -z "$SCALE" ]; then
    echo "Invalid character parameter: $ARG"
    echo "Format: image.png,x,y,scale"
    exit 1
  fi

  CMD+=("(" "$IMG" "-resize" "${SCALE}%" ")" "-geometry" "+${X}+${Y}" "-composite")
done

CMD+=("$OUTPUT")

# Execute the command
"${CMD[@]}"
echo "Created $OUTPUT"



