# pyapi/utils/ingest_sketches.py
"""
SAGE Sketch Ingestion - 3-Vector Upgrade (Primary, Narrative, Style)

Logic:
1. Enforces existence of BOTH sketch_analysis AND sketch_sequence_analysis.
2. Generates 3 vectors per sketch to allow multi-axis querying:
   - Primary: The semantic core (Plot, Description, Entities).
   - Narrative: The structural function (Energy, Position, Masks).
   - Style: The aesthetic vibe (Tone, Visual Style, Symbolism).
3. Atomicity: All 3 documents must succeed to mark the sketch as 'indexed'.
4. Payload Fix: Uses 'collection' instead of 'collection_name' for PyAPI compatibility.
"""

import sys
import os
import json
import time
import requests
import logging
import subprocess
import mimetypes
import numbers
from pathlib import Path
from typing import Dict, Any

# Add parent directory to path to import services/db_connector
sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from services.db_connector import get_db_connection

# --- CONFIGURATION ---
# Matches the DB schema 'sage_sketches_nu'
COLL_SKETCHES = "sage_sketches_nu"
COLL_IMAGES = "sage_nu_images"

CURRENT_DIR = Path(__file__).resolve().parent
PROJECT_ROOT = CURRENT_DIR.parents[1]
PUBLIC_DIR = PROJECT_ROOT / "public"

# --- LOGGING ---
logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')
logger = logging.getLogger("SAGE_INGEST")

# --- URL RESOLVER ---
def resolve_pyapi_url():
    env_url = os.getenv("SAGE_PYAPI_URL")
    if env_url:
        return env_url.rstrip('/')
    # Try echo script
    search_paths = ["../../bash/pyapi_echo.sh", "../../../bash/pyapi_echo.sh"]
    for rel_path in search_paths:
        path = (CURRENT_DIR / rel_path).resolve()
        if path.exists():
            try:
                result = subprocess.run(["bash", str(path)], capture_output=True, text=True)
                url = result.stdout.strip()
                if url.startswith("http"):
                    return url.rstrip('/')
            except Exception:
                pass
    return "http://127.0.0.1:8009"

PYAPI_URL = resolve_pyapi_url()
CHROMA_ADD_TEXT = f"{PYAPI_URL}/chroma/add_text"
CHROMA_ADD_IMAGE = f"{PYAPI_URL}/chroma/add_image"
CHROMA_DEL_URL = f"{PYAPI_URL}/chroma/delete_where"
CHROMA_UNLOAD = f"{PYAPI_URL}/embed/unload"

def fetch_json_col(val: Any) -> Any:
    """Safely parses JSON from DB. Returns empty dict/list on failure."""
    if isinstance(val, (dict, list)):
        return val
    if isinstance(val, str) and val.strip():
        try:
            return json.loads(val)
        except Exception:
            try:
                return json.loads(val.replace("'", '"'))
            except Exception:
                pass
    return {}

def safe_meta_value(v):
    """Convert to Chroma-friendly metadata types (str, int, float, bool)."""
    if v is None:
        return None
    if isinstance(v, (str, bool, numbers.Number)):
        return v
    try:
        return json.dumps(v, ensure_ascii=False)
    except Exception:
        return str(v)

