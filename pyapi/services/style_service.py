# pyapi/services/style_service.py
from __future__ import annotations
"""
Style Profile → Prompt Converter Service (FastAPI router)
Full updated implementation (drop-in) with:
 - persistent phrase_map generation (AI + DB + cache)
 - parentheses emphasis on phrases based on intensity
 - optional AI post-processing (polish) using a separate generator_config_id
 - DB credential resolution via bash/db_name.sh or .env.local
 - max_tokens set to 20000 everywhere to avoid truncated JSON issues
 - debug mode to return raw AI responses

Install deps in your pyapi venv if needed:
  pip install fastapi pydantic requests pymysql

Register router in main.py:
    from services.style_service import router as style_router
    app.include_router(style_router, prefix="/style", tags=["style"])
"""
from fastapi import APIRouter, HTTPException
from pydantic import BaseModel, Field
from typing import List, Optional, Dict, Any, Tuple
from datetime import datetime
import logging
import json
import hashlib
import time
import os
import shlex
import subprocess
from pathlib import Path
from urllib.parse import urlparse

logger = logging.getLogger(__name__)
router = APIRouter(tags=["style"])

# optional DB client
try:
    import pymysql
except Exception:
    pymysql = None

# optional HTTP client for OpenAI-compatible endpoint
import requests

# ----------------------------
# Configuration from env (tune as needed)
# ----------------------------
OPENAI_COMPAT_BASE = os.environ.get("SAGE_OPENAI_COMPAT_URL", "http://127.0.0.1:8009")
CHAT_COMPLETIONS_PATH = "/v1/chat/completions"
MODELS_LIST_PATH = "/v1/models"

DB_TABLE_PHRASE_MAPS = os.environ.get("SAGE_PHRASE_TABLE", "generated_phrase_maps")
_PHRASE_MAP_CACHE: Dict[str, Dict[str, Any]] = {}
_PHRASE_MAP_TTL = int(os.environ.get("SAGE_PHRASE_CACHE_TTL", "3600"))  # seconds

# --- DB credentials helper / open_db_conn ---
_PROJECT_ROOT = Path(__file__).resolve().parents[2]
_BASH_DB_SCRIPT = _PROJECT_ROOT / "bash" / "db_name.sh"
_ENV_LOCAL = _PROJECT_ROOT / ".env.local"


def _parse_mysql_args_from_string(s: str) -> Dict[str, Optional[str]]:
    out = {"user": None, "password": None, "host": None, "port": None, "db": None}
    if not s:
        return out
    try:
        parts = shlex.split(s)
    except Exception:
        parts = s.split()
    i = 0
    while i < len(parts):
        p = parts[i]
        if p.startswith("-u"):
            val = p[2:] if len(p) > 2 else (parts[i + 1] if i + 1 < len(parts) else "")
            out["user"] = val
            if len(p) == 2:
                i += 1
        elif p.startswith("-p"):
            val = p[2:] if len(p) > 2 else (parts[i + 1] if i + 1 < len(parts) else "")
            out["password"] = val
            if len(p) == 2:
                i += 1
        elif p.startswith("-h"):
            val = p[2:] if len(p) > 2 else (parts[i + 1] if i + 1 < len(parts) else "")
            out["host"] = val
            if len(p) == 2:
                i += 1
        elif p.startswith("-P"):
            val = p[2:] if len(p) > 2 else (parts[i + 1] if i + 1 < len(parts) else "")
            out["port"] = val
            if len(p) == 2:
                i += 1
        else:
            if out["db"] is None:
                out["db"] = p
        i += 1
    return out


def _run_db_name_sh_and_parse(arg: str = "main-conn") -> Dict[str, Optional[str]]:
    out = {"user": None, "password": None, "host": None, "port": None, "db": None}
    try:
        if not _BASH_DB_SCRIPT.exists() or not _BASH_DB_SCRIPT.is_file():
            logger.debug("db_name.sh not found at %s", str(_BASH_DB_SCRIPT))
            return out
        proc = subprocess.run([str(_BASH_DB_SCRIPT), arg], capture_output=True, text=True, timeout=30)
        stdout = proc.stdout.strip()
        if not stdout:
            logger.debug("db_name.sh returned empty output or error: %s", proc.stderr)
            return out
        parsed = _parse_mysql_args_from_string(stdout)
        return parsed
    except Exception as e:
        logger.exception("Error running db_name.sh: %s", e)
        return out


