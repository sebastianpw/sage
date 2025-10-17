#!/bin/bash
# rename_table_in_dump_sed.sh
# Usage: ./rename_table_in_dump_sed.sh <source_table> <target_table> [input.sql] [output.sql]

SRC="$1"
DST="$2"
INFILE="${3:-/dev/stdin}"
OUTFILE="${4:-/dev/stdout}"

if [ -z "$SRC" ] || [ -z "$DST" ]; then
  echo "Usage: $0 <source_table> <target_table> [input.sql] [output.sql]"
  exit 1
fi

# Make sure output directory exists
OUTDIR=$(dirname "$OUTFILE")
mkdir -p "$OUTDIR" 2>/dev/null

# Escape slashes for sed
ESC_SRC=$(printf '%s\n' "$SRC" | sed 's/[\/&]/\\&/g')
ESC_DST=$(printf '%s\n' "$DST" | sed 's/[\/&]/\\&/g')

# sed replacements:
# - DROP TABLE (with or without IF EXISTS)
# - CREATE TABLE
# - INSERT INTO
# - ALTER TABLE
# - REFERENCES
sed -E \
    -e "s/DROP TABLE( IF EXISTS)? \`$ESC_SRC\`/DROP TABLE\1 \`$ESC_DST\`/g" \
    -e "s/CREATE TABLE \`$ESC_SRC\`/CREATE TABLE \`$ESC_DST\`/g" \
    -e "s/INSERT INTO \`$ESC_SRC\`/INSERT INTO \`$ESC_DST\`/g" \
    -e "s/ALTER TABLE \`$ESC_SRC\`/ALTER TABLE \`$ESC_DST\`/g" \
    -e "s/REFERENCES \`$ESC_SRC\`/REFERENCES \`$ESC_DST\`/g" \
    "$INFILE" > "$OUTFILE"
