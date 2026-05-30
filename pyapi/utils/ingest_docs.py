# pyapi/utils/ingest_docs.py
import sys
import os
import json
import time
import requests
import logging
import subprocess
from pathlib import Path
from typing import Dict, Any, List

# Add parent directory to path to import services/db_connector
sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from services.db_connector import get_db_connection

# --- LOGGING ---
logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')
logger = logging.getLogger("SAGE_INGEST")

# --- CONFIGURATION RESOLVER ---
def resolve_pyapi_url():
    # 1. Trust Environment Variable first
    env_url = os.getenv("SAGE_PYAPI_URL")
    if env_url:
        return env_url.rstrip('/')

    # 2. Try to find and run the bash echo script (relative to this file)
    search_paths = [
        "../../bash/pyapi_echo.sh",           
        "../../../bash/pyapi_echo.sh"
    ]
    
    current_dir = os.path.dirname(os.path.abspath(__file__))
    for rel_path in search_paths:
        path = os.path.normpath(os.path.join(current_dir, rel_path))
        if os.path.exists(path):
            try:
                # Run the script to get the dynamic IP
                result = subprocess.run(["bash", path], capture_output=True, text=True)
                url = result.stdout.strip()
                if url.startswith("http"):
                    logger.info(f"Resolved PyAPI URL from script: {url}")
                    return url.rstrip('/')
            except Exception as e:
                logger.warning(f"Found {path} but failed to run: {e}")

    # 3. Fallback
    logger.warning("Could not resolve PyAPI URL. Defaulting to localhost.")
    return "http://127.0.0.1:8009"

PYAPI_URL = resolve_pyapi_url()
CHROMA_ADD_URL = f"{PYAPI_URL}/chroma/add_text"
CHROMA_DELETE_URL = f"{PYAPI_URL}/chroma/delete_where"
CHROMA_UNLOAD_URL = f"{PYAPI_URL}/embed/unload"

def clean_text(val: Any) -> str:
    """Recursively converts objects to clean strings for embedding."""
    if val is None: return ""
    if isinstance(val, str): return val
    if isinstance(val, (int, float)): return str(val)
    if isinstance(val, list): return ", ".join([clean_text(v) for v in val])
    if isinstance(val, dict): return "\n".join([f"- {k}: {clean_text(v)}" for k, v in val.items()])
    return str(val)

def fetch_json_col(val: Any) -> Dict:
    """Safely parses JSON from DB."""
    if isinstance(val, dict): return val
    if isinstance(val, str) and val.strip():
        try: return json.loads(val)
        except: return {}
    return {}

def process_deep_dive(raw_list: List) -> str:
    """Aggregates 'raw' chunks from the Showrunner analysis structure."""
    if not raw_list or not isinstance(raw_list, list): return ""
    lines = []
    for chunk in raw_list:
        if not isinstance(chunk, dict): continue
        if 'attributes' in chunk and isinstance(chunk['attributes'], dict):
            for k, v in chunk['attributes'].items():
                lines.append(f"- {k}: {clean_text(v)}")
        if 'goals' in chunk: lines.append("Goals: " + clean_text(chunk['goals']))
        if 'actions' in chunk: lines.append("Actions: " + clean_text(chunk['actions']))
    return "\n".join(lines)

