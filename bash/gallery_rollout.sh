#!/bin/bash

# Directory where the gallery scripts are located
SCRIPT_DIR="./"   # adjust if needed

# The "main" gallery source
MAIN_SCRIPT="gallery_characters.php"

# Remove any old gallery files except the main one
for file in "$SCRIPT_DIR"/gallery_*.php; do
    [ "$(basename "$file")" = "$MAIN_SCRIPT" ] && continue
    rm -f "$file"
done

# Get entity names dynamically via your PHP JSON script
ENTITIES=($(php -f "$SCRIPT_DIR/sage_entities_items_json.php" | jq -r '.[].name'))

# Copy main gallery to all other entities
for entity in "${ENTITIES[@]}"; do
    [ "$entity" = "characters" ] && continue

    DEST="$SCRIPT_DIR/gallery_${entity}.php"
    cp "$SCRIPT_DIR/$MAIN_SCRIPT" "$DEST"
    # Update the $entity line at the top of the PHP file
    sed -i "s/^\(\$entity\s*=\s*\).*/\1\"$entity\";/" "$DEST"
done

echo "Gallery rollout completed."



