# pyapi/backup/job_types/mysqldump.py
"""
mysqldump job — exports one or more MariaDB databases and transfers via SCP.

options_json fields:
  databases     list   ["main","sys"]   DB env-key aliases (matches db_connector names)
  compress      bool   true             gzip the dump
  verify_sha256 bool   true
"""
from __future__ import annotations
import gzip
import hashlib
import logging
import os
import subprocess
import tempfile
from datetime import datetime
from pathlib import Path
from typing import Dict, List, Tuple

logger = logging.getLogger(__name__)

# Map alias → env key (mirrors db_connector.py logic)
_DB_ENV_MAP = {
    'main':    'DATABASE_URL',
    'default': 'DATABASE_URL',
    'sys':     'DATABASE_SYS_URL',
    'wordnet': 'DATABASE_WORDNET_URL',
}


def run(job, conn, run_logger, destination, project_root: str):
    opts       = job.options
    databases  = opts.get('databases', ['main'])
    compress   = opts.get('compress', True)
    verify     = opts.get('verify_sha256', True)
    remote_dir = job.remote_path

    run_logger.log(f"mysqldump: databases={databases}, compress={compress}")

    # Read .env.local to get connection params for each DB
    env_file = Path(project_root) / '.env.local'
    env_vals = _read_env_file(env_file)

    proj_temp = Path(project_root) / 'temp'
    proj_temp.mkdir(parents=True, exist_ok=True)

    tmp_dir = Path(tempfile.mkdtemp(prefix='bkpforge_dump_', dir=proj_temp))
    artifacts: List[Tuple[Path, str, int, str]] = []  # (path, sha, bytes, label)

    try:
        destination.ensure_remote_dir(remote_dir)

        for alias in databases:
            env_key = _DB_ENV_MAP.get(alias, f'DATABASE_{alias.upper()}_URL')
            db_url  = env_vals.get(env_key)
            if not db_url:
                run_logger.log(f"WARNING: No env var '{env_key}' for alias '{alias}' — skipping")
                continue

            params = _parse_db_url(db_url)
            if not params:
                run_logger.log(f"WARNING: Could not parse URL for '{alias}' — skipping")
                continue

            ts       = datetime.now().strftime('%Y%m%d_%H%M%S')
            ext      = '.sql.gz' if compress else '.sql'
            filename = f"{job.slug}_{alias}_{ts}{ext}"
            out_path = tmp_dir / filename

            run_logger.log(f"Dumping database '{params['database']}' → {filename}")
            _run_mysqldump(params, str(out_path), compress, run_logger)

            sha, size = _sha256_and_size(out_path)
            run_logger.log(f"Dump ready: {size:,} bytes, sha={sha[:16]}…")

            remote_file = f"{remote_dir}/{filename}"
            run_logger.log(f"Uploading → {remote_file}")
            destination.upload(str(out_path), remote_file)
            run_logger.add_artifact(alias, filename, sha, size, remote_file)

            if verify:
                ok = destination.verify_sha256(str(out_path), remote_file)
                if ok:
                    run_logger.mark_artifact_ok(filename)
                else:
                    run_logger.log(f"VERIFY FAILED: {alias}")

            artifacts.append((out_path, sha, size, alias))

    finally:
        for path, _, _, _ in artifacts:
            try:
                path.unlink(missing_ok=True)
            except Exception:
                pass
        try:
            tmp_dir.rmdir()
        except Exception:
            pass

    total_bytes = sum(s for _, _, s, _ in artifacts)
    run_logger.finish('done', f"Dumped {len(artifacts)} database(s), {total_bytes:,} bytes total")


# ── Helpers ────────────────────────────────────────────────────────────────

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


def _parse_db_url(url: str) -> Dict:
    """Parse mysql://user:pass@host:port/dbname into dict."""
    from urllib.parse import urlparse, unquote
    try:
        p = urlparse(url)
        return {
            'host':     p.hostname or '127.0.0.1',
            'port':     str(p.port or 3306),
            'user':     unquote(p.username or ''),
            'password': unquote(p.password or ''),
            'database': p.path.lstrip('/').split('?')[0],
        }
    except Exception as e:
        logger.error("URL parse failed: %s", e)
        return {}


def _run_mysqldump(params: Dict, out_path: str, compress: bool, run_logger):
    cmd = [
        'mysqldump',
        f"--host={params['host']}",
        f"--port={params['port']}",
        f"--user={params['user']}",
        f"--password={params['password']}",
        '--single-transaction',
        '--routines',
        '--triggers',
        '--add-drop-table',
        params['database'],
    ]

    if compress:
        # Pipe through gzip
        with gzip.open(out_path, 'wb') as gz:
            proc = subprocess.Popen(
                cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE
            )
            for chunk in iter(lambda: proc.stdout.read(65536), b''):
                gz.write(chunk)
            _, stderr = proc.communicate()
            if proc.returncode != 0:
                raise RuntimeError(f"mysqldump failed: {stderr.decode()[:500]}")
    else:
        result = subprocess.run(cmd, capture_output=True)
        if result.returncode != 0:
            raise RuntimeError(f"mysqldump failed: {result.stderr.decode()[:500]}")
        with open(out_path, 'wb') as f:
            f.write(result.stdout)

    run_logger.log(f"mysqldump complete → {Path(out_path).name}")


def _sha256_and_size(path: Path) -> Tuple[str, int]:
    h = hashlib.sha256()
    with open(path, 'rb') as f:
        for chunk in iter(lambda: f.read(65536), b''):
            h.update(chunk)
    return h.hexdigest(), path.stat().st_size
