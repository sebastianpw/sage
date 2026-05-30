#!/bin/bash

# 1. Update and install system dependencies
apt update
apt install -y \
    build-essential \
    cmake \
    pkg-config \
    git \
    python3-venv \
    python3-dev \
    libsqlite3-dev \
    libssl-dev \
    libffi-dev \
    rustc \
    cargo

# 2. Create and activate Python virtual environment
python3 -m venv /var/www/sage/chroma-venv
source /var/www/sage/chroma-venv/bin/activate

# 3. Upgrade base Python packaging tools
pip install --upgrade pip setuptools wheel

# 4. Install ChromaDB and dependencies
pip install chroma-hnswlib || true
pip install chromadb || pip install chromadb==0.3.26

# 5. Verify installation and print versions
python3 -c "
import sqlite3, sys, chromadb
print('sqlite', sqlite3.sqlite_version)
print('python', sys.version.split()[0])
print('chroma', getattr(chromadb,'__version__', 'n/a'))
"

# Define where the database should be permanently saved
# (This matches the default path in your run-chroma.sh script)
export CHROMA_DB_PATH="/var/www/sage/chroma-server/chroma_db"
mkdir -p "$CHROMA_DB_PATH"

# 7. Pre-download embedding model and create PERSISTENT collections
python3 - <<'PYTHON'
import os
import sys

# Suppress the ONNX PCI warning before importing chromadb
os.environ["ORT_LOGGING_LEVEL"] = "3"
import chromadb

print('\nInitializing Persistent Chroma...')
db_path = os.environ.get('CHROMA_DB_PATH')

# Try modern persistent client, fallback to older Settings method if needed
try:
    client = chromadb.PersistentClient(path=db_path)
except AttributeError:
    # Fallback for ChromaDB v0.3.x
    from chromadb.config import Settings
    client = chromadb.Client(Settings(
        chroma_db_impl="duckdb+parquet",
        persist_directory=db_path
    ))

# Force embedding model download/cache
cache_collection = client.get_or_create_collection('installer_cache')
cache_collection.add(documents=['cache'], ids=['1'])
print('Embedding model downloaded and cached successfully.')

# Create SAGE collections
collections = [
    'sage_sketches_nu',
    'sage_nu_images'
]

for coll in collections:
    client.get_or_create_collection(name=coll)
    print('Ensured collection:', coll)

# In Chroma < 0.4.0, we have to manually call persist()
if hasattr(client, 'persist'):
    client.persist()

print('\nExisting collections stored on disk at:', db_path)
for c in client.list_collections():
    print('-', c.name if hasattr(c, 'name') else c)

print('\nChroma bootstrap complete.')
PYTHON
