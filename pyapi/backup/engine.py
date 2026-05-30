# pyapi/backup/engine.py
"""
Backup engine — dispatches job runs to the correct job_type module.
Called by the FastAPI service or directly from CLI.
"""
from __future__ import annotations
import logging
import os
from pathlib import Path
from typing import Optional

from .config import load_job_by_id, JobConfig
from .db_log import RunLogger

logger = logging.getLogger(__name__)

# Lazy-load job type modules
_JOB_TYPES = {
    'media_tar':  '.job_types.media_tar',
    'mysqldump':  '.job_types.mysqldump',
    'zip_paths':  '.job_types.zip_paths',
}


def run_job(job_id: int, conn, ssh_password: Optional[str] = None) -> dict:
    """
    Run a single backup job by ID.
    Returns a dict with run_id, status, message.
    """
    # Load job config
    job = load_job_by_id(conn, job_id)
    if not job:
        return {'ok': False, 'error': f'Job id={job_id} not found'}

    if not job.active:
        return {'ok': False, 'error': f'Job "{job.slug}" is inactive'}

    # Resolve PROJECT_ROOT
    project_root = _resolve_project_root()
    if not project_root:
        return {'ok': False, 'error': 'PROJECT_ROOT not found in .env.local'}

    # Start run log
    run_log = RunLogger(conn, job.id, job.slug, job.job_type)
    run_id  = run_log.start()

    # Dispatch
    try:
        module = _load_job_module(job.job_type)
        if module is None:
            run_log.finish('failed', f'Unknown job_type: {job.job_type}')
            return {'ok': False, 'run_id': run_id, 'error': f'Unknown job_type: {job.job_type}'}

        dest_cfg = job.destination
        if dest_cfg.type == 'scp':
            from .destinations.scp_destination import SCPDestination
            with SCPDestination(dest_cfg, password=ssh_password, run_logger=run_log) as dest:
                module.run(job, conn, run_log, dest, project_root)
        elif dest_cfg.type == 'local':
            from .destinations.local_destination import LocalDestination
            with LocalDestination(dest_cfg) as dest:
                module.run(job, conn, run_log, dest, project_root)
        else:
            run_log.finish('failed', f'Unknown destination type: {dest_cfg.type}')
            return {'ok': False, 'run_id': run_id, 'error': f'Unknown destination type'}

        return {
            'ok':      True,
            'run_id':  run_id,
            'status':  run_log.status,
            'message': run_log.message,
        }

    except Exception as e:
        logger.exception("Job '%s' raised an exception", job.slug)
        run_log.finish('failed', str(e)[:500])
        return {
            'ok':      False,
            'run_id':  run_id,
            'error':   str(e),
        }


def _load_job_module(job_type: str):
    import importlib
    mod_path = _JOB_TYPES.get(job_type)
    if not mod_path:
        return None
    try:
        return importlib.import_module(mod_path, package=__package__)
    except ImportError as e:
        logger.error("Could not import job module '%s': %s", mod_path, e)
        return None


def _resolve_project_root() -> Optional[str]:
    """Walk up from this file to find .env.local, return PROJECT_ROOT value."""
    here = Path(__file__).resolve()
    for parent in [here.parent, here.parent.parent, here.parent.parent.parent]:
        env_file = parent / '.env.local'
        if env_file.exists():
            for line in env_file.read_text().splitlines():
                if line.startswith('PROJECT_ROOT='):
                    val = line.split('=', 1)[1].strip().strip('"').strip("'")
                    if val:
                        return val
    # Fallback to environment variable
    return os.environ.get('PROJECT_ROOT')
