#!/bin/bash
# ==============================================================================
# SAGE MultiVid Offline Render Client
# Rewritten cleanly to eliminate "curly errors" and separate Status from Download
# ==============================================================================

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# ── DB connection ──────────────────────────────────────────────────────────────
MYSQL_ARGS=$("$SCRIPT_DIR"/db_name.sh main-conn)

# ── Environment ───────────────────────────────────────────────────────────────
if [ -f "$SCRIPT_DIR/load_root.sh" ]; then source "$SCRIPT_DIR/load_root.sh"; fi
if [ -z "$PROJECT_ROOT" ]; then echo "ERROR: PROJECT_ROOT not set"; exit 1; fi

TABLET_PYAPI_URL=""
if [ -f "$SCRIPT_DIR/pyapi_echo.sh" ]; then
    TABLET_PYAPI_URL=$(bash "$SCRIPT_DIR/pyapi_echo.sh" 2>/dev/null)
fi
if [ -z "$TABLET_PYAPI_URL" ]; then
    TABLET_PYAPI_URL="${SAGE_TABLET_PYAPI_URL:-http://127.0.0.1:8010}"
fi

echo "Tablet PyAPI: $TABLET_PYAPI_URL"

JOB_ID="$1"
if [ -z "$JOB_ID" ]; then
    echo "Usage: $0 JOB_ID"
    exit 1
fi

mysql $MYSQL_ARGS -e "UPDATE multivid_render_jobs SET status='processing', updated_at=NOW() WHERE id=$JOB_ID"

fail() {
    local MSG="$1"
    echo "FAIL: $MSG"
    mysql $MYSQL_ARGS -e "UPDATE multivid_render_jobs SET status='failed', error_msg='$(echo "$MSG" | sed "s/'//g")', updated_at=NOW() WHERE id=$JOB_ID"
    exit 1
}

# ── 1. Fetch data ──────────────────────────────────────────────────────────
read ANIMATIC_ID ARRANGEMENT_ID < <(mysql $MYSQL_ARGS -N -e \
    "SELECT animatic_id, arrangement_id FROM multivid_render_jobs WHERE id=$JOB_ID LIMIT 1")
[ -z "$ANIMATIC_ID" ] && fail "Job $JOB_ID not found"
[ -z "$ARRANGEMENT_ID" ] && fail "No arrangement_id on job $JOB_ID"

echo "Animatic: $ANIMATIC_ID | Arrangement: $ARRANGEMENT_ID"

