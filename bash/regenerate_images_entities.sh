#!/bin/bash
# Usage: ./regenerate_images_entities.sh <entity>

DB_USER="root"
DB_NAME=$("$(dirname "$0")/db_name.sh")

ENTITY="$1"

# Verify input
if [[ -z "$ENTITY" ]]; then
  echo "Usage: $0 <entity>"
  echo "Entity must be one of: characters, character_poses, sketches, generatives, animas, artifacts, vehicles, locations, backgrounds, scene_parts"
  exit 1
fi

# Optional: validate the entity is allowed
ALLOWED=("characters" "character_poses" "sketches" "generatives" "animas" "artifacts" "vehicles" "locations" "backgrounds" "scene_parts")
if [[ ! " ${ALLOWED[*]} " =~ " ${ENTITY} " ]]; then
  echo "Invalid entity: $ENTITY"
  echo "Allowed entities: ${ALLOWED[*]}"
  exit 1
fi

# Update the regenerate_images flag
mysql -u"$DB_USER" "$DB_NAME" <<EOF
UPDATE $ENTITY SET regenerate_images = 1;
EOF

echo "All regenerate_images flags of $ENTITY have been set to 1."



