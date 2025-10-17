#!/bin/bash
# Usage: ./truncate_gallery.sh

# -----------------------------
# Resolve script directory
# -----------------------------
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# -----------------------------
# Load project roots
# -----------------------------
source "$SCRIPT_DIR/load_root.sh"

# Now FRAMES_ROOT and PROJECT_ROOT are available
FRAMES_DIR="$FRAMES_ROOT"

DB_USER="root"
DB_NAME=$("$SCRIPT_DIR/db_name.sh")


# Maniac Mansion–style warning
cat << "EOF"
===========================================================
   🏚️ DANGER ZONE 🏚️
===========================================================

   "⚠️  WARNING: DO NOT PUSH THIS BUTTON ⚠️
    (or EVERYTHING [in the gallery] will blow up)"

EOF



# First confirmation: single keypress
read -n1 -r -p "Do you REALLY want to press the forbidden button? (Y/N): " CONFIRM1
echo
if [[ "$CONFIRM1" != "y" && "$CONFIRM1" != "Y" ]]; then
  echo "💨 You walk away. Nothing happens."
  exit 1
fi

# Second confirmation: single keypress
read -n1 -r -p "You won’t regret blowing everything up, right? (Y/N): " CONFIRM2
echo
if [[ "$CONFIRM2" != "n" && "$CONFIRM2" != "N" ]]; then
  echo "💨 You hesitate. The button remains unpressed."
  exit 1
fi


echo "💥 BOOM! You pressed the button... everything is gone!"
echo "✅ Proceeding with truncation..."

# Disable foreign key checks
mysql -u "$DB_USER" "$DB_NAME" -e "SET FOREIGN_KEY_CHECKS=0;"

# Tables to truncate
TABLES=(
  "frames"
  "frames_2_characters"
  "frames_2_animas"
  "frames_2_artifacts"
  "frames_2_vehicles"
  "frames_2_locations"
  "frames_2_backgrounds"
)

for TABLE in "${TABLES[@]}"; do
  echo "Truncating table '$TABLE'..."
  mysql -u "$DB_USER" "$DB_NAME" -e "TRUNCATE TABLE $TABLE;"
done

# Reset regenerate_images flags
mysql -u "$DB_USER" "$DB_NAME" <<EOF
UPDATE characters   SET regenerate_images = 1;
UPDATE animas       SET regenerate_images = 1;
UPDATE artifacts    SET regenerate_images = 1;
UPDATE vehicles     SET regenerate_images = 1;
UPDATE locations    SET regenerate_images = 1;
UPDATE backgrounds  SET regenerate_images = 1;
EOF

# Re-enable foreign key checks
mysql -u "$DB_USER" "$DB_NAME" -e "SET FOREIGN_KEY_CHECKS=1;"

rm -rf "$FRAMES_DIR"

echo "🎉 Reset complete! (Game over... or a fresh start?)"



