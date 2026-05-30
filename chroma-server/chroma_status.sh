#!/bin/bash
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BASE_DIR="${BASE_DIR:-$SCRIPT_DIR/../chroma-server}"
PID_FILE="${PID_FILE:-$BASE_DIR/chroma.pid}"
LOG_FILE="${LOG_FILE:-$BASE_DIR/chroma.log}"
if [ -f "$PID_FILE" ]; then
  PID="$(cat "$PID_FILE")"
  if kill -0 "$PID" 2>/dev/null; then
    echo "Chroma running (pid $PID). Logs: $LOG_FILE"
    ps -o pid,cmd -p "$PID"
    echo
    tail -n 30 "$LOG_FILE"
    exit 0
  else
    echo "Stale pidfile (pid $PID not running)."
    exit 1
  fi
else
  echo "Chroma not running (no pidfile)."
  exit 1
fi
