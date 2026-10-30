#!/bin/bash
# bash/sqlish_run.sh
# Get the directory of this shell script dynamically
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# Run the corresponding PHP script, forwarding all command-line arguments
php "$SCRIPT_DIR/sqlish_run.php" "$@"