def _parse_env_local_for_database(env_path: Path = None) -> Dict[str, Optional[str]]:
    env_path = env_path or _ENV_LOCAL
    out = {"user": None, "password": None, "host": None, "port": None, "db": None}
    try:
        if not env_path.exists():
            logger.debug(".env.local not found at %s", str(env_path))
            return out
        text = env_path.read_text(encoding="utf-8")
        url_line = None
        for name in ("DATABASE_URL", "DATABASE_SYS_URL"):
            prefix = f"{name}="
            for ln in text.splitlines():
                if ln.startswith(prefix):
                    url_line = ln[len(prefix):].strip().strip('"').strip("'")
                    break
            if url_line:
                break
        if not url_line:
            return out
        parsed = urlparse(url_line)
        if parsed.scheme and parsed.scheme.startswith("mysql"):
            db = parsed.path.lstrip("/") if parsed.path else None
            host = parsed.hostname
            port = str(parsed.port) if parsed.port else None
            user = parsed.username
            password = parsed.password
            out.update({"user": user, "password": password, "host": host, "port": port, "db": db})
        return out
    except Exception as e:
        logger.exception("Error parsing .env.local: %s", e)
        return out


def get_db_connection_params() -> Dict[str, Optional[str]]:
    # 1) attempt to use bash/db_name.sh
    parsed = _run_db_name_sh_and_parse("main-conn")
    if parsed and any(parsed.values()):
        logger.debug("Using DB params from bash/db_name.sh")
        return {"host": parsed.get("host"), "port": parsed.get("port"), "user": parsed.get("user"),
                "password": parsed.get("password"), "db": parsed.get("db")}
    # 2) parse .env.local
    parsed2 = _parse_env_local_for_database(_ENV_LOCAL)
    if parsed2 and any(parsed2.values()):
        logger.debug("Using DB params from .env.local")
        return {"host": parsed2.get("host"), "port": parsed2.get("port"), "user": parsed2.get("user"),
                "password": parsed2.get("password"), "db": parsed2.get("db")}
    return {}


def open_db_conn():
    params = get_db_connection_params()
    if pymysql is None:
        logger.warning("pymysql not installed; DB operations disabled")
        return None
    conn_args = {
        "host": params.get("host") or "127.0.0.1",
        "user": params.get("user") or "root",
        "password": params.get("password") or "",
        "db": params.get("db") or "sage_db",
        "cursorclass": pymysql.cursors.DictCursor,
        "autocommit": True
    }
    if params.get("port"):
        try:
            conn_args["port"] = int(params.get("port"))
        except (ValueError, TypeError):
            logger.warning("Invalid port value '%s', ignoring.", params.get("port"))
    try:
        return pymysql.connect(**conn_args)
    except Exception as e:
        logger.exception("Failed to open DB connection with params %s: %s",
                         {k: v for k, v in conn_args.items() if k != "password"}, e)
        return None


# ----------------------------
# Pydantic models (input/output)
# ----------------------------
class StyleAxis(BaseModel):
    id: Optional[int] = None
    key: str
    pole_left: str
    pole_right: str
    value: int = Field(..., ge=0, le=100)
    notes: Optional[str] = None


class StyleProfile(BaseModel):
    id: Optional[int] = None
    profile_name: Optional[str] = None
    created_at: Optional[str] = None
    axes: List[StyleAxis]


class FragmentOut(BaseModel):
    key: str
    pole: str
    intensity: float
    text: Optional[str] = None


class ConverterConfig(BaseModel):
    scale: int = Field(100, ge=2)
    center: int = Field(50, ge=0)
    min_delta_raw: int = Field(15, ge=0)
    adjective_thresholds: Dict[str, float] = Field(
        default_factory=lambda: {"dominant": 0.75, "noticeable": 0.40, "subtle": 0.15}
    )
    max_fragments_in_prompt: int = Field(20, ge=1)
    adapter_variants: int = Field(5, ge=1)
    top_adapter_fragments: int = Field(20, ge=1)
    include_scale_binders: bool = True
    model_name: Optional[str] = None

    # polish options
    polish_generator_config_id: Optional[str] = None
    polish_max_tokens: int = Field(20000, ge=1)
    post_process_with_ai: bool = True
    post_process_model: Optional[str] = None

    # NEW: debug toggle to include raw model responses in convert response
    include_debug_responses: bool = False


class ConvertRequest(BaseModel):
    generator_config_id: str
    profiles: List[StyleProfile]
    config: Optional[ConverterConfig] = Field(default_factory=ConverterConfig)


class ConvertResponse(BaseModel):
    textualStylePrompt: str
    fragments: List[FragmentOut]
    adapterPrompts: List[str]
    merged_profile: StyleProfile
    raw_profiles: List[StyleProfile]
    # NEW optional debug field:
    debug: Optional[Dict[str, Any]] = None


