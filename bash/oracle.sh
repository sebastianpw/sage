#!/bin/bash

# Usage: ./unicode_divination.sh <number_of_characters>
# Example: ./unicode_divination.sh 5

if [ -z "$1" ]; then
  echo "Please provide the number of characters to draw."
  exit 1
fi

NUM_CHARS=$1
CSV_FILE="unicode_rows.csv"
HEX_DIGITS=(0 1 2 3 4 5 6 7 8 9 A B C D E F)

# Read CSV into array
IFS=',' read -r -a ROWS < "$CSV_FILE"

# Function to generate a random valid Unicode character
getRandomValidUnicodeCharacter() {
    local row
    local hex
    local char
    local codepoint

    while true; do
        # Random row
        row="${ROWS[RANDOM % ${#ROWS[@]}]}"
        # Random hex digit
        hex="${HEX_DIGITS[RANDOM % ${#HEX_DIGITS[@]}]}"
        # Construct codepoint (replace 'x' with hex digit)
        codepoint="${row//x/$hex}"
        # Convert to actual Unicode character
        char=$(printf "\U${codepoint:2}")
        # Validity check: character must not be empty
        if [ -n "$char" ]; then
            echo -n "$char"
            return
        fi
    done
}

# Draw n characters
for ((i=0; i<NUM_CHARS; i++)); do
    getRandomValidUnicodeCharacter
done

echo



