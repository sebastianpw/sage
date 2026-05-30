# pyapi/backup/db_log.py
"""
Manages backup_runs rows — insert, update status, append log lines.
"""
from __future__ import annotations
import json
import logging
from datetime import datetime
from typing import Any, Dict, List, Optional

logger = logging.getLogger(__name__)


class RunLogger:
    """
    Encapsulates a single backup_runs row.
    Call update() to push the current state to DB.
    """

    def __init__(self, conn, job_id: int, job_slug: str, job_type: str):
        self.conn      = conn
        self.job_id    = job_id
        self.job_slug  = job_slug
        self.job_type  = job_type
        self.run_id: Optional[int] = None

        self.status        = 'pending'
        self.started_at: Optional[datetime] = None
        self.finished_at: Optional[datetime] = None
        self.artifacts: List[Dict[str, Any]] = []
        self.watermark: Dict[str, Any] = {}
        self.files_total   = 0
        self.files_ok      = 0
        self.bytes_total   = 0
        self.message       = ''
        self._log_lines: List[str] = []

    # ── Lifecycle ──────────────────────────────────────────────────────────

    def start(self) -> int:
        """Insert a 'running' row and return run_id."""
        self.status     = 'running'
        self.started_at = datetime.now()
        self._log('Run started')
        cur = self.conn.cursor()
        cur.execute("""
            INSERT INTO backup_runs
              (job_id, job_slug, job_type, status, started_at, message)
            VALUES (%s, %s, %s, 'running', %s, 'Starting…')
        """, (self.job_id, self.job_slug, self.job_type, self.started_at))
        self.run_id = cur.lastrowid
        cur.close()
        logger.info("[run=%s] started for job '%s'", self.run_id, self.job_slug)
        return self.run_id

    def finish(self, status: str, message: str = ''):
        """Mark the run as done/failed/partial and write final state."""
        self.status      = status
        self.finished_at = datetime.now()
        if message:
            self.message = message
        elapsed = int((self.finished_at - self.started_at).total_seconds()) if self.started_at else None
        self._log(f"Run finished: {status} — {message}")
        self._flush(elapsed)

    # ── Progress helpers ───────────────────────────────────────────────────

    def add_artifact(self, label: str, filename: str, sha256: str,
                     bytes_size: int, remote_path: str):
        self.artifacts.append({
            'label': label, 'filename': filename,
            'sha256': sha256, 'bytes': bytes_size,
            'remote_path': remote_path,
        })
        self.files_total += 1
        self.bytes_total += bytes_size
        self._log(f"Artifact: {label} — {filename} ({bytes_size:,} bytes)")

    def mark_artifact_ok(self, filename: str):
        self.files_ok += 1
        self._log(f"Verified OK: {filename}")

    def set_watermark(self, key: str, value: Any):
        self.watermark[key] = value

    def log(self, msg: str):
        self._log(msg)
        # Flush status every log line so UI can tail it
        self._flush()

    def _log(self, msg: str):
        ts = datetime.now().strftime('%H:%M:%S')
        self._log_lines.append(f"[{ts}] {msg}")
        logger.info("[run=%s] %s", self.run_id, msg)

    # ── DB flush ───────────────────────────────────────────────────────────

    def _flush(self, elapsed: Optional[int] = None):
        if not self.run_id:
            return
        cur = self.conn.cursor()
        cur.execute("""
            UPDATE backup_runs SET
              status          = %s,
              finished_at     = %s,
              elapsed_sec     = %s,
              artifacts_json  = %s,
              watermark_json  = %s,
              files_total     = %s,
              files_ok        = %s,
              bytes_total     = %s,
              message         = %s,
              log_text        = %s,
              updated_at      = NOW()
            WHERE id = %s
        """, (
            self.status,
            self.finished_at,
            elapsed,
            json.dumps(self.artifacts),
            json.dumps(self.watermark) if self.watermark else None,
            self.files_total,
            self.files_ok,
            self.bytes_total,
            self.message[:1000] if self.message else '',
            '\n'.join(self._log_lines),
            self.run_id,
        ))
        cur.close()
