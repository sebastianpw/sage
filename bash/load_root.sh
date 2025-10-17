#!/bin/bash
# load_root.sh
# Load PROJECT_ROOT and FRAMES_ROOT from load_root.share

# Path to the share file
SHARE_FILE="$(dirname "$BASH_SOURCE")/../.env.local"

if [[ ! -f "$SHARE_FILE" ]]; then
    echo "ERROR: .env.local file not found at $SHARE_FILE" >&2
    exit 1
fi

# Read the variables from the share file
# Only allow PROJECT_ROOT and FRAMES_ROOT lines, ignore others
while IFS= read -r line; do
    case "$line" in
        PROJECT_ROOT=*) PROJECT_ROOT="${line#*=}" ;;
        FRAMES_ROOT=*) FRAMES_ROOT="${line#*=}" ;;
    esac
done < "$SHARE_FILE"

# Trim whitespace
PROJECT_ROOT="$(echo -n "$PROJECT_ROOT" | xargs)"
FRAMES_ROOT="$(echo -n "$FRAMES_ROOT" | xargs)"

# Check that both are set
if [[ -z "$PROJECT_ROOT" ]]; then
    echo "ERROR: PROJECT_ROOT not found in $SHARE_FILE" >&2
    exit 1
fi

if [[ -z "$FRAMES_ROOT" ]]; then
    echo "ERROR: FRAMES_ROOT not found in $SHARE_FILE" >&2
    exit 1
fi

# Optionally, verify that the directories actually exist
if [[ ! -d "$PROJECT_ROOT" ]]; then
    echo "ERROR: PROJECT_ROOT directory does not exist: $PROJECT_ROOT" >&2
    exit 1
fi

if [[ ! -d "$FRAMES_ROOT" ]]; then
    echo "ERROR: FRAMES_ROOT directory does not exist: $FRAMES_ROOT" >&2
    exit 1
fi

# Now PROJECT_ROOT and FRAMES_ROOT are available to any script that sources this file
# Usage: source load_root.sh
