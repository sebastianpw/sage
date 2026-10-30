#!/bin/bash

# Get the directory of this shell script dynamically
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Run the corresponding PHP script
php "$SCRIPT_DIR/wtf_advance_runs.php"

