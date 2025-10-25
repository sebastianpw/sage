#!/bin/bash
# Fancy Cloudflare tunnel launcher (port 80) with colors, banner & Ctrl+C hint

PORT=80

# Colors
GREEN="\033[1;32m"
CYAN="\033[1;36m"
YELLOW="\033[1;33m"
RESET="\033[0m"

# Banner header
echo -e "${CYAN}"
echo "╔══════════════════════════════════════════════════╗"
echo "║            🚀 Cloudflare Tunnel Live             ║"
echo "╚══════════════════════════════════════════════════╝"
echo -e "${RESET}"
echo -e "${YELLOW}🌐 Tunneling 127.0.0.1:$PORT ...${RESET}"
echo

# Flag to print URL only once
URL_PRINTED=false

# Start cloudflared in foreground and filter URL
cloudflared tunnel --url http://127.0.0.1:$PORT 2>&1 | while IFS= read -r line; do
    if [[ "$line" =~ https://[-a-z0-9]+\.trycloudflare\.com ]] && [ "$URL_PRINTED" = false ]; then
        echo
        echo -e "${GREEN}╔══════════════════════════════════════════════════╗${RESET}"
        echo -e "${GREEN}🌟 Your app is live at:${RESET}"
        echo -e "${CYAN}${BASH_REMATCH[0]}${RESET}"
        echo -e "${GREEN}╚══════════════════════════════════════════════════╝${RESET}"
        echo -e "${YELLOW}🛑 Keep this terminal open to keep the tunnel alive!${RESET}"
        echo
        echo "Press Ctrl+C to close the tunnel."
        echo
        URL_PRINTED=true
    fi
done
