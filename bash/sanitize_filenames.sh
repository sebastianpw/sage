#!/bin/bash

# Check if a directory parameter was provided
if [ -z "$1" ]; then
    echo "Usage: $0 /path/to/directory"
    exit 1
fi

TARGET_DIR="$1"

# Check if the directory exists
if [ ! -d "$TARGET_DIR" ]; then
    echo "Error: Directory '$TARGET_DIR' does not exist."
    exit 1
fi

# Loop over all files in the directory
for FILE in "$TARGET_DIR"/*; do
    # Skip if it's not a file
    [ -f "$FILE" ] || continue

    # Get the filename without the path
    BASENAME=$(basename "$FILE")
    
    # Replace spaces with underscores
    NEWNAME="${BASENAME// /_}"

    # Only rename if the name actually changed
    if [ "$BASENAME" != "$NEWNAME" ]; then
        mv "$FILE" "$TARGET_DIR/$NEWNAME"
        echo "Renamed '$BASENAME' to '$NEWNAME'"
    fi
done



