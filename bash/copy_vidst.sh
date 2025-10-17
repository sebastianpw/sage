#!/usr/bin/env bash

# Sync videos folder WITHOUT deletions
rsync -a videos/ ~/Download/videos/

# Loop over all frames_* folders
for src in frames_*/; do
  # Skip frames_dst
  [[ "$src" == "frames_dst/" ]] && continue

  # Fully refresh frames_pollinations
  if [[ "$src" == "frames_pollinations/" ]]; then
    dst=~/Download/"$src"
    rm -rf "$dst"
    rsync -a "$src" "$dst"
    continue
  fi

  # For frames_pollinations_* → only copy new files (by name), skip existing
  if [[ "$src" == frames_pollinations_* ]]; then
    dst=~/Download/"$src"
    rsync -a --ignore-existing "$src" "$dst"
    continue
  fi

  # For all other frames_* → normal sync with deletion
  dst=~/Download/"$src"
  rsync -a --delete "$src" "$dst"
done


