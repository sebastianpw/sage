#!/bin/bash
# Usage: ./scandroid.sh MyFolder

FOLDER="$1"

# Check if parameter is provided
if [ -z "$FOLDER" ]; then
    echo "Usage: $0 <folder-name>"
    exit 1
fi

# Full path to folder
TARGET="/storage/emulated/0/Download/$FOLDER"

# Find all files and broadcast them in parallel on all CPU cores
find "$TARGET" -type f | xargs -P $(nproc) -I {} am broadcast -a android.intent.action.MEDIA_SCANNER_SCAN_FILE -d "file://{}"

echo "Media scan broadcast triggered for all files in: $TARGET"
	
