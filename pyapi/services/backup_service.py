# pyapi/services/backup_service.py
"""
Backup Forge — FastAPI service.
Registered in serv.conf.json as 'backup' with prefix '/backup'.

Endpoints:
  GET  /backup/jobs           — list all jobs
  GET  /backup/jobs/{id}      — single job detail
  GET  /backup/destinations   — list destinations
  GET  /backup/runs           — recent run history
  GET  /backup/runs/{id}      — single run detail (includes log_text)
  POST /backup/run/{job_id}   — trigger a job (async background task)
  GET  /backup/run_status/{run_id} — poll run status
"""
from __future__ import annotations
import json
import logging
from typing import Optional

from fastapi import APIRouter, BackgroundTasks, HTTPException
from fastapi.responses import JSONResponse
from pydantic import BaseModel

from services.db_connector import get_db_connection

logger = logging.getLogger(__name__)
router = APIRouter(tags=["backup"])


# ── Pydantic models ────────────────────────────────────────────────────────

class RunJobRequest(BaseModel):
    ssh_password: Optional[str] = None   # Optional — only needed if key auth not configured


# ── Destinations ───────────────────────────────────────────────────────────

@router.get("/destinations")
def list_destinations():
    conn = get_db_connection(use_cache=False)
    if not conn:
        raise HTTPException(503, "DB unavailable")
    try:
        cur = conn.cursor(dictionary=True)
        cur.execute("SELECT * FROM backup_destinations ORDER BY id")
        rows = cur.fetchall()
        cur.close()
        return {"ok": True, "data": rows}
    finally:
        conn.close()


@router.get("/destinations/{dest_id}")
def get_destination(dest_id: int):
    conn = get_db_connection(use_cache=False)
    if not conn:
        raise HTTPException(503, "DB unavailable")
    try:
        cur = conn.cursor(dictionary=True)
        cur.execute("SELECT * FROM backup_destinations WHERE id = %s", (dest_id,))
        row = cur.fetchone()
        cur.close()
        if not row:
            raise HTTPException(404, "Destination not found")
        return {"ok": True, "data": row}
    finally:
        conn.close()


@router.post("/destinations")
def save_destination(body: dict):
    conn = get_db_connection(use_cache=False)
    if not conn:
        raise HTTPException(503, "DB unavailable")
    try:
        dest_id = body.get('id')
        fields  = ['name','slug','type','host_mode','host','port','remote_base','active','note']
        data    = {f: body.get(f) for f in fields if f in body}
        cur     = conn.cursor()
        if dest_id:
            sets = ', '.join(f"`{k}` = %s" for k in data)
            cur.execute(f"UPDATE backup_destinations SET {sets} WHERE id = %s",
                        [*data.values(), dest_id])
            msg = "Destination updated"
        else:
            cols = ', '.join(f"`{k}`" for k in data)
            vals = ', '.join(['%s'] * len(data))
            cur.execute(f"INSERT INTO backup_destinations ({cols}) VALUES ({vals})",
                        list(data.values()))
            dest_id = cur.lastrowid
            msg = "Destination created"
        cur.close()
        return {"ok": True, "message": msg, "data": {"id": dest_id}}
    finally:
        conn.close()


@router.delete("/destinations/{dest_id}")
def delete_destination(dest_id: int):
    conn = get_db_connection(use_cache=False)
    if not conn:
        raise HTTPException(503, "DB unavailable")
    try:
        cur = conn.cursor()
        cur.execute("DELETE FROM backup_destinations WHERE id = %s", (dest_id,))
        cur.close()
        return {"ok": True, "message": "Destination deleted"}
    finally:
        conn.close()


# ── Jobs ───────────────────────────────────────────────────────────────────

@router.get("/jobs")
def list_jobs():
    conn = get_db_connection(use_cache=False)
    if not conn:
        raise HTTPException(503, "DB unavailable")
    try:
        cur = conn.cursor(dictionary=True)
        cur.execute("""
            SELECT j.*, d.name AS destination_name, d.slug AS destination_slug,
                   d.type AS destination_type
            FROM backup_jobs j
            LEFT JOIN backup_destinations d ON d.id = j.destination_id
            ORDER BY j.sort_order, j.id
        """)
        rows = cur.fetchall()
        cur.close()
        # Decode options_json
        for r in rows:
            try:
                r['options'] = json.loads(r.get('options_json') or '{}')
            except Exception:
                r['options'] = {}
        return {"ok": True, "data": rows}
    finally:
        conn.close()


@router.get("/jobs/{job_id}")
def get_job(job_id: int):
    conn = get_db_connection(use_cache=False)
    if not conn:
        raise HTTPException(503, "DB unavailable")
    try:
        cur = conn.cursor(dictionary=True)
        cur.execute("""
            SELECT j.*, d.name AS destination_name, d.slug AS destination_slug,
                   d.type AS destination_type, d.host_mode, d.host, d.port,
                   d.remote_base
            FROM backup_jobs j
            LEFT JOIN backup_destinations d ON d.id = j.destination_id
            WHERE j.id = %s
        """, (job_id,))
        row = cur.fetchone()
        cur.close()
        if not row:
            raise HTTPException(404, "Job not found")
        try:
            row['options'] = json.loads(row.get('options_json') or '{}')
        except Exception:
            row['options'] = {}
        return {"ok": True, "data": row}
    finally:
        conn.close()


