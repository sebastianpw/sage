# pyapi/backup/job_types/zip_paths.py
"""
zip_paths job — zips specified paths from project root and transfers via SCP.
Replaces bkpcp.sh in a configurable, logged way.

options_json fields:
  paths         list   ["src","bash","pyapi","templates","public/*.php"]
  excludes      list   ["pyapi/venv","public/frames"]
  compress      bool   true   (deflate vs store)
  verify_sha256 bool   true
"""
from __future__ import annotations
import hashlib
import logging
import tempfile
import zipfile
from datetime import datetime
from pathlib import Path
from typing import List, Tuple

logger = logging.getLogger(__name__)


def run(job, conn, run_logger, destination, project_root: str):
    opts       = job.options
    paths      = opts.get('paths', [])
    excludes   = set(opts.get('excludes', []))
    compress   = opts.get('compress', True)
    verify     = opts.get('verify_sha256', True)
    remote_dir = job.remote_path

    if not paths:
        run_logger.finish('failed', 'No paths configured for zip_paths job')
        return

    run_logger.log(f"zip_paths: paths={paths}, excludes={list(excludes)}")

    project = Path(project_root)
    proj_temp = project / 'temp'
    proj_temp.mkdir(parents=True, exist_ok=True)
    
    ts       = datetime.now().strftime('%Y%m%d_%H%M%S')
    filename = f"{job.slug}_{ts}.zip"
    tmp_dir  = Path(tempfile.mkdtemp(prefix='bkpforge_zip_', dir=proj_temp))
    out_path = tmp_dir / filename

    try:
        compression = zipfile.ZIP_DEFLATED if compress else zipfile.ZIP_STORED
        file_count  = 0

        with zipfile.ZipFile(str(out_path), 'w', compression=compression) as zf:
            for rel_path in paths:
                
                # Support wildcards like public/*.php
                if '*' in rel_path or '?' in rel_path:
                    for f in project.glob(rel_path):
                        arc = str(f.relative_to(project))
                        if _is_excluded(arc, excludes):
                            continue
                        if f.is_file():
                            zf.write(str(f), arcname=arc)
                            file_count += 1
                    continue

                abs_path = project / rel_path

                # Skip excluded
                if _is_excluded(rel_path, excludes):
                    run_logger.log(f"Excluded: {rel_path}")
                    continue

                if not abs_path.exists():
                    run_logger.log(f"WARNING: Path not found — {rel_path}")
                    continue

                if abs_path.is_file():
                    zf.write(str(abs_path), arcname=rel_path)
                    file_count += 1
                elif abs_path.is_dir():
                    for f in sorted(abs_path.rglob('*')):
                        arc = str(f.relative_to(project))
                        if _is_excluded(arc, excludes):
                            continue
                        if f.is_file():
                            zf.write(str(f), arcname=arc)
                            file_count += 1

        sha, size = _sha256_and_size(out_path)
        run_logger.log(f"ZIP ready: {file_count} files, {size:,} bytes, sha={sha[:16]}…")

        destination.ensure_remote_dir(remote_dir)
        remote_file = f"{remote_dir}/{filename}"
        run_logger.log(f"Uploading → {remote_file}")
        destination.upload(str(out_path), remote_file)
        run_logger.add_artifact('codebase', filename, sha, size, remote_file)

        if verify:
            ok = destination.verify_sha256(str(out_path), remote_file)
            if ok:
                run_logger.mark_artifact_ok(filename)
                run_logger.finish('done', f"Zipped {file_count} files, {size:,} bytes")
            else:
                run_logger.finish('partial', f"Uploaded but SHA256 verify failed")
        else:
            run_logger.finish('done', f"Zipped {file_count} files, {size:,} bytes")

    finally:
        try:
            out_path.unlink(missing_ok=True)
        except Exception:
            pass
        try:
            tmp_dir.rmdir()
        except Exception:
            pass


# ── Helpers ────────────────────────────────────────────────────────────────

def _is_excluded(path_str: str, excludes: set) -> bool:
    for ex in excludes:
        if path_str == ex or path_str.startswith(ex.rstrip('/') + '/'):
            return True
    return False


def _sha256_and_size(path: Path) -> Tuple[str, int]:
    h = hashlib.sha256()
    with open(path, 'rb') as f:
        for chunk in iter(lambda: f.read(65536), b''):
            h.update(chunk)
    return h.hexdigest(), path.stat().st_size
