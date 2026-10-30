#!/bin/bash

# Get the directory of this shell script dynamically
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Run the corresponding PHP script, forwarding all command-line arguments
php "$SCRIPT_DIR/wrish_run.php" "$@"


