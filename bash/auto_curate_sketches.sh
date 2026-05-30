#!/bin/bash

# -----------------------------
# Resolve script directory (key fix)
# -----------------------------
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Change to the public directory where the PHP script is located
cd "$SCRIPT_DIR/../public" || {
    echo "Error: Could not change to public directory" >&2
    exit 1
}

echo "Starting auto-curator loop in $(pwd)"
echo "Running 'php cli_auto_curator.php' every 45 seconds"
echo "Press Ctrl+C to stop"
echo "----------------------------------------"

while true; do
    php cli_auto_curator.php 5 && sleep 45
done
