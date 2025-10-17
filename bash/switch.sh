#!/bin/bash
# ~/www/spwbase/public/switch.sh

cd ~/www/spwbase/public || exit 1

if [ "$(readlink -f genframe_db.sh)" = "$(readlink -f genframe_db.sh.JUPYTER)" ]; then
    echo "Switching to pollinations.ai version..."
    ln -sf genframe_db.sh.pollinations.ai genframe_db.sh
else
    echo "Switching to JUPYTER version..."
    ln -sf genframe_db.sh.JUPYTER genframe_db.sh
fi
