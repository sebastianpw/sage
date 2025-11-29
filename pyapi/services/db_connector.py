# pyapi/services/db_connector.py
"""
Multi-connection DB connector.

Default behavior:
 - get_db_connection()     -> uses DATABASE_URL (keeps existing services like bloom working)
 - get_db_connection(name) -> 'sys' -> DATABASE_SYS_URL, 'wordnet' -> DATABASE_WORDNET_URL
 - get_db_connection(env_key='...') -> explicit env var

Reads .env.local (if present) and falls back to os.environ.
Caches connections and reopens if disconnected.
"""
from pathlib import Path
from urllib.parse import urlparse, parse_qs, unquote
import mysql.connector
import os
import logging
from typing import Optional, Dict, Any

logger = logging.getLogger(__name__)
PROJECT_ROOT = Path(__file__).resolve().parents[2]
ENV_FILE = PROJECT_ROOT / ".env.local"

# connection cache keyed by env_key used
_CONNECTIONS: Dict[str, mysql.connector.MySQLConnection] = {}


def _read_env_value(key: str) -> Optional[str]:
    """Return value for KEY from .env.local (if present) else from os.environ, else None."""
    if ENV_FILE.exists():
        try:
            with open(ENV_FILE, 'r') as f:
                for raw in f:
                    line = raw.strip()
                    if not line or line.startswith('#'):
                        continue
                    if line.startswith(f"{key}="):
                        val = line.split('=', 1)[1].strip().strip('"').strip("'")
                        return val
        except Exception as e:
            logger.debug("Could not read .env.local: %s", e)
    # fallback to environment variable
    return os.environ.get(key)


def _parse_db_url(db_url: str) -> Optional[Dict[str, Any]]:
    """Parse a mysql-style URL into a params dict for mysql.connector."""
    if not db_url:
        return None
    try:
        parsed = urlparse(db_url)
        qs = parse_qs(parsed.query)
        database = parsed.path.lstrip('/') if parsed.path else None

        params = {
            "host": parsed.hostname or "127.0.0.1",
            "port": int(parsed.port or 3306),
            "user": unquote(parsed.username) if parsed.username else "",
            "password": unquote(parsed.password) if parsed.password else "",
            "database": database,
            "charset": qs.get("charset", ["utf8mb4"])[0],
            "raw_options": {k: v for k, v in qs.items()}
        }
        return params
    except Exception as e:
        logger.exception("Failed to parse DB URL '%s': %s", db_url, e)
        return None


def _build_params_for_env_key(env_key: str) -> Optional[Dict[str, Any]]:
    """Read env var (or .env.local entry) and return parsed params or None."""
    url = _read_env_value(env_key)
    if not url:
        return None
    return _parse_db_url(url)


def _connect_with_params(params: Dict[str, Any]) -> Optional[mysql.connector.MySQLConnection]:
    """Create and return a mysql.connector connection from parsed params."""
    if not params or not params.get("database"):
        logger.error("Insufficient DB params: %s", params)
        return None
    try:
        conn = mysql.connector.connect(
            host=params["host"],
            port=params["port"],
            user=params.get("user", ""),
            password=params.get("password", ""),
            database=params["database"],
            charset=params.get("charset", "utf8mb4"),
            use_unicode=True,
            autocommit=True,
        )
        logger.info("Opened DB connection to %s@%s:%s/%s", params.get("user"), params["host"], params["port"], params["database"])
        return conn
    except mysql.connector.Error as err:
        logger.error("MySQL connection error for %s: %s", params.get("database"), err)
        return None


def _get_cached_connection(cache_key: str) -> Optional[mysql.connector.MySQLConnection]:
    """Return cached connection if alive, otherwise remove it and return None."""
    conn = _CONNECTIONS.get(cache_key)
    if not conn:
        return None
    try:
        if conn.is_connected():
            return conn
    except Exception:
        logger.debug("Cached connection '%s' invalid; removing.", cache_key)
    # remove invalid connection
    _CONNECTIONS.pop(cache_key, None)
    return None


