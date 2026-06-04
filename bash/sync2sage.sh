#!/bin/bash

# ==========================================
# Configuration
# ==========================================
DEST_DIR="$HOME/www/gitcodespaces/sage"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SRC_DIR="$SCRIPT_DIR/.."
CONFIG_FILE="$SCRIPT_DIR/sync_config.json"

# Check if jq is installed (required to read the JSON)
if ! command -v jq &> /dev/null; then
    echo "Error: 'jq' is not installed."
    echo "Please install it by running: sudo apt-get install jq"
    exit 1
fi

if [ ! -f "$CONFIG_FILE" ]; then
    echo "Error: Config file not found at $CONFIG_FILE"
    exit 1
fi

echo "=========================================="
echo " Starting Sync to Sage Staging"
echo "=========================================="

# Move into source directory so relative paths in JSON work perfectly
cd "$SRC_DIR" || exit 1

# 1. Parse the 'excludes' from JSON into an array for rsync
EXCLUDE_ARGS=()
while IFS= read -r exclude; do
    EXCLUDE_ARGS+=("--exclude=$exclude")
done < <(jq -r '.excludes[]' "$CONFIG_FILE")

# 2. Parse the 'paths' from JSON into an array
mapfile -t PATHS_TO_SYNC < <(jq -r '.paths[]' "$CONFIG_FILE")

# 3. Loop through each path and sync it
for path_pattern in "${PATHS_TO_SYNC[@]}"; do
    
    # Enable bash nullglob temporarily to safely handle wildcards like public/*.php
    shopt -s nullglob

    # Check if the path pattern contains a wildcard (*)
    if [[ "$path_pattern" == *"*"* ]]; then
        # Expand the wildcard into an array of actual files
        files=($path_pattern)
        if [ ${#files[@]} -gt 0 ]; then
            echo "Syncing files matching: $path_pattern"
            # -R (relative) ensures 'public/file.php' goes to 'sage/public/file.php'
            rsync -avR "${EXCLUDE_ARGS[@]}" "${files[@]}" "$DEST_DIR/"
        else
            echo "  -> Skipping: No files found for $path_pattern"
        fi
    else
        # It's a standard directory or single file (like .env.local)
        if [ -d "$path_pattern" ]; then
            echo "Syncing directory: $path_pattern/"
            # If it's a directory, we include --delete so it removes files in Sage 
            # that you deleted in your Dev environment.
            rsync -avR --delete "${EXCLUDE_ARGS[@]}" "$path_pattern/" "$DEST_DIR/"
        elif [ -f "$path_pattern" ]; then
            echo "Syncing file: $path_pattern"
            rsync -avR "${EXCLUDE_ARGS[@]}" "$path_pattern" "$DEST_DIR/"
        else
            echo "  -> Warning: Path does not exist in dev: $path_pattern"
        fi
    fi
    
    shopt -u nullglob # Turn off nullglob
done

echo "=========================================="
echo " Sync Complete!"
echo " Go to $DEST_DIR and run 'git status'"
echo "=========================================="
