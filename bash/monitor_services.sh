#!/bin/bash

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

while true; do
    "$SCRIPT_DIR/service_monitor.sh"
    sleep 30
done

