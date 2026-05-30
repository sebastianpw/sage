#!/bin/bash

# ==============================================================================
# SAGE PyAPI - Kaggle Dataset Deployer
# Packages the local pyapi code (clean) and uploads it via the running local API
# ==============================================================================

# --- Configuration ---
# 1. Your Kaggle Username and Dataset Slug (CHANGE THIS)
# Format: username/dataset-slug
DATASET_SLUG="spw800621/sage-pyapi-code"
DATASET_TITLE="SAGE PyAPI Codebase"

# 2. Paths
SOURCE_DIR="$HOME/www/spwbase/pyapi"
STAGING_DIR="$HOME/www/spwbase/temp/pyapi_staging"

# 3. Local API Endpoint (The service running on Termux)
API_ENDPOINT="http://localhost:8009/kaggle/datasets/version"

# ==============================================================================

echo "🚀 Starting PyAPI Deployment to Kaggle..."
echo "--------------------------------------------"
echo "Source:  $SOURCE_DIR"
echo "Target:  $DATASET_SLUG"
echo "Staging: $STAGING_DIR"
echo "--------------------------------------------"

# 1. Prepare Staging Directory
echo "🧹 Cleaning staging directory..."
rm -rf "$STAGING_DIR"
mkdir -p "$STAGING_DIR"

# 2. Copy Files with Exclusions (rsync is perfect for this)
# Excludes venv, git, pycache, local env files, logs, temp files
echo "📦 Copying files (excluding venv, cache, secrets)..."

rsync -av \
    --exclude 'venv' \
    --exclude '.venv' \
    --exclude '__pycache__' \
    --exclude '*.pyc' \
    --exclude '.git' \
    --exclude '.DS_Store' \
    --exclude 'syslogs' \
    --exclude '*.pid' \
    --exclude '*.log' \
    --exclude '.env.local' \
    --exclude 'dataset-metadata.json' \
    "$SOURCE_DIR/" "$STAGING_DIR/"

# 3. Create dataset-metadata.json if it doesn't exist
# This is required by the Kaggle API to identify the dataset
METADATA_FILE="$STAGING_DIR/dataset-metadata.json"

echo "📝 Generating metadata..."
cat > "$METADATA_FILE" <<EOF
{
  "title": "$DATASET_TITLE",
  "id": "$DATASET_SLUG",
  "licenses": [
    {
      "name": "CC0-1.0"
    }
  ]
}
EOF

# 4. Call the Local API to Upload
echo "📤 Sending upload request to local PyAPI..."
echo "   Endpoint: $API_ENDPOINT"

# We use the 'version' endpoint because your service implementation handles 
# the fallback: it tries to create a version, and if 404, it creates the dataset.
RESPONSE=$(curl -s -X POST "$API_ENDPOINT" \
     -H "Content-Type: application/json" \
     -d "{
           \"path\": \"$STAGING_DIR\", 
           \"message\": \"Automated deploy via pyapi $(date +'%Y-%m-%d %H:%M')\"
         }")

# 5. Check Result
echo ""
echo "--------------------------------------------"
echo "📨 Response from Server:"
echo "$RESPONSE"
echo "--------------------------------------------"

if echo "$RESPONSE" | grep -q "success"; then
    echo "✅ Deployment Successful!"
    echo "   View at: https://www.kaggle.com/datasets/$DATASET_SLUG"
    # Optional: cleanup
    # rm -rf "$STAGING_DIR"
else
    echo "❌ Deployment Failed. Check the response above."
fi
