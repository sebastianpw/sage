#!/bin/bash

# ==============================================================================
# SAGE DOC INGESTION (Wrapper)
# ------------------------------------------------------------------------------
# Activates PyAPI venv and runs the Python ingestion logic.
# ==============================================================================

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
PYAPI_DIR="$PROJECT_ROOT/pyapi"
VENV_ACTIVATE="$PYAPI_DIR/venv/bin/activate"
PYTHON_SCRIPT="$PYAPI_DIR/utils/ingest_docs.py"

# 1. Check for Virtual Environment
if [ ! -f "$VENV_ACTIVATE" ]; then
    echo "ERROR: Venv not found at $VENV_ACTIVATE"
    echo "Please ensure PyAPI is installed relative to this script."
    exit 1
fi

# 2. Check for Python Script
if [ ! -f "$PYTHON_SCRIPT" ]; then
    echo "ERROR: Ingest script not found at $PYTHON_SCRIPT"
    exit 1
fi

# 3. Load DB Config (Optional: exports DATABASE_URL if needed)
# If PyAPI's .env.local isn't set up, we can try to bridge the gap here.
# For now, we assume PyAPI is configured or db_connector works.

echo "========================================"
echo " Starting Ingestion via Python..."
echo " Environment: $VENV_ACTIVATE"
echo "========================================"

# 4. Activate & Run
source "$VENV_ACTIVATE"
python3 "$PYTHON_SCRIPT"

echo "Done."
