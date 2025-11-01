#!/usr/bin/env bash
set -euo pipefail

# Variables
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PHP_MYADMIN_DIR="$SCRIPT_DIR/../phpmyadmin"
PHP_MYADMIN_VERSION="5.2.1"

# Function to create symlinks
create_symlinks() {
    echo "Creating symbolic links…"
    ln -sfn "$PHP_MYADMIN_DIR" /var/www/sage/public/admin
    ln -sfn /var/www/sage/config/config.inc.php "$PHP_MYADMIN_DIR/config.inc.php"
    chmod 755 "$PHP_MYADMIN_DIR/config.inc.php"
    chmod 755 /var/www/sage/config/config.inc.php
}

# Install phpMyAdmin
if [ ! -d "$PHP_MYADMIN_DIR" ] || [ -z "$(ls -A "$PHP_MYADMIN_DIR" 2>/dev/null)" ]; then
    echo "Downloading full phpMyAdmin release (all-languages)…"
    rm -rf "$PHP_MYADMIN_DIR"
    mkdir -p "$PHP_MYADMIN_DIR"

    curl -L "https://files.phpmyadmin.net/phpMyAdmin/${PHP_MYADMIN_VERSION}/phpMyAdmin-${PHP_MYADMIN_VERSION}-all-languages.tar.gz" \
         | tar xz --strip-components=1 -C "$PHP_MYADMIN_DIR"
fi

# Create symlinks
create_symlinks

echo "phpMyAdmin installation complete."
