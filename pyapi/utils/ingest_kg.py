# pyapi/utils/ingest_kg.py
"""
SAGE Knowledge Graph Ingestion
-------------------------------
Ingests active KG nodes into two dedicated Chroma collections:

  sage_kg_nodes_meta    — Node identity + structure (name, type, keywords,
                          category). Useful for orientation queries even when
                          content is thin or empty.

  sage_kg_nodes_content — Full markdown content for nodes whose content_status
                          is 'partial' or 'filled'. Skipped for empty/stub nodes
                          to avoid polluting the search space with placeholders.

Vector strategy (matches all other SAGE text collections):
  Model : sentence-transformers/all-MiniLM-L6-v2  (384 dim)
  Chunks: recursive_split_text from chroma_service (1000 chars / 150 overlap)
          — re-implemented inline here so this script is self-contained.

Change detection:
  Reuses the existing vector_state table with entity_type = 'kg_node'.
  Two rows per node (one per collection target) using the same
  UNIQUE KEY (entity_type, entity_id, collection).

Usage:
  python3 pyapi/utils/ingest_kg.py
  — or via bash/ingest_kg.sh
"""

import sys
import os
import json
import logging
import subprocess
from pathlib import Path
from typing import List, Optional

import requests

# ---------------------------------------------------------------------------
# Path bootstrap (mirrors ingest_docs.py)
# ---------------------------------------------------------------------------
sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
from services.db_connector import get_db_connection

# ---------------------------------------------------------------------------
# Logging
# ---------------------------------------------------------------------------
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger("SAGE_KG_INGEST")

# ---------------------------------------------------------------------------
# Collection names — hardcoded to match chroma_collections rows
# ---------------------------------------------------------------------------
COLL_META    = "sage_kg_nodes_meta"
COLL_CONTENT = "sage_kg_nodes_content"

# Content statuses worth embedding in the content collection
CONTENT_ELIGIBLE = {"partial", "filled"}

# ---------------------------------------------------------------------------
# PyAPI URL resolver (identical pattern to ingest_docs.py)
# ---------------------------------------------------------------------------
def resolve_pyapi_url() -> str:
    env_url = os.getenv("SAGE_PYAPI_URL")
    if env_url:
        return env_url.rstrip("/")

    current_dir = os.path.dirname(os.path.abspath(__file__))
    search_paths = ["../../bash/pyapi_echo.sh", "../../../bash/pyapi_echo.sh"]
    for rel in search_paths:
        path = os.path.normpath(os.path.join(current_dir, rel))
        if os.path.exists(path):
            try:
                result = subprocess.run(["bash", path], capture_output=True, text=True)
                url = result.stdout.strip()
                if url.startswith("http"):
                    logger.info(f"Resolved PyAPI URL from script: {url}")
                    return url.rstrip("/")
            except Exception as e:
                logger.warning(f"Found {path} but failed to run: {e}")

    logger.warning("Could not resolve PyAPI URL. Defaulting to localhost.")
    return "http://127.0.0.1:8009"


PYAPI_URL      = resolve_pyapi_url()
CHROMA_ADD_URL = f"{PYAPI_URL}/chroma/add_text"
CHROMA_DEL_URL = f"{PYAPI_URL}/chroma/delete_where"

# ---------------------------------------------------------------------------
# Minimal text chunker (self-contained, mirrors chroma_service logic)
# ---------------------------------------------------------------------------
def recursive_split_text(text: str, chunk_size: int = 1000, overlap: int = 150) -> List[str]:
    if len(text) <= chunk_size:
        return [text]
    chunks, start = [], 0
    while start < len(text):
        end = start + chunk_size
        if end < len(text):
            for sep in ("\n", ". "):
                pos = text.rfind(sep, start, end)
                if pos != -1 and pos > start + chunk_size // 2:
                    end = pos + len(sep)
                    break
        chunks.append(text[start:end])
        start = end - overlap
        if start >= end:
            start = end
    return chunks

# ---------------------------------------------------------------------------
# Content-status helper (mirrors kg_api.php logic)
# ---------------------------------------------------------------------------
def content_status(chars: int) -> str:
    if chars == 0:    return "empty"
    if chars < 200:   return "stub"
    if chars < 600:   return "partial"
    return "filled"

