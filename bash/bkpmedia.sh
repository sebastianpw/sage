#!/bin/bash
# bash/bkpmedia.sh
# Create incremental media backups, copy to 'sage_backup/media/' on tablet, verify, and log in DB.
#
# Features:
#  - Auto-detects Tablet IP (via hotspot/ap0 scanning)
#  - Prompts for SSH password ONCE at the start (Shared Connection).
#  - Shows progress for Tar creation and SCP transfer.
#  - Renames files locally before transfer.
#  - Validates tar integrity immediately after creation.
#  - Persists filenames to DB immediately after rename (before transfer).
#  - Resumes interrupted transfers if local tar files are still intact.
#
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# helpers
MYSQL_ARGS=$("$SCRIPT_DIR/db_name.sh" main-conn)
source "$SCRIPT_DIR/load_root.sh"

OUT_DIR="$PROJECT_ROOT/public"
TMPDIR="$HOME/.cache/spw_backups"
mkdir -p "$OUT_DIR" "$TMPDIR"

TS=$(date +%y%m%d_%H%M%S)
TMP_PREFIX="bkpmedia_${TS}"

# ------------------------------------------------------------------------------
# 1. INTEGRATED TABLET DETECTION
# ------------------------------------------------------------------------------

detect_tablet_ip() {
    echo "Detecting hotspot network..." >&2

    local phone_ip=$(ifconfig 2>/dev/null | awk '/ap0:/ {flag=1; next} /flags=/ && flag==1 {flag=0} flag==1 && /inet / {print $2; exit}' || true)

    if [ -z "$phone_ip" ]; then
        phone_ip=$(ip -4 addr show ap0 2>/dev/null | awk '/inet / {print $2}' | cut -d/ -f1 || true)
    fi

    if [ -z "$phone_ip" ]; then
        echo "Error: Hotspot (ap0) not active or IP not found." >&2
        return 1
    fi

    local network=$(echo "$phone_ip" | cut -d'.' -f1-3)
    local target_port="8022"
    local found_ip=""

    check_port() {
        local ip=$1
        if timeout 1 bash -c "cat < /dev/null > /dev/tcp/$ip/$target_port" 2>/dev/null; then
            return 0
        else
            return 1
        fi
    }

    for i in 8; do
        local target="$network.$i"
        if [ "$target" = "$phone_ip" ]; then continue; fi
        if ping -c 1 -W 1 "$target" >/dev/null 2>&1; then
            if check_port "$target"; then
                found_ip="$target"
                break
            fi
        fi
    done

    if [ -z "$found_ip" ]; then
        echo "Scanning network range ($network.1-30)..." >&2
        for i in {1..30}; do
            local target="$network.$i"
            if [ "$target" = "$phone_ip" ]; then continue; fi
            if check_port "$target"; then
                found_ip="$target"
                break
            fi
        done
    fi

    if [ -n "$found_ip" ]; then
        echo "$found_ip"
        return 0
    else
        return 1
    fi
}

# ------------------------------------------------------------------------------
# 2. PRE-FLIGHT: Connect & Authenticate
# ------------------------------------------------------------------------------

echo "--- Pre-flight Check ---"

TABLET_IP=$(detect_tablet_ip) || { echo "Could not find tablet."; exit 1; }
echo "Found Tablet at: $TABLET_IP"

SSH_SOCKET="$TMPDIR/ssh_socket_${TS}"
echo "Establishing secure connection..."
echo "(You will be asked for the password ONCE now)"

if ssh -p 8022 -M -S "$SSH_SOCKET" -fN -o ControlPersist=55m "$TABLET_IP"; then
    echo "Authentication successful. Connection locked."
else
    echo "Authentication failed."
    exit 1
fi
echo "--------------------------------------------------------"
echo ""

ssh_cmd() {
    ssh -p 8022 -S "$SSH_SOCKET" "$TABLET_IP" "$@"
}
scp_cmd() {
    scp -P 8022 -o ControlPath="$SSH_SOCKET" "$@"
}

mysql_scalar(){ mysql $MYSQL_ARGS -N -e "$1" 2>/dev/null || true; }

# ------------------------------------------------------------------------------
# 2.5. RESUME CHECK - Pick up any pending backup with intact local files
# ------------------------------------------------------------------------------

echo "--- Checking for resumable backup ---"

