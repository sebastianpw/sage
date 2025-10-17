#!/bin/bash
# dump_code_safe.sh
# Usage: ./dump_code_safe.sh output_file script1 [script2 ...]

if [ $# -lt 2 ]; then
  echo "Usage: $0 output_file script1 [script2 ...]"
  exit 1
fi

OUTPUT="$1"
shift  # Remove first parameter, $@ now contains scripts
DOWNLOADS="$HOME/Download"

# Check if output file already exists
if [ -f "$OUTPUT" ]; then
  read -p "Warning: $OUTPUT exists! Overwrite? (y/N) " RESP
  case "$RESP" in
    [yY][eE][sS]|[yY])
      echo "Overwriting $OUTPUT..."
      ;;
    *)
      echo "Cancelled. $OUTPUT not overwritten."
      exit 0
      ;;
  esac
fi

# Convert remaining parameters to an array
SCRIPTS=("$@")

# Clear previous output
> "$OUTPUT"

# Loop through all scripts
for ((i=0; i<${#SCRIPTS[@]}; i++)); do
  FILE="${SCRIPTS[$i]}"

  # Add the script filename before its content
  echo "$FILE" >> "$OUTPUT"

  # Append script content
  cat "$FILE" >> "$OUTPUT"

  # Add 5 newlines with the 3rd containing next filename (if not last)
  if [ $i -lt $((${#SCRIPTS[@]}-1)) ]; then
    NEXT_FILE="${SCRIPTS[$((i+1))]}"
    echo -e "\n\n$NEXT_FILE\n\n\n" >> "$OUTPUT"
  fi
done

# Copy to Download folder
cp "$OUTPUT" "$DOWNLOADS/"

echo "Scripts dumped into $OUTPUT and copied to $DOWNLOADS"
