# pyapi/utils/keyword_harvester.py
#!/usr/bin/env python3
"""
Resumable keyword harvester for SAGE PyAPI.

What it does:
- Reads seed phrases from seopy_keyword_seed_inputs
- Expands them via Google Suggest / Autocomplete
- Persists the queue in MariaDB so the job can be resumed later
- Supports starting a new run or resuming an existing run

Important:
- Google Suggest is an unofficial / undocumented discovery surface.
- This script is for keyword discovery, not volume measurement.
- Resume works because queue state is stored in DB, not memory.
"""

from __future__ import annotations

import argparse
import json
import logging
import os
import random
import re
import sys
import time
from dataclasses import dataclass
from datetime import datetime
from pathlib import Path
from typing import Any, Dict, Iterable, List, Optional, Sequence, Set, Tuple

import requests

# ---------------------------------------------------------------------
# Path setup
# ---------------------------------------------------------------------
UTILS_DIR = Path(__file__).resolve().parent
PYAPI_DIR = UTILS_DIR.parent
PROJECT_ROOT = PYAPI_DIR.parent
sys.path.append(str(PYAPI_DIR))

from services.db_connector import get_db_connection  # noqa: E402


# ---------------------------------------------------------------------
# Logging
# ---------------------------------------------------------------------
logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s - %(levelname)s - %(name)s - %(message)s",
)
logger = logging.getLogger("SAGE_KEYWORD_HARVESTER")


# ---------------------------------------------------------------------
# Defaults / config
# ---------------------------------------------------------------------
DEFAULT_SUGGEST_URL = "https://suggestqueries.google.com/complete/search"
DEFAULT_LANG = os.getenv("SEOPY_HARVEST_LANGUAGE", "en")
DEFAULT_COUNTRY = os.getenv("SEOPY_HARVEST_COUNTRY", "DE")
DEFAULT_MAX_DEPTH = int(os.getenv("SEOPY_HARVEST_MAX_DEPTH", "2"))
DEFAULT_MAX_SEEDS = int(os.getenv("SEOPY_HARVEST_MAX_SEEDS", "200"))
DEFAULT_MAX_EXPAND_PER_NODE = int(os.getenv("SEOPY_HARVEST_MAX_EXPAND_PER_NODE", "20"))
DEFAULT_DELAY_MS = int(os.getenv("SEOPY_HARVEST_DELAY_MS", "350"))
DEFAULT_TIMEOUT = float(os.getenv("SEOPY_HARVEST_TIMEOUT", "10"))
DEFAULT_RETRIES = int(os.getenv("SEOPY_HARVEST_RETRIES", "3"))
DEFAULT_RECLAIM_MINUTES = int(os.getenv("SEOPY_HARVEST_RECLAIM_MINUTES", "30"))
DEFAULT_PROBE_CHARS = os.getenv("SEOPY_HARVEST_PROBE_CHARS", "abcdefghijklmnopqrstuvwxyz0123456789")

DEFAULT_MODIFIERS = [
    "free",
    "online",
    "read",
    "chapter",
    "episode",
    "series",
    "manga",
    "webtoon",
    "comic",
    "anime",
    "thriller",
    "mystery",
    "dark",
    "fantasy",
    "sci fi",
    "psychological",
    "school",
    "romance",
    "action",
    "adventure",
]


# ---------------------------------------------------------------------
# Utilities
# ---------------------------------------------------------------------
def now_utc() -> datetime:
    return datetime.utcnow()


def normalize_query(text: Any) -> str:
    if text is None:
        return ""
    text = str(text).replace("\u00a0", " ")
    text = re.sub(r"\s+", " ", text)
    return text.strip()


def normalize_key(text: Any) -> str:
    return normalize_query(text).lower().replace("’", "'")


def stable_hash(text: str) -> str:
    import hashlib
    return hashlib.sha1(text.encode("utf-8", errors="ignore")).hexdigest()


def slugify(text: str, max_len: int = 180) -> str:
    text = normalize_key(text)
    text = re.sub(r"[^a-z0-9]+", "-", text).strip("-")
    return text[:max_len].strip("-")


def build_title_guess(query_text: str) -> str:
    q = normalize_query(query_text)
    if not q:
        return ""
    parts: List[str] = []
    for word in q.split():
        if len(word) <= 3 and word.isalpha():
            parts.append(word.upper())
        else:
            parts.append(word[:1].upper() + word[1:])
    return " ".join(parts)[:255]


