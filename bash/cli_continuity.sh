#!/bin/bash

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

exec php "$SCRIPT_DIR/../public/cli_continuity.php"
