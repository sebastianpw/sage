#!/bin/bash

# Define the project root
PROJECT_ROOT="$(dirname "$(dirname "$(readlink -f "$0")")")"
CRUD_SCRIPT_DIR="$PROJECT_ROOT/public"

# Template file
TEMPLATE_FILE="$PROJECT_ROOT/public/sql_crud_image_template.php"

# Check for Template
if [ ! -f "$TEMPLATE_FILE" ]; then
    echo "Error: Template file not found at $TEMPLATE_FILE"
    exit 1
fi

# Check for jq
if ! command -v jq &> /dev/null; then
    echo "Error: 'jq' is not installed. Please install it."
    exit 1
fi

# Check for the Entity Provider Script
ENTITY_PROVIDER="$CRUD_SCRIPT_DIR/sage_entities_items_json.php"
if [ ! -f "$ENTITY_PROVIDER" ]; then
    echo "Error: Entity provider script not found at $ENTITY_PROVIDER"
    exit 1
fi

echo "Fetching generic entity list..."

# Execute PHP script and parse JSON for names
RAW_JSON=$(php -f "$ENTITY_PROVIDER")

if [ -z "$RAW_JSON" ]; then
    echo "Error: Entity provider returned empty result."
    exit 1
fi

ENTITIES=($(echo "$RAW_JSON" | jq -r '.[].name'))

echo "Found ${#ENTITIES[@]} entities. Starting rollout..."

for entity in "${ENTITIES[@]}"; do
    # Overwrite the standard CRUD file
    TARGET_FILE="$PROJECT_ROOT/public/sql_crud_${entity}.php"
    
    echo "Generating $TARGET_FILE..."
    
    cp "$TEMPLATE_FILE" "$TARGET_FILE"
    
    # Replace Placeholder. Using | delimiter for sed.
    sed -i "s|\[\[ENTITY_NAME\]\]|$entity|g" "$TARGET_FILE"
done

echo "Rollout complete. ${#ENTITIES[@]} CRUDs updated."
