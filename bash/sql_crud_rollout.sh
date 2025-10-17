#!/bin/bash

# Directory where the SQL CRUD scripts are located
SCRIPT_DIR="./"   # change if scripts are in a subfolder

# The "main" source script
MAIN_SCRIPT="sql_crud_characters.php"

# Find all sql_crud_*.php scripts except the main one
for file in "$SCRIPT_DIR"/sql_crud_*.php; do
    [ "$(basename "$file")" = "$MAIN_SCRIPT" ] && continue  # skip main
    rm -f "$file"
done

# Get all entity names dynamically from the PHP JSON script
# Make sure 'jq' is installed for JSON parsing
ENTITIES=($(php -f "$SCRIPT_DIR/sage_entities_items_json.php" | jq -r '.[].name'))

# Copy main script to all other entities with the proper $entity line
for entity in "${ENTITIES[@]}"; do
    # Skip the main script itself
    [ "$entity" = "characters" ] && continue

    DEST="$SCRIPT_DIR/sql_crud_${entity}.php"
    cp "$SCRIPT_DIR/$MAIN_SCRIPT" "$DEST"
    # Replace $entity line at the top
    sed -i "s/^\(\$entity\s*=\s*\).*/\1\"$entity\";/" "$DEST"
done

echo "Rollout completed."

