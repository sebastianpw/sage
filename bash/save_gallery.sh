#!/bin/bash
# Usage: ./save_gallery.sh

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
DUMP_DIR="$HOME/Download/gallery_dumps"

# Create dump directory if it doesn't exist
mkdir -p "$DUMP_DIR"


# Monkey Island–style pirate banner with palm tree
cat << "EOF"
===========================================================
   🏴‍☠️ PIRATE TREASURE VAULT 🏴‍☠️
                 🌴
===========================================================
Ahoy! Ye've stumbled upon the captain's secret stash.
A lever creaks in the captain’s quarters...

Press it to secure all ye precious gallery loot.
X marks the spot!
EOF


# First confirmation: single keypress
read -n1 -r -p "Arrr, ye REALLY want to safeguard all the booty? (Y/N): " CONFIRM1
echo  # just to move to a new line
if [[ "$CONFIRM1" != "y" && "$CONFIRM1" != "Y" ]]; then
  echo "💨 Ye walked the plank. No treasures were saved."
  exit 1
fi

# Second confirmation: single keypress
read -n1 -r -p "I am Bobbin Threadbare. Are you my mom? (Y/N): " CONFIRM2
echo  # move to a new line
if [[ "$CONFIRM2" != "n" && "$CONFIRM2" != "N" ]]; then
  echo "💨 Ye hesitated. The treasure remains in peril."
  exit 1
fi


echo "⚓ Hoisting the Jolly Roger... preparing the treasure!"

# Timestamp for dump file
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
DUMP_FILE="$DUMP_DIR/gallery_backup_$TIMESTAMP.sql"
FRAMES_ARCHIVE="$DUMP_DIR/frames_backup_$TIMESTAMP.tar.gz"

# Tables to dump
TABLES=(
  "frames"
  "frames_2_characters"
  "frames_2_animas"
  "frames_2_artifacts"
  "frames_2_vehicles"
  "frames_2_locations"
  "frames_2_backgrounds"
  "characters"
  "animas"
  "artifacts"
  "vehicles"
  "locations"
  "backgrounds"
)

# Build mysqldump command
DUMP_CMD="mysqldump -u $DB_USER $DB_NAME"
for TABLE in "${TABLES[@]}"; do
  DUMP_CMD+=" $TABLE"
done

# Execute dump
$DUMP_CMD > "$DUMP_FILE"

if [ $? -eq 0 ]; then
  echo "💾 X marks the spot! SQL treasure safely stashed in: $DUMP_FILE"
else
  echo "❌ Curse it! Something went wrong saving the loot."
  exit 1
fi

# Backup frames/images folder as tar.gz
echo "📦 Archiving all frame images..."
tar -czf "$FRAMES_ARCHIVE" -C "$(dirname "$FRAMES_DIR")" "$(basename "$FRAMES_DIR")"


if [ $? -eq 0 ]; then
  echo "🏝️ All frame images safely archived in: $FRAMES_ARCHIVE"
else
  echo "❌ Arrr! Could not archive the images!"
  exit 1
fi

echo "🏴‍☠️ The gallery booty be safe! Adventure continues..."



