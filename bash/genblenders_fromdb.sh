#!/bin/bash

# ==============================================================================
# SAGE Blender Queue Wrapper
# Iterates over pending jobs in 'motion_render_queue' and triggers the worker.
# Usage: ./genblenders_fromdb.sh [batch_limit]
# ==============================================================================

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MYSQL_ARGS=$("$SCRIPT_DIR"/db_name.sh main-conn)
CLIENT_SCRIPT="$SCRIPT_DIR/genblender_db.sh"

BATCH_LIMIT="$1"

if [ ! -f "$CLIENT_SCRIPT" ]; then
  echo "ERROR: Client script not found at $CLIENT_SCRIPT"
  exit 1
fi

# -----------------------------
# 1. Fetch Pending Jobs
# -----------------------------
SQL_QUERY="SELECT id, animatic_id FROM motion_render_queue WHERE status='pending' ORDER BY created_at ASC"

if [ -n "$BATCH_LIMIT" ] && [ "$BATCH_LIMIT" -gt 0 ]; then
  SQL_QUERY="$SQL_QUERY LIMIT $BATCH_LIMIT"
fi

declare -a JOB_IDS
declare -a ANIM_IDS

while IFS=$'\t' read -r jid aid; do
  JOB_IDS+=("$jid")
  ANIM_IDS+=("$aid")
done < <(mysql $MYSQL_ARGS -N -e "$SQL_QUERY")

TOTAL_COUNT=${#JOB_IDS[@]}

if [ "$TOTAL_COUNT" -eq 0 ]; then
  # No output if nothing to do, to keep cron logs clean
  exit 0
fi

echo "Found $TOTAL_COUNT Blender jobs pending."

# -----------------------------
# 2. Process Loop
# -----------------------------
for i in "${!JOB_IDS[@]}"; do
  JOB_ID="${JOB_IDS[$i]}"
  ANIM_ID="${ANIM_IDS[$i]}"
  CURRENT_NUM=$((i + 1))
  
  echo ""
  echo "[$CURRENT_NUM / $TOTAL_COUNT] Processing Queue ID #$JOB_ID (Animatic #$ANIM_ID)..."

  # A. Mark as Processing
  mysql $MYSQL_ARGS -e "UPDATE motion_render_queue SET status='processing', updated_at=NOW() WHERE id=$JOB_ID"

  # B. Run Worker
  "$CLIENT_SCRIPT" "$JOB_ID"
  EXIT_CODE=$?

  # C. Update Status based on result
  if [ $EXIT_CODE -eq 0 ]; then
      echo "✓ Job #$JOB_ID Completed Successfully."
      mysql $MYSQL_ARGS -e "UPDATE motion_render_queue SET status='completed', updated_at=NOW() WHERE id=$JOB_ID"
  else
      echo "✗ Job #$JOB_ID Failed."
      mysql $MYSQL_ARGS -e "UPDATE motion_render_queue SET status='failed', updated_at=NOW() WHERE id=$JOB_ID"
  fi

done

echo "--------------------------------------------------------"
echo "Batch Run Complete."
echo "--------------------------------------------------------"