# ---------------------------------------------------------------------
# Schema
# ---------------------------------------------------------------------
SCHEMA_SQL = [
    """
    CREATE TABLE IF NOT EXISTS seopy_keyword_harvest_runs (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        finished_at DATETIME NULL,
        status ENUM('running','finished','failed') NOT NULL DEFAULT 'running',
        source_mode VARCHAR(50) NOT NULL DEFAULT 'db_seeds',
        language_code VARCHAR(10) NOT NULL DEFAULT 'en',
        country_code VARCHAR(10) NOT NULL DEFAULT 'DE',
        max_depth TINYINT UNSIGNED NOT NULL DEFAULT 2,
        max_seeds INT UNSIGNED NOT NULL DEFAULT 200,
        max_expand_per_node INT UNSIGNED NOT NULL DEFAULT 20,
        delay_ms INT UNSIGNED NOT NULL DEFAULT 350,
        total_seeds INT UNSIGNED NOT NULL DEFAULT 0,
        total_queries INT UNSIGNED NOT NULL DEFAULT 0,
        total_candidates INT UNSIGNED NOT NULL DEFAULT 0,
        total_saved INT UNSIGNED NOT NULL DEFAULT 0,
        error_count INT UNSIGNED NOT NULL DEFAULT 0,
        notes VARCHAR(500) NULL,
        error_text MEDIUMTEXT NULL,
        PRIMARY KEY (id),
        KEY idx_status_started (status, started_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    """,
    """
    CREATE TABLE IF NOT EXISTS seopy_keyword_seed_inputs (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        source_type VARCHAR(50) NOT NULL DEFAULT 'manual',
        source_table_name VARCHAR(128) NULL,
        source_row_id VARCHAR(64) NULL,
        seed_text VARCHAR(255) NOT NULL,
        seed_weight DECIMAL(6,2) NOT NULL DEFAULT 1.00,
        language_code VARCHAR(10) NOT NULL DEFAULT 'en',
        country_code VARCHAR(10) NOT NULL DEFAULT 'DE',
        active TINYINT(1) NOT NULL DEFAULT 1,
        notes VARCHAR(255) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_active_weight (active, seed_weight),
        KEY idx_seed_text (seed_text),
        UNIQUE KEY uq_seed_origin (
            source_type,
            source_table_name,
            source_row_id,
            seed_text
        )
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    """,
    """
    CREATE TABLE IF NOT EXISTS seopy_keyword_harvest_queue (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        run_id BIGINT UNSIGNED NOT NULL,
        seed_id BIGINT UNSIGNED NULL,
        query_text VARCHAR(255) NOT NULL,
        query_norm VARCHAR(255) NOT NULL,
        query_hash CHAR(40) NOT NULL,
        parent_query VARCHAR(255) NULL,
        source_kind VARCHAR(32) NOT NULL DEFAULT 'seed',
        depth TINYINT UNSIGNED NOT NULL DEFAULT 0,
        suggestion_rank INT UNSIGNED NULL,
        discovered_count INT UNSIGNED NOT NULL DEFAULT 1,
        score DECIMAL(10,4) NOT NULL DEFAULT 0.0000,
        language_code VARCHAR(10) NOT NULL DEFAULT 'en',
        country_code VARCHAR(10) NOT NULL DEFAULT 'DE',
        status ENUM('pending','processing','done','failed') NOT NULL DEFAULT 'pending',
        attempts INT UNSIGNED NOT NULL DEFAULT 0,
        locked_at DATETIME NULL,
        started_at DATETIME NULL,
        finished_at DATETIME NULL,
        error_text MEDIUMTEXT NULL,
        suggestion_payload JSON NULL,
        title_guess VARCHAR(255) NULL,
        slug_guess VARCHAR(255) NULL,
        first_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_run_query_locale (run_id, query_hash, language_code, country_code),
        KEY idx_run_status_score (run_id, status, score, id),
        KEY idx_run_status_depth (run_id, status, depth, id),
        KEY idx_run_parent (run_id, parent_query),
        KEY idx_status_locked (status, locked_at),
        KEY idx_query_norm (query_norm),
        CONSTRAINT fk_seopy_keyword_harvest_queue_run
            FOREIGN KEY (run_id) REFERENCES seopy_keyword_harvest_runs(id)
            ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    """,
]


