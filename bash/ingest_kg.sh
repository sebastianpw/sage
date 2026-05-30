#!/bin/bash

# ==============================================================================
# SAGE KNOWLEDGE GRAPH INGESTION (Wrapper)
# ------------------------------------------------------------------------------
# Activates PyAPI venv and runs the KG ingestion logic.
# Mirrors the structure of ingest_docs.sh exactly.
# ==============================================================================

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
PYAPI_DIR="$PROJECT_ROOT/pyapi"
VENV_ACTIVATE="$PYAPI_DIR/venv/bin/activate"
PYTHON_SCRIPT="$PYAPI_DIR/utils/ingest_kg.py"

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

echo "========================================"
echo " Starting KG Node Ingestion..."
echo " Collections: sage_kg_nodes_meta"
echo "              sage_kg_nodes_content"
echo "========================================"

# 3. Activate & Run
source "$VENV_ACTIVATE"
python3 "$PYTHON_SCRIPT"

echo "Done."
