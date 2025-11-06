#!/bin/bash

# -----------------------------
# Resolve script directory
# -----------------------------
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# -----------------------------
# Load project roots (must set FRAMES_ROOT and PROJECT_ROOT)
# -----------------------------
if [ -f "$SCRIPT_DIR/load_root.sh" ]; then
  source "$SCRIPT_DIR/load_root.sh"
fi

if [ -z "$PROJECT_ROOT" ]; then
  echo "ERROR: PROJECT_ROOT is not set. Please ensure load_root.sh exports PROJECT_ROOT. Aborting."
  exit 1
fi

echo "Scanning $PROJECT_ROOT/src/"

php "$PROJECT_ROOT/public/analyze_code.php" "$PROJECT_ROOT/src/"

echo "Done."


