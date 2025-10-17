#!/data/data/com.termux/files/usr/bin/bash

# Directory where your frames are located
SEARCH_DIR="/storage/emulated/0/Download"

# Directory where unique frames will be copied
OUTPUT_DIR="/storage/emulated/0/Download/unique_frames"

mkdir -p "$OUTPUT_DIR"

# Temporary file to store hashes
HASHLIST=$(mktemp)

# Counters
total=0
copied=0
skipped=0

echo "Scanning directory: $SEARCH_DIR (excluding FRAMESALL) ..."
echo "Unique frames will be copied to: $OUTPUT_DIR"
echo "--------------------------------------------"

# Traverse, find candidate frames, hash them, and copy unique ones
find "$SEARCH_DIR" -type f \( -iname "frame*.jpg" -o -iname "frame*.png" \) ! -path "$SEARCH_DIR/FRAMESALL/*" -print0 |
while IFS= read -r -d '' file; do
    total=$((total + 1))
    hash=$(sha1sum "$file" | awk '{print $1}')

    if grep -q "$hash" "$HASHLIST"; then
        echo "Skipping duplicate (same content): $file"
        skipped=$((skipped + 1))
    else
        # Not seen before, keep it
        echo "$hash" >> "$HASHLIST"

        # Determine target filename
        base=$(basename "$file")
        target="$OUTPUT_DIR/$base"

        # If filename exists, append first 8 chars of hash
        if [ -e "$target" ]; then
            ext="${base##*.}"
            name="${base%.*}"
            target="$OUTPUT_DIR/${name}_${hash:0:8}.$ext"
        fi

        echo "Copying unique frame: $file -> $target"
        cp "$file" "$target"
        copied=$((copied + 1))
    fi
done

echo "--------------------------------------------"
echo "Scan complete!"
echo "Total files scanned: $total"
echo "Unique frames copied: $copied"
echo "Duplicates skipped (same content): $skipped"
echo "All unique frames are in: $OUTPUT_DIR"
