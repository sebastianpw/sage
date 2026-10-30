# pyapi/services/tiktok_transport.py
"""
TikTok Transport Forge — FastAPI service.
Registered in serv.conf.json as 'tiktok' with prefix '/tiktok'.

Endpoints:
  GET    /tiktok/queue          — list all runs
  POST   /tiktok/download       — add new URL and trigger background download
  POST   /tiktok/retrigger/{id} — re-trigger an existing URL
  GET    /tiktok/serve/{id}     — download the mp4 to browser AND instantly delete it
  DELETE /tiktok/queue/{id}     — remove record from history
"""
import os
import logging
from pathlib import Path

from fastapi import APIRouter, BackgroundTasks, HTTPException
from fastapi.responses import StreamingResponse
from pydantic import BaseModel
import yt_dlp

from services.db_connector import get_db_connection

logger = logging.getLogger(__name__)
router = APIRouter(tags=["tiktok"])

# Temp directory (files only live here for a few seconds/minutes)
PROJECT_ROOT = Path(__file__).resolve().parents[2]
REELS_DIR = PROJECT_ROOT / "temp" / "reels_staging"
REELS_DIR.mkdir(parents=True, exist_ok=True)

class DownloadRequest(BaseModel):
    url: str

@router.get("/queue")
def list_queue():
    conn = get_db_connection(use_cache=False)
    if not conn:
        raise HTTPException(503, "DB unavailable")
    try:
        cur = conn.cursor(dictionary=True)
        cur.execute("SELECT id, url, status, error_msg, created_at FROM tiktok_queue ORDER BY id DESC")
        rows = cur.fetchall()
        cur.close()
        return {"ok": True, "data": rows}
    finally:
        conn.close()

@router.post("/download")
def trigger_download(req: DownloadRequest, background_tasks: BackgroundTasks):
    conn = get_db_connection(use_cache=False)
    if not conn:
        raise HTTPException(503, "DB unavailable")
    try:
        cur = conn.cursor()
        cur.execute(
            "INSERT INTO tiktok_queue (url, status) VALUES (%s, 'downloading')", 
            (req.url,)
        )
        item_id = cur.lastrowid
        cur.close()
        
        background_tasks.add_task(_download_reel_task, item_id, req.url)
        return {"ok": True, "message": "Download queued", "data": {"id": item_id}}
    finally:
        conn.close()

@router.post("/retrigger/{item_id}")
def retrigger_download(item_id: int, background_tasks: BackgroundTasks):
    conn = get_db_connection(use_cache=False)
    if not conn:
        raise HTTPException(503, "DB unavailable")
    try:
        cur = conn.cursor(dictionary=True)
        cur.execute("SELECT url FROM tiktok_queue WHERE id = %s", (item_id,))
        row = cur.fetchone()
        if not row:
            raise HTTPException(404, "Item not found")

        cur.execute("UPDATE tiktok_queue SET status = 'downloading', error_msg = NULL WHERE id = %s", (item_id,))
        cur.close()
        
        background_tasks.add_task(_download_reel_task, item_id, row['url'])
        return {"ok": True, "message": "Re-download queued"}
    finally:
        conn.close()

def _download_reel_task(item_id: int, url: str):
    logger.info("Starting yt-dlp download for queue id=%s", item_id)
    conn = get_db_connection(use_cache=False)
    if not conn: return

    filepath_base = str(REELS_DIR / f"reel_{item_id}")
    ydl_opts = {
        'outtmpl': f'{filepath_base}.%(ext)s',
        'format': 'bestvideo[ext=mp4]+bestaudio[ext=m4a]/best[ext=mp4]',
        'merge_output_format': 'mp4',
        'quiet': True,
        'no_warnings': True,
    }

    try:
        with yt_dlp.YoutubeDL(ydl_opts) as ydl:
            ydl.download([url])
            
        mp4_path = f"{filepath_base}.mp4"
        
        cur = conn.cursor()
        cur.execute("UPDATE tiktok_queue SET status = 'downloaded', filepath = %s WHERE id = %s", (mp4_path, item_id))
        cur.close()
    except Exception as e:
        logger.exception("yt-dlp failed")
        cur = conn.cursor()
        cur.execute("UPDATE tiktok_queue SET status = 'error', error_msg = %s WHERE id = %s", (str(e), item_id))
        cur.close()
    finally:
        conn.close()

@router.get("/serve/{item_id}")
def serve_and_delete(item_id: int):
    """
    Streams the MP4 to the browser.
    The moment the stream finishes (or is cancelled), the finally block deletes the file.
    """
    conn = get_db_connection(use_cache=False)
    if not conn:
        raise HTTPException(503, "DB unavailable")
        
    try:
        cur = conn.cursor(dictionary=True)
        cur.execute("SELECT filepath FROM tiktok_queue WHERE id = %s", (item_id,))
        row = cur.fetchone()
        
        if not row or not row['filepath'] or not os.path.exists(row['filepath']):
            cur.close()
            raise HTTPException(404, "File not ready, missing, or already consumed.")
            
        filepath = row['filepath']
        
        # Reset DB status to pending so it can be fetched again in the future
        cur.execute("UPDATE tiktok_queue SET status = 'pending', filepath = NULL WHERE id = %s", (item_id,))
        cur.close()

        def file_iterator_and_cleanup():
            try:
                with open(filepath, "rb") as f:
                    while chunk := f.read(65536):
                        yield chunk
            finally:
                # GUARANTEED CLEANUP: Executes when stream ends or connection drops
                try:
                    if os.path.exists(filepath):
                        os.remove(filepath)
                    logger.info("Cleaned up file %s immediately after serving", filepath)
                except Exception as e:
                    logger.error("Failed to delete file %s: %s", filepath, e)

        headers = {"Content-Disposition": f'attachment; filename="ig_reel_{item_id}.mp4"'}
        return StreamingResponse(file_iterator_and_cleanup(), media_type="video/mp4", headers=headers)
        
    finally:
        conn.close()

@router.delete("/queue/{item_id}")
def delete_item(item_id: int):
    conn = get_db_connection(use_cache=False)
    if not conn: raise HTTPException(503, "DB unavailable")
    try:
        cur = conn.cursor(dictionary=True)
        cur.execute("SELECT filepath FROM tiktok_queue WHERE id = %s", (item_id,))
        row = cur.fetchone()
        
        if row and row['filepath'] and os.path.exists(row['filepath']):
            try: os.remove(row['filepath'])
            except: pass

        cur.execute("DELETE FROM tiktok_queue WHERE id = %s", (item_id,))
        cur.close()
        return {"ok": True, "message": "Deleted"}
    finally:
        conn.close()