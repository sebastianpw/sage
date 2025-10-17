#!/bin/bash

# genframes_from_prompts.sh - generate frames from a list of prompts
# Usage: ./genframes_from_prompts.sh prompts.txt [limit] [offset]

PROMPTS_FILE="$1"
LIMIT="$2"
OFFSET="$3"

OUTPUT_DIR="frames_from_prompts"
mkdir -p "$OUTPUT_DIR"

if [ -z "$PROMPTS_FILE" ]; then
  echo "Usage: $0 prompts.txt [limit] [offset]"
  exit 1
fi

if [ ! -f "$PROMPTS_FILE" ]; then
  echo "Prompts file '$PROMPTS_FILE' not found!"
  exit 1
fi

COUNT=1

while IFS= read -r prompt || [ -n "$prompt" ]; do
  [ -z "$prompt" ] && continue

  # Apply offset
  if [ -n "$OFFSET" ] && [ "$COUNT" -lt "$OFFSET" ]; then
    ((COUNT++))
    continue
  fi

  # Apply limit
  if [ -n "$LIMIT" ] && [ $((COUNT - OFFSET + 1)) -gt "$LIMIT" ]; then
    break
  fi

  echo "Processing prompt $COUNT: $prompt"

  # Use genframe.sh to generate a single frame
  ./genframe.sh "$prompt" 1 1

  # Move the last generated frame to the output folder
  LAST_FRAME=$(ls -t frames_pollinations/frame*.jpg 2>/dev/null | head -n 1)
  if [ -n "$LAST_FRAME" ]; then
    mv "$LAST_FRAME" "${OUTPUT_DIR}/frame_from_prompt_$(printf "%04d" $COUNT).jpg"
    echo "Saved: ${OUTPUT_DIR}/frame_from_prompt_$(printf "%04d" $COUNT).jpg"
  else
    echo "Warning: no frame generated for prompt $COUNT"
  fi

  ((COUNT++))
done < "$PROMPTS_FILE"

echo "Done processing all prompts."