def ensure_schema(conn) -> None:
    cur = conn.cursor()
    try:
        for sql in SCHEMA_SQL:
            cur.execute(sql)
        conn.commit()
    finally:
        cur.close()


# ---------------------------------------------------------------------
# Seed input
# ---------------------------------------------------------------------
@dataclass
class SeedInput:
    id: int
    seed_text: str
    seed_weight: float
    language_code: str
    country_code: str
    source_type: str = "manual"
    source_table_name: Optional[str] = None
    source_row_id: Optional[str] = None


def load_seeds(conn, limit: int, language_code: str, country_code: str) -> List[SeedInput]:
    sql = """
        SELECT
            id, seed_text, seed_weight, language_code, country_code,
            source_type, source_table_name, source_row_id
        FROM seopy_keyword_seed_inputs
        WHERE active = 1
          AND (language_code = %s OR language_code IS NULL OR language_code = '')
          AND (country_code = %s OR country_code IS NULL OR country_code = '')
        ORDER BY seed_weight DESC, id ASC
        LIMIT %s
    """
    cur = conn.cursor(dictionary=True)
    try:
        cur.execute(sql, (language_code, country_code, limit))
        rows = cur.fetchall() or []
        out: List[SeedInput] = []
        for row in rows:
            txt = normalize_query(row.get("seed_text"))
            if not txt:
                continue
            out.append(
                SeedInput(
                    id=int(row["id"]),
                    seed_text=txt,
                    seed_weight=float(row.get("seed_weight") or 1.0),
                    language_code=row.get("language_code") or language_code,
                    country_code=row.get("country_code") or country_code,
                    source_type=row.get("source_type") or "manual",
                    source_table_name=row.get("source_table_name"),
                    source_row_id=row.get("source_row_id"),
                )
            )
        return out
    finally:
        cur.close()


# ---------------------------------------------------------------------
# Suggest client
# ---------------------------------------------------------------------
class GoogleSuggestClient:
    def __init__(self, language_code: str, country_code: str, timeout: float, retries: int, delay_ms: int):
        self.language_code = language_code
        self.country_code = country_code
        self.timeout = timeout
        self.retries = retries
        self.delay_ms = delay_ms
        self.session = requests.Session()
        self.session.headers.update(
            {
                "User-Agent": (
                    "Mozilla/5.0 (X11; Linux x86_64) "
                    "AppleWebKit/537.36 (KHTML, like Gecko) "
                    "Chrome/124.0 Safari/537.36"
                ),
                "Accept": "application/json,text/plain,*/*",
                "Accept-Language": f"{language_code}-{country_code},{language_code};q=0.9,en;q=0.8",
                "Referer": "https://www.google.com/",
            }
        )

    def fetch(self, query: str) -> List[str]:
        query = normalize_query(query)
        if not query:
            return []

        params = {
            "client": "firefox",
            "hl": self.language_code,
            "gl": self.country_code,
            "q": query,
        }

        last_error: Optional[Exception] = None
        for attempt in range(1, self.retries + 1):
            try:
                time.sleep(self._delay())
                resp = self.session.get(DEFAULT_SUGGEST_URL, params=params, timeout=self.timeout)
                resp.raise_for_status()
                data = resp.json()
                return self._parse(data)
            except Exception as exc:
                last_error = exc
                logger.warning("Suggest fetch failed for %r (attempt %s/%s): %s", query, attempt, self.retries, exc)
                time.sleep(0.5 * attempt)

        if last_error:
            logger.error("Giving up on %r: %s", query, last_error)
        return []

    @staticmethod
    def _parse(data: Any) -> List[str]:
        if isinstance(data, list) and len(data) >= 2 and isinstance(data[1], list):
            return [normalize_query(x) for x in data[1] if normalize_query(x)]
        if isinstance(data, dict):
            for key in ("suggestions", "items", "results"):
                val = data.get(key)
                if isinstance(val, list):
                    out: List[str] = []
                    for item in val:
                        if isinstance(item, str):
                            out.append(normalize_query(item))
                        elif isinstance(item, dict):
                            candidate = item.get("suggestion") or item.get("value") or item.get("query")
                            if candidate:
                                out.append(normalize_query(candidate))
                    return [x for x in out if x]
        return []

    def _delay(self) -> float:
        base = max(0, self.delay_ms) / 1000.0
        return base + random.uniform(0.0, min(0.4, base * 0.5))


