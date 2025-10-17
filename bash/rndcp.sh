#!/bin/bash

# Source folders
SRC_FOLDERS=(frames_collab_1 frames_collab_2 frames_collab_3 frames_collab_4 frames_collab_5 frames_collab_6)
# Destination folder
DEST_FOLDER="frames_collab"

# Create destination folder if it doesn't exist
mkdir -p "$DEST_FOLDER"

# Temporary file to hold all image paths
TMP_FILE=$(mktemp)

# Collect all image paths from all source folders
for folder in "${SRC_FOLDERS[@]}"; do
    find "$folder" -type f \( -iname "*.jpg" -o -iname "*.jpeg" -o -iname "*.png" -o -iname "*.gif" \) >> "$TMP_FILE"
done

# Shuffle the file paths and copy them with sequential frame names as JPEG
COUNTER=1
shuf "$TMP_FILE" | while read -r IMG; do
    FNAME=$(printf "frame%07d.jpg" "$COUNTER")
    # Convert any image to JPEG using ImageMagick v7
    magick "$IMG" "$DEST_FOLDER/$FNAME"
    ((COUNTER++))
done

# Cleanup
rm "$TMP_FILE"

echo "Done! Copied and converted $((COUNTER-1)) images to $DEST_FOLDER."


