#!/bin/bash

# SAGE PyAPI - Server Launch Script
# Launcher for Python microservice

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

echo "========================================="
echo "SAGE PyAPI - Starting Server"
echo "========================================="
echo ""

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠ $1${NC}"
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
}

# --- Virtualenv must exist ---
if [ ! -d "venv" ]; then
    print_error "Virtual environment not found! Please create it first."
    exit 1
fi

print_success "Activating existing virtual environment..."
# shellcheck source=/dev/null
source venv/bin/activate

# --- Ensure Kaggle config directory exists ---
KAGGLE_CONFIG="$SCRIPT_DIR/../token/.kaggle"
mkdir -p "$KAGGLE_CONFIG"
export KAGGLE_CONFIG_DIR="$KAGGLE_CONFIG"

# --- Ensure required directories exist ---
mkdir -p datasets kernels models
mkdir -p "$SCRIPT_DIR/../syslogs"

# --- Stop any existing server ---
if [ -f "$SCRIPT_DIR/stop_server.py" ]; then
    print_success "Stopping any existing server using stop_server.py..."
    python stop_server.py
fi

# --- Server info ---
PORT=8009
LOG_FILE="$SCRIPT_DIR/../syslogs/pyapi.log"
PID_FILE="$SCRIPT_DIR/pyapi_server.pid"

echo ""
echo "========================================="
print_success "Starting FastAPI server on port $PORT..."
echo "========================================="
echo ""
echo "API Documentation:"
echo "  Swagger UI: http://127.0.0.1:$PORT/docs"
echo "  ReDoc:      http://127.0.0.1:$PORT/redoc"
echo ""

# --- Start server in background with nohup ---
nohup python -m uvicorn main:app --host 0.0.0.0 --port $PORT > "$LOG_FILE" 2>&1 &
SERVER_PID=$!

# Save PID to file
echo "$SERVER_PID" > "$PID_FILE"

echo ""
print_success "FastAPI server started with nohup in background"
echo "PID:  $SERVER_PID"
echo "Log:  $LOG_FILE"
echo "PID file: $PID_FILE"
echo ""
echo "To stop the server manually, run:"
echo "  python stop_server.py"
echo ""
echo "To view logs, run:"
echo "  tail -f \"$LOG_FILE\""
echo ""
