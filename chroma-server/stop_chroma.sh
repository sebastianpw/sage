#!/bin/bash
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BASE_DIR="${BASE_DIR:-$SCRIPT_DIR/../chroma-server}"
PID_FILE="${PID_FILE:-$BASE_DIR/chroma.pid}"
LOG_FILE="${LOG_FILE:-$BASE_DIR/chroma.log}"

if [ ! -f "$PID_FILE" ]; then
  echo "No pidfile found at $PID_FILE — not running?"
  exit 0
fi

PID="$(cat "$PID_FILE")"
if kill -0 "$PID" 2>/dev/null; then
  echo "Stopping Chroma pid $PID..."
  kill "$PID"
  # give it a moment to die nicely
  for i in 1 2 3 4 5; do
    if kill -0 "$PID" 2>/dev/null; then
      sleep 1
    else
      break
    fi
  done
  # force kill if still alive
  if kill -0 "$PID" 2>/dev/null; then
    echo "PID still alive, sending SIGKILL..."
    kill -9 "$PID"
  fi
  echo "Stopped."
else
  echo "Process $PID not running; removing stale pidfile."
fi

rm -f "$PID_FILE"
exit 0
