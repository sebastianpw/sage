#!/bin/bash

# ==============================================================================
# SAGE Video Rembg Batcher
# Scans 'video_enhancements' table for rows with regenerate_videos=1
# Results are mapped back to the original animatic via videos_2_animatics.
# ==============================================================================

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MYSQL_ARGS=$("$SCRIPT_DIR"/db_name.sh main-conn)

# 1. Create Map Run
MAP_RUN_ID=$(mysql $MYSQL_ARGS -N -e "
    INSERT INTO map_runs (entity_type, note)
    VALUES ('animatics', 'Video Background Removal (vidremgsbgs_fromdb.sh)');
    SELECT LAST_INSERT_ID();
")
echo "Created new map_run ID: $MAP_RUN_ID"

# 2. Fetch IDs to process
SQL_QUERY="SELECT id FROM video_enhancements WHERE regenerate_videos = 1"

declare -a ENHANCEMENT_IDS

while IFS=$'\t' read -r id; do
    ENHANCEMENT_IDS+=("$id")
done < <(mysql $MYSQL_ARGS -N -e "$SQL_QUERY")

if [ ${#ENHANCEMENT_IDS[@]} -eq 0 ]; then
    # Silent exit if nothing to do
    exit 0
fi

echo "Found ${#ENHANCEMENT_IDS[@]} video enhancement(s) to process."

# 3. Process Loop
for id in "${ENHANCEMENT_IDS[@]}"; do
    echo "--------------------------------------------------------"
    echo "Processing video_enhancements ID: $id"

    "$SCRIPT_DIR/vidremgsbg_db.sh" "$id" "$MAP_RUN_ID"

    if [ $? -eq 0 ]; then
        # Update active_map_run_id on success (mirrors genhances_fromdb.sh pattern)
        mysql $MYSQL_ARGS -e "
            UPDATE video_enhancements
            SET active_map_run_id = $MAP_RUN_ID
            WHERE id = $id
        "
        echo "Success."
    else
        echo "Failed."
    fi
done

echo "--------------------------------------------------------"
echo "Batch Run Complete."
echo "--------------------------------------------------------"
