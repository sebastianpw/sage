# services/chroma_service.py
"""
SAGE Chroma Service (Full Lifecycle Edition)
--------------------------------------------
- Text: Auto-chunking for large documents.
- Images: CLIP embedding.
- Search: Multimodal (Text -> Text, Text -> Image, Image -> Image).
- Lifecycle: Supports 'delete_where' for metadata-based purging.

V2 Changes:
- /query now accepts optional 'where' metadata filter (passed to Chroma).
- /add_text now stores 'sketch_id' and 'vector_type' in chunk metadata.
- Internal logic ensures sketch_id is stored as Integer to match DB schema.
"""

import os
import json
import logging
import uuid
from typing import Optional, Dict, Any, List

import requests
from fastapi import APIRouter, UploadFile, File, Form, HTTPException
from pydantic import BaseModel

logger = logging.getLogger(__name__)

router = APIRouter(tags=["chroma"])

# ----------------------------
# CONFIG
# ----------------------------
CHROMA_HOST = os.getenv("CHROMA_HOST", "127.0.0.1")
CHROMA_PORT = int(os.getenv("CHROMA_PORT", "8000"))
CHROMA_TENANT = os.getenv("CHROMA_TENANT", "default_tenant")
CHROMA_DATABASE = os.getenv("CHROMA_DATABASE", "default_database")
CHROMA_DEFAULT_COLLECTION = os.getenv("CHROMA_COLLECTION", "sage_default")

# Internal Service URLs
EMBED_URL_TEXT = "http://127.0.0.1:8009/embed"
EMBED_URL_IMAGE = "http://127.0.0.1:8009/embed_image"
EMBED_URL_CLIP_TEXT = "http://127.0.0.1:8009/embed_search_query"

