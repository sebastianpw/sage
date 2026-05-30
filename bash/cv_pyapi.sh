#!/bin/bash
# Usage: ./test_cv.sh /path/to/image.jpg "Describe this"

IMAGE_PATH="$1"
PROMPT="${2:-Describe this image}"

if [ -z "$IMAGE_PATH" ]; then
  echo "Usage: $0 <image_path> [prompt]"
  exit 1
fi

if [ ! -f "$IMAGE_PATH" ]; then
  echo "Error: File not found at $IMAGE_PATH"
  exit 1
fi

echo "Sending $IMAGE_PATH to PyAPI CV Service..."

curl -X POST "http://127.0.0.1:8009/cv/analyze" \
  -H "accept: application/json" \
  -H "Content-Type: multipart/form-data" \
  -F "file=@$IMAGE_PATH" \
  -F "prompt=$PROMPT" \
  -F "model=claude-large"
