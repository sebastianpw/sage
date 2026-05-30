# pyapi/backup/job_types/media_tar.py
"""
media_tar job — incremental tar backup of frames/audios/videos.
Direct Python port of bash/bkpmedia.sh with improved error handling.

options_json fields:
  sources       list  ["frames","audios","videos"]   which tables/dirs to include
  incremental   bool  true                           use max_id watermarks
  verify_sha256 bool  true                           verify after transfer
"""
from __future__ import annotations
import hashlib
import os
import subprocess
import tarfile
import tempfile
import logging
from datetime import datetime
from pathlib import Path
from typing import Dict, List, Optional, Tuple

logger = logging.getLogger(__name__)

SUPPORTED_SOURCES = ('frames', 'audios', 'videos')

# SQL to get new file paths since last run
_QUERIES = {
    'frames': "SELECT TRIM(LEADING '/' FROM filename) FROM frames WHERE id > %s ORDER BY id",
    'audios': "SELECT TRIM(LEADING '/' FROM filename) FROM audios WHERE id > %s ORDER BY id",
    'videos': "SELECT TRIM(LEADING '/' FROM url)      FROM videos WHERE id > %s ORDER BY id",
}
_MAX_ID_QUERIES = {
    'frames': "SELECT COALESCE(MAX(id), %s) FROM frames WHERE id > %s",
    'audios': "SELECT COALESCE(MAX(id), %s) FROM audios WHERE id > %s",
    'videos': "SELECT COALESCE(MAX(id), %s) FROM videos WHERE id > %s",
}


def run(job, conn, run_logger, destination, project_root: str):
    """
    Entry point called by the engine.

    job          — JobConfig
    conn         — mysql.connector connection (main DB)
    run_logger   — RunLogger instance (already started)
    destination  — connected SCPDestination (context already entered)
    project_root — PROJECT_ROOT from .env.local
    """
    opts        = job.options
    sources     = [s for s in opts.get('sources', list(SUPPORTED_SOURCES))
                   if s in SUPPORTED_SOURCES]
    incremental = opts.get('incremental', True)
    verify      = opts.get('verify_sha256', True)

    public_root = Path(project_root) / 'public'
    remote_dir  = job.remote_path

    proj_temp = Path(project_root) / 'temp'
    proj_temp.mkdir(parents=True, exist_ok=True)

    env_file = Path(project_root) / '.env.local'
    env_vals = _read_env_file(env_file)
    ts = datetime.now().strftime('%y%m%d_%H%M%S')

    run_logger.log(f"media_tar: sources={sources}, incremental={incremental}")

    # ── 1. Load watermarks ────────────────────────────────────────────────
    watermarks: Dict[str, int] = {}
    if incremental:
        watermarks = _load_watermarks(conn, run_logger)
    else:
        watermarks = {s: 0 for s in sources}

    # ── 2. Collect file lists from DB ─────────────────────────────────────
    file_lists: Dict[str, List[str]] = {}
    for src in sources:
        base_id = watermarks.get(f'{src}_max_id', 0)
        paths   = _query_new_files(conn, src, base_id)
        file_lists[src] = [p for p in paths if p.strip()]
        run_logger.log(f"{src}: {len(file_lists[src])} new files since id={base_id}")

    total_files = sum(len(v) for v in file_lists.values())
    if total_files == 0:
        run_logger.log("No new media — nothing to do")
        run_logger.finish('done', 'No new media since last run')
        return

    # ── 3. Create tar archives ────────────────────────────────────────────
    tmp_dir = Path(tempfile.mkdtemp(prefix='bkpforge_', dir=proj_temp))
    tars: Dict[str, Tuple[Path, str, int]] = {}   # src → (path, sha256, bytes)

    try:
        for src, paths in file_lists.items():
            if not paths:
                continue
            
            # Reimplement legacy bkpmedia.sh filename format
            suffix = _get_suffix(env_vals, src)
            filename = f"backup_{run_logger.run_id}_{src}_{suffix}_{ts}.tar"
            tar_path = tmp_dir / filename
            
            run_logger.log(f"Creating {src} tar ({len(paths)} files)…")
            _create_tar(tar_path, public_root, paths, run_logger)
            sha, size = _sha256_and_size(tar_path)
            tars[src] = (tar_path, sha, size)
            run_logger.log(f"{src} tar ready: {size:,} bytes, sha={sha[:16]}…")

        # ── 4. Compute new max_ids ────────────────────────────────────────
        new_watermarks: Dict[str, int] = {}
        for src in sources:
            base_id = watermarks.get(f'{src}_max_id', 0)
            new_watermarks[f'{src}_max_id'] = _new_max_id(conn, src, base_id)

        # ── 5. Transfer ───────────────────────────────────────────────────
        all_ok = True
        for src, (tar_path, sha, size) in tars.items():
            # Create subfolder path (e.g. sage_backup/media/frames)
            src_remote_dir = f"{remote_dir}/{src}"
            destination.ensure_remote_dir(src_remote_dir)

            remote_file = f"{src_remote_dir}/{tar_path.name}"
            run_logger.log(f"Uploading {src} tar → {remote_file}")
            destination.upload(str(tar_path), remote_file)
            run_logger.add_artifact(src, tar_path.name, sha, size, remote_file)

            if verify:
                ok = destination.verify_sha256(str(tar_path), remote_file)
                if ok:
                    run_logger.mark_artifact_ok(tar_path.name)
                else:
                    run_logger.log(f"VERIFY FAILED: {src}")
                    all_ok = False

        # ── 6. Persist watermarks ─────────────────────────────────────────
        for k, v in new_watermarks.items():
            run_logger.set_watermark(k, v)

        status  = 'done' if all_ok else 'partial'
        message = (f"Backed up {len(tars)} archive(s), "
                   f"{sum(s for _, _, s in tars.values()):,} bytes total")
        run_logger.finish(status, message)

    finally:
        # Always clean up temp files
        for tar_path, _, _ in tars.values():
            try:
                tar_path.unlink(missing_ok=True)
            except Exception:
                pass
        try:
            tmp_dir.rmdir()
        except Exception:
            pass


