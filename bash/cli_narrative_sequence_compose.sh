#!/bin/bash
# bash/cli_narrative_sequence_compose.sh
# Wrapper for the narrative sequence episode composer
#
# Usage:
#   bash/cli_narrative_sequence_compose.sh --seq=146
#   bash/cli_narrative_sequence_compose.sh --seq=146 --rerun
#   bash/cli_narrative_sequence_compose.sh --seq=146 --rerun --model=claude-sonnet-4-6
#   bash/cli_narrative_sequence_compose.sh 2 --qjobs   (pull 2 jobs from forge_jobs queue)

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

QJOBS="$1"
if [[ "$QJOBS" =~ ^[0-9]+$ ]]; then
  shift
else
  QJOBS=1
fi

exec php "$SCRIPT_DIR/../public/cli_narrative_sequence_compose.php" --qjobs="$QJOBS" "$@"