def run_ingestion():
    logger.info(f"Targeting PyAPI at: {PYAPI_URL}")
    logger.info("Mode: 3-Vector Ingestion (Primary / Narrative / Style)")

    conn = get_db_connection()
    if not conn:
        logger.error("Could not connect to Database.")
        return

    cursor = conn.cursor(dictionary=True)

    # ---------------------------------------------------------
    # PHASE 1: SYNC STATE
    # ---------------------------------------------------------
    logger.info("Syncing Vector State...")

    try:
        # A. Detect New Sketches
        # Requirement: Must have description + sketch_analysis + sketch_sequence_analysis
        cursor.execute(f"""
            INSERT IGNORE INTO vector_state (entity_type, entity_id, collection, status)
            SELECT 'sketches', s.id, '{COLL_SKETCHES}', 'pending'
            FROM sketches s
            WHERE s.searchable = 1
              AND (s.description IS NOT NULL AND s.description != '')
              AND EXISTS (SELECT 1 FROM sketch_analysis sa WHERE sa.sketch_id = s.id)
              AND EXISTS (SELECT 1 FROM sketch_sequence_analysis ssa WHERE ssa.sketch_id = s.id);
        """)

        # B. Detect Updated Sketches (reindex if sketch or analyses changed)
        cursor.execute("""
            UPDATE vector_state vs
            JOIN sketches s ON vs.entity_id = s.id
            LEFT JOIN sketch_analysis sa ON s.id = sa.sketch_id
            LEFT JOIN sketch_sequence_analysis ssa ON s.id = ssa.sketch_id
            SET vs.status = 'pending'
            WHERE vs.entity_type = 'sketches' AND vs.status = 'indexed'
            AND s.searchable = 1
            AND (
                s.updated_at > vs.last_ingested_at
                OR (sa.analyzed_at IS NOT NULL AND sa.analyzed_at > vs.last_ingested_at)
                OR (ssa.updated_at IS NOT NULL AND ssa.updated_at > vs.last_ingested_at)
            );
        """)

        # C. Detect Frames
        # Only frames linked to searchable sketches and only when the frame itself
        # is marked as entity_type='sketches'
        cursor.execute(f"""
            INSERT IGNORE INTO vector_state (entity_type, entity_id, collection, status)
            SELECT DISTINCT 'frames', f.id, '{COLL_IMAGES}', 'pending'
            FROM frames f
            JOIN frames_2_sketches map ON f.id = map.from_id
            JOIN sketches s ON s.id = map.to_id
            WHERE f.filename IS NOT NULL AND f.filename != ''
              AND f.entity_type = 'sketches'
              AND s.searchable = 1;
        """)

        # D. Detect Outdated Sketches (searchable turned to 0, or deleted entirely)
        cursor.execute("""
            UPDATE vector_state vs
            SET vs.status = 'outdated'
            WHERE vs.entity_type = 'sketches'
              AND vs.status != 'outdated'
              AND NOT EXISTS (
                  SELECT 1 FROM sketches s 
                  WHERE s.id = vs.entity_id AND s.searchable = 1
              );
        """)

        # E. Detect Outdated Frames (sketch turned searchable 0, or deleted entirely)
        cursor.execute("""
            UPDATE vector_state vs
            SET vs.status = 'outdated'
            WHERE vs.entity_type = 'frames'
              AND vs.status != 'outdated'
              AND NOT EXISTS (
                  SELECT 1 FROM frames f
                  JOIN frames_2_sketches map ON f.id = map.from_id
                  JOIN sketches s ON s.id = map.to_id
                  WHERE f.id = vs.entity_id AND s.searchable = 1
              );
        """)

        conn.commit()
    except Exception as e:
        logger.error(f"Sync failed: {e}")
        return

    # ---------------------------------------------------------
    # PHASE 1b: PURGE OUTDATED VECTORS
    # ---------------------------------------------------------
    cursor.execute("""
        SELECT id, entity_type, entity_id, collection 
        FROM vector_state 
        WHERE status = 'outdated' AND entity_type IN ('sketches', 'frames')
    """)
    outdated_jobs = cursor.fetchall()
    
    for row in outdated_jobs:
        logger.info(f"Purging archived {row['entity_type']} #{row['entity_id']} from {row['collection']}")
        try:
            if row['entity_type'] == 'sketches':
                requests.post(CHROMA_DEL_URL, data={'collection': row['collection'], 'metadata_field': 'sketch_id', 'metadata_value': str(row['entity_id'])}, timeout=5)
            elif row['entity_type'] == 'frames':
                requests.post(CHROMA_DEL_URL, data={'collection': row['collection'], 'metadata_field': 'frame_id', 'metadata_value': str(row['entity_id'])}, timeout=5)
        except Exception as e:
            logger.warning(f"  Purge failed for {row['entity_type']} #{row['entity_id']}: {e}")
            
        cursor.execute("DELETE FROM vector_state WHERE id = %s", (row['id'],))
    conn.commit()

    # ---------------------------------------------------------
    # PHASE 2: PROCESSING
    # ---------------------------------------------------------
    cursor.execute("""
        SELECT id, entity_type, entity_id
        FROM vector_state
        WHERE status IN ('pending', 'failed')
        ORDER BY entity_type DESC, id ASC
    """)
    jobs = cursor.fetchall()

    if not jobs:
        logger.info("System idle.")
        cursor.close()
        conn.close()
        return

    for job in jobs:
        state_id = job['id']
        entity_type = job['entity_type']
        entity_id = job['entity_id']
        status = "failed"
        msg = "Unknown Error"

        # =================================================
        # TYPE: SKETCHES (3-Vector Split)
        # =================================================
        if entity_type == 'sketches':
            logger.info(f"Processing Sketch #{entity_id}...")

            # Fetch joined data from all 3 tables
            query = """
                SELECT 
                    s.id, s.name, s.description,
                    sa.id AS analysis_id, sa.generator_config_id, sa.analyzed_at,
                    sa.scoring, COALESCE(sa.overall_quality, 0) as quality,
                    sa.classification, sa.thematics, sa.entities, sa.recommendations,
                    ssa.narrative_function, ssa.layer, ssa.energy, ssa.position, ssa.intensity,
                    ssa.standalone, ssa.connective_hint, ssa.structure_type, ssa.edit_relationship,
                    ssa.narrative_function_mask, ssa.layer_mask, ssa.confidence
                FROM sketches s
                JOIN sketch_analysis sa ON s.id = sa.sketch_id
                JOIN sketch_sequence_analysis ssa ON s.id = ssa.sketch_id
                WHERE s.id = %s
            """
            cursor.execute(query, (entity_id,))
            row = cursor.fetchone()

            if row:
                # --- PARSE JSON FIELDS ---
                cls = fetch_json_col(row.get('classification')) or {}
                them = fetch_json_col(row.get('thematics')) or {}
                ent = fetch_json_col(row.get('entities')) or {}
                recs = fetch_json_col(row.get('recommendations')) or {}
                narr_funcs = fetch_json_col(row.get('narrative_function')) or []
                layers = fetch_json_col(row.get('layer')) or []

                def join_list(v):
                    if isinstance(v, list): return ", ".join([str(x) for x in v if x])
                    return str(v) if v else ""

                # --- 1. PRIMARY TEXT (Semantic Core) ---
                # Description + Themes + Entities
                # Symbolism moved to Style per request
                p_parts = [
                    f"Title: {row['name']}",
                    f"Description: {row['description']}",
                    f"Narrative Role: {cls.get('narrative_function', '')}",
                    f"Themes: {join_list(them.get('primary_themes', []))}",
                    f"Characters: {join_list(ent.get('characters', []))}",
                    f"Locations: {join_list(ent.get('locations', []))}",
                    f"Artifacts: {join_list(ent.get('artifacts', []))}"
                ]
                text_primary = "\n".join([p for p in p_parts if p.strip()])

                # --- 2. NARRATIVE TEXT (Structure/Function) ---
                n_parts = [
                    f"Narrative Function: {join_list(narr_funcs)}",
                    f"Layers: {join_list(layers)}",
                    f"Energy: {row['energy']}",
                    f"Position: {row['position']}",
                    f"Intensity: {row['intensity']}",
                    f"Structure: {row['structure_type']}",
                    f"Edit Relationship: {row['edit_relationship']}",
                    f"Standalone: {row['standalone']}",
                    f"Connective Hint: {row['connective_hint']}"
                ]
                text_narrative = "\n".join([p for p in n_parts if p.strip()])

                # --- 3. STYLE TEXT (Vibe/Symbolism) ---
                s_parts = [
                    f"Emotional Tone: {cls.get('emotional_tone', '')}",
                    f"Visual Style: {cls.get('visual_style', '')}",
                    f"Symbolic Meaning: {them.get('symbolic_meaning', '')}"
                ]
                text_style = "\n".join([p for p in s_parts if p.strip()])

                # --- METADATA (Shared) ---
                meta = {
                    "sketch_id": int(entity_id),
                    "db_id": int(entity_id),
                    "type": "sketch",
                    "name": safe_meta_value(row['name']),
                    "quality": float(row['quality'] or 0),
                    "narrative_mask": int(row['narrative_function_mask'] or 0),
                    "layer_mask": int(row['layer_mask'] or 0),
                    "confidence": float(row['confidence'] or 0),
                    "energy": safe_meta_value(row['energy']),
                    "position": safe_meta_value(row['position']),
                    "intensity": safe_meta_value(row['intensity']),
                    "standalone": safe_meta_value(row['standalone']),
                    "analyzed_at": str(row['analyzed_at'])
                }

                # --- ATOMIC INGESTION ---
                try:
                    # 1. Primary
                    m1 = meta.copy(); m1['type'] = 'primary'
                    res1 = requests.post(CHROMA_ADD_TEXT, json={
                        "collection": COLL_SKETCHES,  # FIXED: was collection_name
                        "text": text_primary,
                        "metadata": m1,
                        "id": f"sketch_{entity_id}_primary"
                    })

                    # 2. Narrative
                    m2 = meta.copy(); m2['type'] = 'narrative'
                    res2 = requests.post(CHROMA_ADD_TEXT, json={
                        "collection": COLL_SKETCHES,  # FIXED: was collection_name
                        "text": text_narrative,
                        "metadata": m2,
                        "id": f"sketch_{entity_id}_narrative"
                    })

                    # 3. Style
                    m3 = meta.copy(); m3['type'] = 'style'
                    res3 = requests.post(CHROMA_ADD_TEXT, json={
                        "collection": COLL_SKETCHES,  # FIXED: was collection_name
                        "text": text_style,
                        "metadata": m3,
                        "id": f"sketch_{entity_id}_style"
                    })

                    if res1.status_code == 200 and res2.status_code == 200 and res3.status_code == 200:
                        status = "indexed"
                        msg = "OK"
                    else:
                        msg = f"API Fail: P:{res1.status_code} N:{res2.status_code} S:{res3.status_code}"
                        logger.warning(f"   [ERR] #{entity_id} {msg}")

                except Exception as e:
                    msg = f"Net Error: {str(e)}"
                    logger.error(f"   [ERR] #{entity_id} {e}")

            else:
                msg = "Missing Analysis Data (skipped)"
                logger.warning(f"   [WARN] #{entity_id} Missing required analysis rows.")

        # =================================================
        # TYPE: FRAMES
        # =================================================
        elif entity_type == 'frames':
            # Get Filename and related sketch ID
            cursor.execute("""
                SELECT f.filename, COALESCE(map.to_id, 0) as sketch_id
                FROM frames f
                JOIN frames_2_sketches map ON f.id = map.from_id
                JOIN sketches s ON s.id = map.to_id
                WHERE f.id = %s
                  AND f.entity_type = 'sketches'
                  AND s.searchable = 1
            """, (entity_id,))
            row = cursor.fetchone()

            if row:
                filename_raw = row.get('filename') or ""
                filename = filename_raw.lstrip("/\\")
                sketch_id = row.get('sketch_id') or 0
                file_path = PUBLIC_DIR / filename

                if file_path.exists():
                    logger.info(f"Processing Frame #{entity_id}...")
                    meta_obj = {
                        "sketch_id": int(sketch_id),
                        "frame_id": int(entity_id),
                        "filename": filename,
                        "type": "frame"
                    }
                    try:
                        mimetype, _ = mimetypes.guess_type(str(file_path))
                        if not mimetype: mimetype = 'application/octet-stream'
                        with open(file_path, 'rb') as f_img:
                            files = {'file': (file_path.name, f_img, mimetype)}
                            data = {
                                'id': f"frame_{entity_id}",
                                'collection': COLL_IMAGES,
                                'metadata': json.dumps(meta_obj)
                            }
                            # Images use 'collection' via Form param correctly here
                            r = requests.post(CHROMA_ADD_IMAGE, data=data, files=files, timeout=60)
                            if r.status_code in (200, 201):
                                status = "indexed"
                                msg = "OK"
                            else:
                                msg = f"API Error {r.status_code}"
                    except Exception as e:
                        msg = str(e)
                else:
                    msg = "File not found"
            else:
                msg = "Not eligible for ingest"

        # =================================================
        # UPDATE STATE
        # =================================================
        safe_msg = (str(msg) if msg else "")[:255].replace("'", "''")
        try:
            cursor.execute(f"""
                UPDATE vector_state
                SET status = %s,
                    last_ingested_at = NOW(),
                    error_msg = %s,
                    attempts = attempts + 1
                WHERE id = %s
            """, (status, safe_msg, state_id))
            conn.commit()
        except Exception as e:
            logger.error(f"DB Update failed: {e}")

    cursor.close()
    conn.close()
    logger.info("Batch Complete.")

if __name__ == "__main__":
    run_ingestion()