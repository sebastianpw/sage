#!/bin/sh
# Common binary finder for Termux + Linux

# find_bin <binary_name>
# Returns the full path, or empty string if not found.
find_bin() {
    BIN="$1"

    # 1) PATH lookup
    if command -v "$BIN" >/dev/null 2>&1; then
        command -v "$BIN"
        return 0
    fi

    # 2) Termux explicit paths
    if [ -x "/data/data/com.termux/files/usr/bin/$BIN" ]; then
        echo "/data/data/com.termux/files/usr/bin/$BIN"
        return 0
    fi

    # 3) Common Linux paths
    if [ -x "/usr/bin/$BIN" ]; then
        echo "/usr/bin/$BIN"
        return 0
    fi
    if [ -x "/usr/local/bin/$BIN" ]; then
        echo "/usr/local/bin/$BIN"
        return 0
    fi

    # Not found → return empty string
    echo ""
    return 1
}

# require_bin <binary_name>
# Same as find_bin but aborts with error if missing.
require_bin() {
    BIN_PATH="$(find_bin "$1")"
    if [ -z "$BIN_PATH" ]; then
        echo "Error: $1 not found" >&2
        exit 1
    fi
    echo "$BIN_PATH"
}
