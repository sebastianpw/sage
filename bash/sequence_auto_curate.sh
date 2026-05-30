#!/bin/bash

# -----------------------------
# Resolve script directory
# -----------------------------
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

cd "$SCRIPT_DIR/../public" || {
    echo "Error: Could not change to public directory" >&2
    exit 1
}

echo "Starting sequence curator loop in $(pwd)"
echo "Running 'php cli_sequence_curator.php' every 45 seconds"
echo "Press Ctrl+C to stop"
echo "----------------------------------------"

while true; do
    php cli_sequence_curator.php 500 && sleep 45
done
