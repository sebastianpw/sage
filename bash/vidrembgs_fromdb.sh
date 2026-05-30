#!/bin/bash

# ==============================================================================
# SAGE Video Rembg Batcher
# Scans 'derivates' table for items with regenerate_videos=1 and vid2vid=1
# ==============================================================================

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MYSQL_ARGS=$("$SCRIPT_DIR"/db_name.sh main-conn)

# 1. Fetch IDs to process
# We select ID from derivates where flags are set
SQL_QUERY="SELECT id FROM derivates WHERE regenerate_videos=1 AND vid2vid=1"

declare -a DERIVATE_IDS

while IFS=$'\t' read -r id; do
  DERIVATE_IDS+=("$id")
done < <(mysql $MYSQL_ARGS -N -e "$SQL_QUERY")

if [ ${#DERIVATE_IDS[@]} -eq 0 ]; then
    # Silent exit if nothing to do
    exit 0
fi

echo "Found ${#DERIVATE_IDS[@]} video(s) to process."

# 2. Process Loop
for id in "${DERIVATE_IDS[@]}"; do
    echo "--------------------------------------------------------"
    echo "Processing Derivate ID: $id"
    
    # Call the worker script
    "$SCRIPT_DIR/vidrembg_db.sh" "$id"
    
    # Check result
    if [ $? -eq 0 ]; then
        echo "Success."
    else
        echo "Failed."
    fi
done