def get_db_connection(name: Optional[str] = None, env_key: Optional[str] = None) -> Optional[mysql.connector.MySQLConnection]:
    """
    Return a mysql.connector connection.

    - If env_key provided -> uses that environment variable name (e.g. 'DATABASE_WORDNET_URL').
    - Else if name provided:
        - 'wordnet' -> DATABASE_WORDNET_URL
        - 'sys'     -> DATABASE_SYS_URL
        - any other name -> tries DATABASE_<NAME>_URL if present
    - If neither provided (default) -> prefer DATABASE_URL (main), then DATABASE_SYS_URL, then DATABASE_WORDNET_URL.
    """
    # normalize name
    if name:
        name = str(name).lower()

    # determine env_key_to_use
    env_key_to_use = None
    if env_key:
        env_key_to_use = env_key
    else:
        if name == "wordnet":
            env_key_to_use = "DATABASE_WORDNET_URL"
        elif name == "sys":
            env_key_to_use = "DATABASE_SYS_URL"
        elif name in (None, "", "default"):
            # default precedence: DATABASE_URL (main) -> DATABASE_SYS_URL -> DATABASE_WORDNET_URL
            for candidate in ("DATABASE_URL", "DATABASE_SYS_URL", "DATABASE_WORDNET_URL"):
                if _read_env_value(candidate):
                    env_key_to_use = candidate
                    break
        else:
            trial = f"DATABASE_{name.upper()}_URL"
            if _read_env_value(trial):
                env_key_to_use = trial

    if not env_key_to_use:
        logger.error("No database env var available for name='%s' (checked defaults).", name)
        return None

    cache_key = env_key_to_use

    # return cached connection if alive
    cached = _get_cached_connection(cache_key)
    if cached:
        return cached

    # parse and open connection
    params = _build_params_for_env_key(env_key_to_use)
    if not params:
        logger.error("Failed to parse DB params for env key %s", env_key_to_use)
        return None

    conn = _connect_with_params(params)
    if conn:
        _CONNECTIONS[cache_key] = conn
    return conn


# convenience helpers
def get_wordnet_connection() -> Optional[mysql.connector.MySQLConnection]:
    return get_db_connection(name="wordnet", env_key="DATABASE_WORDNET_URL")


def get_sys_connection() -> Optional[mysql.connector.MySQLConnection]:
    return get_db_connection(name="sys", env_key="DATABASE_SYS_URL")


def close_all_connections():
    """Close and clear cached connections (call on shutdown if desired)."""
    for key, conn in list(_CONNECTIONS.items()):
        try:
            conn.close()
            logger.info("Closed DB connection cache '%s'", key)
        except Exception as e:
            logger.debug("Error closing connection %s: %s", key, e)
        _CONNECTIONS.pop(key, None)


# Debug helper (optional): returns dict of which env keys are present and the DB names (lightweight)
def inspect_configured_databases() -> Dict[str, Optional[str]]:
    """
    Returns a map {env_key: database_name_or_None} for quick debugging.
    Does not create persistent cache entries; opens a temp connection and closes it immediately.
    """
    keys = ["DATABASE_URL", "DATABASE_SYS_URL", "DATABASE_WORDNET_URL"]
    out = {}
    for k in keys:
        val = _read_env_value(k)
        if not val:
            out[k] = None
            continue
        p = _parse_db_url(val)
        if not p:
            out[k] = None
            continue
        # quick probe: try a transient connection to read SELECT DATABASE()
        try:
            conn = _connect_with_params(p)
            if conn and conn.is_connected():
                cur = conn.cursor()
                cur.execute("SELECT DATABASE()")
                dbname = cur.fetchone()[0] if cur.fetchone() is not None else None
                cur.close()
                conn.close()
                out[k] = dbname
            else:
                out[k] = None
        except Exception:
            out[k] = None
    return out
