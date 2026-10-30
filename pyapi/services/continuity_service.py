# pyapi/services/continuity_service.py
"""
Continuity & Prompt Substitution Service.
Registered in serv.conf.json as 'continuity' with prefix '/continuity'.

Endpoints:
  GET  /continuity/tags             — list all active canonical tags
  POST /continuity/tags             — create or update a tag
  POST /continuity/process          — deeply inspects strings/dicts/lists and replaces {TAGS}
"""
from __future__ import annotations
import re
import logging
from typing import Any, Dict, List, Union, Optional

from fastapi import APIRouter, HTTPException
from pydantic import BaseModel

from services.db_connector import get_db_connection

logger = logging.getLogger(__name__)
router = APIRouter(tags=["continuity"])

# ── Pydantic models ────────────────────────────────────────────────────────

class ProcessRequest(BaseModel):
    # payload can be a single string, a list of strings, or a full DB row/dict
    payload: Union[str, List[Any], Dict[str, Any]]
    # Optional dynamic overrides for this specific request (bypasses DB)
    overrides: Optional[Dict[str, str]] = None

class EntityRequest(BaseModel):
    tag_name: str
    entity_type: str = "character"
    description: str
    active: bool = True

# ── Core Engine Logic ──────────────────────────────────────────────────────

def _get_active_entities() -> Dict[str, str]:
    """Fetches all active tags from the database."""
    conn = get_db_connection(use_cache=True)
    if not conn:
        raise HTTPException(503, "DB unavailable")
    
    try:
        cur = conn.cursor(dictionary=True)
        cur.execute("SELECT tag_name, description FROM continuity_entities WHERE active = 1")
        rows = cur.fetchall()
        cur.close()
        # Ensure tag names are uppercase for consistency
        return {row["tag_name"].upper(): row["description"] for row in rows}
    finally:
        # We don't close the connection here if we rely on db_connector's cache
        # But if you prefer transient connections, use use_cache=False and close it.
        pass

def _apply_continuity(text: str, entities_map: Dict[str, str]) -> str:
    """
    Safely replaces {TAG} in a string. 
    If a tag doesn't exist, it leaves it alone (preventing crashes on JSON-like brackets).
    """
    if not isinstance(text, str):
        return text

    # Matches {ANY_TAG} - non-greedy, alphanumeric + underscores
    pattern = r'\{([a-zA-Z0-9_]+)\}'
    
    def replacer(match):
        key = match.group(1).upper()
        # Return the canonical replacement, or the original string (e.g. {unknown}) if not found
        return entities_map.get(key, match.group(0))
    
    return re.sub(pattern, replacer, text)

def _recursive_replace(data: Any, entities_map: Dict[str, str]) -> Any:
    """Recursively traverses dicts/lists to replace tags in all nested strings."""
    if isinstance(data, str):
        return _apply_continuity(data, entities_map)
    elif isinstance(data, dict):
        return {k: _recursive_replace(v, entities_map) for k, v in data.items()}
    elif isinstance(data, list):
        return [_recursive_replace(item, entities_map) for item in data]
    
    # Return ints, floats, booleans, None as-is
    return data

# ── Endpoints ──────────────────────────────────────────────────────────────

@router.post("/process")
def process_payload(req: ProcessRequest):
    """
    The main engine endpoint. Send strings, lists, or full DB JSON payloads here.
    """
    try:
        # 1. Load baseline canonical definitions from DB
        entities_map = _get_active_entities()
        
        # 2. Apply request-level overrides if provided
        if req.overrides:
            for k, v in req.overrides.items():
                entities_map[k.upper()] = str(v)
                
        # 3. Process the payload deeply
        processed = _recursive_replace(req.payload, entities_map)
        
        return {"ok": True, "data": processed}
    except Exception as e:
        logger.exception("Failed to process continuity payload")
        raise HTTPException(500, f"Processing error: {str(e)}")


@router.get("/tags")
def list_tags():
    """List all available continuity tags."""
    conn = get_db_connection(use_cache=False)
    try:
        cur = conn.cursor(dictionary=True)
        cur.execute("SELECT id, tag_name, entity_type, description, active FROM continuity_entities ORDER BY tag_name")
        rows = cur.fetchall()
        cur.close()
        return {"ok": True, "data": rows}
    finally:
        if conn: conn.close()


@router.post("/tags")
def save_tag(req: EntityRequest):
    """Create or update a continuity tag."""
    conn = get_db_connection(use_cache=False)
    if not conn:
        raise HTTPException(503, "DB unavailable")
    try:
        cur = conn.cursor()
        
        # Upsert logic (Insert or Update if exists)
        sql = """
            INSERT INTO continuity_entities (tag_name, entity_type, description, active)
            VALUES (%s, %s, %s, %s)
            ON DUPLICATE KEY UPDATE
            entity_type = VALUES(entity_type),
            description = VALUES(description),
            active = VALUES(active)
        """
        tag_upper = req.tag_name.upper().strip()
        cur.execute(sql, (tag_upper, req.entity_type, req.description, req.active))
        
        conn.commit()
        cur.close()
        return {"ok": True, "message": f"Tag {tag_upper} saved successfully."}
    finally:
        if conn: conn.close()