@router.post("/jobs")
def save_job(body: dict):
    conn = get_db_connection(use_cache=False)
    if not conn:
        raise HTTPException(503, "DB unavailable")
    try:
        job_id  = body.get('id')
        options = body.get('options', {})
        fields  = ['name','slug','active','sort_order','job_type',
                   'destination_id','remote_subfolder','schedule_hint','note']
        data    = {f: body.get(f) for f in fields if f in body}
        data['options_json'] = json.dumps(options)

        cur = conn.cursor()
        if job_id:
            sets = ', '.join(f"`{k}` = %s" for k in data)
            cur.execute(f"UPDATE backup_jobs SET {sets} WHERE id = %s",
                        [*data.values(), job_id])
            msg = "Job updated"
        else:
            cols = ', '.join(f"`{k}`" for k in data)
            vals = ', '.join(['%s'] * len(data))
            cur.execute(f"INSERT INTO backup_jobs ({cols}) VALUES ({vals})",
                        list(data.values()))
            job_id = cur.lastrowid
            msg = "Job created"
        cur.close()
        return {"ok": True, "message": msg, "data": {"id": job_id}}
    finally:
        conn.close()


@router.delete("/jobs/{job_id}")
def delete_job(job_id: int):
    conn = get_db_connection(use_cache=False)
    if not conn:
        raise HTTPException(503, "DB unavailable")
    try:
        cur = conn.cursor()
        cur.execute("DELETE FROM backup_jobs WHERE id = %s", (job_id,))
        cur.close()
        return {"ok": True, "message": "Job deleted"}
    finally:
        conn.close()


# ── Runs ───────────────────────────────────────────────────────────────────

@router.get("/runs")
def list_runs(job_id: Optional[int] = None, limit: int = 50):
    conn = get_db_connection(use_cache=False)
    if not conn:
        raise HTTPException(503, "DB unavailable")
    try:
        cur = conn.cursor(dictionary=True)
        if job_id:
            cur.execute("""
                SELECT id, job_id, job_slug, job_type, status, started_at, finished_at,
                       elapsed_sec, files_total, files_ok, bytes_total, message, created_at
                FROM backup_runs WHERE job_id = %s
                ORDER BY id DESC LIMIT %s
            """, (job_id, limit))
        else:
            cur.execute("""
                SELECT id, job_id, job_slug, job_type, status, started_at, finished_at,
                       elapsed_sec, files_total, files_ok, bytes_total, message, created_at
                FROM backup_runs
                ORDER BY id DESC LIMIT %s
            """, (limit,))
        rows = cur.fetchall()
        cur.close()
        return {"ok": True, "data": rows}
    finally:
        conn.close()


@router.get("/runs/{run_id}")
def get_run(run_id: int):
    conn = get_db_connection(use_cache=False)
    if not conn:
        raise HTTPException(503, "DB unavailable")
    try:
        cur = conn.cursor(dictionary=True)
        cur.execute("SELECT * FROM backup_runs WHERE id = %s", (run_id,))
        row = cur.fetchone()
        cur.close()
        if not row:
            raise HTTPException(404, "Run not found")
        for json_col in ('artifacts_json', 'watermark_json'):
            if row.get(json_col):
                try:
                    row[json_col.replace('_json', '')] = json.loads(row[json_col])
                except Exception:
                    pass
        return {"ok": True, "data": row}
    finally:
        conn.close()


# ── Trigger ────────────────────────────────────────────────────────────────

# Track running jobs to prevent duplicate concurrent runs
_running: set = set()

@router.post("/run/{job_id}")
async def trigger_run(job_id: int, req: RunJobRequest,
                      background_tasks: BackgroundTasks):
    
    # DB lock check (safer than memory set if uvicorn restarted)
    conn = get_db_connection(use_cache=False)
    if not conn:
        raise HTTPException(503, "DB unavailable")
    try:
        cur = conn.cursor()
        cur.execute("SELECT id FROM backup_runs WHERE job_id = %s AND status = 'running'", (job_id,))
        if cur.fetchone():
            return JSONResponse({"ok": False, "error": "Job is already marked as running in DB"}, status_code=409)
        cur.close()
    finally:
        conn.close()

    if job_id in _running:
        return JSONResponse({"ok": False, "error": "Job is already running"},
                            status_code=409)

    _running.add(job_id)
    background_tasks.add_task(_execute_job, job_id, req.ssh_password)
    return {"ok": True, "message": f"Job {job_id} queued", "data": {"job_id": job_id}}

def _execute_job(job_id: int, ssh_password: Optional[str]):
    conn = None
    try:
        from backup.engine import run_job
        conn   = get_db_connection(use_cache=False)
        result = run_job(job_id, conn, ssh_password=ssh_password)
        logger.info("Job %d finished: %s", job_id, result)
    except Exception as e:
        logger.exception("Background job %d crashed", job_id)
    finally:
        _running.discard(job_id)
        if conn:
            try:
                conn.close()
            except Exception:
                pass


@router.get("/run_status/{run_id}")
def run_status(run_id: int):
    """Lightweight poll endpoint — returns status + message without log_text."""
    conn = get_db_connection(use_cache=False)
    if not conn:
        raise HTTPException(503, "DB unavailable")
    try:
        cur = conn.cursor(dictionary=True)
        cur.execute("""
            SELECT id, status, message, files_total, files_ok, bytes_total,
                   elapsed_sec, started_at, finished_at
            FROM backup_runs WHERE id = %s
        """, (run_id,))
        row = cur.fetchone()
        cur.close()
        if not row:
            raise HTTPException(404, "Run not found")
        return {"ok": True, "data": row}
    finally:
        conn.close()