# ---------------------------------------------------------------------
# Scoring
# ---------------------------------------------------------------------
def candidate_score(
    query_text: str,
    depth: int,
    discovered_count: int,
    seed_weight: float,
    from_exact_query: bool,
    from_probe_query: bool,
) -> float:
    q = normalize_query(query_text)
    words = len(q.split())
    length = len(q)

    score = 0.0
    score += max(0.0, seed_weight) * 10.0

    if 2 <= words <= 6:
        score += 8.0
    elif words == 1:
        score += 1.5
    else:
        score += max(0.0, 6.0 - (words - 6) * 0.75)

    if length >= 8:
        score += 1.5

    score += min(discovered_count, 8) * 2.25
    score += max(0.0, 6.0 - depth * 1.35)

    if from_exact_query:
        score += 3.0
    if from_probe_query:
        score += 1.0

    if length <= 70:
        score += 1.0

    return round(score, 4)


# ---------------------------------------------------------------------
# Queue upsert / candidate persistence
# ---------------------------------------------------------------------
def queue_upsert(
    conn,
    *,
    run_id: int,
    seed_id: Optional[int],
    query_text: str,
    parent_query: Optional[str],
    source_kind: str,
    depth: int,
    suggestion_rank: Optional[int],
    discovered_count: int,
    score: float,
    language_code: str,
    country_code: str,
    suggestion_payload: Optional[Any],
    allow_reset_to_pending: bool = True,
) -> bool:
    query_text = normalize_query(query_text)
    if not query_text:
        return False

    query_norm = normalize_key(query_text)
    query_hash = stable_hash(query_norm)
    title_guess = build_title_guess(query_text)
    slug_guess = slugify(query_text)

    payload_json = None
    if suggestion_payload is not None:
        try:
            payload_json = json.dumps(suggestion_payload, ensure_ascii=False)
        except Exception:
            payload_json = None

    sql = """
        INSERT INTO seopy_keyword_harvest_queue
        (
            run_id, seed_id, query_text, query_norm, query_hash, parent_query,
            source_kind, depth, suggestion_rank, discovered_count, score,
            language_code, country_code, status, attempts, locked_at, started_at, finished_at,
            error_text, suggestion_payload, title_guess, slug_guess
        )
        VALUES
        (
            %s, %s, %s, %s, %s, %s,
            %s, %s, %s, %s, %s,
            %s, %s, 'pending', 0, NULL, NULL, NULL,
            NULL, %s, %s, %s
        )
        ON DUPLICATE KEY UPDATE
            seed_id = COALESCE(VALUES(seed_id), seed_id),
            parent_query = COALESCE(VALUES(parent_query), parent_query),
            source_kind = VALUES(source_kind),
            depth = LEAST(depth, VALUES(depth)),
            suggestion_rank = COALESCE(LEAST(COALESCE(suggestion_rank, 999999), COALESCE(VALUES(suggestion_rank), 999999)), suggestion_rank),
            discovered_count = discovered_count + VALUES(discovered_count),
            score = GREATEST(score, VALUES(score)),
            last_seen_at = CURRENT_TIMESTAMP,
            suggestion_payload = COALESCE(VALUES(suggestion_payload), suggestion_payload),
            title_guess = COALESCE(VALUES(title_guess), title_guess),
            slug_guess = COALESCE(VALUES(slug_guess), slug_guess),
            status = CASE
                WHEN status = 'done' THEN 'done'
                WHEN status = 'processing' THEN 'processing'
                ELSE 'pending'
            END
    """
    cur = conn.cursor()
    try:
        cur.execute(
            sql,
            (
                run_id,
                seed_id,
                query_text,
                query_norm,
                query_hash,
                parent_query,
                source_kind,
                depth,
                suggestion_rank,
                discovered_count,
                score,
                language_code,
                country_code,
                payload_json,
                title_guess,
                slug_guess,
            ),
        )
        conn.commit()
        return True
    finally:
        cur.close()


