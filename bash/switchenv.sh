#!/bin/bash
# switchenv.sh
# Usage: ./switchenv.sh <suffix>
# Example: ./switchenv.sh nu
# Example: ./switchenv.sh bday_draft
#
# This version no longer modifies load_root.share.
# All relevant values are now stored and switched in .env.local.

# Get the directory where this script is located (should be in public/)
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Define absolute path to .env.local
ENV_FILE="$SCRIPT_DIR/../.env.local"

# Check if suffix parameter was provided
if [ -z "$1" ]; then
    echo "ERROR: No suffix provided"
    echo "Usage: $0 <suffix>"
    echo "Example: $0 nu"
    echo "Example: $0 bday_draft"
    exit 1
fi

SUFFIX="$1"

# Validate that the .env.local file exists
if [ ! -f "$ENV_FILE" ]; then
    echo "ERROR: .env.local not found at $ENV_FILE"
    exit 1
fi

echo "Starting environment switch to suffix: $SUFFIX"
echo "----------------------------------------"

# Backup current .env.local before modification
cp "$ENV_FILE" "$ENV_FILE.bak"

# Update database name in DATABASE_URL line
echo "Updating $ENV_FILE..."
sed -i "s|/starlight_guardians_[^?]*|/starlight_guardians_${SUFFIX}|g" "$ENV_FILE"

# Update FRAMES_ROOT value
sed -i "s|frames_starlightguardians_[^/]*|frames_starlightguardians_${SUFFIX}|g" "$ENV_FILE"

if [ $? -eq 0 ]; then
    echo "✓ .env.local updated successfully"
else
    echo "✗ ERROR: Failed to update .env.local"
    echo "Restoring from backup..."
    mv "$ENV_FILE.bak" "$ENV_FILE"
    exit 1
fi

echo "----------------------------------------"
echo "Switch completed successfully!"
echo ""
echo "Updated values in .env.local:"
grep -E "DATABASE_URL|FRAMES_ROOT" "$ENV_FILE"
echo ""
echo "Restarting scheduler..."

# Stop and restart scheduler (no need to restart MariaDB/NGINX)
php /data/data/com.termux/files/home/www/spwbase/public/scheduler_stop.php
sleep 2
php /data/data/com.termux/files/home/www/spwbase/public/scheduler_start.php &

echo "✓ Scheduler restarted"
echo ""
echo "Switch complete! New configuration is active."
