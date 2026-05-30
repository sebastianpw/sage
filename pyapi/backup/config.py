# pyapi/backup/config.py
"""
Loads backup job and destination definitions from MariaDB.
All config lives in the DB — no JSON files needed.
"""
from __future__ import annotations
import json
import logging
from dataclasses import dataclass, field
from typing import Any, Dict, List, Optional

logger = logging.getLogger(__name__)


@dataclass
class DestinationConfig:
    id: int
    name: str
    slug: str
    type: str            # 'scp' | 'local'
    host_mode: str       # 'static' | 'ap0_scan'
    host: Optional[str]
    port: int
    remote_base: str
    active: bool
    note: Optional[str]


@dataclass
class JobConfig:
    id: int
    name: str
    slug: str
    active: bool
    sort_order: int
    job_type: str        # 'media_tar' | 'mysqldump' | 'zip_paths'
    destination: DestinationConfig
    options: Dict[str, Any]
    remote_subfolder: Optional[str]
    schedule_hint: Optional[str]
    note: Optional[str]

    @property
    def remote_path(self) -> str:
        base = self.destination.remote_base.rstrip('/')
        sub  = (self.remote_subfolder or '').strip('/')
        return f"{base}/{sub}" if sub else base


def load_destinations(conn) -> Dict[int, DestinationConfig]:
    cur = conn.cursor(dictionary=True)
    cur.execute("SELECT * FROM backup_destinations WHERE active = 1 ORDER BY id")
    rows = cur.fetchall()
    cur.close()
    return {
        r['id']: DestinationConfig(
            id=r['id'], name=r['name'], slug=r['slug'],
            type=r['type'], host_mode=r['host_mode'],
            host=r.get('host'), port=r['port'],
            remote_base=r['remote_base'], active=bool(r['active']),
            note=r.get('note'),
        )
        for r in rows
    }


def load_jobs(conn, active_only: bool = True) -> List[JobConfig]:
    dests = load_destinations(conn)
    cur   = conn.cursor(dictionary=True)
    query = "SELECT * FROM backup_jobs"
    if active_only:
        query += " WHERE active = 1"
    query += " ORDER BY sort_order, id"
    cur.execute(query)
    rows = cur.fetchall()
    cur.close()

    jobs = []
    for r in rows:
        dest = dests.get(r['destination_id'])
        if not dest:
            logger.warning("Job '%s' references unknown destination_id=%s — skipping",
                           r['slug'], r['destination_id'])
            continue
        try:
            options = json.loads(r['options_json'] or '{}')
        except json.JSONDecodeError:
            logger.error("Job '%s' has invalid options_json — using {}", r['slug'])
            options = {}
        jobs.append(JobConfig(
            id=r['id'], name=r['name'], slug=r['slug'],
            active=bool(r['active']), sort_order=r['sort_order'],
            job_type=r['job_type'], destination=dest,
            options=options,
            remote_subfolder=r.get('remote_subfolder'),
            schedule_hint=r.get('schedule_hint'),
            note=r.get('note'),
        ))
    return jobs


def load_job_by_id(conn, job_id: int) -> Optional[JobConfig]:
    jobs = load_jobs(conn, active_only=False)
    return next((j for j in jobs if j.id == job_id), None)
