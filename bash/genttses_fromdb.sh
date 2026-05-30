#!/bin/bash

# Usage: ./genttses_fromdb.sh
# Iterates documentations marked for audio regeneration (regenerate_audios=1)
# Uses Qwen3-TTS Base Model with Voice Cloning (ICL Mode).

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MYSQL_ARGS=$("$SCRIPT_DIR"/db_name.sh main-conn)

# 1. Resolve Project Root
if [ -f "$SCRIPT_DIR/load_root.sh" ]; then
  source "$SCRIPT_DIR/load_root.sh"
fi

# 2. PHP Helper for Text Extraction
PHP_HELPER="$SCRIPT_DIR/_tmp_qwen_get_md.php"

cat << 'EOF' > "$PHP_HELPER"
<?php
ob_start();
require_once __DIR__ . '/../public/bootstrap.php';
require __DIR__ . '/../public/env_locals.php';
error_reporting(0);
ini_set('display_errors', '0');

$docId = $argv[1] ?? 0;
if (!$docId) { ob_end_clean(); exit; }

$stmt = $pdo->prepare("SELECT content FROM documentations WHERE id = ?");
$stmt->execute([$docId]);
$doc = $stmt->fetch(PDO::FETCH_ASSOC);

$finalText = "";
if ($doc && !empty($doc['content'])) {
    $parsedown = new Parsedown();
    $html = $parsedown->text($doc['content']);
    $html = str_replace(['</p>', '<br>', '</h1>', '</h2>', '</div>'], "\n", $html);
    $text = strip_tags($html);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5);
    $finalText = trim(preg_replace('/\n\s*\n/', "\n\n", $text));
}
ob_end_clean();
echo $finalText;
EOF

# ------------------------------------------------------------------
# CONFIGURATION
# ------------------------------------------------------------------

# 1. The Audio File (Filename in qwen3tts_voices)

#VOICE_FILE="hal-9000.wav"
#VOICE_FILE="09_warning_from_the_stars_cocking_rd_64kb44100.wav"
#VOICE_FILE="08_toyshop_harrison_cs_64kb44100.wav"
#VOICE_FILE="07_repairman_harrison_ae_64kb44100.wav"
#VOICE_FILE="fujikura-uruka.wav"
#VOICE_FILE="martin-sheen.wav"
#VOICE_FILE="06_outaroundrigel_wilson_ae_64kb44100.wav"
#VOICE_FILE="01_4ddoodler_waldeyer_edm_64kb44100.wav"
#VOICE_FILE="hal-9000.wav"
#VOICE_FILE="agent-47.wav"
#VOICE_FILE="03_imageofthegods_nourse_jk_64kb44100.wav"
#VOICE_FILE="04_martianvfw_vandenburg_cy_64kb44100.wav"
#VOICE_FILE="02_bread_overhead_leiber_ms_64kb44100.wav"
VOICE_FILE="05_oneshot_blish_r_64kb44100.wav"



# 2. The Transcript (REQUIRED for high quality). 
# If left empty "", the system will use lower quality X-Vector cloning.
#VOICE_TEXT="Shadows of shadows passing. It is now 1831 and as always I am absorbed by the delicate thought that music is essential, for without music or an intriguing idea everything becomes a carcass."

VOICE_TEXT=""

# 3. Map Run Info
MAP_RUN_ID=$(mysql $MYSQL_ARGS -N -e "INSERT INTO map_runs (entity_type, note) VALUES ('documentations', 'Qwen3 Clone: $VOICE_FILE'); SELECT LAST_INSERT_ID();")
echo "Created Map Run ID: $MAP_RUN_ID using voice: $VOICE_FILE"

# 4. Get IDs
declare -a IDS
while IFS=$'\t' read -r id; do
  IDS+=("$id")
done < <(mysql $MYSQL_ARGS -N -e "SELECT id FROM documentations WHERE regenerate_audios=1")

TOTAL=${#IDS[@]}
echo "Found $TOTAL documents to process."

# 5. Process Loop
for i in "${!IDS[@]}"; do
  DOC_ID="${IDS[$i]}"
  HUMAN_INDEX=$((i+1))
  
  echo "---------------------------------------------------"
  echo "[Qwen3 $HUMAN_INDEX/$TOTAL] Processing Doc ID: $DOC_ID"

  # Fetch Text
  CLEAN_TEXT=$(php "$PHP_HELPER" "$DOC_ID")

  if [ -z "$CLEAN_TEXT" ]; then
    echo "  -> Error: Text empty."
    continue
  fi

  # Call Worker Script
  # Arguments: TEXT | MAP_RUN | TYPE | ID | REF_AUDIO | REF_TEXT
  "$SCRIPT_DIR/gentts_db.sh" "$CLEAN_TEXT" "$MAP_RUN_ID" "documentations" "$DOC_ID" "$VOICE_FILE" "$VOICE_TEXT"

  # Update DB
  mysql $MYSQL_ARGS -e "UPDATE documentations SET active_map_run_id = '$MAP_RUN_ID' WHERE id = '$DOC_ID';"

  # Cool down
  sleep 2
done

# Cleanup
rm -f "$PHP_HELPER"
echo "Done."
