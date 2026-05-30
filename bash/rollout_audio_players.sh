#!/bin/bash

# Define the project root
PROJECT_ROOT="$(dirname "$(dirname "$(readlink -f "$0")")")"
TEMPLATE_FILE="$PROJECT_ROOT/public/player_audio_template.php"

# List of Audio Entities
ENTITIES=(
    "audio_ambiences"
    "audio_cues"
    "audio_dialogue_lines"
    "audio_foleys"
    "audio_fxsounds"
    "audio_themes"
)

echo "Starting Audio Player rollout..."

if [ ! -f "$TEMPLATE_FILE" ]; then
    echo "Error: Template file not found at $TEMPLATE_FILE"
    exit 1
fi

for entity in "${ENTITIES[@]}"; do
    # Creates /public/player_audio_[entity].php
    TARGET_FILE="$PROJECT_ROOT/public/player_${entity}.php"
    
    echo "Generating $TARGET_FILE..."
    
    cp "$TEMPLATE_FILE" "$TARGET_FILE"
    
    # Replace placeholder
    sed -i "s|\[\[ENTITY_NAME\]\]|$entity|g" "$TARGET_FILE"
done

echo "Rollout complete."
