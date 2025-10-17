#!/bin/bash

# Run rec.sh, print all output live, and capture the last line (filename)
FILENAME=$(./rec.sh | tee /dev/tty | tail -n 1)

if [ ! -f "$FILENAME" ]; then
  echo "Error: Recording file not found: $FILENAME"
  exit 1
fi

./transcribe.sh "$FILENAME"


