#!/bin/bash
# switchenv.sh
# Usage: ./switchenv.sh <suffix>   e.g. ./switchenv.sh nu

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# Define Project Root (one level up from bash/)
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
ENV_FILE="$PROJECT_ROOT/.env.local"
PUBLIC_DIR="$PROJECT_ROOT/public"

if [ -z "$1" ]; then
    echo "ERROR: No suffix provided"
    echo "Usage: $0 <suffix>"
    exit 1
fi
SUFFIX="$1"

if [ ! -f "$ENV_FILE" ]; then
    echo "ERROR: .env.local not found at $ENV_FILE"
    exit 1
fi

echo "Starting environment switch to suffix: $SUFFIX"
cp "$ENV_FILE" "$ENV_FILE.bak"

# Update DB name in DATABASE_URL
sed -i "s|\(/starlight_guardians_\)[^/?]*|\1${SUFFIX}|g" "$ENV_FILE"

# Update root lines for all media types in .env
sed -i "s|frames_starlightguardians_[^/]*|frames_starlightguardians_${SUFFIX}|g" "$ENV_FILE"
sed -i "s|audios_starlightguardians_[^/]*|audios_starlightguardians_${SUFFIX}|g" "$ENV_FILE"
sed -i "s|videos_starlightguardians_[^/]*|videos_starlightguardians_${SUFFIX}|g" "$ENV_FILE"
sed -i "s|media_starlightguardians_[^/]*|media_starlightguardians_${SUFFIX}|g" "$ENV_FILE"
sed -i "s|storyboards_starlightguardians_[^/]*|storyboards_starlightguardians_${SUFFIX}|g" "$ENV_FILE"
sed -i "s|editorial_starlightguardians_[^/]*|editorial_starlightguardians_${SUFFIX}|g" "$ENV_FILE"

if [ $? -ne 0 ]; then
    echo "✗ ERROR: Failed to update .env.local; restoring backup"
    mv "$ENV_FILE.bak" "$ENV_FILE"
    exit 1
fi

echo "✓ .env.local updated successfully"
grep -E "DATABASE_URL|FRAMES_ROOT|AUDIOS_ROOT|VIDEOS_ROOT|MEDIA_ROOT|STORYBOARDS_ROOT|EDITORIAL_ROOT" "$ENV_FILE" || true

# ------------------------------------------------------------------------------
# Updating Instance Symlinks (in /public)
# ------------------------------------------------------------------------------
if [ -d "$PUBLIC_DIR" ]; then
    echo "Updating symlinks in $PUBLIC_DIR..."
    
    # Enter public directory to ensure relative links are created correctly
    cd "$PUBLIC_DIR" || exit 1

    # Define targets (assuming specific folders are siblings inside public/)
    VIDEO_TARGET="videos_starlightguardians_${SUFFIX}"
    AUDIO_TARGET="audios_starlightguardians_${SUFFIX}"
    MEDIA_TARGET="media_starlightguardians_${SUFFIX}"
    STORYBOARDS_TARGET="storyboards_starlightguardians_${SUFFIX}"
    EDITORIAL_TARGET="editorial_starlightguardians_${SUFFIX}"

    # 1. Remove existing symlinks/folders
    rm -rf videos
    rm -rf audios
    rm -rf media
    rm -rf storyboards
    rm -rf editorial

    # 2. Create new symlinks
    ln -s "$VIDEO_TARGET" videos
    ln -s "$AUDIO_TARGET" audios
    ln -s "$MEDIA_TARGET" media
    ln -s "$STORYBOARDS_TARGET" storyboards
    ln -s "$EDITORIAL_TARGET" editorial

    echo "✓ Symlinks updated in public/:"
    ls -l videos audios media storyboards editorial
else
    echo "✗ ERROR: Public directory not found at $PUBLIC_DIR"
fi
# ------------------------------------------------------------------------------

# Restart the scheduler
if [ -x "$SCRIPT_DIR/scheduler_startup.sh" ]; then
  "$SCRIPT_DIR/scheduler_startup.sh" &
  echo "✓ Scheduler restarted"
else
  echo "Note: scheduler_startup.sh not found or not executable; skipping restart"
fi

echo "Switch complete."