# ---------------------------------------------------------------------
# Run table
# ---------------------------------------------------------------------
def create_run(conn, args) -> int:
    sql = """
        INSERT INTO seopy_keyword_harvest_runs
        (status, source_mode, language_code, country_code, max_depth, max_seeds, max_expand_per_node, delay_ms, notes)
        VALUES ('running', %s, %s, %s, %s, %s, %s, %s, %s)
    """
    cur = conn.cursor()
    try:
        cur.execute(
            sql,
            (
                args.source_mode,
                args.language,
                args.country,
                args.max_depth,
                args.max_seeds,
                args.max_expand_per_node,
                args.delay_ms,
                args.notes or None,
            ),
        )
        conn.commit()
        return int(cur.lastrowid)
    finally:
        cur.close()


def resume_run(conn, run_id: int) -> None:
    cur = conn.cursor()
    try:
        cur.execute(
            """
            UPDATE seopy_keyword_harvest_runs
            SET status='running', finished_at=NULL
            WHERE id=%s
            """,
            (run_id,),
        )
        conn.commit()
    finally:
        cur.close()


def update_run_stats(conn, run_id: int, **fields: Any) -> None:
    allowed_set = {"finished_at", "status", "error_text"}
    allowed_add = {"total_seeds", "total_queries", "total_candidates", "total_saved", "error_count"}
    
    set_items = []
    values = []
    
    for k, v in fields.items():
        if k in allowed_set:
            set_items.append(f"{k} = %s")
            values.append(v)
        elif k in allowed_add:
            set_items.append(f"{k} = {k} + %s")
            values.append(v)
            
    if not set_items:
        return
        
    set_clause = ", ".join(set_items)
    values.append(run_id)
    cur = conn.cursor()
    try:
        cur.execute(f"UPDATE seopy_keyword_harvest_runs SET {set_clause} WHERE id = %s", values)
        conn.commit()
    finally:
        cur.close()


# ---------------------------------------------------------------------
# Job claiming / processing
# ---------------------------------------------------------------------
@dataclass
class QueueJob:
    id: int
    run_id: int
    seed_id: Optional[int]
    query_text: str
    parent_query: Optional[str]
    source_kind: str
    depth: int
    suggestion_rank: Optional[int]
    discovered_count: int
    score: float
    language_code: str
    country_code: str
    attempts: int


def claim_next_job(conn, run_id: int, reclaim_minutes: int) -> Optional[QueueJob]:
    cur = conn.cursor(dictionary=True)
    try:
        # One row at a time, single-worker friendly.
        # A stale 'processing' row is reclaimable after reclaim_minutes.
        cur.execute(
            """
            UPDATE seopy_keyword_harvest_queue
            SET
                status='processing',
                locked_at=NOW(),
                started_at=COALESCE(started_at, NOW()),
                attempts=attempts + 1
            WHERE run_id=%s
              AND (
                    status IN ('pending', 'failed')
                    OR (status='processing' AND locked_at IS NOT NULL AND locked_at < (NOW() - INTERVAL %s MINUTE))
                  )
            ORDER BY score DESC, id ASC
            LIMIT 1
            """,
            (run_id, reclaim_minutes),
        )
        
        # If no rows met the reclaim/pending condition, bail out early.
        # This prevents picking up an actively processing job from another worker/thread
        # just because it happened to be the latest one requested by the SELECT query below.
        if cur.rowcount == 0:
            conn.commit()
            return None

        conn.commit()

        cur.execute(
            """
            SELECT
                id, run_id, seed_id, query_text, parent_query, source_kind, depth,
                suggestion_rank, discovered_count, score, language_code, country_code, attempts
            FROM seopy_keyword_harvest_queue
            WHERE run_id=%s AND status='processing'
            ORDER BY locked_at DESC, id DESC
            LIMIT 1
            """,
            (run_id,),
        )
        row = cur.fetchone()
        if not row:
            return None
        return QueueJob(
            id=int(row["id"]),
            run_id=int(row["run_id"]),
            seed_id=row.get("seed_id"),
            query_text=row["query_text"],
            parent_query=row.get("parent_query"),
            source_kind=row["source_kind"],
            depth=int(row["depth"]),
            suggestion_rank=row.get("suggestion_rank"),
            discovered_count=int(row["discovered_count"] or 1),
            score=float(row["score"] or 0.0),
            language_code=row["language_code"] or DEFAULT_LANG,
            country_code=row["country_code"] or DEFAULT_COUNTRY,
            attempts=int(row["attempts"] or 0),
        )
    finally:
        cur.close()


