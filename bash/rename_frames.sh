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

echo "Renaming files sequentially in folder: $DIR"

n=1
for f in $(ls -1v "$DIR"/*.jpg 2>/dev/null); do
    newname=$(printf "%s/frame%07d.jpg" "$DIR" "$n")
    if [ "$f" != "$newname" ]; then
        mv "$f" "$newname"
        echo "Renamed: $f -> $newname"
    fi
    n=$((n + 1))
done

echo "Renaming done. $((n - 1)) images renamed."


