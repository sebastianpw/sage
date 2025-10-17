#!/bin/bash

# genframe.sh - unified frame generator (Pollinations / Colab)

# -----------------------------
# Configuration
# -----------------------------
export PAI_TOKEN=$(cat "$SCRIPT_DIR/../token/.pollinationsaitoken")
export FREEIMAGE_KEY=$(cat "$SCRIPT_DIR/../token/.freeimage_key")

# Explicitly set source: pollinations or colab
GEN_SOURCE="pollinations"

# Explicitly choose if Turbo model is used (text-to-image only)
USE_TURBO=false

NGROK_URL="https://4edd82f5cb83.ngrok-free.app"
STYLE_FILE="styles.txt"
OUTPUT_DIR="frames_nodb"
MAX_RETRIES=10
RETRY_DELAY=2
CONVERT_COLAB_TO_JPEG=true

# -----------------------------
# Input
# -----------------------------
BASE_PROMPT="$1"
LIMIT="$2"
OFFSET="$3"
INPUT_IMAGE="$4"

if [ -z "$BASE_PROMPT" ]; then
  echo "Usage: $0 \"base prompt\" [limit] [offset] [input image URL or local file]"
  exit 1
fi

if [ ! -f "$STYLE_FILE" ]; then
  echo "Style file '$STYLE_FILE' not found!"
  exit 1
fi

mkdir -p "$OUTPUT_DIR"

# -----------------------------
# Determine next frame index
# -----------------------------
index=1
for f in "$OUTPUT_DIR"/frame*.jpg; do
  [ -e "$f" ] || continue
  num=${f##*frame}; num=${num%%.jpg}; num=$((10#$num))
  if [ "$num" -ge "$index" ]; then index=$((num + 1)); fi
done

# -----------------------------
# Load styles
# -----------------------------
mapfile -t styles < "$STYLE_FILE"
TOTAL_STYLES=${#styles[@]}

START=$((OFFSET > 0 ? OFFSET - 1 : 0))
END=$((LIMIT > 0 ? START + LIMIT - 1 : TOTAL_STYLES - 1))
[ $END -ge $TOTAL_STYLES ] && END=$((TOTAL_STYLES - 1))

# -----------------------------
# Handle input image upload (img2img)
# -----------------------------
if [ -n "$INPUT_IMAGE" ] && [ -f "$INPUT_IMAGE" ]; then
  echo "Uploading local image $INPUT_IMAGE to Freeimage.host..."
  response=$(curl -s -X POST "https://freeimage.host/api/1/upload" \
    -F "key=$FREEIMAGE_KEY" \
    -F "action=upload" \
    -F "source=@$INPUT_IMAGE" \
    -F "format=json")
  IMAGE_URL=$(echo "$response" | jq -r '.image.url')
  if [ -z "$IMAGE_URL" ] || [ "$IMAGE_URL" = "null" ]; then
    echo "Failed to upload image. Response: $response"
    exit 1
  fi
  echo "Uploaded image URL: $IMAGE_URL"
elif [ -n "$INPUT_IMAGE" ]; then
  IMAGE_URL="$INPUT_IMAGE"
else
  IMAGE_URL=""
fi

# -----------------------------
# Main loop
# -----------------------------
for ((i=START; i<=END; i++)); do
  style="${styles[i]}"
  prompt="$BASE_PROMPT, $style"
  echo "Generating image $index for prompt: $prompt"
  url_prompt=$(echo "$prompt" | sed -e 's/ /%20/g' -e 's/,/%2C/g')
  outfile="$OUTPUT_DIR/frame$(printf "%07d" $index).jpg"

  attempt=1
  while [ $attempt -le $MAX_RETRIES ]; do
    if [ "$GEN_SOURCE" = "pollinations" ]; then
      if [ -n "$IMAGE_URL" ]; then
        # img2img call (always)
        curl -s -L -H "Authorization: Bearer $PAI_TOKEN" \
          "https://image.pollinations.ai/prompt/$url_prompt?model=kontext&image=$IMAGE_URL&width=1024&height=1024&nologo=true&removewatermark=true" \
          -o "$outfile"
      else
        if [ "$USE_TURBO" = true ]; then
          # txt2img Turbo model call
          curl -s -L -H "Authorization: Bearer $PAI_TOKEN" \
            "https://image.pollinations.ai/prompt/$url_prompt?model=turbo&width=1024&height=1024&nologo=true&removewatermark=true" \
            -o "$outfile"
        else
          # txt2img default call
          curl -s -L -H "Authorization: Bearer $PAI_TOKEN" \
            "https://image.pollinations.ai/prompt/$url_prompt?width=1024&height=1024&nologo=true" \
            -o "$outfile"
        fi
      fi
    else
      # Colab / NGROK endpoint
      tmp_png="$OUTPUT_DIR/tmp$(printf "%07d" $index).png"
      curl -s -X POST "$NGROK_URL/generate" \
        -F "prompt=$prompt" \
        -F "height=512" \
        -F "width=512" \
        -F "num_inference_steps=35" \
        -F "guidance_scale=7.5" \
        --output "$tmp_png"
      if [ "$CONVERT_COLAB_TO_JPEG" = true ]; then
        ffmpeg -y -i "$tmp_png" -q:v 2 "$outfile" >/dev/null 2>&1
        rm -f "$tmp_png"
      else
        mv "$tmp_png" "$outfile"
      fi
    fi

    # Validate image
    ffmpeg -v error -i "$outfile" -f null - 2>/dev/null
    if [ $? -eq 0 ]; then
      echo "Saved valid image: $outfile"
      break
    else
      echo "Broken image. Retry $attempt/$MAX_RETRIES..."
      rm -f "$outfile"
      attempt=$((attempt + 1))
      sleep $RETRY_DELAY
    fi
  done

  if [ $attempt -gt $MAX_RETRIES ]; then
    echo "Failed to download a valid frame $index after $MAX_RETRIES attempts."
  fi

  index=$((index + 1))
  sleep 2
done

echo "Done generating new frames up to frame$(printf "%07d" $((index - 1)))."


