#!/data/data/com.termux/files/usr/bin/bash

# Require directory argument
if [ -z "$1" ]; then
    echo "Usage: $0 <directory>"
    exit 1
fi

DIR="$1"

# Check if directory exists
if [ ! -d "$DIR" ]; then
    echo "Error: Directory '$DIR' does not exist."
    exit 1
fi

echo "Renaming files in reverse order in folder: $DIR"

# Get total number of jpg files
total=$(ls -1 "$DIR"/*.jpg 2>/dev/null | wc -l)
echo "Total frames: $total"

# Reverse order loop
n=1
for f in $(ls -1v "$DIR"/*.jpg 2>/dev/null); do
    newnum=$((total - n + 1))
    newname=$(printf "%s/frame%07d.jpg" "$DIR" "$newnum")
    if [ "$f" != "$newname" ]; then
        mv "$f" "$newname"
        echo "Renamed: $f -> $newname"
    fi
    n=$((n + 1))
done

echo "Reverse renaming done. $((n - 1)) images renamed."


