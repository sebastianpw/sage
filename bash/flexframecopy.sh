#!/bin/bash
# flexframecopy.sh - High Performance Frame Copy (Fixed)

usage() {
    echo "Usage: $0 [THRESHOLD] [LIMIT] [OPTIONS]"
    echo "Copy frame files with numbers above a threshold using high-performance streaming."
    echo ""
    echo "Arguments:"
    echo "  THRESHOLD            Minimum frame number (default: 1)"
    echo "  LIMIT                Maximum number of files to copy (default: 0 = unlimited)"
    echo ""
    echo "Options:"
    echo "  -t, --threshold NUM  Same as THRESHOLD argument"
    echo "  -l, --limit NUM      Same as LIMIT argument"
    echo "  -s, --source DIR     Source directory (overrides .env.local)"
    echo "  -d, --dest DIR       Destination directory"
    echo "  -n, --dry-run        Show what would be copied without copying"
    echo "  -h, --help           Show this help"
    exit 1
}

# --- Configuration Loading ---

load_frames_root() {
    local env_file="${1:-.env.local}"
    if [ -f "$env_file" ]; then
        grep -v '^#' "$env_file" | grep 'FRAMES_ROOT' | cut -d '=' -f2- | tr -d "\"' "
    fi
}

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
ENV_FILE="$PROJECT_ROOT/.env.local"

# --- Defaults ---
THRESHOLD=1
LIMIT=0
TARGET_DIR="$HOME/www/gitpages/sg_showcase_01/frames_starlightguardians_nu"
DRY_RUN=false
SOURCE_DIR_PROVIDED=false

# --- Argument Parsing ---
POSITIONAL_ARGS=()
while [[ $# -gt 0 ]] && [[ ! $1 =~ ^- ]]; do
    POSITIONAL_ARGS+=("$1")
    shift
done

if [[ ${#POSITIONAL_ARGS[@]} -ge 1 ]]; then THRESHOLD=${POSITIONAL_ARGS[0]}; fi
if [[ ${#POSITIONAL_ARGS[@]} -ge 2 ]]; then LIMIT=${POSITIONAL_ARGS[1]}; fi

while [[ $# -gt 0 ]]; do
    case $1 in
        -t|--threshold) THRESHOLD="$2"; shift 2 ;;
        -l|--limit)     LIMIT="$2"; shift 2 ;;
        -s|--source)    SOURCE_DIR="$2"; SOURCE_DIR_PROVIDED=true; shift 2 ;;
        -d|--dest)      TARGET_DIR="$2"; shift 2 ;;
        -n|--dry-run)   DRY_RUN=true; shift ;;
        -h|--help)      usage ;;
        *)              echo "Unknown option: $1"; usage ;;
    esac
done

# --- Path Resolution ---
if [ "$SOURCE_DIR_PROVIDED" = false ]; then
    FRAMES_ROOT=$(load_frames_root "$ENV_FILE")
    if [ -n "$FRAMES_ROOT" ] && [ -d "$FRAMES_ROOT" ]; then
        SOURCE_DIR="$FRAMES_ROOT"
    else
        SOURCE_DIR="."
    fi
fi

if [ ! -d "$SOURCE_DIR" ]; then
    echo "Error: Source directory does not exist: $SOURCE_DIR"
    exit 1
fi

mkdir -p "$TARGET_DIR"

# --- Execution Report ---
echo "---------------------------------------------------"
echo "High Performance Copy Mode"
echo "Source:      $SOURCE_DIR"
echo "Destination: $TARGET_DIR"
echo "Threshold:   > $THRESHOLD"
echo "Limit:       $([ "$LIMIT" -eq 0 ] && echo "Unlimited" || echo "$LIMIT files")"
echo "Dry Run:     $DRY_RUN"
echo "---------------------------------------------------"

# Switch to source directory to handle filenames easily
cd "$SOURCE_DIR" || exit 1

# Define the command structure
if [ "$DRY_RUN" = true ]; then
    # -r prevents running if empty, -0 handles special chars
    CMD="xargs -0 -r -n 1 echo 'Would copy:'"
else
    # cp -t is a GNU extension (standard on Linux/Termux)
    CMD="xargs -0 -r cp -t $TARGET_DIR"
fi

echo "Streaming files..."

# ------------------------------------------------------------------------------
# THE FIXED PIPELINE
# 1. find: Lists files (null terminated)
# 2. awk:  Filters numbers and handles the LIMIT internally
# 3. xargs: Batches the copy command (with -r to avoid empty run errors)
# ------------------------------------------------------------------------------

find . -maxdepth 1 -name "frame*.jpg" -print0 | \
awk -v th="$THRESHOLD" -v limit="$LIMIT" '
    BEGIN {
        # Set Record Separator and Output Record Separator to null byte
        # This safely handles filenames with spaces or weird characters
        RS = "\0"
        ORS = "\0"
        count = 0
    }
    {
        # Get filename (e.g., "./frame123.jpg")
        fname = $0
        
        # Create a clean copy for number extraction
        clean = fname
        sub(/^\.\//, "", clean)  # Remove leading ./
        
        # Extract number: remove "frame" prefix and ".jpg" suffix
        # regex: replace "frame" or ".jpg" with nothing
        gsub(/frame|\.jpg/, "", clean)
        
        # Force numeric conversion
        val = clean + 0
        
        if (val > th) {
            print fname
            count++
            
            # Exit if limit is reached (if limit is not 0)
            if (limit > 0 && count >= limit) {
                exit
            }
        }
    }
' | eval $CMD

echo ""
echo "Done."