# ----------------------------
# Default phrase map (fallback) - keep this inventory
# ----------------------------
DEFAULT_PHRASE_MAP: Dict[str, str] = {
    "Glossy": "glossy specular highlights, polished surfaces",
    "Matte": "diffuse matte surfaces, soft reflections",
    "Vibrant": "saturated, vivid palette",
    "Muted": "desaturated, restrained palette",
    "Painterly": "visible brush strokes, canvas texture",
    "Photoreal": "ultra-photorealistic detail",
    "Soft-focus": "shallow depth-of-field, cinematic bloom",
    "Sharp-focus": "crisply detailed, high-frequency detail",
    "Warm": "warm amber/orange light",
    "Cool": "cool blue/teal tones",
    "Functional": "utilitarian, minimal ornament, modular components",
    "Alien": "subtly nonhuman anatomy, alien motifs",
    "Human": "human proportions and facial geometry",
    "Textured": "rich surface texture and brush grain",
    "Flat": "smooth flat color planes",
    "High-contrast": "strong blacks and highlights, dramatic contrast",
    "Low-contrast": "gentle tonal range, soft transitions",
    "Emotional": "expressive, emotive micro-expressions",
    "Stoic": "reserved, impassive facial expression",
    "Agile": "nimble, lithe motion language",
    "Heavy": "dense, braced construction and mass",
    "Youthful": "small youthful proportions, soft features",
    "Aged": "weathered skin, timeworn texture",
    "Scarred": "visible scars and surface wear",
    "Pristine": "immaculate, unmarked surfaces",
    "Charismatic": "magnetic bearing and presence",
    "Awkward": "clumsy or socially awkward posture",
    "Mystic": "ritual motifs and arcane glyphs",
    "Scientific": "clinical, lab-precision detailing",
    "Lawful": "ordered, dependable posture and gear",
    "Chaotic": "ragged, unpredictable construction",
    "Symbolic": "decorative, emblematic adornments",
}

# ----------------------------
# Utilities: fingerprint, cache, DB helpers
# ----------------------------
def _profile_fingerprint(profile: StyleProfile) -> str:
    axes_sorted = sorted(
        [
            {"key": a.key, "pole_left": a.pole_left, "pole_right": a.pole_right, "value": int(a.value)}
            for a in profile.axes
        ],
        key=lambda x: x["key"]
    )
    payload = {"profile_name": profile.profile_name or "", "axes": axes_sorted}
    canon = json.dumps(payload, separators=(",", ":"), sort_keys=True)
    return hashlib.md5(canon.encode("utf-8")).hexdigest()


def _cache_get(profile_hash: str) -> Optional[Dict[str, str]]:
    ent = _PHRASE_MAP_CACHE.get(profile_hash)
    if not ent:
        return None
    if time.time() - ent["ts"] > _PHRASE_MAP_TTL:
        _PHRASE_MAP_CACHE.pop(profile_hash, None)
        return None
    return ent["phrase_map"]


def _cache_set(profile_hash: str, phrase_map: Dict[str, str]) -> None:
    _PHRASE_MAP_CACHE[profile_hash] = {"phrase_map": phrase_map, "ts": time.time()}


def _db_get_phrase_map(profile_hash: str, model_name: str) -> Optional[Dict[str, Any]]:
    conn = open_db_conn()
    if conn is None:
        logger.debug("DB connection unavailable; skipping phrase_map DB lookup")
        return None
    try:
        with conn.cursor() as cur:
            sql = f"SELECT * FROM `{DB_TABLE_PHRASE_MAPS}` WHERE profile_hash=%s AND model_name=%s LIMIT 1"
            cur.execute(sql, (profile_hash, model_name))
            row = cur.fetchone()
        if row:
            try:
                with conn.cursor() as cur:
                    cur.execute(
                        f"UPDATE `{DB_TABLE_PHRASE_MAPS}` SET usage_count = usage_count + 1, last_used_at = NOW() WHERE id=%s",
                        (row["id"],),
                    )
            except Exception:
                logger.debug("Could not update usage metadata for phrase_map id %s", row.get("id"))
        if not row:
            return None
        phrase_map_json = row.get("phrase_map_json") or "{}"
        phrase_map = json.loads(phrase_map_json) if phrase_map_json else {}
        return {"phrase_map": phrase_map, "row": row}
    except Exception as e:
        logger.exception("DB lookup error for phrase_map: %s", e)
        return None
    finally:
        try:
            conn.close()
        except Exception:
            pass


