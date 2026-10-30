#!/bin/bash

# Usage: ./genttses_fromdb.sh
# Iterates documentations marked for audio regeneration (regenerate_audios=1)
# Uses PHP CLI with Output Buffering to prevent PHP Warnings from leaking into Audio
# Uses Pollinations API for synchronous TTS generation.

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MYSQL_ARGS=$("$SCRIPT_DIR"/db_name.sh main-conn)

# 1. Resolve Project Root
if [ -f "$SCRIPT_DIR/load_root.sh" ]; then
  source "$SCRIPT_DIR/load_root.sh"
fi
if [ -z "$PROJECT_ROOT" ]; then
   PROJECT_ROOT="$(cd "$(dirname "$SCRIPT_DIR")" && pwd)"
fi

# 2. PHP Helper for Text Extraction (using output buffering to silence warnings)
PHP_HELPER="$SCRIPT_DIR/_tmp_gentts_get_md.php"

cat << 'EOF' > "$PHP_HELPER"
<?php
// Start buffering immediately to catch any premature output/warnings
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
    $html = str_replace(['</p>', '<br>', '<br />', '</h1>', '</h2>', '</h3>', '</h4>', '</h5>', '</h6>', '</li>', '</div>', '</blockquote>', '</pre>'], "\n", $html);
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

# Note: The MODEL and VOICE configurations are now placed at the top of 
# the worker script (gentts_db.sh) for easy adjustment.

# 3. Map Run Info
MAP_RUN_ID=$(mysql $MYSQL_ARGS -N -e "INSERT INTO map_runs (entity_type, note) VALUES ('documentations', 'Pollinations TTS Sync'); SELECT LAST_INSERT_ID();")
echo "Created Map Run ID: $MAP_RUN_ID"

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
  echo "[Pollinations TTS $HUMAN_INDEX/$TOTAL] Processing Doc ID: $DOC_ID"

  # Fetch Text
  CLEAN_TEXT=$(php "$PHP_HELPER" "$DOC_ID")

  if [ -z "$CLEAN_TEXT" ]; then
    echo "  -> Error: Text empty or extraction failed."
    continue
  fi

  # Call Worker Script
  # Arguments: TEXT | MAP_RUN | TYPE | ID
  "$SCRIPT_DIR/gentts_db.sh" "$CLEAN_TEXT" "$MAP_RUN_ID" "documentations" "$DOC_ID"

  # Update DB
  mysql $MYSQL_ARGS -e "UPDATE documentations SET active_map_run_id = '$MAP_RUN_ID' WHERE id = '$DOC_ID';"

  # Cool down between standard API requests
  sleep 2
done

# Cleanup
rm -f "$PHP_HELPER"
echo "Done."
