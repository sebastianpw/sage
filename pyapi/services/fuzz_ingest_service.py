# pyapi/services/fuzz_ingest_service.py
"""
SAGE Fuzz Forge - Local Database Ingestion Service
Handles massive bulk inserts and conceptual prioritization.
Must run on the local PyAPI (where MariaDB is accessible).
"""

import gc
import logging
import time
import uuid
from concurrent.futures import ThreadPoolExecutor
from threading import Lock
from typing import Any, Dict, List, Optional

from fastapi import APIRouter, HTTPException
from pydantic import BaseModel

from services.db_connector import get_db_connection

logger = logging.getLogger(__name__)
router = APIRouter(tags=["fuzz_ingest"])

INGEST_EXECUTOR = ThreadPoolExecutor(max_workers=1)
JOB_LOCK = Lock()
JOB_REGISTRY: Dict[str, Dict[str, Any]] = {}


class IngestRequest(BaseModel):
    clusters: List[List[str]]
    max_candidates: int = 500000


def _job_now() -> float:
    return time.time()


def _create_job(total_items: int) -> str:
    job_id = uuid.uuid4().hex
    with JOB_LOCK:
        JOB_REGISTRY[job_id] = {
            "job_id": job_id,
            "status": "queued",
            "created_at": _job_now(),
            "updated_at": _job_now(),
            "total_items": total_items,
            "progress": {
                "stage": "queued",
                "processed": 0,
                "total": total_items,
                "message": "Queued for ingestion",
            },
            "result": None,
            "error": None,
        }
    return job_id


def _update_job(job_id: str, **updates: Any) -> None:
    with JOB_LOCK:
        job = JOB_REGISTRY.get(job_id)
        if not job:
            return
        if "progress" in updates and job.get("progress"):
            job["progress"].update(updates["progress"])
            del updates["progress"]
        job.update(updates)
        job["updated_at"] = _job_now()


def _get_job_copy(job_id: str) -> Optional[Dict[str, Any]]:
    with JOB_LOCK:
        job = JOB_REGISTRY.get(job_id)
        if not job:
            return None
        return {
            "job_id": job["job_id"],
            "status": job["status"],
            "created_at": job["created_at"],
            "updated_at": job["updated_at"],
            "total_items": job["total_items"],
            "progress": dict(job.get("progress") or {}),
            "result": job.get("result"),
            "error": job.get("error"),
        }


