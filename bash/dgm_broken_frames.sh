#!/usr/bin/env bash

for f in frames_pollinations/*.jpg; do
  if ! gm identify "$f" >/dev/null 2>&1; then
    echo "Broken JPEG detected: $f"
  fi
done
