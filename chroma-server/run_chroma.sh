#!/bin/bash
# run-chroma.sh — start chroma inside the chroma-venv using nohup and write a pid file
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BASE_DIR="${BASE_DIR:-$SCRIPT_DIR/../chroma-server}"
VENV_DIR="${VENV_DIR:-$SCRIPT_DIR/../chroma-venv}"
PID_FILE="${PID_FILE:-$BASE_DIR/chroma.pid}"
LOG_FILE="${LOG_FILE:-$BASE_DIR/chroma.log}"
DB_PATH="${DB_PATH:-$BASE_DIR/chroma_db}"
HOST="${HOST:-0.0.0.0}"
PORT="${PORT:-8000}"

mkdir -p "$BASE_DIR"
cd "$BASE_DIR" || exit 1

# activate venv
if [ ! -f "$VENV_DIR/bin/activate" ]; then
  echo "ERROR: venv not found at $VENV_DIR" >&2
  exit 2
fi
# shellcheck disable=SC1090
source "$VENV_DIR/bin/activate"

# If already running, refuse to start another
if [ -f "$PID_FILE" ]; then
  if kill -0 "$(cat "$PID_FILE")" 2>/dev/null; then
    echo "Chroma already running (pid $(cat "$PID_FILE"))"
    exit 0
  else
    echo "Stale pidfile found, removing."
    rm -f "$PID_FILE"
  fi
fi

# Start chroma with nohup in background
nohup chroma run --path "$DB_PATH" --host "$HOST" --port "$PORT" >"$LOG_FILE" 2>&1 &
CHROMA_PID=$!
echo $CHROMA_PID > "$PID_FILE"
echo "Chroma started with pid $CHROMA_PID — logs: $LOG_FILE"
exit 0
