#!/bin/bash

# Get absolute directory of this script
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

TOKEN_FILE="$SCRIPT_DIR/token/.pollinationsaitoken"
OUT_FILE="$SCRIPT_DIR/../epic_anime_120s.mp3"

curl -X POST "https://gen.pollinations.ai/v1/audio/speech" \
  -H "Authorization: Bearer $(cat "$TOKEN_FILE")" \
  -H "Content-Type: application/json" \
  -o "$OUT_FILE" \
  -d @- <<'JSON'
{
  "input": "Epic anime soundtrack — heroic rising motif: soaring full orchestra (strings, brass), thunderous taiko and cinematic percussion, distant ethereal choir, warm analog synth pads and a driving electric-guitar counterline. Build from quiet, emotional opening into a triumphant, cathartic climax with wide stereo cinematic mix. No lyrics; purely instrumental; clear melodic lead, 120–140 BPM feel.",
  "duration": 120,
  "instrumental": true,
  "model": "elevenmusic",
  "response_format": "mp3",
  "speed": 1.0
}
JSON