read DUR_MS FPS MOVE_X MOVE_Y ZOOM_START ZOOM_END CANVAS_W CANVAS_H < <(mysql $MYSQL_ARGS -N -e "
    SELECT
        COALESCE(duration_ms,3000), COALESCE(fps,30),
        COALESCE(move_x,80), COALESCE(move_y,0),
        COALESCE(zoom_start,1.0), COALESCE(zoom_end,1.04),
        COALESCE(canvas_width,1024), COALESCE(canvas_height,1024)
    FROM multivid_settings WHERE animatic_id=$ANIMATIC_ID LIMIT 1")

DUR_MS=${DUR_MS:-3000}; FPS=${FPS:-30}; MOVE_X=${MOVE_X:-80}; MOVE_Y=${MOVE_Y:-0}
ZOOM_START=${ZOOM_START:-1.0}; ZOOM_END=${ZOOM_END:-1.04}
CANVAS_W=${CANVAS_W:-1024}; CANVAS_H=${CANVAS_H:-1024}

FRAMES=$(awk "BEGIN{printf \"%d\", ($DUR_MS / 1000) * $FPS}")
DURATION_S=$(awk "BEGIN{printf \"%.3f\", $DUR_MS / 1000}")

LAYER_CONFIG=$(mysql $MYSQL_ARGS -N -e "SELECT COALESCE(layer_config,'{}') FROM multivid_arrangements WHERE id=$ARRANGEMENT_ID LIMIT 1")
[ -z "$LAYER_CONFIG" ] && LAYER_CONFIG="{}"

SQL_LAYERS="
    SELECT
        CONCAT('$PROJECT_ROOT/public/', f.filename) as abs_path, 'frame' as asset_type, f.id as asset_id,
        COALESCE(ml.speed, 0.5), COALESCE(ml.z_index, 0), COALESCE(ml.opacity, 1.0), COALESCE(ml.start_offset, 0.0), COALESCE(ml.end_offset, -1), COALESCE(ml.playback_speed, 1.0), CONCAT('frame_', f.id)
    FROM animatic_frames af JOIN frames f ON af.frame_id = f.id LEFT JOIN multivid_layers ml ON (ml.animatic_id=af.animatic_id AND ml.asset_type='frame' AND ml.asset_id=f.id) WHERE af.animatic_id = $ANIMATIC_ID
    UNION ALL
    SELECT
        CONCAT('$PROJECT_ROOT/public/', v.url) as abs_path, 'video' as asset_type, v.id as asset_id,
        COALESCE(ml.speed, 0.7), COALESCE(ml.z_index, 50), COALESCE(ml.opacity, 1.0), COALESCE(ml.start_offset, 0.0), COALESCE(ml.end_offset, -1), COALESCE(ml.playback_speed, 1.0), CONCAT('video_', v.id)
    FROM animatic_videos av JOIN videos v ON av.video_id = v.id LEFT JOIN multivid_layers ml ON (ml.animatic_id=av.animatic_id AND ml.asset_type='video' AND ml.asset_id=v.id) WHERE av.animatic_id = $ANIMATIC_ID
    ORDER BY 5 ASC
"

declare -a LAYER_PATHS
declare -a LAYER_KEYS
declare -a LAYER_META

HAS_FILES=0
while IFS=$'\t' read -r abs_path asset_type asset_id speed z_index opacity start_off end_off pb_speed layer_key; do
    if [ ! -f "$abs_path" ]; then continue; fi
    HAS_FILES=1
    LAYER_PATHS+=("$abs_path")
    LAYER_KEYS+=("$layer_key")
    LAYER_META+=("{\"key\":\"$layer_key\",\"asset_type\":\"$asset_type\",\"asset_id\":$asset_id,\"speed\":$speed,\"z_index\":$z_index,\"opacity\":$opacity,\"start_offset\":$start_off,\"end_offset\":$end_off,\"playback_speed\":$pb_speed}")
done < <(mysql $MYSQL_ARGS -N -e "$SQL_LAYERS")
[ "$HAS_FILES" -eq 0 ] && fail "No valid layer files found for animatic $ANIMATIC_ID"
LAYERS_META_JSON="[$(IFS=,; echo "${LAYER_META[*]}")]"

# ── 2. POST to API ──────────────────────────────────────────────────────────
echo "Uploading to PyAPI..."
CURL_ARGS=()
for idx in "${!LAYER_PATHS[@]}"; do
    path="${LAYER_PATHS[$idx]}"
    key="${LAYER_KEYS[$idx]}"
    CURL_ARGS+=("-F" "files=@${path};filename=${key}.${path##*.}")
done

CREATE_RESP=$(curl -s --max-time 120 -X POST "$TABLET_PYAPI_URL/multivid/compose-async" \
    "${CURL_ARGS[@]}" \
    -F "layers_meta=$LAYERS_META_JSON" \
    -F "arrangement_config=$(echo "$LAYER_CONFIG" | head -c 65000)" \
    -F "frames=$FRAMES" -F "fps=$FPS" -F "move_x=$MOVE_X" -F "move_y=$MOVE_Y" \
    -F "zoom_start=$ZOOM_START" -F "zoom_end=$ZOOM_END" \
    -F "canvas_w=$CANVAS_W" -F "canvas_h=$CANVAS_H")

TASK_ID=$(echo "$CREATE_RESP" | python3 -c "import sys,json; print(json.load(sys.stdin).get('task_id',''))" 2>/dev/null | tr -d '\r\n')
[ -z "$TASK_ID" ] && fail "Failed to get task_id. API Response: $CREATE_RESP"

mysql $MYSQL_ARGS -e "UPDATE multivid_render_jobs SET task_id='$TASK_ID', updated_at=NOW() WHERE id=$JOB_ID"
echo "Task queued: $TASK_ID"

# ── 3. File setup ──────────────────────────────────────────────────────────
VIDEOS_DIR="$PROJECT_ROOT/public/videos"
THUMBS_DIR="$VIDEOS_DIR/thumbnails"
mkdir -p "$VIDEOS_DIR" "$THUMBS_DIR"

video_basename=$(mysql $MYSQL_ARGS -N --batch --skip-column-names -e "
  UPDATE video_counter SET next_video = LAST_INSERT_ID(next_video + 1) ORDER BY next_video DESC LIMIT 1;
  SELECT LPAD(LAST_INSERT_ID(), 7, '0');
")
[ -z "$video_basename" ] && fail "Failed DB video counter allocation"
VIDEO_FILENAME="video${video_basename}.mp4"
THUMB_FILENAME="video${video_basename}.jpg"
VIDEO_ABS="$VIDEOS_DIR/$VIDEO_FILENAME"
THUMB_ABS="$THUMBS_DIR/$THUMB_FILENAME"

# ── 4. Clean JSON Polling ─────────────────────────────────────────────────
MAX_POLLS=120
POLL_OK=0
echo "Polling JSON status..."

for (( i=1; i<=MAX_POLLS; i++ )); do
    sleep 3

    # 1. Ask for JSON Status
    STATUS_JSON=$(curl -s --max-time 10 "$TABLET_PYAPI_URL/multivid/status/$TASK_ID")
    if [ $? -ne 0 ] || [ -z "$STATUS_JSON" ]; then
        echo "  Warning: curl network fail. Retrying..."
        continue
    fi

    STATUS=$(echo "$STATUS_JSON" | python3 -c "import sys,json; print(json.load(sys.stdin).get('status','unknown'))" 2>/dev/null || echo "unknown")

    if [ "$STATUS" = "completed" ]; then
        echo "✓ Render complete! Downloading final MP4..."
        # 2. Status is completed! Do a pure clean download.
        HTTP_CODE=$(curl -s -w "%{http_code}" -o "$VIDEO_ABS" "$TABLET_PYAPI_URL/multivid/download/$TASK_ID")

        if [ "$HTTP_CODE" = "200" ]; then
            POLL_OK=1
            break
        else
            fail "Render completed but download failed with HTTP code $HTTP_CODE"
        fi

    elif [ "$STATUS" = "failed" ]; then
        ERR=$(echo "$STATUS_JSON" | python3 -c "import sys,json; print(json.load(sys.stdin).get('error','unknown err'))" 2>/dev/null)
        fail "PyAPI failed: $ERR"

    elif [[ "$STATUS" == "processing" || "$STATUS" == "queued" ]]; then
        echo "  [$i/$MAX_POLLS] Processing..."
    else
        echo "  [$i/$MAX_POLLS] Unknown status: $STATUS_JSON"
    fi
done

[ "$POLL_OK" -eq 0 ] && { rm -f "$VIDEO_ABS"; fail "Timed out waiting for render"; }

# ── 5. Wrap up ────────────────────────────────────────────────────────────
if command -v ffmpeg &>/dev/null; then
    ffmpeg -y -i "$VIDEO_ABS" -ss 00:00:01 -vframes 1 -vf "scale=320:180:force_original_aspect_ratio=decrease,pad=320:180:(ow-iw)/2:(oh-ih)/2" "$THUMB_ABS" &>/dev/null || \
    ffmpeg -y -i "$VIDEO_ABS" -vframes 1 "$THUMB_ABS" &>/dev/null
fi

FILE_SIZE=$(stat -c%s "$VIDEO_ABS" 2>/dev/null || echo 0)
VIDEO_ID=$(mysql $MYSQL_ARGS -N -e "
    INSERT INTO videos (name, description, url, thumbnail, duration, type, file_size, width, height, created_at)
    VALUES ('$VIDEO_FILENAME', 'MultiVid Render (#$ANIMATIC_ID)', 'videos/$VIDEO_FILENAME', 'videos/thumbnails/$THUMB_FILENAME', $DURATION_S, 'video/mp4', $FILE_SIZE, $CANVAS_W, $CANVAS_H, NOW());
    SELECT LAST_INSERT_ID();
")

[ -z "$VIDEO_ID" ] || [ "$VIDEO_ID" -eq 0 ] && fail "Failed to register video"

mysql $MYSQL_ARGS -e "INSERT INTO videos_2_animatics (from_id, to_id) VALUES ($VIDEO_ID, $ANIMATIC_ID)"
mysql $MYSQL_ARGS -e "UPDATE multivid_render_jobs SET status='completed', video_id=$VIDEO_ID, updated_at=NOW() WHERE id=$JOB_ID"

echo "══════════════════════════════════════════"
echo " MultiVid Render Complete | Video #$VIDEO_ID"
echo " File: $VIDEO_FILENAME"
echo "══════════════════════════════════════════"
exit 0
