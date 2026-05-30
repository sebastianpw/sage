#!/bin/bash

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

QJOBS="$1"
if [[ "$QJOBS" =~ ^[0-9]+$ ]]; then
  shift
else
  QJOBS=1
fi

exec php "$SCRIPT_DIR/../public/cli_lore_to_sketch_generator_db.php" --qjobs="$QJOBS" "$@"
