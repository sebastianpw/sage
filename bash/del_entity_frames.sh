#!/bin/bash
# Usage: ./delete_entity_frames.sh <entity> <entity_id>

DB_USER="root"
DB_NAME=$("$(dirname "$0")/db_name.sh")

ENTITY="$1"
ENTITY_ID="$2"

# Verify input
if [[ -z "$ENTITY" || -z "$ENTITY_ID" ]]; then
  echo "Usage: $0 <entity> <entity_id>"
  echo "Entity must be one of: characters, animas, artifacts, vehicles, locations, backgrounds"
  exit 1
fi

# Simple confirmation
#read -n1 -r -p "Are you sure you want to delete frames for $ENTITY ID $ENTITY_ID? (Y to confirm): " CONFIRM
#echo
#if [[ "$CONFIRM" != "y" && "$CONFIRM" != "Y" ]]; then
#  echo "Aborted. Nothing was deleted."
#  exit 1
#fi

# Flag entity for regeneration
echo "Flagging entity $ENTITY ID $ENTITY_ID for regeneration..."
mysql -u "$DB_USER" "$DB_NAME" -e "UPDATE $ENTITY SET regenerate_images = 1 WHERE id = $ENTITY_ID;"

# Delete mappings from frames_2_<entity>
MAPPING_TABLE="frames_2_$ENTITY"
echo "Deleting mappings in $MAPPING_TABLE for entity ID $ENTITY_ID..."
mysql -u "$DB_USER" "$DB_NAME" -e "DELETE FROM $MAPPING_TABLE WHERE to_id = $ENTITY_ID;"

echo "Done! Frames for entity flagged for regeneration and mapping table cleared."