RESUME_ID=$(mysql_scalar "SELECT id FROM backups_media WHERE status='pending' ORDER BY id DESC LIMIT 1;")

SKIP_TO_TRANSFER=false
BACKUP_ID=""
FRAMES_FINAL=""
AUDIOS_FINAL=""
VIDEOS_FINAL=""
FRAMES_SHA=""
AUDIOS_SHA=""
VIDEOS_SHA=""

if [ -n "$RESUME_ID" ]; then
    echo "Found pending backup ID=$RESUME_ID -- checking local files..."

    R_FRAMES_TAR=$(mysql_scalar "SELECT COALESCE(frames_tar,'')    FROM backups_media WHERE id=$RESUME_ID;")
    R_AUDIOS_TAR=$(mysql_scalar "SELECT COALESCE(audios_tar,'')    FROM backups_media WHERE id=$RESUME_ID;")
    R_VIDEOS_TAR=$(mysql_scalar "SELECT COALESCE(videos_tar,'')    FROM backups_media WHERE id=$RESUME_ID;")
    R_FRAMES_SHA=$(mysql_scalar "SELECT COALESCE(frames_sha256,'') FROM backups_media WHERE id=$RESUME_ID;")
    R_AUDIOS_SHA=$(mysql_scalar "SELECT COALESCE(audios_sha256,'') FROM backups_media WHERE id=$RESUME_ID;")
    R_VIDEOS_SHA=$(mysql_scalar "SELECT COALESCE(videos_sha256,'') FROM backups_media WHERE id=$RESUME_ID;")

    resume_verify_local() {
        local filename="$1"; local expected_sha="$2"
        [ -z "$filename" ] && return 0
        local path="$OUT_DIR/$filename"
        if [ ! -f "$path" ]; then
            echo "  MISSING: $filename"
            return 1
        fi
        echo -n "  Verifying $filename ... "
        local actual_sha=$(sha256sum "$path" | awk '{print $1}')
        if [ "$actual_sha" = "$expected_sha" ]; then
            echo "OK"
            return 0
        else
            echo "FAIL (expected $expected_sha, got $actual_sha)"
            return 1
        fi
    }

    RESUME_OK=true
    resume_verify_local "$R_FRAMES_TAR" "$R_FRAMES_SHA" || RESUME_OK=false
    resume_verify_local "$R_AUDIOS_TAR" "$R_AUDIOS_SHA" || RESUME_OK=false
    resume_verify_local "$R_VIDEOS_TAR" "$R_VIDEOS_SHA" || RESUME_OK=false

    if [ "$RESUME_OK" = true ]; then
        echo "All local files intact -- resuming transfer for backup ID=$RESUME_ID"
        BACKUP_ID="$RESUME_ID"
        FRAMES_FINAL="$R_FRAMES_TAR"
        AUDIOS_FINAL="$R_AUDIOS_TAR"
        VIDEOS_FINAL="$R_VIDEOS_TAR"
        FRAMES_SHA="$R_FRAMES_SHA"
        AUDIOS_SHA="$R_AUDIOS_SHA"
        VIDEOS_SHA="$R_VIDEOS_SHA"
        SKIP_TO_TRANSFER=true
    else
        echo "Local files missing or corrupt -- marking ID=$RESUME_ID as failed, starting fresh."
        mysql $MYSQL_ARGS -e "UPDATE backups_media SET status='failed' WHERE id=$RESUME_ID;"
        SKIP_TO_TRANSFER=false
    fi
else
    echo "No pending backups found -- starting fresh."
fi

echo "--------------------------------------------------------"
echo ""

# ------------------------------------------------------------------------------
# 3. DB & FILE LISTS  (skipped on resume)
# ------------------------------------------------------------------------------

