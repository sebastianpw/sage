#!/bin/bash

# Usage: ./share_images.sh /path/to/directory

DIR="$1"

if [ -z "$DIR" ]; then
    echo "Usage: $0 /path/to/directory"
    exit 1
fi

if [ ! -d "$DIR" ]; then
    echo "Error: Directory '$DIR' does not exist."
    exit 1
fi

# Find all image files (common formats)
shopt -s nullglob
for img in "$DIR"/*.{png,jpeg,jpg,gif,bmp,tiff,webp}; do
    ext="${img##*.}"
    base="${img%.*}"
    
    if [ "$ext" != "jpg" ]; then
        # Convert to JPG using magick
        magick "$img" "$base.jpg"
        rm -f "$img"
        echo "Converted $img -> $base.jpg"
    else
        # Ensure extension is exactly .jpg
        mv -f "$img" "$base.jpg"
        echo "Renamed $img -> $base.jpg"
    fi
done

echo "All images in '$DIR' are now in .jpg format."