# ---------------------------------------------------------------------------
# Send one vector to Chroma via PyAPI
# ---------------------------------------------------------------------------
def send_vector(doc_id: str, text: str, collection: str, metadata: dict) -> bool:
    payload = {
        "id":         doc_id,
        "text":       text,
        "collection": collection,
        "metadata":   metadata,
    }
    try:
        r = requests.post(CHROMA_ADD_URL, json=payload, timeout=15)
        if r.status_code in (200, 201):
            return True
        logger.warning(f"  add_text failed [{r.status_code}]: {r.text[:200]}")
        return False
    except Exception as e:
        logger.error(f"  Network error sending {doc_id}: {e}")
        return False

# ---------------------------------------------------------------------------
# Purge old vectors for a node from a collection
# ---------------------------------------------------------------------------
def purge_node_vectors(node_id: int, collection: str) -> None:
    try:
        requests.post(
            CHROMA_DEL_URL,
            data={"collection": collection, "metadata_field": "node_id", "metadata_value": str(node_id)},
            timeout=5,
        )
    except Exception as e:
        logger.warning(f"  Purge failed for node {node_id} in {collection}: {e}")

# ---------------------------------------------------------------------------
# Main ingestion
# ---------------------------------------------------------------------------
def run_ingestion() -> None:
    logger.info(f"Targeting PyAPI at: {PYAPI_URL}")
    logger.info(f"Collections: {COLL_META} | {COLL_CONTENT}")

    conn = get_db_connection()
    if not conn:
        logger.error("Could not connect to database.")
        return

    cur = conn.cursor(dictionary=True)

    # -----------------------------------------------------------------------
    # PHASE 1 — Sync vector_state
    # -----------------------------------------------------------------------
    logger.info("Syncing vector_state...")
    try:
        # Register new active nodes for both collections
        for coll in (COLL_META, COLL_CONTENT):
            cur.execute(f"""
                INSERT IGNORE INTO vector_state (entity_type, entity_id, collection, status)
                SELECT 'kg_node', id, %s, 'pending'
                FROM kg_nodes
                WHERE status = 'active'
            """, (coll,))

        # Mark updated nodes as pending in both collections
        for coll in (COLL_META, COLL_CONTENT):
            cur.execute("""
                UPDATE vector_state vs
                JOIN kg_nodes n ON vs.entity_id = n.id
                SET vs.status = 'pending'
                WHERE vs.entity_type = 'kg_node'
                  AND vs.collection  = %s
                  AND vs.status      = 'indexed'
                  AND n.updated_at   > vs.last_ingested_at
            """, (coll,))

        # Mark archived nodes for removal (status = outdated)
        for coll in (COLL_META, COLL_CONTENT):
            cur.execute("""
                UPDATE vector_state vs
                LEFT JOIN kg_nodes n ON vs.entity_id = n.id AND n.status = 'active'
                SET vs.status = 'outdated'
                WHERE vs.entity_type = 'kg_node'
                  AND vs.collection  = %s
                  AND vs.status NOT IN ('outdated')
                  AND (n.id IS NULL)
            """, (coll,))

        conn.commit()
    except Exception as e:
        logger.error(f"Sync failed: {e}")
        cur.close(); conn.close()
        return

    # -----------------------------------------------------------------------
    # PHASE 1b — Purge outdated vectors from Chroma
    # -----------------------------------------------------------------------
    cur.execute("""
        SELECT id, entity_id, collection
        FROM vector_state
        WHERE entity_type = 'kg_node' AND status = 'outdated'
    """)
    outdated = cur.fetchall()
    for row in outdated:
        logger.info(f"Purging archived node #{row['entity_id']} from {row['collection']}")
        purge_node_vectors(row['entity_id'], row['collection'])
        cur.execute("DELETE FROM vector_state WHERE id = %s", (row['id'],))
    conn.commit()

    # -----------------------------------------------------------------------
    # PHASE 2 — Process pending / failed jobs
    # -----------------------------------------------------------------------
    cur.execute("""
        SELECT vs.id AS state_id, vs.entity_id AS node_id, vs.collection
        FROM vector_state vs
        WHERE vs.entity_type = 'kg_node'
          AND vs.status IN ('pending', 'failed')
        ORDER BY vs.entity_id ASC, vs.collection ASC
    """)
    jobs = cur.fetchall()

    if not jobs:
        logger.info("System idle — nothing to ingest.")
        cur.close(); conn.close()
        return

    logger.info(f"Processing {len(jobs)} job(s)...")

    for job in jobs:
        state_id   = job["state_id"]
        node_id    = job["node_id"]
        collection = job["collection"]
        ok         = False
        err_msg    = "Unknown error"

        # Fetch node data
        cur.execute("""
            SELECT
                n.id, n.name, n.node_type, n.keywords, n.category_id,
                c.name AS category_name,
                CHAR_LENGTH(COALESCE(n.content, '')) AS content_chars,
                n.content
            FROM kg_nodes n
            LEFT JOIN kg_categories c ON c.id = n.category_id
            WHERE n.id = %s AND n.status = 'active'
        """, (node_id,))
        node = cur.fetchone()

        if not node:
            err_msg = "Node not found or archived"
            logger.warning(f"  Skipping node #{node_id}: {err_msg}")
        else:
            chars  = int(node["content_chars"] or 0)
            status = content_status(chars)

            # Shared metadata for both collections
            base_meta = {
                "node_id":       int(node["id"]),
                "node_type":     node["node_type"] or "note",
                "category_id":   int(node["category_id"]) if node["category_id"] else 0,
                "category_name": node["category_name"] or "",
                "keywords":      node["keywords"] or "",
                "content_status": status,
                "content_chars": chars,
            }

            # -----------------------------------------------------------
            # META collection — always ingest (even empty nodes)
            # Text: name + type + keywords + category for orientation
            # -----------------------------------------------------------
            if collection == COLL_META:
                parts = [
                    f"Node: {node['name']}",
                    f"Type: {node['node_type'] or 'note'}",
                ]
                if node["category_name"]:
                    parts.append(f"Category: {node['category_name']}")
                if node["keywords"]:
                    parts.append(f"Keywords: {node['keywords']}")
                parts.append(f"Content status: {status}")

                text = "\n".join(parts)
                purge_node_vectors(node_id, COLL_META)
                ok = send_vector(
                    doc_id     = f"kg_meta_{node_id}",
                    text       = text,
                    collection = COLL_META,
                    metadata   = {**base_meta, "subtype": "meta"},
                )
                err_msg = "OK" if ok else "add_text failed"

            # -----------------------------------------------------------
            # CONTENT collection — only for partial / filled nodes
            # Text: name header + full markdown content (chunked by PyAPI)
            # -----------------------------------------------------------
            elif collection == COLL_CONTENT:
                if status not in CONTENT_ELIGIBLE:
                    # Nothing to embed yet — mark indexed so we don't retry
                    ok = True
                    err_msg = f"Skipped ({status}) — no content to embed"
                    logger.info(f"  Node #{node_id} [{status}] — skipping content collection")
                else:
                    content_text = (
                        f"Node: {node['name']}\n"
                        f"Type: {node['node_type'] or 'note'}\n\n"
                        f"{node['content'] or ''}"
                    )
                    purge_node_vectors(node_id, COLL_CONTENT)
                    ok = send_vector(
                        doc_id     = f"kg_content_{node_id}",
                        text       = content_text,
                        collection = COLL_CONTENT,
                        metadata   = {**base_meta, "subtype": "content"},
                    )
                    err_msg = "OK" if ok else "add_text failed"

        # Update vector_state
        new_status = "indexed" if ok else "failed"
        cur.execute("""
            UPDATE vector_state
            SET status           = %s,
                last_ingested_at = NOW(),
                error_msg        = %s,
                attempts         = attempts + 1
            WHERE id = %s
        """, (new_status, str(err_msg)[:255], state_id))
        conn.commit()

        icon = "✓" if ok else "✗"
        logger.info(f"  [{icon}] Node #{node_id} -> {collection} | {err_msg}")

    cur.close()
    conn.close()
    logger.info("Batch complete.")


if __name__ == "__main__":
    run_ingestion()