def _run_ingestion(job_id: str, payload: Dict[str, Any]):
    try:
        clusters = payload.get("clusters", [])
        max_cand = payload.get("max_candidates", 500000)
        
        _update_job(job_id, status="running", progress={"stage": "starting", "message": "Acquiring DB connection..."})
        
        conn = get_db_connection()
        if not conn:
            raise Exception("Failed to acquire local Database connection.")
            
        try:
            cur = conn.cursor(dictionary=True)
            
            # 1. Load full queue into memory mapping
            _update_job(job_id, progress={"stage": "loading_queue", "message": "Loading fuzz_queue records into memory..."})
            cur.execute("SELECT * FROM fuzz_queue")
            queue_rows = cur.fetchall()
            
            queue_by_norm = {}
            for row in queue_rows:
                norm = row.get('normalized_text')
                if not norm:
                    continue
                if norm not in queue_by_norm:
                    queue_by_norm[norm] = []
                queue_by_norm[norm].append(row)
                
            # Safely release bulk flat list to keep RAM usage low
            del queue_rows
            gc.collect()

            created = 0
            skipped = 0
            mentions_inserted = 0
            
            _update_job(job_id, progress={"stage": "ingesting", "message": "Beginning bulk insertion..."})

            # Tiers for naming prioritization
            TIER_1 = {"characters", "animas", "locations", "backgrounds", "artifacts", "vehicles", "kg_nodes"}
            TIER_2 = {"sketch_analysis", "sketch_lore_history"}
            # Everything else (sketches, sketch_ingredients) is TIER_3

            for idx, cluster in enumerate(clusters[:max_cand]):
                members = []
                for norm in cluster:
                    members.extend(queue_by_norm.get(norm, []))
                    
                if not members:
                    skipped += 1
                    continue
                    
                # 2. Prioritize Canonical Entity Label
                best_member = None
                best_tier = 99
                
                for m in members:
                    tbl = str(m.get('source_table', ''))
                    tier = 1 if tbl in TIER_1 else 2 if tbl in TIER_2 else 3
                        
                    if tier < best_tier:
                        best_tier = tier
                        best_member = m
                    elif tier == best_tier:
                        # Tiebreaker: Longest text typically provides the richest descriptor
                        cur_text = str(m.get('extracted_text', ''))
                        best_text = str(best_member.get('extracted_text', '')) if best_member else ''
                        if best_member is None or len(cur_text) > len(best_text):
                            best_member = m
                            
                if not best_member:
                    skipped += 1
                    continue

                label = str(best_member.get('extracted_text', ''))[:512]
                if len(label.strip()) < 2:
                    skipped += 1
                    continue

                # 3. Create or find candidate
                cur.execute("SELECT id FROM fuzz_candidates WHERE label = %s LIMIT 1", (label,))
                existing = cur.fetchone()

                if existing:
                    candidate_id = existing['id']
                    skipped += 1
                else:
                    cur.execute("SELECT id FROM kg_nodes WHERE name = %s AND status = 'active' LIMIT 1", (label,))
                    kg_res = cur.fetchone()
                    kg_id = kg_res['id'] if kg_res else None
                    status = 'promoted' if kg_id else 'extracted'

                    cur.execute(
                        "INSERT INTO fuzz_candidates (label, status, confidence, kg_node_id) VALUES (%s, %s, 75, %s)", 
                        (label, status, kg_id)
                    )
                    candidate_id = cur.lastrowid
                    created += 1

                    if kg_id:
                        cur.execute(
                            "INSERT INTO fuzz_resolutions (candidate_id, kg_node_id, outcome, note) VALUES (%s, %s, 'promoted', 'Auto-linked to KG during PyAPI clustering')", 
                            (candidate_id, kg_id)
                        )

                # 4. Prepare bulk mentions logic
                mention_data = []
                for m in members:
                    ext_txt = str(m.get('extracted_text', ''))[:512]
                    ctx_snp = str(m.get('context_snippet', ''))[:500] if m.get('context_snippet') else None
                    mention_data.append((
                        candidate_id,
                        m.get('source_table'),
                        m.get('source_row_id'),
                        m.get('source_field'),
                        m.get('mention_type'),
                        ext_txt,
                        m.get('normalized_text'),
                        ctx_snp
                    ))

                if mention_data:
                    cur.executemany("""
                        INSERT IGNORE INTO fuzz_mentions 
                        (candidate_id, source_table, source_row_id, source_field, mention_type, extracted_text, normalized_text, context_snippet)
                        VALUES (%s, %s, %s, %s, %s, %s, %s, %s)
                    """, mention_data)
                    mentions_inserted += cur.rowcount

                # Periodically commit and update frontend
                if idx % 100 == 0:
                    conn.commit()
                    _update_job(job_id, progress={
                        "processed": idx, 
                        "message": f"Assembled {idx} candidate concepts..."
                    })

            conn.commit()

            result = {
                "created": created,
                "skipped": skipped,
                "mentions_inserted": mentions_inserted
            }

            _update_job(
                job_id,
                status="success",
                result=result,
                progress={"stage": "done", "processed": len(clusters), "message": f"Successfully ingested {created} new candidates and {mentions_inserted} mentions."}
            )

        except Exception as e:
            conn.rollback()
            raise e
        finally:
            cur.close()
            conn.close()

    except Exception as e:
        logger.exception("Async ingest job failed.")
        _update_job(
            job_id,
            status="error",
            error=str(e),
            progress={"stage": "error", "message": f"Failed: {str(e)}"}
        )


@router.post("/start")
def start_ingest_job(req: IngestRequest):
    job_id = _create_job(total_items=len(req.clusters))
    payload = {"clusters": req.clusters, "max_candidates": req.max_candidates}
    INGEST_EXECUTOR.submit(_run_ingestion, job_id, payload)
    return {"status": "queued", "job_id": job_id}


@router.get("/status/{job_id}")
def get_ingest_job_status(job_id: str):
    job = _get_job_copy(job_id)
    if not job:
        raise HTTPException(status_code=404, detail="Unknown ingest job id.")
    
    response = {
        "job_id": job["job_id"],
        "status": job["status"],
        "progress": job.get("progress") or {},
    }

    if job["status"] == "success":
        response.update(job.get("result") or {})
    elif job["status"] == "error":
        response["error"] = job.get("error")

    return response
