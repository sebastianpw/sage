#!/usr/bin/env bash

for f in frames_pollinations/*.jpg; do
  if ffmpeg -v error -i "$f" -f null - 2>&1 | grep -q .; then
    echo "Broken JPEG detected: $f"
  fi
done
