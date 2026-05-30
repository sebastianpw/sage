#!/bin/bash
# load_root.sh - load PROJECT_ROOT, FRAMES_ROOT, AUDIOS_ROOT, VIDEOS_ROOT from .env.local

SHARE_FILE="$(dirname "$BASH_SOURCE")/../.env.local"

if [[ ! -f "$SHARE_FILE" ]]; then
    echo "ERROR: .env.local file not found at $SHARE_FILE" >&2
    exit 1
fi

PROJECT_ROOT=""
FRAMES_ROOT=""
AUDIOS_ROOT=""
VIDEOS_ROOT=""

while IFS= read -r line; do
    case "$line" in
        PROJECT_ROOT=*) PROJECT_ROOT="${line#*=}" ;;
        FRAMES_ROOT=*) FRAMES_ROOT="${line#*=}" ;;
        AUDIOS_ROOT=*) AUDIOS_ROOT="${line#*=}" ;;
        VIDEOS_ROOT=*) VIDEOS_ROOT="${line#*=}" ;;
    esac
done < "$SHARE_FILE"

PROJECT_ROOT="$(echo -n "$PROJECT_ROOT" | xargs)"
FRAMES_ROOT="$(echo -n "$FRAMES_ROOT" | xargs)"
AUDIOS_ROOT="$(echo -n "$AUDIOS_ROOT" | xargs)"
VIDEOS_ROOT="$(echo -n "$VIDEOS_ROOT" | xargs)"

if [[ -z "$PROJECT_ROOT" ]]; then
    echo "ERROR: PROJECT_ROOT not found in $SHARE_FILE" >&2
    exit 1
fi
if [[ -z "$FRAMES_ROOT" ]]; then
    echo "ERROR: FRAMES_ROOT not found in $SHARE_FILE" >&2
    exit 1
fi
if [[ -z "$AUDIOS_ROOT" ]]; then
    echo "ERROR: AUDIOS_ROOT not found in $SHARE_FILE" >&2
    exit 1
fi
if [[ -z "$VIDEOS_ROOT" ]]; then
    echo "ERROR: VIDEOS_ROOT not found in $SHARE_FILE" >&2
    exit 1
fi

# Optionally verify directories - they may be symlinks, which is fine
if [[ ! -e "$PROJECT_ROOT" ]]; then
    echo "ERROR: PROJECT_ROOT path does not exist: $PROJECT_ROOT" >&2
    exit 1
fi
if [[ ! -e "$FRAMES_ROOT" ]]; then
    echo "ERROR: FRAMES_ROOT path does not exist: $FRAMES_ROOT" >&2
    exit 1
fi
if [[ ! -e "$AUDIOS_ROOT" ]]; then
    echo "ERROR: AUDIOS_ROOT path does not exist: $AUDIOS_ROOT" >&2
    exit 1
fi
if [[ ! -e "$VIDEOS_ROOT" ]]; then
    echo "ERROR: VIDEOS_ROOT path does not exist: $VIDEOS_ROOT" >&2
    exit 1
fi

export PROJECT_ROOT FRAMES_ROOT AUDIOS_ROOT VIDEOS_ROOT