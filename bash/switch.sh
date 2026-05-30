# bash/switch.sh

#!/bin/bash
# ==============================================================================
# switch.sh — Select which model the manual queue worker uses
# 
# Replaces the legacy symlink system. Now updates the database configuration
# for the 'manual' worker scope so the UI and queue sync seamlessly.
# ==============================================================================

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MYSQL_ARGS=$("$SCRIPT_DIR"/db_name.sh main-conn)

MODEL_NAME="$1"

if [ -z "$MODEL_NAME" ]; then
    echo "Usage: $0 [model_name]"
    echo "Example: $0 flux"
    echo "Example: $0 nanobanana"
    echo "Example: $0 gptimage"
    echo ""
    
    # Fetch and display current manual model
    CURRENT=$(mysql $MYSQL_ARGS -N -e "SELECT COALESCE(model_override, 'endpoint default (blank)') FROM worker_img_provider_default WHERE scope='manual' LIMIT 1;")
    echo "Current manual model: ${CURRENT:-not set}"
    exit 1
fi

SAFE_MODEL=$(echo "$MODEL_NAME" | sed "s/'/''/g")

# Update the manual scope in the DB. If the row doesn't exist yet, we insert it 
# and point it to endpoint_id=1 (Pollinations GET) by default.
mysql $MYSQL_ARGS -e "
  INSERT INTO worker_img_provider_default (scope, endpoint_id, model_override)
  VALUES ('manual', 1, '$SAFE_MODEL')
  ON DUPLICATE KEY UPDATE model_override = '$SAFE_MODEL';
"

echo "✅ Switched manual worker model to: $MODEL_NAME"