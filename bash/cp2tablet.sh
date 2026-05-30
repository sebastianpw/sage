#!/bin/bash
# cp2tablet.sh - Copy files/dirs to Termux home via SCP
# Auto-detects tablet IP on hotspot (ap0 interface)

# Termux standard SSH port
PORT="8022"

# ------------------------------------------------------------------------------
# 1. Argument Check
# ------------------------------------------------------------------------------

if [ $# -eq 0 ]; then
    echo "Usage: $0 [-r] <file_or_directory> ..."
    echo "  Example: $0 myfile.txt"
    echo "  Example: $0 -r my_folder"
    exit 1
fi

# ------------------------------------------------------------------------------
# 2. Network Detection (Standalone Logic)
# ------------------------------------------------------------------------------

echo "Detecting hotspot network..."

# Get phone's hotspot IP (looking for ap0 interface)
PHONE_IP=$(ifconfig 2>/dev/null | awk '/ap0:/ {flag=1; next} /flags=/ && flag==1 {flag=0} flag==1 && /inet / {print $2; exit}')

if [ -z "$PHONE_IP" ]; then
    # Fallback to ip command if ifconfig fails or output differs
    PHONE_IP=$(ip -4 addr show ap0 2>/dev/null | awk '/inet / {print $2}' | cut -d/ -f1)
fi

if [ -z "$PHONE_IP" ]; then
    echo "Error: Hotspot (ap0) not active or IP not found."
    exit 1
fi

NETWORK=$(echo $PHONE_IP | cut -d'.' -f1-3)
TABLET_IP=""

# ------------------------------------------------------------------------------
# 3. Scan for Tablet
# ------------------------------------------------------------------------------

# Function to check connectivity to port 8022 (SSH)
check_ssh_port() {
    local ip=$1
    if timeout 1 bash -c "cat < /dev/null > /dev/tcp/$ip/$PORT" 2>/dev/null; then
        return 0
    else
        return 1
    fi
}

# 3a. Check common specific IPs first (e.g., .8)
for i in 8; do
    target="$NETWORK.$i"
    if [ "$target" = "$PHONE_IP" ]; then continue; fi
    
    # Ping first
    if ping -c 1 -W 1 "$target" >/dev/null 2>&1; then
        # Check if SSH port is open
        if check_ssh_port "$target"; then
            TABLET_IP="$target"
            break
        fi
    fi
done

# 3b. Quick scan of range 1-30 if not found
if [ -z "$TABLET_IP" ]; then
    echo "Scanning network range for device with port $PORT open..."
    for i in {1..30}; do
        target="$NETWORK.$i"
        if [ "$target" = "$PHONE_IP" ]; then continue; fi

        # We can skip ping and go straight to port check for speed/accuracy regarding SSH
        if check_ssh_port "$target"; then
            TABLET_IP="$target"
            break
        fi
    done
fi

if [ -z "$TABLET_IP" ]; then
    echo "Error: Could not find tablet with SSH port ($PORT) open in range $NETWORK.1-30"
    exit 1
fi

echo "✓ Found Tablet at: $TABLET_IP"

# ------------------------------------------------------------------------------
# 4. Execute SCP
# ------------------------------------------------------------------------------

echo "Copying..."
echo "> scp -P $PORT $* $TABLET_IP:~/"

# We pass "$@" directly to scp. 
# If the user passed '-r', scp receives it and copies recursively.
# If the user omitted '-r' and targets a dir, scp will fail (just like cp).
scp -P "$PORT" "$@" "${TABLET_IP}:~/"

EXIT_CODE=$?

if [ $EXIT_CODE -eq 0 ]; then
    echo "✓ Success"
else
    echo "✗ Failed"
    exit $EXIT_CODE
fi