def _db_store_phrase_map(profile_hash: str, model_name: str, prompt_obj: Any, phrase_map: Dict[str, str],
                         raw_model_response: str, profile_id: Optional[int] = None) -> bool:
    conn = open_db_conn()
    if conn is None:
        logger.debug("DB connection unavailable; skipping phrase_map DB store")
        return False
    try:
        prompt_json = json.dumps(prompt_obj, ensure_ascii=False) if not isinstance(prompt_obj, str) else prompt_obj
        phrase_map_json = json.dumps(phrase_map, ensure_ascii=False)
        with conn.cursor() as cur:
            sql = f"""
                INSERT INTO `{DB_TABLE_PHRASE_MAPS}`
                (profile_hash, profile_id, model_name, prompt, phrase_map_json, raw_model_response, created_at, last_used_at, usage_count)
                VALUES (%s, %s, %s, %s, %s, %s, NOW(), NOW(), 1)
                ON DUPLICATE KEY UPDATE
                  phrase_map_json = VALUES(phrase_map_json),
                  raw_model_response = VALUES(raw_model_response),
                  prompt = VALUES(prompt),
                  last_used_at = NOW(),
                  usage_count = usage_count + 1
            """
            cur.execute(sql, (profile_hash, profile_id, model_name, prompt_json, phrase_map_json, raw_model_response))
        return True
    except Exception as e:
        logger.exception("DB store error for phrase_map: %s", e)
        return False
    finally:
        try:
            conn.close()
        except Exception:
            pass


# ----------------------------
# AI prompt builder + call
# ----------------------------
def _db_get_generator_config(generator_config_id: str) -> Optional[Dict[str, Any]]:
    conn = open_db_conn()
    if conn is None:
        logger.debug("DB connection unavailable; skipping generator_config DB lookup")
        return None
    try:
        with conn.cursor() as cur:
            sql = "SELECT * FROM `generator_config` WHERE config_id = %s LIMIT 1"
            cur.execute(sql, (generator_config_id,))
            row = cur.fetchone()
        return row
    except Exception as e:
        logger.exception("DB lookup error for generator_config: %s", e)
        return None
    finally:
        try:
            conn.close()
        except Exception:
            pass


def _build_ai_generation_prompt(generator_config_id: str, merged_profile: StyleProfile) -> Dict[str, str]:
    """
    Build the prompt for generating phrase_map using the generator_config row.
    """
    config = _db_get_generator_config(generator_config_id)
    if not config:
        # fallback to a safe system/user instruction if generator_config is missing
        system = (
            "You are an expert prompt-crafting assistant for visual style. "
            "Given a style profile (list of axes with left/right pole labels and numeric values 0..100), "
            "produce a compact mapping 'phrase_map' that maps each pole label (exact string) to "
            "a short, 3-8 word style phrase suitable for Stable Diffusion/SDXL prompt fragments. "
            "Be concise; do NOT add extra commentary. Return only valid JSON, nothing else."
        )
        user = "INPUT_PROFILE: (no generator_config row found)\n"
    else:
        system = config.get("system_role", "You are an expert content generator.")
        # instructions is stored as JSON array (string) in the DB; join for readability
        try:
            instructions = json.loads(config.get("instructions") or "[]")
            if isinstance(instructions, list):
                instr_text = " ".join(instructions)
            else:
                instr_text = str(instructions)
        except Exception:
            instr_text = str(config.get("instructions") or "")
        user = ""

    axes_summary = [f"{a.key} => left:'{a.pole_left}', right:'{a.pole_right}', value:{int(a.value)}" for a in merged_profile.axes]
    axes_text = "\n".join(axes_summary)
    user += f"INPUT_PROFILE:\n{axes_text}\n\n"
    # output_schema may be present in DB but we still ask for the simple phrase_map JSON
    user += "OUTPUT FORMAT:\n{ \"phrase_map\": { \"PoleLabel\": \"short phrase\", ... } }\n\n"
    user += "Guidelines:\n- For each pole label create a short phrase suitable for SDXL prompts (3-8 words).\n- Return only valid JSON with a top-level 'phrase_map' object.\n"
    return {"system": system, "user": user}


def _call_openai_compatible_chat(model_name: str, messages: List[Dict[str, str]], max_tokens: int = 20000, temperature: float = 0.2) -> Tuple[bool, str]:
    """
    Note: max_tokens default = 20000 per request (user requested).
    """
    url = OPENAI_COMPAT_BASE.rstrip("/") + CHAT_COMPLETIONS_PATH
    payload = {"model": model_name, "messages": messages, "max_tokens": max_tokens, "temperature": temperature}
    try:
        resp = requests.post(url, json=payload, timeout=300)
        resp.raise_for_status()
        j = resp.json()
        content = ""
        if j.get("choices"):
            choice = j["choices"][0]
            if "message" in choice and "content" in choice["message"]:
                content = choice["message"]["content"]
            elif "text" in choice:
                content = choice["text"]
        return True, content
    except requests.exceptions.RequestException as e:
        logger.error("OpenAI-compat returned error: %s", e)
        return False, str(e)
    except Exception as e:
        logger.exception("Error calling OpenAI-compatible endpoint: %s", e)
        return False, str(e)