if [ "$SKIP_TO_TRANSFER" = false ]; then

    FRAMES_INSTANCE="$(basename "$FRAMES_ROOT")"
    AUDIOS_INSTANCE="$(basename "$AUDIOS_ROOT")"
    VIDEOS_INSTANCE="$(basename "$VIDEOS_ROOT")"

    FRAMES_SUFFIX="${FRAMES_INSTANCE#frames_}"
    AUDIOS_SUFFIX="${AUDIOS_INSTANCE#audios_}"
    VIDEOS_SUFFIX="${VIDEOS_INSTANCE#videos_}"

    FL_FRAMES="$TMPDIR/${TMP_PREFIX}.frames.txt"
    FL_AUDIOS="$TMPDIR/${TMP_PREFIX}.audios.txt"
    FL_VIDEOS="$TMPDIR/${TMP_PREFIX}.videos.txt"
    : > "$FL_FRAMES" ; : > "$FL_AUDIOS" ; : > "$FL_VIDEOS"

    LAST_FRAMES_MAX=$(mysql_scalar "SELECT COALESCE(frames_max_id,0) FROM backups_media ORDER BY id DESC LIMIT 1;")
    LAST_AUDIOS_MAX=$(mysql_scalar "SELECT COALESCE(audios_max_id,0) FROM backups_media ORDER BY id DESC LIMIT 1;")
    LAST_VIDEOS_MAX=$(mysql_scalar "SELECT COALESCE(videos_max_id,0) FROM backups_media ORDER BY id DESC LIMIT 1;")

    LAST_FRAMES_MAX=${LAST_FRAMES_MAX:-0}
    LAST_AUDIOS_MAX=${LAST_AUDIOS_MAX:-0}
    LAST_VIDEOS_MAX=${LAST_VIDEOS_MAX:-0}

    echo "Base IDs: Frames=$LAST_FRAMES_MAX, Audios=$LAST_AUDIOS_MAX, Videos=$LAST_VIDEOS_MAX"

    mysql $MYSQL_ARGS -N -e "SELECT TRIM(LEADING '/' FROM filename) FROM frames WHERE id > $LAST_FRAMES_MAX ORDER BY id;" > "$FL_FRAMES" || true
    mysql $MYSQL_ARGS -N -e "SELECT TRIM(LEADING '/' FROM filename) FROM audios WHERE id > $LAST_AUDIOS_MAX ORDER BY id;" > "$FL_AUDIOS" || true
    mysql $MYSQL_ARGS -N -e "SELECT TRIM(LEADING '/' FROM url) FROM videos WHERE id > $LAST_VIDEOS_MAX ORDER BY id;" > "$FL_VIDEOS" || true

    sed -i '/^\s*$/d' "$FL_FRAMES" || true
    sed -i '/^\s*$/d' "$FL_AUDIOS" || true
    sed -i '/^\s*$/d' "$FL_VIDEOS" || true

    if [ ! -s "$FL_FRAMES" ] && [ ! -s "$FL_AUDIOS" ] && [ ! -s "$FL_VIDEOS" ]; then
        echo "No new media. Closing connection."
        ssh -S "$SSH_SOCKET" -O exit "$TABLET_IP" 2>/dev/null || true
        exit 0
    fi

    # ----------------------------------------------------------------------------
    # 4. CREATE TARS, VALIDATE & COMPUTE SHA
    # ----------------------------------------------------------------------------

    FRAMES_TAR_TMP="$OUT_DIR/${TMP_PREFIX}.${FRAMES_SUFFIX}.frames.tar"
    AUDIOS_TAR_TMP="$OUT_DIR/${TMP_PREFIX}.${AUDIOS_SUFFIX}.audios.tar"
    VIDEOS_TAR_TMP="$OUT_DIR/${TMP_PREFIX}.${VIDEOS_SUFFIX}.videos.tar"

    create_tar(){
        local filelist="$1"; local outfile="$2"; local label="$3"
        if [ ! -s "$filelist" ]; then return 1; fi

        echo "Creating $label tar..."
        if command -v pv >/dev/null 2>&1; then
            tar -C "$PROJECT_ROOT/public" -cf - -T "$filelist" | pv > "$outfile"
        else
            echo -n "Processing"
            tar -C "$PROJECT_ROOT/public" -cf "$outfile" -T "$filelist" --checkpoint=500 --checkpoint-action=dot
            echo " Done."
        fi

        echo -n "Validating $label tar integrity... "
        if tar -tf "$outfile" > /dev/null 2>&1; then
            echo "OK"
        else
            echo "CORRUPT -- aborting."
            rm -f "$outfile"
            exit 1
        fi

        return 0
    }

    rm -f "$FRAMES_TAR_TMP" "$AUDIOS_TAR_TMP" "$VIDEOS_TAR_TMP"
    create_tar "$FL_FRAMES" "$FRAMES_TAR_TMP" "Frames" || true
    create_tar "$FL_AUDIOS" "$AUDIOS_TAR_TMP" "Audios" || true
    create_tar "$FL_VIDEOS" "$VIDEOS_TAR_TMP" "Videos" || true

    compute_sha_bytes(){
        local f="$1"
        if [ -f "$f" ]; then
            local sha=$(sha256sum "$f" | awk '{print $1}')
            local bytes=$(stat -c%s "$f")
            echo "${sha}|${bytes}"
        else
            echo "|0"
        fi
    }
    FRAMES_META=$(compute_sha_bytes "$FRAMES_TAR_TMP")
    AUDIOS_META=$(compute_sha_bytes "$AUDIOS_TAR_TMP")
    VIDEOS_META=$(compute_sha_bytes "$VIDEOS_TAR_TMP")

    FRAMES_SHA=$(echo "$FRAMES_META" | cut -d'|' -f1)
    FRAMES_BYTES=$(echo "$FRAMES_META" | cut -d'|' -f2)
    AUDIOS_SHA=$(echo "$AUDIOS_META" | cut -d'|' -f1)
    AUDIOS_BYTES=$(echo "$AUDIOS_META" | cut -d'|' -f2)
    VIDEOS_SHA=$(echo "$VIDEOS_META" | cut -d'|' -f1)
    VIDEOS_BYTES=$(echo "$VIDEOS_META" | cut -d'|' -f2)

    # ----------------------------------------------------------------------------
    # 5. DB INSERT (PENDING), RENAME LOCAL & PERSIST FILENAMES
    # ----------------------------------------------------------------------------

    frames_max_new=$(mysql_scalar "SELECT COALESCE(MAX(id), $LAST_FRAMES_MAX) FROM frames WHERE id > $LAST_FRAMES_MAX;")
    audios_max_new=$(mysql_scalar "SELECT COALESCE(MAX(id), $LAST_AUDIOS_MAX) FROM audios WHERE id > $LAST_AUDIOS_MAX;")
    videos_max_new=$(mysql_scalar "SELECT COALESCE(MAX(id), $LAST_VIDEOS_MAX) FROM videos WHERE id > $LAST_VIDEOS_MAX;")

    BACKUP_ID=$(mysql $MYSQL_ARGS -N -e "
      INSERT INTO backups_media
        (status, frames_tar, frames_sha256, frames_bytes, frames_max_id,
         audios_tar, audios_sha256, audios_bytes, audios_max_id,
         videos_tar, videos_sha256, videos_bytes, videos_max_id, note)
      VALUES
        ('pending', NULL,
         $( [ -n "${FRAMES_SHA:-}" ] && printf "'%s'" "$FRAMES_SHA" || printf "NULL" ),
         $( [ -n "${FRAMES_BYTES:-}" ] && printf "%d" "$FRAMES_BYTES" || printf "NULL" ), $frames_max_new,
         NULL,
         $( [ -n "${AUDIOS_SHA:-}" ] && printf "'%s'" "$AUDIOS_SHA" || printf "NULL" ),
         $( [ -n "${AUDIOS_BYTES:-}" ] && printf "%d" "$AUDIOS_BYTES" || printf "NULL" ), $audios_max_new,
         NULL,
         $( [ -n "${VIDEOS_SHA:-}" ] && printf "'%s'" "$VIDEOS_SHA" || printf "NULL" ),
         $( [ -n "${VIDEOS_BYTES:-}" ] && printf "%d" "$VIDEOS_BYTES" || printf "NULL" ), $videos_max_new,
         'auto incremental backup');
      SELECT LAST_INSERT_ID();
    ")

    if [ -z "$BACKUP_ID" ]; then
        echo "DB Error: Failed to insert pending row."
        ssh -S "$SSH_SOCKET" -O exit "$TABLET_IP" 2>/dev/null
        exit 5
    fi
    echo "Backup ID Reserved: $BACKUP_ID"

    rename_local(){
        local src="$1"; local type="$2"; local suffix="$3"
        if [ -f "$src" ]; then
            local final="backup_${BACKUP_ID}_${type}_${suffix}_${TS}.tar"
            mv "$src" "$OUT_DIR/$final"
            echo "$final"
        fi
    }
    FRAMES_FINAL=$( [ -f "$FRAMES_TAR_TMP" ] && rename_local "$FRAMES_TAR_TMP" "frames" "$FRAMES_SUFFIX" || echo "" )
    AUDIOS_FINAL=$( [ -f "$AUDIOS_TAR_TMP" ] && rename_local "$AUDIOS_TAR_TMP" "audios" "$AUDIOS_SUFFIX" || echo "" )
    VIDEOS_FINAL=$( [ -f "$VIDEOS_TAR_TMP" ] && rename_local "$VIDEOS_TAR_TMP" "videos" "$VIDEOS_SUFFIX" || echo "" )

    # Persist filenames to DB immediately -- before transfer starts.
    # Critical: if transfer is interrupted, resume logic needs these names to find local files.
    mysql $MYSQL_ARGS -e "
      UPDATE backups_media SET
        frames_tar = $( [ -n "${FRAMES_FINAL:-}" ] && printf "'%s'" "$FRAMES_FINAL" || printf "NULL" ),
        audios_tar = $( [ -n "${AUDIOS_FINAL:-}" ] && printf "'%s'" "$AUDIOS_FINAL" || printf "NULL" ),
        videos_tar = $( [ -n "${VIDEOS_FINAL:-}" ] && printf "'%s'" "$VIDEOS_FINAL" || printf "NULL" )
      WHERE id=$BACKUP_ID;
    "
    echo "Filenames persisted to DB (id=$BACKUP_ID)."

    rm -f "$FL_FRAMES" "$FL_AUDIOS" "$FL_VIDEOS"

fi # end SKIP_TO_TRANSFER=false block

# ------------------------------------------------------------------------------
# 6. COPY & VERIFY (Using Cached Connection)
# ------------------------------------------------------------------------------

REMOTE_DIR="sage_backup/media"
echo "Preparing remote folder: $REMOTE_DIR"
ssh_cmd "mkdir -p \"$REMOTE_DIR\""

copy_file(){
    local filename="$1"
    [ -z "$filename" ] && return 0
    local path="$OUT_DIR/$filename"
    echo "Copying $filename..."
    scp_cmd "$path" "${TABLET_IP}:${REMOTE_DIR}/${filename}"
    if [ $? -ne 0 ]; then echo "SCP Failed for $filename"; return 1; fi
    return 0
}

[ -n "$FRAMES_FINAL" ] && copy_file "$FRAMES_FINAL"
[ -n "$AUDIOS_FINAL" ] && copy_file "$AUDIOS_FINAL"
[ -n "$VIDEOS_FINAL" ] && copy_file "$VIDEOS_FINAL"

verify_remote(){
    local filename="$1"; local sha="$2"
    [ -z "$filename" ] && return 0
    echo -n "Verifying $filename ... "
    local r_sha=$(ssh_cmd "cd $REMOTE_DIR && sha256sum \"$filename\"" 2>/dev/null | awk '{print $1}')
    if [ "$r_sha" == "$sha" ]; then
        echo "OK"
        return 0
    else
        echo "FAIL (Local: $sha, Remote: $r_sha)"
        return 1
    fi
}

[ -n "$FRAMES_FINAL" ] && verify_remote "$FRAMES_FINAL" "$FRAMES_SHA"
[ -n "$AUDIOS_FINAL" ] && verify_remote "$AUDIOS_FINAL" "$AUDIOS_SHA"
[ -n "$VIDEOS_FINAL" ] && verify_remote "$VIDEOS_FINAL" "$VIDEOS_SHA"

# ------------------------------------------------------------------------------
# 7. FINALIZE
# ------------------------------------------------------------------------------

mysql $MYSQL_ARGS -e "
  UPDATE backups_media SET status='done'
  WHERE id=$BACKUP_ID;
"

# Remove local tar files now that remote is verified
[ -n "$FRAMES_FINAL" ] && rm -f "$OUT_DIR/$FRAMES_FINAL"
[ -n "$AUDIOS_FINAL" ] && rm -f "$OUT_DIR/$AUDIOS_FINAL"
[ -n "$VIDEOS_FINAL" ] && rm -f "$OUT_DIR/$VIDEOS_FINAL"

# Close SSH Connection
ssh -S "$SSH_SOCKET" -O exit "$TABLET_IP" >/dev/null 2>&1 || true

echo "Backup complete (id=$BACKUP_ID)."
exit 0
