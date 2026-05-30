# pyapi/services/backup_download_service.py
"""
Backup Download Service — FastAPI service for browsing and downloading
remote backup files from any configured backup destination.

This is a READ-ONLY service — it never writes to the remote.
It reuses SCPDestination's SSH infrastructure (ControlMaster socket).

Register in serv.conf.json as:
  "backup_download": {
    "active": true,
    "module": "services.backup_download_service",
    "router_var": "router",
    "prefix": "/backup-dl",
    "tags": ["backup-download"]
  }

Endpoints:
  GET /backup-dl/destinations
      — list all active destinations (reuses backup service DB)

  GET /backup-dl/destinations/{dest_id}/tree?path=sage_backup/media
      — list files and subdirectories at a remote path

  GET /backup-dl/destinations/{dest_id}/fetch?path=sage_backup/media/frames/backup_42_frames_xxx.tar
      — stream the remote file to the client as a download
      — uses scp pull into a server-side temp file, then streams it
"""
from __future__ import annotations
import logging
import os
import shutil
import subprocess
import tempfile
from pathlib import Path
from typing import Optional

from fastapi import APIRouter, HTTPException, Query
from fastapi.responses import FileResponse, JSONResponse

from services.db_connector import get_db_connection

logger = logging.getLogger(__name__)
router = APIRouter(tags=["backup-download"])

# Reuse the same SSH options as scp_destination.py (no key needed — ControlMaster handles it)
_SSH_OPTS = [
    '-o', 'StrictHostKeyChecking=no',
    '-o', 'BatchMode=yes',
    '-o', 'ConnectTimeout=20',
    '-o', 'ServerAliveInterval=30',
]

# Temp dir for staging downloaded files before streaming to client
# Files are deleted immediately after the response is sent
_STAGE_DIR = Path(tempfile.gettempdir()) / 'bkpdl_stage'
_STAGE_DIR.mkdir(parents=True, exist_ok=True)


# ── DB helpers ─────────────────────────────────────────────────────────────

def _load_destination(dest_id: int):
    """Load a single destination row from DB. Returns dict or None."""
    conn = get_db_connection(use_cache=False)
    if not conn:
        return None
    try:
        cur = conn.cursor(dictionary=True)
        cur.execute("SELECT * FROM backup_destinations WHERE id = %s", (dest_id,))
        row = cur.fetchone()
        cur.close()
        return row
    finally:
        conn.close()


def _load_all_destinations():
    conn = get_db_connection(use_cache=False)
    if not conn:
        return []
    try:
        cur = conn.cursor(dictionary=True)
        cur.execute("SELECT * FROM backup_destinations WHERE active = 1 ORDER BY id")
        rows = cur.fetchall()
        cur.close()
        return rows
    finally:
        conn.close()


# ── SSH helpers ────────────────────────────────────────────────────────────

def _resolve_host(dest: dict) -> str:
    """
    Resolve the SSH host for this destination.
    For ap0_scan mode we do a quick scan (same logic as scp_destination.py).
    For static mode we return dest['host'].
    """
    if dest['host_mode'] == 'static':
        if not dest.get('host'):
            raise ValueError("Destination has no static host configured")
        return dest['host']

    # ap0_scan — import the discovery function from scp_destination
    from backup.destinations.scp_destination import discover_tablet_ip
    host = discover_tablet_ip(port=dest['port'])
    if not host:
        raise ConnectionError("Could not discover tablet IP via ap0 scan")
    return host


def _ssh_run(host: str, port: int, remote_cmd: str, timeout: int = 30) -> str:
    """Run a single SSH command and return stdout. Raises on failure."""
    cmd = ['ssh', '-p', str(port), *_SSH_OPTS, host, remote_cmd]
    result = subprocess.run(cmd, capture_output=True, text=True, timeout=timeout)
    if result.returncode != 0:
        logger.warning("SSH cmd failed (rc=%d): %s", result.returncode, result.stderr.strip()[:200])
    return result.stdout


def _scp_pull(host: str, port: int, remote_path: str, local_path: str, timeout: int = 3600):
    """Pull a single file from remote to local_path via scp."""
    cmd = [
        'scp',
        '-P', str(port),
        *_SSH_OPTS,
        f"{host}:{remote_path}",
        local_path,
    ]
    result = subprocess.run(cmd, capture_output=True, text=True, timeout=timeout)
    if result.returncode != 0:
        raise RuntimeError(
            f"scp pull failed (rc={result.returncode}): {result.stderr.strip()[:300]}"
        )


# ── Endpoints ──────────────────────────────────────────────────────────────

@router.get("/destinations")
def list_destinations():
    """List all active backup destinations."""
    rows = _load_all_destinations()
    return {"ok": True, "data": rows}