def get_or_create_phrase_map(generator_config_id: str, merged_profile: StyleProfile, cfg: ConverterConfig, model_name: Optional[str] = None) -> Tuple[Dict[str, str], Any, str]:
    """
    Returns (phrase_map, fallback_fn, raw_model_response).
    raw_model_response is the raw text returned by the model when generating phrase_map (may be empty).
    """
    model_name = model_name or cfg.model_name or os.environ.get("SAGE_DEFAULT_STYLE_MODEL", "gemini-2.5-flash")
    profile_hash = _profile_fingerprint(merged_profile)
    cached = _cache_get(profile_hash)
    if cached:
        logger.debug("phrase_map loaded from memory cache for %s", profile_hash)
        return cached, (lambda p: cached.get(p) or DEFAULT_PHRASE_MAP.get(p) or f"{p.lower()} style"), ""

    db_data = _db_get_phrase_map(profile_hash, model_name)
    if db_data and db_data.get("phrase_map"):
        phrase_map = db_data["phrase_map"]
        _cache_set(profile_hash, phrase_map)
        logger.info("phrase_map loaded from DB for %s (model=%s)", profile_hash, model_name)
        return phrase_map, (lambda p: phrase_map.get(p) or DEFAULT_PHRASE_MAP.get(p) or f"{p.lower()} style"), ""

    prompt_pack = _build_ai_generation_prompt(generator_config_id, merged_profile)
    messages = [{"role": "system", "content": prompt_pack["system"]}, {"role": "user", "content": prompt_pack["user"]}]
    ok, raw = _call_openai_compatible_chat(model_name, messages, max_tokens=20000, temperature=0.2)
    raw_text = raw or ""
    phrase_map: Dict[str, str] = {}
    if ok and raw_text:
        text = raw_text.strip()
        if text.startswith("```"):
            parts = text.split("```")
            if len(parts) >= 2:
                text = parts[1].strip()
        if text.lower().startswith("json"):
            text = text[4:].strip()
        try:
            parsed = json.loads(text)
            if isinstance(parsed, dict) and "phrase_map" in parsed and isinstance(parsed["phrase_map"], dict):
                phrase_map = parsed["phrase_map"]
            elif isinstance(parsed, dict):
                phrase_map = parsed
        except json.JSONDecodeError:
            logger.error("Failed to parse model JSON response for phrase_map: raw: %.400s", raw_text)

    if not phrase_map:
        logger.info("Falling back to DEFAULT_PHRASE_MAP for profile hash %s", profile_hash)
        for a in merged_profile.axes:
            if a.pole_left not in phrase_map:
                phrase_map[a.pole_left] = DEFAULT_PHRASE_MAP.get(a.pole_left, f"{a.pole_left.lower()} style")
            if a.pole_right not in phrase_map:
                phrase_map[a.pole_right] = DEFAULT_PHRASE_MAP.get(a.pole_right, f"{a.pole_right.lower()} style")

    _db_store_phrase_map(profile_hash, model_name, prompt_pack, phrase_map, raw_text, profile_id=merged_profile.id)
    _cache_set(profile_hash, phrase_map)
    return phrase_map, (lambda p: phrase_map.get(p) or DEFAULT_PHRASE_MAP.get(p) or f"{p.lower()} style"), raw_text


# ----------------------------
# Core conversion logic (with parentheses emphasis)
# ----------------------------
def merge_profiles_avg(profiles: List[StyleProfile]) -> StyleProfile:
    axis_map: Dict[str, Dict[str, Any]] = {}
    for p in profiles:
        for a in p.axes:
            key = a.key
            if key not in axis_map:
                axis_map[key] = {"sum": 0.0, "count": 0, "pole_left": a.pole_left, "pole_right": a.pole_right}
            axis_map[key]["sum"] += a.value
            axis_map[key]["count"] += 1
    merged_axes: List[StyleAxis] = []
    for key, v in axis_map.items():
        avg_value = int(round(v["sum"] / v["count"])) if v["count"] > 0 else 50
        merged_axes.append(StyleAxis(key=key, pole_left=v["pole_left"], pole_right=v["pole_right"], value=avg_value))
    return StyleProfile(profile_name="merged_profile", created_at=datetime.utcnow().isoformat(), axes=merged_axes)


def _paren_wrap(text: str, count: int) -> str:
    if count <= 0:
        return text
    return ("(" * count) + text + (")" * count)


