#!/bin/bash
# Usage: ./refrentity.sh <entity> <entity_id> <limit>

DB_USER="root"
DB_NAME=$("$(dirname "$0")/db_name.sh")

ENTITY="$1"
ENTITY_ID="$2"
LIMIT="$3"

# Verify input
if [[ -z "$ENTITY" || -z "$ENTITY_ID" ]]; then
  echo "Usage: $0 <entity> <entity_id> <limit>"
  echo "Entity must be one of: characters, animas, artifacts, vehicles, locations, backgrounds"
  exit 1
fi

./del_entity_frames.sh "$ENTITY" "$ENTITY_ID"

./genframes_fromdb.sh "$ENTITY" "$LIMIT"



