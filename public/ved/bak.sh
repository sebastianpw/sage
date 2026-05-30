#!/bin/bash

# === CONFIGURATION ===
MAX=5          # maximum backups to keep per original file
PAD=3          # zero‑padding for backup numbers (001, 002, …)
SCRIPT_NAME="bak.sh"
# =====================

set -euo pipefail

# Run from the directory where this script is stored
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$SCRIPT_DIR"

# Iterate over every regular file (skip backups and the script itself)
find . -type f ! -name '*.bak' ! -name "$SCRIPT_NAME" -print0 | while IFS= read -r -d '' file; do
    dir=$(dirname "$file")
    base=$(basename "$file")

    # Gather all backups of this exact file
    backups=()
    while IFS= read -r -d '' bf; do
        backups+=("$bf")
    done < <(find "$dir" -maxdepth 1 -type f -name "${base}.*.bak" -print0 2>/dev/null)

    # Extract their numeric suffixes (strip leading zeros)
    numbers=()
    for b in "${backups[@]}"; do
        # Extract "num" from e.g. "index.php.007.bak", force decimal interpretation
        num=${b##*"${base}."}
        num=${num%.bak}
        if [[ $num =~ ^[0-9]+$ ]]; then
            numbers+=("$((10#$num))")   # <-- store as decimal integer
        fi
    done

    count=${#numbers[@]}

    if [[ $count -lt $MAX ]]; then
        # Not yet at limit – use next number (highest + 1, or start at 1)
        if [[ $count -eq 0 ]]; then
            next=1
        else
            # Sort numbers (now they are safe decimal integers)
            IFS=$'\n' sorted=($(printf '%s\n' "${numbers[@]}" | sort -n))
            unset IFS
            last=${sorted[-1]}
            next=$((last + 1))
        fi
        new_suffix=$(printf "%0${PAD}d" "$next")
        cp "$file" "${file}.${new_suffix}.bak"
    else
        # Already at or above limit – delete the oldest (smallest number)
        # and create a new backup with highest + 1
        IFS=$'\n' sorted=($(printf '%s\n' "${numbers[@]}" | sort -n))
        unset IFS
        oldest=${sorted[0]}
        highest=${sorted[-1]}

        # Remove the oldest backup
        oldest_padded=$(printf "%0${PAD}d" "$oldest")
        rm -f "${file}.${oldest_padded}.bak"

        # New backup number = highest (decimal) + 1
        next=$((highest + 1))
        new_suffix=$(printf "%0${PAD}d" "$next")
        cp "$file" "${file}.${new_suffix}.bak"
    fi
done