# ── Helpers ────────────────────────────────────────────────────────────────

def _load_watermarks(conn, run_logger) -> Dict[str, int]:
    """Read the latest successful watermarks from the most recent done run."""
    cur = conn.cursor(dictionary=True)
    cur.execute("""
        SELECT watermark_json FROM backup_runs
        WHERE status IN ('done','partial') AND watermark_json IS NOT NULL
        ORDER BY id DESC LIMIT 1
    """)
    row = cur.fetchone()
    cur.close()
    if row and row['watermark_json']:
        import json
        try:
            wm = json.loads(row['watermark_json'])
            run_logger.log(f"Loaded watermarks: {wm}")
            return wm
        except Exception:
            pass
    run_logger.log("No previous watermarks — full backup")
    return {'frames_max_id': 0, 'audios_max_id': 0, 'videos_max_id': 0}


def _query_new_files(conn, source: str, base_id: int) -> List[str]:
    cur = conn.cursor()
    cur.execute(_QUERIES[source], (base_id,))
    rows = cur.fetchall()
    cur.close()
    return [r[0] for r in rows if r[0]]


def _new_max_id(conn, source: str, base_id: int) -> int:
    cur = conn.cursor()
    cur.execute(_MAX_ID_QUERIES[source], (base_id, base_id))
    row = cur.fetchone()
    cur.close()
    return int(row[0]) if row and row[0] else base_id


def _create_tar(tar_path: Path, base_dir: Path,
                relative_paths: List[str], run_logger):
    """Create a tar archive of files relative to base_dir."""
    missing = 0
    with tarfile.open(str(tar_path), 'w') as tf:
        for rel in relative_paths:
            full = base_dir / rel
            if full.exists():
                tf.add(str(full), arcname=rel)
            else:
                missing += 1

    if missing:
        run_logger.log(f"Warning: {missing} files not found on disk (may have been deleted)")

    # Validate
    with tarfile.open(str(tar_path), 'r') as tf:
        _ = tf.getnames()  # raises if corrupt


def _sha256_and_size(path: Path) -> Tuple[str, int]:
    h = hashlib.sha256()
    with open(path, 'rb') as f:
        for chunk in iter(lambda: f.read(65536), b''):
            h.update(chunk)
    return h.hexdigest(), path.stat().st_size


def _read_env_file(env_file: Path) -> Dict[str, str]:
    vals = {}
    if not env_file.exists():
        return vals
    for line in env_file.read_text().splitlines():
        line = line.strip()
        if not line or line.startswith('#') or '=' not in line:
            continue
        key, _, val = line.partition('=')
        vals[key.strip()] = val.strip().strip('"').strip("'")
    return vals


def _get_suffix(env_vals: Dict[str, str], src: str) -> str:
    key = f"{src.upper()}_ROOT"
    root_path = env_vals.get(key, '')
    if not root_path:
        return "unknown"
    base = Path(root_path).name
    prefix = f"{src}_"
    if base.startswith(prefix):
        return base[len(prefix):]
    return base