def _parens_for_intensity(intensity: float) -> int:
    """
    Map intensity [0..1] to parentheses strength:
      intensity >= 0.95 -> 3
      intensity >= 0.6  -> 2
      intensity >= 0.3  -> 1
      else              -> 0 (no parens)
    """
    if intensity >= 0.95:
        return 3
    if intensity >= 0.6:
        return 2
    if intensity >= 0.3:
        return 1
    return 0


def compute_fragments(merged: StyleProfile, cfg: ConverterConfig, phrase_map: Dict[str, str], fallback_fn) -> List[FragmentOut]:
    half = cfg.scale / 2.0
    fragments: List[FragmentOut] = []
    for a in merged.axes:
        delta_raw = a.value - cfg.center
        abs_delta = abs(delta_raw)
        intensity = min(1.0, abs_delta / half)
        pole = a.pole_right if delta_raw >= 0 else a.pole_left
        base_phrase = phrase_map.get(pole) or fallback_fn(pole)
        paren_count = _parens_for_intensity(intensity)
        text = _paren_wrap(base_phrase, paren_count)
        fragments.append(FragmentOut(key=a.key, pole=pole, intensity=round(float(intensity), 3), text=text))
    return fragments


def apply_scale_binders(fragments: List[FragmentOut], merged: StyleProfile, cfg: ConverterConfig) -> List[FragmentOut]:
    def axis_value(key_candidates: List[str]) -> Optional[int]:
        for kc in key_candidates:
            for ax in merged.axes:
                if ax.key == kc or ax.key.startswith(kc):
                    return ax.value
        return None

    if cfg.include_scale_binders:
        heavy_val = axis_value(["Agile vs Heavy", "Heavy Mass vs Lightweight Skeletal"])
        youthful_val = axis_value(["Youthful vs Aged"])
        if heavy_val is not None and youthful_val is not None and heavy_val > 85 and youthful_val < 35:
            binder = FragmentOut(key="scale_binder", pole="compact", intensity=1.0, text="compact humanoid scale, human-sized")
            if not any(f.key == "scale_binder" for f in fragments):
                fragments.insert(0, binder)
    return fragments


def build_textual_style_prompt(fragments: List[FragmentOut], cfg: ConverterConfig) -> str:
    textual_fragments = sorted([f for f in fragments if f.text], key=lambda x: x.intensity, reverse=True)
    top = textual_fragments[: cfg.max_fragments_in_prompt]
    core = ", ".join(f.text for f in top) if top else "neutral style"
    anchors = ", cinematic, high detail, filmic lighting"
    return f"{core}{anchors}"


def build_adapter_prompts(fragments: List[FragmentOut], cfg: ConverterConfig) -> List[str]:
    textual_fragments = sorted([f for f in fragments if f.text], key=lambda x: x.intensity, reverse=True)
    picks = textual_fragments[: cfg.top_adapter_fragments]
    adapter_prompts = []
    for i in range(cfg.adapter_variants):
        parts = []
        for f in picks:
            prefix = ""
            if (i % 3 == 0) and (f.intensity > 0.6):
                prefix = "very "
            parts.append(f"{prefix}{f.text}")
        variant = ", ".join(parts) + ", style reference, no characters, plain background"
        adapter_prompts.append(variant)
    return adapter_prompts


