#!/bin/bash
# regenerate_images_all.sh
# Marks all entities to regenerate images in the database

MYSQL_USER="root"
MYSQL_DB=$("$(dirname "$0")/db_name.sh")

mysql -u"$MYSQL_USER" "$MYSQL_DB" <<EOF
-- For scene_parts
UPDATE scene_parts SET regenerate_images = 1;

-- For characters
UPDATE characters SET regenerate_images = 1;

-- For animas
UPDATE animas SET regenerate_images = 1;

-- For artifacts
UPDATE artifacts SET regenerate_images = 1;

-- For vehicles
UPDATE vehicles SET regenerate_images = 1;

-- For locations 
UPDATE locations SET regenerate_images = 1;

-- For backgrounds
UPDATE backgrounds SET regenerate_images = 1;
EOF

echo "All regenerate_images flags have been set to 1."




