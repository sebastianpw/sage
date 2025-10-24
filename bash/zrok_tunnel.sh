#!/bin/bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

TOKEN_FILE="$SCRIPT_DIR/../token/.zrok_api_key"
if [ ! -r "$TOKEN_FILE" ]; then
  echo "ERROR: token file not found or unreadable: $TOKEN_FILE" >&2
  exit 1
fi

# read first non-empty line and trim whitespace
TOKEN="$(sed -n '1p' "$TOKEN_FILE" | tr -d '\r\n' | sed 's/^[[:space:]]*//;s/[[:space:]]*$//')"

if [ -z "$TOKEN" ]; then
  echo "ERROR: token file appears empty: $TOKEN_FILE" >&2
  exit 2
fi

# Enable zrok (silently)
zrok enable "$TOKEN" > /dev/null 2>&1 || {
  echo "ERROR: failed to enable zrok" >&2
  exit 3
}

# Try a few quick retries to get the share URL
URL=""
for i in 1 2 3 4 5; do
  URL="$(zrok overview 2>/dev/null | grep -o 'https://[^[:space:]]*\.share\.zrok\.io' | head -n1 || true)"
  if [ -n "$URL" ]; then
    break
  fi
  sleep 1
done

# Disable zrok (cleanup)
zrok disable > /dev/null 2>&1 || true

if [ -z "$URL" ]; then
  echo "ERROR: no zrok share URL found" >&2
  exit 4
fi

# Output only the URL
printf '%s\n' "$URL"