# ----------------------------
# Polish step using generator_config (if provided) and large token budget
# ----------------------------
def _ai_finalize_prompt(polish_generator_config_id: Optional[str], merged_profile: StyleProfile, raw_prompt: str,
                        fragments: List[FragmentOut], model_name: str, max_tokens: int = 20000) -> Tuple[bool, str, str]:
    """
    Robust polish: returns (ok, final_single_line_prompt_or_empty, raw_model_response_text_or_empty)
    """
    def _strip_fences(s: str) -> str:
        if not s:
            return s
        s = s.strip()
        if s.startswith("```") and "```" in s[3:]:
            parts = s.split("```")
            if len(parts) >= 2:
                return parts[1].strip()
        if s.startswith("```"):
            return s.strip("` \n")
        return s

    def _collapse_to_single_line(s: str) -> str:
        return " ".join(line.strip() for line in s.splitlines() if line.strip())

    def _looks_like_json(s: str) -> bool:
        if not s:
            return False
        st = s.lstrip()
        return st.startswith("{") or st.startswith("[") or '"phrase_map"' in st or '"result"' in st

    def _synthesize_prompt_from_phrase_map(phrase_map: Dict[str, str], fragments_list: List[FragmentOut]) -> str:
        picks = sorted(fragments_list, key=lambda x: x.intensity, reverse=True)[:20]
        parts = []
        for f in picks:
            phrase = None
            if isinstance(phrase_map, dict):
                phrase = phrase_map.get(f.pole)
            if not phrase:
                phrase = f.text or f"{f.pole.lower()}"
            parts.append(phrase)
        core = ", ".join(parts)
        return f"{core}, cinematic, high detail, filmic lighting"

    def _extract_from_json_like(text: str) -> Optional[str]:
        try:
            parsed = json.loads(text)
        except Exception:
            return None
        if isinstance(parsed, dict):
            for k in ("result", "prompt", "text", "final_prompt", "output"):
                if k in parsed and isinstance(parsed[k], str) and parsed[k].strip():
                    return _collapse_to_single_line(parsed[k].strip())
            if "phrase_map" in parsed and isinstance(parsed["phrase_map"], dict):
                try:
                    return _synthesize_prompt_from_phrase_map(parsed["phrase_map"], fragments)
                except Exception:
                    pass
            for v in parsed.values():
                if isinstance(v, str) and v.strip():
                    return _collapse_to_single_line(v.strip())
        if isinstance(parsed, list):
            str_items = [str(x).strip() for x in parsed if isinstance(x, str) and x.strip()]
            if str_items:
                return _collapse_to_single_line(", ".join(str_items))
        return None

    user_base = (
        "RAW_STYLE_PROMPT:\n"
        f"{raw_prompt}\n\n"
        "TOP_FRAGMENTS:\n"
        f"{', '.join([f.text for f in sorted(fragments, key=lambda x: x.intensity, reverse=True)[:8]])}\n\n"
        "AXES_SUMMARY:\n"
        f"{'; '.join([f'{a.key}={a.value}' for a in merged_profile.axes[:40]])}\n\n"
        "GUIDELINES:\n"
        "- Produce one concise prompt suitable for image generation models.\n"
        "- Preserve parentheses emphasis if present.\n"
        "- Avoid meta words like 'dominant' — use purely descriptive visual terms and parentheses for emphasis.\n"
        "- Return only a single-line prompt string, nothing else.\n"
    )

    # Try configured polish generator first (if provided)
    if polish_generator_config_id:
        cfg_row = _db_get_generator_config(polish_generator_config_id)
        if cfg_row:
            system = cfg_row.get("system_role", "")
            try:
                instr = json.loads(cfg_row.get("instructions") or "[]")
                if isinstance(instr, list):
                    system = system + "\n" + " ".join(instr)
            except Exception:
                pass
            try:
                out_schema = json.loads(cfg_row.get("output_schema") or "{}")
                user = f"CONFIG_OUTPUT_SCHEMA:\n{json.dumps(out_schema)}\n\n" + user_base
            except Exception:
                user = user_base
            messages = [{"role": "system", "content": system}, {"role": "user", "content": user}]
            ok, content = _call_openai_compatible_chat(model_name, messages, max_tokens=max_tokens, temperature=0.2)
            raw_response = content or ""
            raw_response = _strip_fences(raw_response)
            logger.debug("Polish config(%s) raw_response len=%d", polish_generator_config_id, len(raw_response))
            if _looks_like_json(raw_response):
                extracted = _extract_from_json_like(raw_response)
                if extracted:
                    return True, extracted, raw_response
                # try synthesize from phrase_map JSON if present
                try:
                    parsed = json.loads(raw_response)
                    if isinstance(parsed, dict) and "phrase_map" in parsed and isinstance(parsed["phrase_map"], dict):
                        synthesized = _synthesize_prompt_from_phrase_map(parsed["phrase_map"], fragments)
                        return True, synthesized, raw_response
                except Exception:
                    pass
            else:
                single = _collapse_to_single_line(raw_response)
                if single:
                    return True, single, raw_response
            # fallthrough to strict fallback

    # Strict fallback call (guarantee single-line prompt)
    strict_system = (
        "You are a pragmatic prompt engineer for image generation models. "
        "Given the raw style descriptor and fragment list, produce ONE concise Stable Diffusion/SDXL-ready prompt line. "
        "Preserve parentheses emphasis if present. Do not output JSON, lists, or explanations — return exactly one prompt line."
    )
    messages = [{"role": "system", "content": strict_system}, {"role": "user", "content": user_base}]
    ok2, content2 = _call_openai_compatible_chat(model_name, messages, max_tokens=max_tokens, temperature=0.2)
    raw2 = content2 or ""
    raw2 = _strip_fences(raw2)
    logger.debug("Polish strict-fallback raw_response len=%d", len(raw2))
    if raw2:
        if _looks_like_json(raw2):
            extracted2 = _extract_from_json_like(raw2)
            if extracted2:
                return True, extracted2, raw2
            try:
                parsed2 = json.loads(raw2)
                if isinstance(parsed2, dict) and "phrase_map" in parsed2 and isinstance(parsed2["phrase_map"], dict):
                    return True, _synthesize_prompt_from_phrase_map(parsed2["phrase_map"], fragments), raw2
            except Exception:
                pass
        else:
            single2 = _collapse_to_single_line(raw2)
            if single2:
                return True, single2, raw2

    # final failure
    logger.error("Polish step produced no usable single-line prompt. raw lengths: primary=%d fallback=%d",
                 0, 0)
    return False, "", ""


