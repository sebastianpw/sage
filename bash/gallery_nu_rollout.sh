#!/bin/bash

# bash/gallery_nu_rollout.sh
# Rollout script for new modular galleries (Nu version)

SCRIPT_DIR="./"
MAIN_SCRIPT="gallery_characters_nu.php"

# Remove any old gallery_*_nu.php files except the main one
for file in "$SCRIPT_DIR"/gallery_*_nu.php; do
    [ "$(basename "$file")" = "$MAIN_SCRIPT" ] && continue
    rm -f "$file"
done

# Get entity names dynamically via your PHP JSON script
ENTITIES=($(php -f "$SCRIPT_DIR/sage_entities_items_json.php" | jq -r '.[].name'))

# Copy main gallery to all other entities
for entity in "${ENTITIES[@]}"; do
    [ "$entity" = "characters" ] && continue

    DEST="$SCRIPT_DIR/gallery_${entity}_nu.php"
    cp "$SCRIPT_DIR/$MAIN_SCRIPT" "$DEST"
    
    # Update the entity name in the copied file
    # This will replace the use statement and gallery instantiation
    sed -i "s/CharactersNuGallery/$(echo $entity | sed 's/_/ /g' | sed 's/\b\(.\)/\u\1/g' | sed 's/ //g')NuGallery/g" "$DEST"
    
    # Update the title
    sed -i "s/\"Characters Gallery (Modular)\"/\"$(echo $entity | sed 's/_/ /g' | sed 's/\b\(.\)/\u\1/g') Gallery (Modular)\"/g" "$DEST"
    
    # Update the entity in gear menu configuration
    sed -i "s/'show_for_entities' => \['characters'\]/'show_for_entities' => ['$entity']/g" "$DEST"
    
    # Update addAction calls to use the new entity
    sed -i "s/\$gearMenu->addAction('characters',/\$gearMenu->addAction('$entity',/g" "$DEST"
done

echo "Modular gallery rollout completed."
echo "Created gallery files for: ${ENTITIES[*]}"
