#!/bin/bash

# Backup folder
BACKUP_DIR="/storage/emulated/0/Download/public"

# Timestamp for ZIP file
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
ZIP_FILE="/storage/emulated/0/Download/public_$TIMESTAMP.zip"

# Ensure backup folder exists
mkdir -p "$BACKUP_DIR"

# Remove old contents (keep for temporary storage)
rm -rf "$BACKUP_DIR"/*

# Copy top-level files from current folder
cp * "$BACKUP_DIR/."

# Copy only selected subfolders (preserve structure)
#
# TODO: folders imglab skeleton WITHOUT images
for folder in js css docs adminer imglab vendor amplitude content mannequin skeleton posts; do
    if [ -d "$folder" ]; then
        cp -r "$folder" "$BACKUP_DIR/"
    fi
done



# Copy ../src recursively into backup
if [ -d "../src" ]; then
    mkdir -p "$BACKUP_DIR/src"
    cp -r ../src/* "$BACKUP_DIR/src/"
fi



# Create ZIP preserving folder structure
cd /storage/emulated/0/Download/ || exit
zip -r "$ZIP_FILE" public





