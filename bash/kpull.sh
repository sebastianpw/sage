#!/data/data/com.termux/files/usr/bin/bash
# kpull.sh — Pull a Kaggle notebook (and metadata) into the project’s notebooks/kaggle folder
# Compatible with SAGE project environment
# Author: Peter Sebring

# -----------------------------
# Resolve script directory
# -----------------------------
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# -----------------------------
# Load project roots (must define PROJECT_ROOT)
# -----------------------------
if [ -f "$SCRIPT_DIR/load_root.sh" ]; then
  source "$SCRIPT_DIR/load_root.sh"
else
  echo "❌ Missing load_root.sh — cannot resolve PROJECT_ROOT"
  exit 1
fi

if [ -z "$PROJECT_ROOT" ]; then
  echo "❌ PROJECT_ROOT not set."
  exit 1
fi

# -----------------------------
# Core variables
# -----------------------------
KAGGLE_BIN="$HOME/.local/bin/kaggle"
KAGGLE_NOTEBOOKS_FOLDER="$PROJECT_ROOT/notebooks/kaggle"

# -----------------------------
# Sanity checks
# -----------------------------
if [ ! -x "$KAGGLE_BIN" ]; then
  echo "❌ kaggle binary not found or not executable at $KAGGLE_BIN"
  exit 2
fi

NOTEBOOK="$1"
if [ -z "$NOTEBOOK" ]; then
  echo "Usage: $0 username/kernel-slug"
  exit 1
fi

# -----------------------------
# Target directory
# -----------------------------
BASENAME=$(basename "$NOTEBOOK")
TARGET_DIR="$KAGGLE_NOTEBOOKS_FOLDER/$BASENAME"

mkdir -p "$TARGET_DIR" || { echo "❌ Failed to create $TARGET_DIR"; exit 3; }
cd "$TARGET_DIR" || { echo "❌ Cannot cd to $TARGET_DIR"; exit 4; }

# -----------------------------
# Download notebook + metadata
# -----------------------------
echo "📘 Pulling $NOTEBOOK (notebook + metadata) into $TARGET_DIR ..."
"$KAGGLE_BIN" kernels pull "$NOTEBOOK" -p ./ --metadata

if [ $? -eq 0 ]; then
  echo "✅ Done — notebook and metadata stored in:"
  echo "   $TARGET_DIR"
else
  echo "⚠️ Download failed."
fi