def run_ingestion():
    logger.info(f"Targeting PyAPI at: {PYAPI_URL}")
    
    conn = get_db_connection()
    if not conn:
        logger.error("Could not connect to Database. Ensure DATABASE_URL is set or pyapi/.env.local exists.")
        return

    cursor = conn.cursor(dictionary=True)

    # ---------------------------------------------------------
    # PHASE 1: SYNC STATE
    # ---------------------------------------------------------
    logger.info("Syncing Vector State...")
    
    try:
        # A. Register New
        cursor.execute("""
            INSERT IGNORE INTO vector_state (entity_type, entity_id, collection, status)
            SELECT 'documentation', d.id, COALESCE(da.target_collection, 'sage_nu_lore_draft'), 'pending'
            FROM documentations d
            INNER JOIN md_doc_analysis da ON d.id = da.doc_id
            WHERE d.is_active = 1
        """)
        
        # B. Detect Content Updates
        cursor.execute("""
            UPDATE vector_state vs
            JOIN documentations d ON vs.entity_id = d.id
            INNER JOIN md_doc_analysis da ON d.id = da.doc_id
            SET vs.status = 'pending'
            WHERE vs.entity_type = 'documentation' AND vs.status = 'indexed'
            AND (d.updated_at > vs.last_ingested_at OR (da.analyzed_at IS NOT NULL AND da.analyzed_at > vs.last_ingested_at))
        """)

        # C. Detect Moves (Draft -> Mature)
        # FIX: Added COLLATE utf8mb4_general_ci to resolve collation mismatch
        cursor.execute("""
            UPDATE vector_state vs
            JOIN md_doc_analysis da ON vs.entity_id = da.doc_id
            SET vs.status = 'pending'
            WHERE vs.entity_type = 'documentation'
            AND vs.collection != da.target_collection COLLATE utf8mb4_general_ci
        """)
        conn.commit()
    except Exception as e:
        logger.error(f"Sync failed: {e}")
        return

    # ---------------------------------------------------------
    # PHASE 2: PROCESSING
    # ---------------------------------------------------------
    cursor.execute("SELECT id, entity_id, collection FROM vector_state WHERE entity_type='documentation' AND status IN ('pending', 'failed') ORDER BY id ASC")
    jobs = cursor.fetchall()

    if not jobs:
        logger.info("System idle.")
        cursor.close()
        conn.close()
        return

    for job in jobs:
        state_id = job['id']
        doc_id = job['entity_id']
        old_collection = job['collection']

        # Determine Target Collection
        cursor.execute("SELECT target_collection FROM md_doc_analysis WHERE doc_id = %s", (doc_id,))
        row = cursor.fetchone()
        target_collection = row['target_collection'] if row and row['target_collection'] else 'sage_nu_lore_draft'

        logger.info(f"Processing Doc #{doc_id} -> {target_collection}")

        # Purge Old Vectors
        try:
            requests.post(CHROMA_DELETE_URL, data={'collection': old_collection, 'metadata_field': 'db_id', 'metadata_value': doc_id}, timeout=5)
            if old_collection != target_collection:
                requests.post(CHROMA_DELETE_URL, data={'collection': target_collection, 'metadata_field': 'db_id', 'metadata_value': doc_id}, timeout=5)
        except Exception as e:
            logger.error(f"Failed to contact PyAPI for deletion: {e}")

        # Fetch Full Data
        cursor.execute("""
            SELECT 
                d.id, d.name, c.name as category,
                da.summary, da.series_bible, da.narrative_utility,
                da.thematics, da.entities, da.showrunner_analysis
            FROM documentations d
            LEFT JOIN documentation_categories c ON d.category_id = c.id
            INNER JOIN md_doc_analysis da ON d.id = da.doc_id
            WHERE d.id = %s
        """, (doc_id,))
        
        doc_data = cursor.fetchone()
        if not doc_data:
            logger.warning(f"Doc {doc_id} not found.")
            continue

        # Prepare Data
        title = doc_data['name']
        category = doc_data['category'] or 'Uncategorized'
        utility = float(doc_data['narrative_utility'] or 0)
        
        themes_json = fetch_json_col(doc_data['thematics'])
        entities_json = fetch_json_col(doc_data['entities'])
        showrunner_json = fetch_json_col(doc_data['showrunner_analysis'])

        if 'entities' in entities_json and isinstance(entities_json['entities'], dict):
            entities_json = entities_json['entities']

        vectors_to_send = []

        # A. Core Bible
        core_text = f"Title: {title}\nCategory: {category}\nType: Series Overview\n\n"
        if themes_json.get('themes'): core_text += f"Themes: {clean_text(themes_json['themes'])}\n"
        if themes_json.get('mood'): core_text += f"Mood: {clean_text(themes_json['mood'])}\n"
        if doc_data['summary']: core_text += f"\nSUMMARY:\n{doc_data['summary']}\n"
        if doc_data['series_bible']: core_text += f"\nSERIES BIBLE:\n{doc_data['series_bible']}\n"
        
        vectors_to_send.append({
            "id": f"doc_{doc_id}_overview",
            "text": core_text,
            "metadata": {"subtype": "overview", "entity_name": "Series Bible"}
        })

        # B. Characters / Factions / Locations
        for type_key, subtype in [('characters', 'character'), ('factions', 'faction'), ('locations', 'location')]:
            items = entities_json.get(type_key, [])
            if not isinstance(items, list): continue
            
            for item in items:
                if not isinstance(item, dict): continue
                name = item.get('name', 'Unknown')
                safe_name = "".join([c for c in name if c.isalnum()])[:30]
                
                desc = f"Title: {title}\nEntity: {name}\nType: {subtype.capitalize()}\n"
                if item.get('roles'): desc += f"Roles: {clean_text(item['roles'])}\n"
                if item.get('aliases'): desc += f"Aliases: {clean_text(item['aliases'])}\n"
                if item.get('description'): desc += f"Description: {clean_text(item['description'])}\n"
                
                deep = process_deep_dive(item.get('raw'))
                if deep: desc += "\n--- DEEP DIVE ---\n" + deep

                vectors_to_send.append({
                    "id": f"doc_{doc_id}_{subtype}_{safe_name}_{int(time.time()*1000)}",
                    "text": desc,
                    "metadata": {"subtype": subtype, "entity_name": name}
                })

        # C. Episodes
        episodes = showrunner_json.get('episode_concepts', [])
        if isinstance(episodes, list):
            for ep in episodes:
                if not isinstance(ep, dict): continue
                ep_title = ep.get('title', 'Untitled')
                safe_ep = "".join([c for c in ep_title if c.isalnum()])[:30]
                
                desc = f"Title: {title}\nEpisode: {ep.get('episode','')}: {ep_title}\n"
                if ep.get('logline'): desc += f"Logline: {clean_text(ep['logline'])}\n"
                if ep.get('act_structure'): desc += f"Structure: {clean_text(ep['act_structure'])}\n"
                if ep.get('description'): desc += f"Description: {clean_text(ep['description'])}\n"

                vectors_to_send.append({
                    "id": f"doc_{doc_id}_episode_{safe_ep}_{int(time.time()*1000)}",
                    "text": desc,
                    "metadata": {"subtype": "episode", "entity_name": ep_title}
                })

        # --- SEND ---
        success_count = 0
        for vec in vectors_to_send:
            payload = {
                "id": vec["id"],
                "text": vec["text"],
                "collection": target_collection,
                "metadata": {
                    "db_id": int(doc_id),
                    "type": "documentation",
                    "title": title,
                    "category": category,
                    "utility": utility,
                    **vec["metadata"]
                }
            }
            try:
                r = requests.post(CHROMA_ADD_URL, json=payload, timeout=10)
                if r.status_code in [200, 201]:
                    success_count += 1
                else:
                    logger.warning(f"Failed to add vector {vec['id']}: {r.text}")
            except Exception as e:
                logger.error(f"API Error ({PYAPI_URL}): {e}")

        # Update State
        cursor.execute("""
            UPDATE vector_state 
            SET status='indexed', last_ingested_at=NOW(), collection=%s, attempts=attempts+1 
            WHERE id=%s
        """, (target_collection, state_id))
        conn.commit()
        
        logger.info(f"   -> Ingested {success_count} vectors.")

    # Cleanup
    try:
        requests.post(CHROMA_UNLOAD_URL, timeout=5)
    except: pass
    
    cursor.close()
    conn.close()
    logger.info("Batch Complete.")

if __name__ == "__main__":
    run_ingestion()
