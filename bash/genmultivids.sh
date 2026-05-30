#!/bin/bash
# ==============================================================================
# SAGE MultiVid Batch Wrapper
# Usage: ./genmultivids.sh [batch_limit]
# Example: ./genmultivids.sh
# Example: ./genmultivids.sh 10
# ==============================================================================
# Finds queued MultiVid jobs and runs genmultivid.sh for each open row.
# A single map run is created for the whole batch and passed to the worker.
# ==============================================================================

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MYSQL_ARGS=$("$SCRIPT_DIR"/db_name.sh main-conn)
CLIENT_SCRIPT="$SCRIPT_DIR/genmultivid.sh"

BATCH_LIMIT="$1"

if [ ! -f "$CLIENT_SCRIPT" ]; then
    echo "ERROR: Client script not found at $CLIENT_SCRIPT"
    exit 1
fi

if [ -f "$SCRIPT_DIR/load_root.sh" ]; then
    source "$SCRIPT_DIR/load_root.sh"
fi

SQL_QUERY="SELECT id FROM multivid_render_jobs WHERE status='queued' ORDER BY id ASC"

if [ -n "$BATCH_LIMIT" ]; then
    if ! [[ "$BATCH_LIMIT" =~ ^[0-9]+$ ]]; then
        echo "Usage: $0 [batch_limit]"
        exit 1
    fi
    if [ "$BATCH_LIMIT" -gt 0 ]; then
        SQL_QUERY="$SQL_QUERY LIMIT $BATCH_LIMIT"
    fi
fi

declare -a JOB_IDS

while IFS=$'\t' read -r JOB_ID; do
    [ -n "$JOB_ID" ] && JOB_IDS+=("$JOB_ID")
done < <(mysql $MYSQL_ARGS -N -e "$SQL_QUERY")

TOTAL_COUNT=${#JOB_IDS[@]}

if [ "$TOTAL_COUNT" -eq 0 ]; then
    echo "No queued MultiVid jobs found."
    exit 0
fi

MAP_RUN_ID=$(mysql $MYSQL_ARGS -N -e "
    INSERT INTO map_runs (entity_type, note)
    VALUES ('animatics', 'Batch MultiVid Generation ($TOTAL_COUNT items)');
    SELECT LAST_INSERT_ID();
")

if [ -z "$MAP_RUN_ID" ]; then
    echo "ERROR: Failed to create map run."
    exit 1
fi

echo "--------------------------------------------------------"
echo "Starting MultiVid Batch Run"
echo "Map Run ID: $MAP_RUN_ID"
echo "Queued Jobs: $TOTAL_COUNT"
echo "--------------------------------------------------------"

SUCCESS_COUNT=0
FAIL_COUNT=0

for i in "${!JOB_IDS[@]}"; do
    JOB_ID="${JOB_IDS[$i]}"
    CURRENT_NUM=$((i + 1))

    echo ""
    echo "[$CURRENT_NUM / $TOTAL_COUNT] Processing job #$JOB_ID..."

    "$CLIENT_SCRIPT" "$MAP_RUN_ID" "$JOB_ID"
    CLIENT_EXIT_CODE=$?

    if [ $CLIENT_EXIT_CODE -eq 0 ]; then
        SUCCESS_COUNT=$((SUCCESS_COUNT + 1))
        echo "✓ Job #$JOB_ID completed."
    else
        FAIL_COUNT=$((FAIL_COUNT + 1))
        echo "✗ Job #$JOB_ID failed."
    fi
done

echo ""
echo "--------------------------------------------------------"
echo "Batch Run Complete."
echo "Map Run ID: $MAP_RUN_ID"
echo "Success: $SUCCESS_COUNT"
echo "Failed:  $FAIL_COUNT"
echo "--------------------------------------------------------"

exit 0
