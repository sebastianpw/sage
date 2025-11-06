#!/bin/bash
# ngrok_tunnel.sh

NGROK_JSON=$(curl -s -H "Authorization: Bearer $NGROK_API_KEY" \
                   -H "Ngrok-Version: 2" \
                   https://api.ngrok.com/tunnels)

echo $(echo "$NGROK_JSON" | jq -r '.tunnels[] | select(.proto=="https") | .public_url' | head -n1)
