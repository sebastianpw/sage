#!/bin/bash

# Backup folder
BACKUP_DIR="/storage/emulated/0/Download/adminer"

# Timestamp for ZIP file
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
ZIP_FILE="/storage/emulated/0/Download/adminer_$TIMESTAMP.zip"

# Ensure backup folder exists
mkdir -p "$BACKUP_DIR"

# Remove old contents (keep for temporary storage)
rm -rf "$BACKUP_DIR"/*

# Copy top-level files from current folder
cp * "$BACKUP_DIR/."

# Create ZIP (flattened, as before)
zip -j "$ZIP_FILE" "$BACKUP_DIR"/*



