#!/bin/bash
# switchenv.sh
# Usage: ./switchenv.sh <suffix>
# Example: ./switchenv.sh nu
# Example: ./switchenv.sh bday_draft

# Get the directory where this script is located (should be in public/)
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Define absolute paths
ENV_FILE="$SCRIPT_DIR/../.env.local"
LOAD_ROOT_FILE="$SCRIPT_DIR/../load_root.share"

# Check if suffix parameter was provided
if [ -z "$1" ]; then
    echo "ERROR: No suffix provided"
    echo "Usage: $0 <suffix>"
    echo "Example: $0 nu"
    echo "Example: $0 bday_draft"
    exit 1
fi

SUFFIX="$1"

# Validate that the files exist
if [ ! -f "$ENV_FILE" ]; then
    echo "ERROR: .env.local not found at $ENV_FILE"
    exit 1
fi

if [ ! -f "$LOAD_ROOT_FILE" ]; then
    echo "ERROR: load_root.share not found at $LOAD_ROOT_FILE"
    exit 1
fi

echo "Starting switch to suffix: $SUFFIX"
echo "----------------------------------------"

# Update .env.local
# Replace database name in DATABASE_URL line
echo "Updating $ENV_FILE..."
sed -i.bak "s|/starlight_guardians_[^?]*|/starlight_guardians_${SUFFIX}|g" "$ENV_FILE"

if [ $? -eq 0 ]; then
    echo "✓ .env.local updated successfully"
else
    echo "✗ ERROR: Failed to update .env.local"
    exit 1
fi

# Update load_root.share
# Replace frames folder in FRAMES_ROOT line
echo "Updating $LOAD_ROOT_FILE..."
sed -i.bak "s|frames_starlightguardians_[^/]*$|frames_starlightguardians_${SUFFIX}|g" "$LOAD_ROOT_FILE"

if [ $? -eq 0 ]; then
    echo "✓ load_root.share updated successfully"
else
    echo "✗ ERROR: Failed to update load_root.share"
    exit 1
fi

echo "----------------------------------------"
echo "Switch completed successfully!"
echo ""
echo "Updated to:"
grep "DATABASE_URL" "$ENV_FILE"
grep "FRAMES_ROOT" "$LOAD_ROOT_FILE"
echo ""
echo "Restarting scheduler..."

# Stop and restart scheduler (no need to restart MariaDB/NGINX)
php /data/data/com.termux/files/home/www/spwbase/public/scheduler_stop.php
sleep 2
php /data/data/com.termux/files/home/www/spwbase/public/scheduler_start.php &

echo "✓ Scheduler restarted"
echo ""
echo "Switch complete! New configuration is active."
