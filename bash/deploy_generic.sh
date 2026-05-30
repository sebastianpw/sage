#!/bin/bash

# ==============================================================================
# SAGE PyAPI - Generic Kaggle Dataset Deployer (Client-Side Logic)
# Usage: ./deploy_generic.sh <local_path> <dataset_slug> [dataset_title]
#
# example:
#  ./bash/deploy_generic.sh ~/www/spwbase/qwen3tts_voices/ spw800621/qwen3-voices "Qwen3 TTS Voices"
# ==============================================================================

# --- Constants ---
# We use two different endpoints depending on if we are updating or creating
URL_VERSION="http://localhost:8009/kaggle/datasets/version"
URL_CREATE="http://localhost:8009/kaggle/datasets/create"
STAGING_BASE="$HOME/www/spwbase/temp/staging"

# --- Input Parsing ---
SOURCE_PATH="${1%/}" # Strip trailing slash for calculation
DATASET_SLUG="$2"
DATASET_TITLE="$3"

# --- Help / Validation ---
if [[ -z "$SOURCE_PATH" || -z "$DATASET_SLUG" ]]; then
    echo "❌ Error: Missing arguments."
    echo "Usage: $0 <path_to_source> <username/dataset-slug> [\"Dataset Title\"]"
    exit 1
fi

# Set Title default if missing
if [[ -z "$DATASET_TITLE" ]]; then
    DATASET_TITLE=$(basename "$SOURCE_PATH")
    DATASET_TITLE="$(tr '[:lower:]' '[:upper:]' <<< ${DATASET_TITLE:0:1})${DATASET_TITLE:1}"
fi

# Detect Trailing Slash
if [[ "$1" == */ ]]; then
    RSYNC_SOURCE="$1"
    echo "ℹ️  Trailing slash detected: Deploying CONTENTS of $SOURCE_PATH"
else
    RSYNC_SOURCE="$1"
    echo "ℹ️  No trailing slash: Deploying FOLDER $SOURCE_PATH"
fi

# Define unique staging dir
STAGING_DIR="$STAGING_BASE/$(basename "$SOURCE_PATH")_$(date +%s)"

# ==============================================================================

echo "🚀 Starting Deployment..."
echo "--------------------------------------------"
echo "Source:  $RSYNC_SOURCE"
echo "Target:  $DATASET_SLUG"
echo "Staging: $STAGING_DIR"
echo "--------------------------------------------"

# 1. Prepare Staging
mkdir -p "$STAGING_DIR"

# 2. Copy Files (Rsync)
echo "📦 Copying files..."
rsync -av \
    --exclude 'venv' --exclude '.venv' \
    --exclude '__pycache__' --exclude '*.pyc' \
    --exclude '.git' --exclude '.DS_Store' \
    --exclude 'syslogs' --exclude '*.pid' \
    --exclude '*.log' --exclude '.env*' \
    --exclude 'dataset-metadata.json' \
    "$RSYNC_SOURCE" "$STAGING_DIR/" > /dev/null

# 3. Generate Metadata
METADATA_FILE="$STAGING_DIR/dataset-metadata.json"
echo "📝 Generating metadata..."
cat > "$METADATA_FILE" <<EOF
{
  "title": "$DATASET_TITLE",
  "id": "$DATASET_SLUG",
  "licenses": [ { "name": "CC0-1.0" } ]
}
EOF

# 4. Upload Logic (Client-Side Fallback)
echo "📤 Step 1: Attempting to UPDATE existing dataset..."

# Try to VERSION it first
RESPONSE=$(curl -s -X POST "$URL_VERSION" \
     -H "Content-Type: application/json" \
     -d "{
           \"path\": \"$STAGING_DIR\", 
           \"message\": \"Deploy $(basename "$SOURCE_PATH") $(date +'%Y-%m-%d %H:%M')\"
         }")

# Check if versioning succeeded
if echo "$RESPONSE" | grep -q "success"; then
    echo "✅ Update Successful!"
    echo "   https://www.kaggle.com/datasets/$DATASET_SLUG"
else
    # If versioning failed, it's likely because the dataset doesn't exist 
    # (or the Python service crashed trying to read the error). 
    # We proceed to try creating it.
    echo "⚠️  Update failed (Dataset likely implies new). Falling back to CREATE..."
    
    # Try to CREATE it
    RESPONSE_CREATE=$(curl -s -X POST "$URL_CREATE" \
        -H "Content-Type: application/json" \
        -d "{
            \"path\": \"$STAGING_DIR\", 
            \"public\": true
            }")
            
    if echo "$RESPONSE_CREATE" | grep -q "success"; then
        echo "✅ Creation Successful!"
        echo "   https://www.kaggle.com/datasets/$DATASET_SLUG"
    else
        echo "❌ Deployment Failed."
        echo "--- Update Attempt Response ---"
        echo "$RESPONSE"
        echo "--- Create Attempt Response ---"
        echo "$RESPONSE_CREATE"
    fi
fi

# 5. Cleanup
rm -rf "$STAGING_DIR"
echo "--------------------------------------------"
