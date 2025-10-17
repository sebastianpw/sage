#!/bin/bash

folder="$1"

# Check if folder exists
if [ ! -d "$folder" ]; then
  echo "Error: Folder '$folder' does not exist."
  exit 1
fi

cd "$folder" || exit 1

# Enable case-insensitive matching and allow no-match to expand to nothing
shopt -s nocaseglob nullglob

# Loop over files matching frame???.jpg or frame???.jpeg
for file in frame???.jpg frame???.jpeg frame???.png; do
  [ -e "$file" ] || continue

  # Extract the numeric part and the extension
  num=$(echo "$file" | sed -E 's/frame([0-9]{3})\.(jpe?g|png)/\1/i')
  ext=$(echo "$file" | sed -E 's/.*\.([jJ][pP][eE]?[gG]|[pP][nN][gG])$/\1/')

  # Remove leading zeros to avoid octal interpretation
  num=$((10#$num))

  # Convert to 7-digit number
  newnum=$(printf "%07d" "$num")
  newfile="frame${newnum}.${ext,,}"  # convert extension to lowercase

  if [ "$file" != "$newfile" ]; then
    mv -v "$file" "$newfile"
  fi
done

shopt -u nocaseglob