def mark_job_done(conn, job_id: int) -> None:
    cur = conn.cursor()
    try:
        cur.execute(
            """
            UPDATE seopy_keyword_harvest_queue
            SET status='done', finished_at=NOW(), error_text=NULL
            WHERE id=%s
            """,
            (job_id,),
        )
        conn.commit()
    finally:
        cur.close()


def mark_job_failed(conn, job_id: int, error_text: str) -> None:
    cur = conn.cursor()
    try:
        cur.execute(
            """
            UPDATE seopy_keyword_harvest_queue
            SET status='failed', finished_at=NOW(), error_text=%s
            WHERE id=%s
            """,
            (error_text[:5000], job_id),
        )
        conn.commit()
    finally:
        cur.close()


def enqueue_seed_jobs(conn, run_id: int, seeds: Sequence[SeedInput], language: str, country: str) -> int:
    count = 0
    for seed in seeds:
        score = candidate_score(
            seed.seed_text,
            depth=0,
            discovered_count=1,
            seed_weight=seed.seed_weight,
            from_exact_query=True,
            from_probe_query=False,
        )
        if queue_upsert(
            conn,
            run_id=run_id,
            seed_id=seed.id,
            query_text=seed.seed_text,
            parent_query=None,
            source_kind="seed",
            depth=0,
            suggestion_rank=0,
            discovered_count=1,
            score=score,
            language_code=seed.language_code or language,
            country_code=seed.country_code or country,
            suggestion_payload={"type": "seed"},
            allow_reset_to_pending=True,
        ):
            count += 1
    return count


def build_probe_queries(base_query: str, probe_chars: str, modifiers: Sequence[str]) -> List[str]:
    base_query = normalize_query(base_query)
    if not base_query:
        return []

    probes = [base_query]
    for ch in probe_chars:
        if ch == " ":
            continue
        probes.append(f"{base_query} {ch}")

    for mod in modifiers:
        probes.append(f"{base_query} {mod}")

    out: List[str] = []
    seen: Set[str] = set()
    for q in probes:
        nq = normalize_query(q)
        k = normalize_key(nq)
        if not nq or k in seen:
            continue
        seen.add(k)
        out.append(nq)
    return out


def should_expand(query_text: str, depth: int, max_depth: int) -> bool:
    q = normalize_query(query_text)
    if not q:
        return False
    if depth >= max_depth:
        return False
    if len(q) > 80:
        return False
    if sum(1 for c in q if c in "!@#$%^&*()[]{}<>") > 3:
        return False
    return True


