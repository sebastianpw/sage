#!/bin/bash
# switch.sh — Select which genframe_db.sh variant to activate

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR" || exit 1

TARGET_LINK="genframe_db.sh"
DEFAULT="pollinations"  # Default option if no argument is given

# Define available variants
declare -A OPTIONS=(
    ["pollinations"]="genframe_db.sh.pollinations.ai"
    ["polliwan"]="genframe_db.sh.polliwan"
    ["pollinana"]="genframe_db.sh.pollinana"
    ["pollinana2"]="genframe_db.sh.pollinana2"
    ["pollpimgedt"]="genframe_db.sh.pollpimgedt"
    ["polliklein"]="genframe_db.sh.polliklein"
    ["polligross"]="genframe_db.sh.polligross"
    ["pollzimage"]="genframe_db.sh.pollzimage"
    ["pollgptimage"]="genframe_db.sh.pollgptimage"
    ["pollgptmini"]="genframe_db.sh.pollgptmini"
    ["pollgrok"]="genframe_db.sh.pollgrokimagine"
    ["pollimagen"]="genframe_db.sh.pollimagen"
    ["pollux"]="genframe_db.sh.pollux"
    ["pollux2"]="genframe_db.sh.pollux2"
    ["pollqwenplus"]="genframe_db.sh.pollqwenplus"
    ["pollipruna"]="genframe_db.sh.pollipruna"
    ["pollseedream"]="genframe_db.sh.pollseedream"
    ["pollseedream5"]="genframe_db.sh.pollseedream5"
    ["jupyter"]="genframe_db.sh.JUPYTER"
    ["jupyter_lcm"]="genframe_db.sh.JUPYTER_LCM"
    ["jupyter_turbo"]="genframe_db.sh.JUPYTER_TURBO"
    ["jupyter_async"]="genframe_db.sh.JUPYTER_ASYNC"
    ["freepik"]="genframe_db.sh.freepik"
)

# Function to print usage
print_usage() {
    echo "Usage: $0 [pollinations|jupyter|jupyter_lcm]"
    echo "No argument = default (${DEFAULT})"
    echo "Current link: $(readlink -f "$TARGET_LINK" 2>/dev/null || echo 'none')"
}

# Use argument or fallback to default
CHOICE="${1:-$DEFAULT}"

# Validate choice
if [[ -z "${OPTIONS[$CHOICE]}" ]]; then
    echo "❌ Invalid option: $CHOICE"
    echo
    print_usage
    exit 1
fi

# Switch symlink
ln -sf "${OPTIONS[$CHOICE]}" "$TARGET_LINK"
echo "✅ Switched to ${CHOICE} (${OPTIONS[$CHOICE]})"
