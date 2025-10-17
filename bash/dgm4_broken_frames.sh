#!/usr/bin/env bash

find frames_pollinations -iname '*.jpg' | xargs -P 4 -I{} sh -c '
  if ! gm identify "{}" >/dev/null 2>&1; then
    echo "Removing broken JPEG: {}"
    rm -f "{}"
  fi
'