class KeywordHarvester:
    def __init__(
        self,
        conn,
        *,
        run_id: int,
        language: str,
        country: str,
        max_depth: int,
        max_expand_per_node: int,
        delay_ms: int,
        probe_chars: str,
        modifiers: Optional[List[str]] = None,
        timeout: float = 10.0,
        retries: int = 3,
        reclaim_minutes: int = 30,
    ):
        self.conn = conn
        self.run_id = run_id
        self.language = language
        self.country = country
        self.max_depth = max_depth
        self.max_expand_per_node = max_expand_per_node
        self.delay_ms = delay_ms
        self.probe_chars = probe_chars
        self.modifiers = modifiers or DEFAULT_MODIFIERS
        self.reclaim_minutes = reclaim_minutes
        self.client = GoogleSuggestClient(language, country, timeout, retries, delay_ms)

        self.total_queries = 0
        self.total_candidates = 0
        self.total_saved = 0
        self.error_count = 0

    def run(self) -> None:
        seen_candidates: Dict[str, int] = {}

        while True:
            job = claim_next_job(self.conn, self.run_id, self.reclaim_minutes)
            if not job:
                break

            logger.info("Processing job #%s depth=%s query=%r", job.id, job.depth, job.query_text)

            try:
                self.process_job(job, seen_candidates)
                mark_job_done(self.conn, job.id)
            except Exception as exc:
                self.error_count += 1
                logger.exception("Job failed for %r: %s", job.query_text, exc)
                mark_job_failed(self.conn, job.id, str(exc))

        logger.info("No more pending work in run %s.", self.run_id)

    def process_job(self, job: QueueJob, seen_candidates: Dict[str, int]) -> None:
        probes = build_probe_queries(job.query_text, self.probe_chars, self.modifiers)
        self.total_queries += len(probes)

        # Save the job query itself as a confirmed candidate state.
        self._save_candidate(job, seen_candidates, source_kind=job.source_kind, suggestion_payload={"type": "self"})

        for probe_index, probe_query in enumerate(probes):
            suggestions = self.client.fetch(probe_query)

            for rank, suggestion in enumerate(suggestions, start=1):
                suggestion = normalize_query(suggestion)
                if not suggestion:
                    continue

                key = normalize_key(suggestion)
                seen_candidates[key] = seen_candidates.get(key, 0) + 1

                score = candidate_score(
                    suggestion,
                    depth=job.depth,
                    discovered_count=seen_candidates[key],
                    seed_weight=self._seed_weight_for_job(job),
                    from_exact_query=(probe_index == 0),
                    from_probe_query=(probe_index > 0),
                )

                saved = queue_upsert(
                    self.conn,
                    run_id=self.run_id,
                    seed_id=job.seed_id,
                    query_text=suggestion,
                    parent_query=job.query_text,
                    source_kind="suggest",
                    depth=job.depth + 1 if probe_index > 0 else job.depth,
                    suggestion_rank=rank,
                    discovered_count=seen_candidates[key],
                    score=score,
                    language_code=job.language_code,
                    country_code=job.country_code,
                    suggestion_payload={
                        "probe_query": probe_query,
                        "origin_query": job.query_text,
                        "suggestion_rank": rank,
                        "probe_index": probe_index,
                    },
                    allow_reset_to_pending=True,
                )
                if saved:
                    self.total_candidates += 1
                    self.total_saved += 1

                if rank <= self.max_expand_per_node and should_expand(suggestion, job.depth + 1, self.max_depth):
                    # Persist the child node immediately so it survives interruption.
                    child_score = candidate_score(
                        suggestion,
                        depth=job.depth + 1,
                        discovered_count=seen_candidates[key],
                        seed_weight=self._seed_weight_for_job(job),
                        from_exact_query=False,
                        from_probe_query=True,
                    )
                    queue_upsert(
                        self.conn,
                        run_id=self.run_id,
                        seed_id=job.seed_id,
                        query_text=suggestion,
                        parent_query=job.query_text,
                        source_kind="suggest",
                        depth=job.depth + 1,
                        suggestion_rank=rank,
                        discovered_count=seen_candidates[key],
                        score=child_score,
                        language_code=job.language_code,
                        country_code=job.country_code,
                        suggestion_payload={
                            "enqueued_from": job.query_text,
                            "probe_query": probe_query,
                            "rank": rank,
                        },
                        allow_reset_to_pending=True,
                    )

    def _seed_weight_for_job(self, job: QueueJob) -> float:
        # We store only the queue state here. For scoring the depth tree, we
        # approximate the seed weight from the current row score if needed.
        # If the row originated from a seed, the score already encodes it.
        base = max(1.0, min(5.0, job.score / 10.0))
        return base

    def _save_candidate(self, job: QueueJob, seen_candidates: Dict[str, int], source_kind: str, suggestion_payload: Dict[str, Any]) -> None:
        query = normalize_query(job.query_text)
        if not query:
            return
        key = normalize_key(query)
        seen_candidates[key] = seen_candidates.get(key, 0) + 1

        score = candidate_score(
            query,
            depth=job.depth,
            discovered_count=seen_candidates[key],
            seed_weight=self._seed_weight_for_job(job),
            from_exact_query=True,
            from_probe_query=False,
        )

        queue_upsert(
            self.conn,
            run_id=self.run_id,
            seed_id=job.seed_id,
            query_text=query,
            parent_query=job.parent_query,
            source_kind=source_kind,
            depth=job.depth,
            suggestion_rank=0,
            discovered_count=seen_candidates[key],
            score=score,
            language_code=job.language_code,
            country_code=job.country_code,
            suggestion_payload=suggestion_payload,
            allow_reset_to_pending=True,
        )