# ----------------------------
# Endpoints
# ----------------------------
@router.post("/convert", response_model=ConvertResponse)
def convert_style_profiles(req: ConvertRequest):
    try:
        if not req.profiles:
            raise HTTPException(status_code=400, detail="No profiles provided")
        cfg = req.config or ConverterConfig()
        generator_config_id = req.generator_config_id
        merged = merge_profiles_avg(req.profiles)

        # obtain phrase_map (AI-generated or cached) — now returns raw response too
        phrase_map, fallback_fn, phrase_map_raw = get_or_create_phrase_map(generator_config_id, merged, cfg, cfg.model_name)

        # compute fragments
        fragments = compute_fragments(merged, cfg, phrase_map, fallback_fn)
        fragments = apply_scale_binders(fragments, merged, cfg)
        raw_textual = build_textual_style_prompt(fragments, cfg)

        # Optional: post-process with AI to produce a polished single-line prompt
        final_prompt = raw_textual
        polish_raw = ""
        polish_used_config = None
        if cfg.post_process_with_ai:
            polish_id = cfg.polish_generator_config_id or generator_config_id
            polish_used_config = polish_id
            post_model = cfg.post_process_model or cfg.model_name or os.environ.get("SAGE_DEFAULT_STYLE_MODEL", "gemini-2.5-flash")
            ok, polished, polish_raw = _ai_finalize_prompt(polish_id, merged, raw_textual, fragments, post_model, max_tokens=cfg.polish_max_tokens)
            if ok and polished:
                final_prompt = polished
            else:
                logger.info("AI post-process failed or returned empty; using raw textual prompt")

        adapter_prompts = build_adapter_prompts(fragments, cfg)

        debug_obj = None
        if cfg.include_debug_responses:
            debug_obj = {
                "profile_hash": _profile_fingerprint(merged),
                "phrase_map_raw": phrase_map_raw,
                "phrase_map_used_model": cfg.model_name or os.environ.get("SAGE_DEFAULT_STYLE_MODEL", "gemini-2.5-flash"),
                "polish_used_config": polish_used_config,
                "polish_raw_response": polish_raw
            }

        return ConvertResponse(
            textualStylePrompt=final_prompt,
            fragments=fragments,
            adapterPrompts=adapter_prompts,
            merged_profile=merged,
            raw_profiles=req.profiles,
            debug=debug_obj
        )

    except HTTPException:
        raise
    except Exception as e:
        logger.exception("Error in style conversion: %s", e)
        raise HTTPException(status_code=500, detail=f"An internal error occurred: {e}")


@router.post("/cache/clear")
def clear_phrase_cache():
    _PHRASE_MAP_CACHE.clear()
    return {"status": "ok", "cleared": True, "now": time.time()}


# ----------------------------
# Optional smoke test when run directly
# ----------------------------
if __name__ == "__main__":
    sample = {
        "generator_config_id": "777af2baa9d8360fb01e6337368880c9",
        "profiles": [
            {
                "id": 8,
                "profile_name": "Support09",
                "axes": [
                    {"id": 22, "key": "Emotional vs Stoic", "pole_left": "Emotional", "pole_right": "Stoic", "value": 82},
                    {"id": 24, "key": "Agile vs Heavy", "pole_left": "Agile", "pole_right": "Heavy", "value": 100},
                    {"id": 26, "key": "Human vs Alien", "pole_left": "Human", "pole_right": "Alien", "value": 93},
                    {"id": 39, "key": "Expressive vs Reserved", "pole_left": "Expressive", "pole_right": "Reserved", "value": 83},
                    {"id": 41, "key": "Symbolic vs Functional", "pole_left": "Symbolic", "pole_right": "Functional", "value": 100}
                ]
            }
        ],
        "config": {
            "post_process_with_ai": True,
            "polish_generator_config_id": "your_polish_config_id_here",
            "polish_max_tokens": 20000,
            "post_process_model": "gemini-2.5-flash-lite",
            "include_debug_responses": True
        }
    }
    try:
        cr = ConvertRequest(**sample)
        out = convert_style_profiles(cr)
        print(json.dumps(out.dict(), indent=2, ensure_ascii=False))
    except Exception as ex:
        logger.exception("Smoke test failed: %s", ex)
