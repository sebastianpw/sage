#!/bin/bash

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

php "$SCRIPT_DIR/../public/cli_rapid_gen.php"