@router.get("/destinations/{dest_id}/tree")
def list_remote_tree(
    dest_id: int,
    path: str = Query(default="sage_backup", description="Remote path to list"),
):
    """
    List files and subdirectories at a remote path.

    Returns:
      {
        "ok": true,
        "path": "sage_backup/media",
        "entries": [
          {"name": "frames", "type": "dir",  "size": null,    "mtime": "..."},
          {"name": "backup_42_frames.tar", "type": "file", "size": 104857600, "mtime": "..."},
          ...
        ]
      }
    """
    dest = _load_destination(dest_id)
    if not dest:
        raise HTTPException(404, "Destination not found")

    try:
        host = _resolve_host(dest)
    except (ValueError, ConnectionError) as e:
        raise HTTPException(503, str(e))

    # Use ls -la --time-style=long-iso for parseable output
    # --color=never prevents ANSI codes on some systems; -p appends / to dirs
    remote_cmd = f'ls -lap --time-style=long-iso "{path}" 2>&1'
    try:
        stdout = _ssh_run(host, dest['port'], remote_cmd, timeout=15)
    except subprocess.TimeoutExpired:
        raise HTTPException(504, "SSH command timed out")
    except Exception as e:
        raise HTTPException(503, f"SSH error: {str(e)[:200]}")

    if 'No such file or directory' in stdout:
        raise HTTPException(404, f"Remote path not found: {path}")

    entries = _parse_ls_output(stdout)
    return {"ok": True, "path": path, "host": host, "entries": entries}


@router.get("/destinations/{dest_id}/fetch")
def fetch_remote_file(
    dest_id: int,
    path: str = Query(..., description="Full remote path to the file"),
):
    """
    Download a single file from the remote backup destination.

    The file is pulled via scp into a server-side staging temp file,
    then streamed to the client. The temp file is auto-deleted after
    FastAPI finishes streaming (via background task).

    This keeps memory usage low even for large tar archives.
    """
    dest = _load_destination(dest_id)
    if not dest:
        raise HTTPException(404, "Destination not found")

    # Security: prevent path traversal
    if '..' in path or path.startswith('/'):
        raise HTTPException(400, "Invalid path")

    # Resolve remote host
    try:
        host = _resolve_host(dest)
    except (ValueError, ConnectionError) as e:
        raise HTTPException(503, str(e))

    # Derive filename for Content-Disposition
    filename = Path(path).name
    if not filename:
        raise HTTPException(400, "Path must point to a file, not a directory")

    # Stage locally
    stage_path = _STAGE_DIR / f"dl_{dest_id}_{os.getpid()}_{filename}"

    try:
        logger.info("Pulling %s:%s -> %s", host, path, stage_path)
        _scp_pull(host, dest['port'], path, str(stage_path), timeout=3600)
    except RuntimeError as e:
        raise HTTPException(502, str(e))
    except subprocess.TimeoutExpired:
        raise HTTPException(504, "scp transfer timed out")
    except Exception as e:
        raise HTTPException(503, f"Transfer error: {str(e)[:200]}")

    if not stage_path.exists():
        raise HTTPException(502, "File transfer appeared to succeed but file not found locally")

    # Detect media type from extension
    media_type = _guess_media_type(filename)

    # FileResponse streams the file and auto-deletes via background_tasks if we pass
    # delete after response — but FileResponse doesn't support that directly.
    # Instead we return it and register cleanup via a background task.
    from fastapi import BackgroundTasks
    from fastapi.responses import FileResponse

    def _cleanup():
        try:
            stage_path.unlink(missing_ok=True)
            logger.info("Cleaned up staged file: %s", stage_path.name)
        except Exception:
            pass

    response = FileResponse(
        path=str(stage_path),
        media_type=media_type,
        filename=filename,
        background=None,  # we handle cleanup via lifespan instead
    )

    # Attach cleanup — FileResponse accepts a 'background' BackgroundTask
    from starlette.background import BackgroundTask
    response.background = BackgroundTask(_cleanup)

    return response


# ── Parsers / utils ────────────────────────────────────────────────────────

def _parse_ls_output(ls_text: str) -> list:
    """
    Parse `ls -lap --time-style=long-iso` output into a list of entry dicts.
    Skips 'total', '.', '..' lines.

    Example line:
      -rw-r--r-- 1 u0_a123 u0_a123 104857600 2026-04-13 14:22 backup_42_frames.tar
      drwxr-xr-x 2 u0_a123 u0_a123      4096 2026-04-13 14:20 frames/
    """
    entries = []
    for line in ls_text.splitlines():
        line = line.rstrip()
        if not line or line.startswith('total') or line.startswith('ls:'):
            continue

        parts = line.split(None, 7)
        if len(parts) < 8:
            continue

        perms, _, _, _, size_str, date_str, time_str, name = parts
        if name in ('.', '..', './', '../'):
            continue

        is_dir  = perms.startswith('d') or name.endswith('/')
        name    = name.rstrip('/')  # remove trailing slash from dirs

        try:
            size = int(size_str) if not is_dir else None
        except ValueError:
            size = None

        mtime = f"{date_str} {time_str}"

        entries.append({
            "name":  name,
            "type":  "dir" if is_dir else "file",
            "size":  size,
            "mtime": mtime,
        })

    # Dirs first, then files, both sorted by name
    entries.sort(key=lambda e: (0 if e['type'] == 'dir' else 1, e['name'].lower()))
    return entries


def _guess_media_type(filename: str) -> str:
    ext = Path(filename).suffix.lower()
    return {
        '.tar':    'application/x-tar',
        '.gz':     'application/gzip',
        '.sql':    'text/plain',
        '.sql.gz': 'application/gzip',
        '.zip':    'application/zip',
        '.mp4':    'video/mp4',
        '.webm':   'video/webm',
        '.png':    'image/png',
        '.jpg':    'image/jpeg',
        '.jpeg':   'image/jpeg',
    }.get(ext, 'application/octet-stream')