# ---------------------------------------------------------------------
# CLI
# ---------------------------------------------------------------------
def build_arg_parser() -> argparse.ArgumentParser:
    p = argparse.ArgumentParser(description="Resumable keyword harvester using Google Suggest and MariaDB.")
    p.add_argument("--language", default=DEFAULT_LANG, help="Language code, e.g. en or de.")
    p.add_argument("--country", default=DEFAULT_COUNTRY, help="Country code, e.g. DE or US.")
    p.add_argument("--max-depth", type=int, default=DEFAULT_MAX_DEPTH)
    p.add_argument("--max-seeds", type=int, default=DEFAULT_MAX_SEEDS)
    p.add_argument("--max-expand-per-node", type=int, default=DEFAULT_MAX_EXPAND_PER_NODE)
    p.add_argument("--delay-ms", type=int, default=DEFAULT_DELAY_MS)
    p.add_argument("--timeout", type=float, default=DEFAULT_TIMEOUT)
    p.add_argument("--retries", type=int, default=DEFAULT_RETRIES)
    p.add_argument("--reclaim-minutes", type=int, default=DEFAULT_RECLAIM_MINUTES)
    p.add_argument("--probe-chars", default=DEFAULT_PROBE_CHARS)
    p.add_argument("--source-mode", default="db_seeds")
    p.add_argument("--notes", default="")
    p.add_argument("--resume-run-id", type=int, default=None, help="Resume an existing harvest run.")
    p.add_argument("--init-schema", action="store_true", help="Create required tables before running.")
    p.add_argument("--reset-stuck", action="store_true", help="Reset stale processing jobs to pending before resuming.")
    return p


def reset_stuck_jobs(conn, run_id: int) -> int:
    cur = conn.cursor()
    try:
        cur.execute(
            """
            UPDATE seopy_keyword_harvest_queue
            SET status='pending', locked_at=NULL
            WHERE run_id=%s AND status='processing'
            """,
            (run_id,),
        )
        conn.commit()
        return cur.rowcount or 0
    finally:
        cur.close()


def main() -> int:
    args = build_arg_parser().parse_args()

    conn = get_db_connection()
    if not conn:
        logger.error("Could not connect to the database.")
        return 2

    try:
        if args.init_schema:
            ensure_schema(conn)

        if args.resume_run_id:
            run_id = int(args.resume_run_id)
            resume_run(conn, run_id)
            logger.info("Resuming run id=%s", run_id)

            if args.reset_stuck:
                reset_count = reset_stuck_jobs(conn, run_id)
                logger.info("Reset %s stuck processing jobs to pending.", reset_count)

        else:
            run_id = create_run(conn, args)
            logger.info("Created new harvest run id=%s", run_id)

            seeds = load_seeds(conn, args.max_seeds, args.language, args.country)
            if not seeds:
                update_run_stats(
                    conn,
                    run_id,
                    status="failed",
                    finished_at=now_utc(),
                    error_text="No active seeds available.",
                )
                logger.warning("No seeds found in seopy_keyword_seed_inputs.")
                return 1

            inserted = enqueue_seed_jobs(conn, run_id, seeds, args.language, args.country)
            update_run_stats(conn, run_id, total_seeds=inserted)
            logger.info("Enqueued %s seed jobs.", inserted)

        harvester = KeywordHarvester(
            conn,
            run_id=run_id,
            language=args.language,
            country=args.country,
            max_depth=args.max_depth,
            max_expand_per_node=args.max_expand_per_node,
            delay_ms=args.delay_ms,
            probe_chars=args.probe_chars,
            timeout=args.timeout,
            retries=args.retries,
            reclaim_minutes=args.reclaim_minutes,
        )

        harvester.run()

        update_run_stats(
            conn,
            run_id,
            status="finished",
            finished_at=now_utc(),
            total_queries=harvester.total_queries,
            total_candidates=harvester.total_candidates,
            total_saved=harvester.total_saved,
            error_count=harvester.error_count,
            error_text=None,
        )

        logger.info(
            "Harvest finished. run=%s queries=%s candidates=%s saved=%s errors=%s",
            run_id,
            harvester.total_queries,
            harvester.total_candidates,
            harvester.total_saved,
            harvester.error_count,
        )
        return 0

    except Exception as exc:
        logger.exception("Harvest run failed: %s", exc)
        try:
            if "run_id" in locals():
                update_run_stats(
                    conn,
                    run_id,
                    status="failed",
                    finished_at=now_utc(),
                    error_count=1,
                    error_text=str(exc),
                )
        except Exception:
            pass
        return 3
    finally:
        try:
            conn.close()
        except Exception:
            pass


if __name__ == "__main__":
    raise SystemExit(main())