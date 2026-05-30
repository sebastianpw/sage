#!/bin/bash
# trigger_restart.sh
# Based on hotspot_echo.sh
# Finds the tablet and triggers the remote restart endpoint on port 8010

# ---------------------------
# Configuration
# ---------------------------
RESTARTER_PORT="8010"
MAIN_PORT="8009"

echo "🔍 Detecting hotspot network..."

# Get phone's hotspot IP (Android specific logic from your script)
PHONE_IP=$(ifconfig 2>/dev/null | awk '/ap0:/ {flag=1; next} /flags=/ && flag==1 {flag=0} flag==1 && /inet / {print $2; exit}')

if [ -z "$PHONE_IP" ]; then
    # Fallback for some other Android terminals if ap0 isn't named strictly
    PHONE_IP=$(ifconfig 2>/dev/null | grep -v 127.0.0.1 | awk '/inet / {print $2}' | head -n 1)
fi

if [ -z "$PHONE_IP" ]; then
    echo "❌ Hotspot not active or cannot detect IP"
    exit 1
fi

NETWORK=$(echo $PHONE_IP | cut -d'.' -f1-3)
echo "📱 Phone IP: $PHONE_IP"
echo "🌐 Network range: $NETWORK.0/24"
echo ""

# ---------------------------
# Discovery Logic
# ---------------------------
TABLET_IP=""

echo "Searching for Tablet (Port $RESTARTER_PORT)..."

# 1. Try common IPs first (like .8)
for i in 8; do
    ip="$NETWORK.$i"
    
    # Skip phone
    if [ "$ip" = "$PHONE_IP" ]; then continue; fi
    
    # Quick Ping
    if ping -c 1 -W 1 "$ip" >/dev/null 2>&1; then
        echo -n "   Found device at $ip... "
        
        # Check if Restarter Port (8010) is open
        if timeout 2 bash -c "cat < /dev/null > /dev/tcp/$ip/$RESTARTER_PORT" 2>/dev/null; then
            echo "✅ Restarter Service UP!"
            TABLET_IP="$ip"
            break
        else
            echo "❌ Device online, but Port $RESTARTER_PORT closed."
        fi
    fi
done

# 2. Full scan if common IPs failed
if [ -z "$TABLET_IP" ]; then
    echo ""
    echo "⚠️  Common IPs failed. Scanning range 1-30..."
    for i in {1..30}; do
        ip="$NETWORK.$i"
        if [ "$ip" = "$PHONE_IP" ]; then continue; fi
        
        if timeout 1 bash -c "cat < /dev/null > /dev/tcp/$ip/$RESTARTER_PORT" 2>/dev/null; then
            echo "✅ Found Tablet at $ip"
            TABLET_IP="$ip"
            break
        fi
    done
fi

# ---------------------------
# Execution
# ---------------------------
if [ -z "$TABLET_IP" ]; then
    echo ""
    echo "❌ ERROR: Could not find tablet running Restarter on port $RESTARTER_PORT"
    exit 1
fi

URL="http://${TABLET_IP}:${RESTARTER_PORT}/restart"

echo ""
echo "🚀 Triggering Restart Sequence..."
echo "   Target: $URL"

# Send POST request
RESPONSE=$(curl -s -X POST "$URL")

# Check if curl succeeded
if [ $? -eq 0 ]; then
    echo ""
    echo "✅ Success! Server response:"
    echo "   $RESPONSE"
else
    echo ""
    echo "❌ Failed to send restart command."
fi
