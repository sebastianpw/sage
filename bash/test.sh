#!/bin/bash

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
KAGGLE_DIR="$SCRIPT_DIR/../token/.kaggle"
KAGGLE_FILE="$KAGGLE_DIR/kaggle.json"

# Ensure folder exists and is writable
mkdir -p "$KAGGLE_DIR"
chmod 777 "$KAGGLE_DIR"

# Use PHP CLI to read/write the JSON directly
php <<PHP_CODE
<?php
\$kaggleFile = '$KAGGLE_FILE';

if (file_exists(\$kaggleFile)) {
    \$json = file_get_contents(\$kaggleFile);
    if (json_decode(\$json) === null) {
        \$json = '{}';
    }
} else {
    \$json = '{}';
}

// Rewrite file with full permissions
file_put_contents(\$kaggleFile, \$json);
chmod(\$kaggleFile, 0777);
echo "Kaggle JSON restored at \$kaggleFile\n";
?>
PHP_CODE
