#!/data/data/com.termux/files/usr/bin/bash

if [ "$#" -lt 2 ] || [ "$#" -gt 4 ]; then
  echo "Usage: $0 <number_of_images> <total_duration_seconds> [boxblur_radius:power] [audio_file.wav]"
  echo "Example: $0 97 126 20:10 song.wav"
  exit 1
fi

NUM_IMAGES=$1
DURATION=$2
BOXBLUR=${3:-10:1}
AUDIO_FILE=$4

TIMESTAMP=$(date +%Y%m%d_%H%M%S)
OUTPUT="videos/video_${TIMESTAMP}.mp4"

mkdir -p videos

FRAMERATE=$(echo "scale=6; $NUM_IMAGES / $DURATION" | bc)
GOP=$(printf "%.0f" "$FRAMERATE")
if [ "$GOP" -lt 1 ]; then
  GOP=1
fi

if [ -n "$AUDIO_FILE" ] && [ ! -f "$AUDIO_FILE" ]; then
  echo "Error: Audio file '$AUDIO_FILE' does not exist."
  exit 2
fi

echo "Using framerate: $FRAMERATE fps"
echo "Using GOP size: $GOP"
echo "Boxblur: $BOXBLUR"
[ -n "$AUDIO_FILE" ] && echo "Adding audio from: $AUDIO_FILE"

INPUT_PATH="frames_pollinations/frame%07d.jpg"

FILTER_COMPLEX="
[0:v]scale=1280:720,format=yuv420p,boxblur=${BOXBLUR}[bg];
[0:v]scale=720:720,format=yuv420p[fg];
[bg][fg]overlay=(W-w)/2:(H-h)/2:format=auto[tmp];
[tmp]scale=1280:720,setsar=1,format=yuv420p[out]
"

COMMON_OPTS="-vframes $NUM_IMAGES \
-c:v libx264 -crf 20 -preset slow -pix_fmt yuv420p -g $GOP \
-profile:v baseline -level 3.1 -s 1280x720 -movflags +faststart"

if [ -n "$AUDIO_FILE" ]; then
  ffmpeg -fflags +genpts -noautorotate \
    -framerate "$FRAMERATE" -start_number 1 -i "$INPUT_PATH" -i "$AUDIO_FILE" \
    -filter_complex "$FILTER_COMPLEX" \
    -map "[out]" -map 1:a \
    -c:a aac -b:a 192k -shortest \
    $COMMON_OPTS "$OUTPUT"
else
  ffmpeg -fflags +genpts -noautorotate \
    -framerate "$FRAMERATE" -start_number 1 -i "$INPUT_PATH" \
    -filter_complex "$FILTER_COMPLEX" \
    -map "[out]" \
    $COMMON_OPTS "$OUTPUT"
fi

echo "✅ Forced 1280x720 video saved as: $OUTPUT"