# ----------------------------
# HELPER: Text Chunking
# ----------------------------
def recursive_split_text(text: str, chunk_size: int = 1000, overlap: int = 100) -> List[str]:
    """
    Splits text into chunks of approx 'chunk_size' characters.
    Includes 'overlap' to ensure context isn't lost at the cut.
    """
    if len(text) <= chunk_size:
        return [text]

    chunks = []
    start = 0
    text_len = len(text)

    while start < text_len:
        end = start + chunk_size

        if end < text_len:
            last_newline = text.rfind('\n', start, end)
            if last_newline != -1 and last_newline > start + (chunk_size // 2):
                end = last_newline + 1
            else:
                last_period = text.rfind('. ', start, end)
                if last_period != -1 and last_period > start + (chunk_size // 2):
                    end = last_period + 1

        chunk = text[start:end]
        chunks.append(chunk)

        start = end - overlap
        if start >= end:
            start = end

    return chunks

# ----------------------------
# Chroma Connectivity
# ----------------------------
def _base() -> str:
    return f"http://{CHROMA_HOST}:{CHROMA_PORT}"

def _collections_base() -> str:
    return f"{_base()}/api/v2/tenants/{CHROMA_TENANT}/databases/{CHROMA_DATABASE}/collections"

_collection_id_cache: Dict[str, str] = {}

def _get_or_create_collection_id(name: str) -> str:
    if name in _collection_id_cache:
        return _collection_id_cache[name]

    url = f"{_collections_base()}/{name}"
    r = requests.get(url, timeout=5)

    if r.status_code == 200:
        coll_id = r.json()["id"]
        _collection_id_cache[name] = coll_id
        return coll_id

    create_url = _collections_base()
    payload = {
        "name": name,
        "metadata": {"hnsw:space": "cosine"},
        "get_or_create": True
    }
    rc = requests.post(create_url, json=payload, timeout=10)
    if rc.status_code in (200, 201):
        coll_id = rc.json()["id"]
        _collection_id_cache[name] = coll_id
        return coll_id

    raise RuntimeError(f"Failed to create collection {name}: {r.text}")

def _collection_url(name: str) -> str:
    coll_id = _get_or_create_collection_id(name)
    return f"{_collections_base()}/{coll_id}"

# ----------------------------
# Embedding Wrappers
# ----------------------------
def _embed_text_generic(text: str, url: str) -> List[float]:
    try:
        r = requests.post(url, json={"text": text}, timeout=30)
        r.raise_for_status()
        data = r.json()
        return data.get("embedding") or data.get("vector")
    except Exception as e:
        raise RuntimeError(f"Embedding failed at {url}: {e}")

def _embed_image(upload: UploadFile) -> List[float]:
    try:
        upload.file.seek(0)
        file_bytes = upload.file.read()
        files = {"file": (upload.filename, file_bytes, upload.content_type or "application/octet-stream")}
        r = requests.post(EMBED_URL_IMAGE, files=files, timeout=60)
        r.raise_for_status()
        data = r.json()
        return data.get("embedding") or data.get("vector")
    except Exception as e:
        raise RuntimeError(f"Image embedding failed: {e}")

# ----------------------------
# API Models
# ----------------------------
class AddTextRequest(BaseModel):
    id: Optional[str] = None
    text: str
    metadata: Optional[Dict[str, Any]] = {}
    collection: Optional[str] = None

class QueryRequest(BaseModel):
    text: str
    collection: Optional[str] = None
    n_results: int = 20
    modality: str = "text"
    where: Optional[Dict[str, Any]] = None

# ----------------------------
# Helper for where normalization
# ----------------------------
def _normalize_where_dict(input_where: Dict[str, Any]) -> Dict[str, Any]:
    """
    Convert user-facing where shapes into Chroma's expected operator format.
    Refined logic:
    - Dicts (like {"$in": [...]}) are passed through as-is.
    - Lists are converted to {"$in": list} without altering element types.
    - Scalars (numeric strings) are coerced to int/float for convenience.
    """
    chroma_where: Dict[str, Any] = {}
    for k, v in input_where.items():
        # 1. Operator syntax (e.g. {"$in": [1, 2]}) -> Pass through directly
        if isinstance(v, dict):
            chroma_where[k] = v
            continue

        # 2. List -> Implicit $in
        # We trust the input types here. If PHP sends integers, we keep integers.
        if isinstance(v, list):
            chroma_where[k] = {"$in": v}
            continue

        # 3. Scalar -> Implicit $eq
        # Minimal coercion for numeric strings to support basic Form inputs
        if isinstance(v, str):
            if v.isdigit():
                chroma_where[k] = {"$eq": int(v)}
            else:
                try:
                    # simplistic float check
                    if v.replace('.', '', 1).isdigit() and v.count('.') == 1:
                        chroma_where[k] = {"$eq": float(v)}
                    else:
                        chroma_where[k] = {"$eq": v}
                except Exception:
                    chroma_where[k] = {"$eq": v}
        else:
            chroma_where[k] = {"$eq": v}

    return chroma_where

# ----------------------------
# ENDPOINTS
# ----------------------------

@router.post("/add_text")
def add_text(req: AddTextRequest):
    """
    Ingests text.
    AUTOMATICALLY CHUNKS text if it's long.
    Forces 'sketch_id' to integer if 'db_id' is present, matching legacy schema.
    """
    collection_name = req.collection or CHROMA_DEFAULT_COLLECTION
    base_id = req.id or str(uuid.uuid4())

    chunks = recursive_split_text(req.text, chunk_size=1000, overlap=150)
    logger.info(f"Splitting '{base_id}' into {len(chunks)} chunks.")

    ids = []
    embeddings = []
    documents = []
    metadatas = []

    for i, chunk in enumerate(chunks):
        try:
            emb = _embed_text_generic(chunk, EMBED_URL_TEXT)
        except Exception as e:
            raise HTTPException(status_code=502, detail=f"Embed failed on chunk {i}: {e}")

        chunk_id = f"{base_id}_chunk_{i}"

        meta = req.metadata.copy() if req.metadata else {}
        meta["chunk_index"] = i
        meta["total_chunks"] = len(chunks)
        meta["parent_id"] = base_id

        # Logic to ensure sketch_id is stored as INT (matching data dump)
        if "sketch_id" not in meta and "db_id" in meta:
            try:
                meta["sketch_id"] = int(meta["db_id"])
            except (ValueError, TypeError):
                meta["sketch_id"] = meta["db_id"]
        elif "sketch_id" in meta:
            # If sketch_id provided, ensure it's int if it looks like one
            try:
                meta["sketch_id"] = int(meta["sketch_id"])
            except (ValueError, TypeError):
                pass

        ids.append(chunk_id)
        embeddings.append(emb)
        documents.append(chunk)
        metadatas.append(meta)

    url = f"{_collection_url(collection_name)}/add"
    payload = {
        "ids": ids,
        "embeddings": embeddings,
        "documents": documents,
        "metadatas": metadatas
    }

    try:
        r = requests.post(url, json=payload, timeout=30)
        if r.status_code not in (200, 201):
            raise HTTPException(status_code=500, detail=f"Chroma add failed: {r.text}")
    except HTTPException:
        raise
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Chroma add error: {e}")

    return {"ok": True, "base_id": base_id, "chunks": len(chunks)}


@router.post("/add_image")
def add_image(
    id: Optional[str] = Form(None),
    metadata: str = Form("{}"),
    collection: Optional[str] = Form(None),
    file: UploadFile = File(...)
):
    """Ingest image using CLIP embedding."""
    collection_name = collection or CHROMA_DEFAULT_COLLECTION
    item_id = id or str(uuid.uuid4())

    try:
        meta = json.loads(metadata)
    except:
        meta = {}

    try:
        emb = _embed_image(file)
    except Exception as e:
        raise HTTPException(status_code=502, detail=str(e))

    url = f"{_collection_url(collection_name)}/add"
    payload = {
        "ids": [item_id],
        "embeddings": [emb],
        "documents": [file.filename or "image"],
        "metadatas": [meta]
    }

    try:
        r = requests.post(url, json=payload, timeout=30)
        if r.status_code not in (200, 201):
            raise HTTPException(status_code=500, detail=f"Chroma add failed: {r.text}")
    except HTTPException:
        raise
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Chroma add error: {e}")

    return {"ok": True, "id": item_id}


@router.post("/query")
def query_text(
    text: Optional[str] = Form(None),
    file: Optional[UploadFile] = File(None),
    n_results: int = Form(5),
    collection: Optional[str] = Form(None),
    modality: str = Form("text"),
    where: Optional[str] = Form(None)
):
    """
    Search (Form-based).
    """
    collection_name = collection or CHROMA_DEFAULT_COLLECTION
    emb = []

    if file:
        emb = _embed_image(file)
    elif text:
        if modality == "image":
            emb = _embed_text_generic(text, EMBED_URL_CLIP_TEXT)
        else:
            emb = _embed_text_generic(text, EMBED_URL_TEXT)

    if not emb:
        raise HTTPException(status_code=400, detail="No query provided")

    url = f"{_collection_url(collection_name)}/query"
    payload = {
        "query_embeddings": [emb],
        "n_results": n_results,
        "include": ["metadatas", "documents", "distances"]
    }

    if where:
        try:
            where_dict = json.loads(where)
            chroma_where = _normalize_where_dict(where_dict)
            payload["where"] = chroma_where
        except Exception as e:
            logger.warning(f"Invalid where filter JSON, ignoring: {e}")

    try:
        r = requests.post(url, json=payload, timeout=15)
        if r.status_code != 200:
            raise HTTPException(status_code=500, detail=f"Query failed: {r.text}")
        return {"ok": True, "result": r.json()}
    except HTTPException:
        raise
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Query error: {e}")


@router.post("/query_json")
def query_json(req: QueryRequest):
    """
    JSON body alternative to /query.
    Preferred for complex 'where' filters (lists/$in).
    """
    collection_name = req.collection or CHROMA_DEFAULT_COLLECTION

    if not req.text.strip():
        raise HTTPException(status_code=400, detail="No query text provided")

    if req.modality == "image":
        emb = _embed_text_generic(req.text, EMBED_URL_CLIP_TEXT)
    else:
        emb = _embed_text_generic(req.text, EMBED_URL_TEXT)

    url = f"{_collection_url(collection_name)}/query"
    payload = {
        "query_embeddings": [emb],
        "n_results": req.n_results,
        "include": ["metadatas", "documents", "distances"]
    }

    if req.where:
        # Use cleaner normalization that respects input types
        chroma_where = _normalize_where_dict(req.where)
        payload["where"] = chroma_where

    try:
        r = requests.post(url, json=payload, timeout=15)
        if r.status_code != 200:
            raise HTTPException(status_code=500, detail=f"Query failed: {r.text}")
        return {"ok": True, "result": r.json()}
    except HTTPException:
        raise
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Query error: {e}")


@router.post("/delete")
def delete_item(
    id: str = Form(...),
    collection: Optional[str] = Form(None)
):
    """Delete an item by specific ID."""
    collection_name = collection or CHROMA_DEFAULT_COLLECTION
    url = f"{_collection_url(collection_name)}/delete"
    payload = {"ids": [id]}

    try:
        r = requests.post(url, json=payload, timeout=10)
        if r.status_code != 200:
            raise HTTPException(status_code=500, detail=f"Delete failed: {r.text}")
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Delete error: {e}")

    return {"ok": True, "deleted": id}


@router.post("/delete_where")
def delete_where(
    metadata_field: str = Form(...),
    metadata_value: str = Form(...),
    collection: Optional[str] = Form(None)
):
    """
    Delete items based on metadata.
    """
    collection_name = collection or CHROMA_DEFAULT_COLLECTION
    url = f"{_collection_url(collection_name)}/delete"

    val = metadata_value
    # Auto-convert numeric strings to support CLI/Form usage
    if val.isdigit():
        val = int(val)
    elif val.replace('.', '', 1).isdigit():
        try:
            val = float(val)
        except ValueError:
            pass

    payload = {"where": {metadata_field: val}}

    try:
        r = requests.post(url, json=payload, timeout=10)
        if r.status_code != 200:
            raise HTTPException(status_code=500, detail=f"Delete-where failed: {r.text}")
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Delete-where error: {e}")

    return {"ok": True, "collection": collection_name, "deleted_where": {metadata_field: val}}


@router.get("/info")
def info():
    """Returns connectivity info and lists all collections."""
    try:
        r = requests.get(_collections_base(), timeout=5)
        collections = r.json() if r.status_code == 200 else []
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Cannot reach Chroma server: {e}")

    return {
        "ok": True,
        "chroma_host": CHROMA_HOST,
        "collections": collections
    }
