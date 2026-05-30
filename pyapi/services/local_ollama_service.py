"""
local_ollama_service.py
OpenAI-compatible wrapper for a local Ollama instance.

Compatible with ollama-python 0.6.x — defensive invocation to avoid
passing unsupported kwargs (e.g. 'temperature' on Client.chat).
Place in: pyapi/services/local_ollama_service.py
"""
import os
import time
import uuid
import logging
import asyncio
import traceback
import inspect
from typing import List, Optional, Dict, Any

from fastapi import APIRouter, HTTPException, Header, Request
from pydantic import BaseModel, Field

logger = logging.getLogger(__name__)
logger.setLevel(logging.INFO)

router = APIRouter(tags=["local_ollama"])

# Config
OLLAMA_BASE_URL = os.environ.get("OLLAMA_BASE_URL", "http://localhost:11434")
LOCAL_API_KEY = os.environ.get("LOCAL_LM_API_KEY", "")

# Try to import ollama - endpoints will return helpful errors if not present
try:
    import ollama
    HAS_OLLAMA = True
    logger.info("ollama python package available.")
except Exception as e:
    HAS_OLLAMA = False
    logger.warning("ollama python package not available: %s", e)

# Pydantic schemas
class OpenAIChatMessage(BaseModel):
    role: str
    content: str
    name: Optional[str] = None

class ChatRequest(BaseModel):
    model: Optional[str] = None
    messages: List[OpenAIChatMessage]
    temperature: Optional[float] = Field(default=0.7, ge=0.0, le=2.0)
    max_tokens: Optional[int] = Field(default=256, ge=1)
    stream: Optional[bool] = Field(default=False)

class ModelPullRequest(BaseModel):
    model: str
    tags: Optional[Dict[str, Any]] = None

class EmbeddingsRequest(BaseModel):
    model: Optional[str] = None
    input: List[str]


# ---- Helpers: safe wrappers around ollama functions ----

def _ensure_ollama_available():
    if not HAS_OLLAMA:
        raise RuntimeError("Ollama python package not installed in the venv")

async def _call_thread(fn, *args, **kwargs):
    return await asyncio.to_thread(lambda: fn(*args, **kwargs))

def _filter_kwargs_for_callable(fn, kwargs: Dict[str, Any]) -> Dict[str, Any]:
    """
    Inspect callable signature and return a dict with only supported kwargs.
    If inspection fails, conservatively return an empty dict (don't pass extras).
    """
    try:
        sig = inspect.signature(fn)
        allowed = {}
        for k, v in kwargs.items():
            if k in sig.parameters:
                allowed[k] = v
        return allowed
    except Exception:
        # Some C-extensions or wrapped functions may not expose signature; don't pass kwargs
        return {}

async def _ollama_list_raw():
    """
    Calls ollama.list() safely in a thread and returns the raw result.
    """
    _ensure_ollama_available()
    try:
        # prefer top-level list function if present
        if hasattr(ollama, "list"):
            return await _call_thread(ollama.list)
        # fallback to making HTTP call via client library if available
        if hasattr(ollama, "Client"):
            def _c(): return ollama.Client(base_url=OLLAMA_BASE_URL).list_models()
            return await _call_thread(_c)
        raise RuntimeError("No supported listing method in ollama package")
    except Exception:
        raise

def _normalize_models(raw) -> List[Dict[str, Any]]:
    """
    Normalize the raw return of ollama.list() into a list of dicts:
    [{'model': <name>, 'size': <bytes>, 'params': <string>, 'format': <fmt>, 'digest': <digest>, 'raw': <original>}]
    """
    models_out: List[Dict[str, Any]] = []

    try:
        # Unwrap tuple-like results (some versions return (models_list,) )
        if isinstance(raw, tuple) and len(raw) > 0:
            candidate = raw[0]
        else:
            candidate = raw

        # If dict with 'models' key
        if isinstance(candidate, dict) and "models" in candidate:
            items = candidate["models"]
        elif isinstance(candidate, (list, tuple)):
            items = list(candidate)
        else:
            # If candidate has attribute 'models'
            if hasattr(candidate, "models"):
                items = getattr(candidate, "models") or []
            else:
                # fallback: nothing recognizable
                items = []

        for it in items:
            entry = {"model": None, "size": None, "params": None, "format": None, "digest": None, "raw": None}
            entry["raw"] = repr(it)

            if isinstance(it, dict):
                entry["model"] = it.get("model") or it.get("name") or it.get("id")
                entry["size"] = it.get("size")
                details = it.get("details") or {}
                if isinstance(details, dict):
                    entry["params"] = details.get("parameter_size") or details.get("params")
                    entry["format"] = details.get("format")
                entry["digest"] = it.get("digest")
                models_out.append(entry)
                continue

            # object-like
            try:
                entry["model"] = getattr(it, "model", None) or getattr(it, "name", None) or getattr(it, "id", None)
                entry["size"] = getattr(it, "size", None)
                entry["digest"] = getattr(it, "digest", None)
                details = getattr(it, "details", None)
                if details is not None:
                    if isinstance(details, dict):
                        entry["params"] = details.get("parameter_size")
                        entry["format"] = details.get("format")
                    else:
                        entry["params"] = getattr(details, "parameter_size", None) or getattr(details, "parameterSize", None)
                        entry["format"] = getattr(details, "format", None)
                models_out.append(entry)
                continue
            except Exception:
                entry["raw"] = repr(it)
                models_out.append(entry)
                continue
    except Exception:
        logger.exception("Normalization of ollama.list result failed: %s", traceback.format_exc())
        try:
            return [{"model": str(raw), "raw": repr(raw)}]
        except Exception:
            return []

    return models_out

async def _ollama_list_models_normalized() -> List[Dict[str, Any]]:
    raw = await _ollama_list_raw()
    return _normalize_models(raw)

async def _ollama_pull_model(model: str) -> Dict[str, Any]:
    _ensure_ollama_available()
    try:
        if hasattr(ollama, "pull"):
            return await _call_thread(ollama.pull, model)
        # fallback to client if top-level not available
        if hasattr(ollama, "Client"):
            def _c(): return ollama.Client(base_url=OLLAMA_BASE_URL).pull(model)
            return await _call_thread(_c)
        raise RuntimeError("ollama.pull not available in installed package")
    except Exception:
        logger.exception("ollama.pull failed: %s", traceback.format_exc())
        raise

async def _ollama_chat(model: str, messages: List[Dict[str, str]], temperature: float = 0.7, max_tokens: int = 256) -> str:
    """
    Make a chat request to Ollama and return assistant text.
    Avoids passing unsupported kwargs by introspecting callable signatures.
    """
    _ensure_ollama_available()

    try:
        # Prefer top-level chat function if available
        if hasattr(ollama, "chat"):
            fn = ollama.chat
            kwargs = {"model": model, "messages": messages}
            # do not blindly pass temperature/max_tokens — filter by signature
            allowed_kwargs = _filter_kwargs_for_callable(fn, {"messages": messages, "model": model, "temperature": temperature, "max_tokens": max_tokens, "stream": False})
            # call in thread
            res = await _call_thread(lambda: fn(**allowed_kwargs))
        elif hasattr(ollama, "generate"):
            # build a single prompt and call generate
            prompt_parts = []
            for m in messages:
                role = m.get("role", "user")
                prompt_parts.append(f"{role}: {m.get('content','')}")
            prompt_parts.append("assistant:")
            prompt = "\n".join(prompt_parts)
            fn = ollama.generate
            allowed_kwargs = _filter_kwargs_for_callable(fn, {"model": model, "prompt": prompt, "max_tokens": max_tokens})
            if "model" in allowed_kwargs and "prompt" in allowed_kwargs:
                # some wrappers expect positional (model, prompt)
                res = await _call_thread(lambda: fn(**allowed_kwargs))
            else:
                # try positional fallback
                res = await _call_thread(lambda: fn(model, prompt))
        elif hasattr(ollama, "Client"):
            # Client-based approach
            def _sync_call():
                client = ollama.Client(base_url=OLLAMA_BASE_URL)
                # prefer client.chat if available
                if hasattr(client, "chat"):
                    fn = client.chat
                    allowed = _filter_kwargs_for_callable(fn, {"model": model, "messages": messages, "stream": False})
                    return fn(**allowed)
                if hasattr(client, "generate"):
                    # build prompt
                    prompt_parts = []
                    for m in messages:
                        role = m.get("role", "user")
                        prompt_parts.append(f"{role}: {m.get('content','')}")
                    prompt_parts.append("assistant:")
                    prompt = "\n".join(prompt_parts)
                    fn = client.generate
                    allowed = _filter_kwargs_for_callable(fn, {"model": model, "prompt": prompt, "max_tokens": max_tokens})
                    if allowed:
                        return fn(**allowed)
                    return fn(model, prompt)
                raise RuntimeError("Client has no chat/generate")
            res = await _call_thread(_sync_call)
        else:
            raise RuntimeError("No supported chat/generate function in ollama package")
    except Exception:
        logger.exception("ollama.chat/generate failed: %s", traceback.format_exc())
        raise

    # Normalize result
    try:
        # If string
        if isinstance(res, str):
            return res
        # dict-like
        if isinstance(res, dict):
            # common shapes: {'message': {'content': '...'}} or {'output': '...'} etc.
            msg = res.get("message") or res.get("output") or res.get("text") or res.get("response")
            if isinstance(msg, dict):
                return msg.get("content", str(res))
            if isinstance(msg, str):
                return msg
            # fallback to raw serialization
            return str(res)
        # object-like
        if hasattr(res, "message"):
            # try message.content
            try:
                return getattr(res.message, "content", str(res))
            except Exception:
                return str(res)
        # list-like: take first element if dict contains text
        if isinstance(res, (list, tuple)) and len(res):
            first = res[0]
            if isinstance(first, dict):
                return first.get("text") or first.get("output") or str(first)
            if isinstance(first, str):
                return first
            if hasattr(first, "text"):
                return getattr(first, "text", str(first))
        # fallback
        return str(res)
    except Exception:
        logger.exception("Failed to normalize ollama response: %s", traceback.format_exc())
        return str(res)

async def _ollama_embeddings(model: str, inputs: List[str]) -> List[List[float]]:
    _ensure_ollama_available()
    try:
        if hasattr(ollama, "embed"):
            fn = ollama.embed
            allowed = _filter_kwargs_for_callable(fn, {"model": model, "input": inputs})
            if allowed:
                out = await _call_thread(lambda: fn(**allowed))
            else:
                out = await _call_thread(lambda: fn(model, inputs))
            if isinstance(out, dict) and "embeddings" in out:
                return out["embeddings"]
            if isinstance(out, list):
                return out
            return [out]
        # fallback to client
        if hasattr(ollama, "Client"):
            def _sync_emb():
                client = ollama.Client(base_url=OLLAMA_BASE_URL)
                if hasattr(client, "embeddings"):
                    fn = client.embeddings
                    allowed = _filter_kwargs_for_callable(fn, {"model": model, "input": inputs})
                    if allowed:
                        return fn(**allowed)
                    return fn(model, inputs)
                raise RuntimeError("client.embeddings not available")
            out = await _call_thread(_sync_emb)
            if isinstance(out, dict) and "embeddings" in out:
                return out["embeddings"]
            if isinstance(out, list):
                return out
            return [out]
        raise RuntimeError("ollama.embed not available in this package")
    except Exception:
        logger.exception("ollama.embed failed: %s", traceback.format_exc())
        raise

# ---- End helpers ----

def api_key_ok(authorization: Optional[str]) -> bool:
    if not LOCAL_API_KEY:
        return True
    if not authorization:
        return False
    if not authorization.startswith("Bearer "):
        return False
    return authorization.split(" ", 1)[1].strip() == LOCAL_API_KEY

# ---- Endpoints ----

@router.get("/v1/health")
async def health():
    if not HAS_OLLAMA:
        return {"status": "error", "detail": "ollama python package not installed in venv"}
    try:
        models = await _ollama_list_models_normalized()
        return {"status": "ok", "service": "local_ollama", "models_available": len(models), "ollama_base_url": OLLAMA_BASE_URL}
    except Exception as e:
        logger.exception("Health check error: %s", e)
        return {"status": "error", "detail": f"Ollama daemon unreachable or error: {e}"}

@router.get("/v1/models")
async def list_models():
    if not HAS_OLLAMA:
        raise HTTPException(status_code=500, detail="ollama python package not installed in venv")
    try:
        models = await _ollama_list_models_normalized()
        return {"object": "list", "data": models}
    except Exception as e:
        logger.exception("Failed to list ollama models: %s", e)
        raise HTTPException(status_code=503, detail=f"Failed to contact Ollama daemon: {e}")

@router.post("/v1/models/pull")
async def pull_model(req: ModelPullRequest, authorization: Optional[str] = Header(None)):
    if not api_key_ok(authorization):
        raise HTTPException(status_code=401, detail="Unauthorized")
    if not HAS_OLLAMA:
        raise HTTPException(status_code=500, detail="ollama python package not installed in venv")
    if not req.model:
        raise HTTPException(status_code=400, detail="Missing 'model' field")
    try:
        res = await _ollama_pull_model(req.model)
        return {"status": "ok", "detail": res}
    except Exception as e:
        logger.exception("Model pull failed: %s", e)
        raise HTTPException(status_code=500, detail=f"Model pull failed: {e}")

@router.post("/v1/chat/completions")
async def chat_completions(req: ChatRequest, authorization: Optional[str] = Header(None), request: Request = None):
    if not api_key_ok(authorization):
        raise HTTPException(status_code=401, detail="Unauthorized")

    if req.stream:
        raise HTTPException(status_code=400, detail="Streaming not supported")

    if not HAS_OLLAMA:
        raise HTTPException(status_code=500, detail="ollama python package not installed in venv")

    model_id = req.model
    if not model_id:
        try:
            models = await _ollama_list_models_normalized()
            if models:
                model_id = models[0].get("model")
        except Exception:
            model_id = None

    if not model_id:
        raise HTTPException(status_code=503, detail="No model specified and no local models available. Pull a model first.")

    messages = [{"role": m.role, "content": m.content} for m in req.messages]

    try:
        # call with given temperature and max_tokens but the helper will only pass supported kwargs
        text = await _ollama_chat(model=model_id, messages=messages, temperature=req.temperature or 0.0, max_tokens=req.max_tokens or 256)
    except Exception as e:
        logger.exception("Ollama chat error: %s", e)
        raise HTTPException(status_code=503, detail=f"Ollama chat failed: {e}")

    response = {
        "id": f"chatcmpl-local-{uuid.uuid4().hex[:8]}",
        "object": "chat.completion",
        "created": int(time.time()),
        "model": model_id,
        "choices": [
            {"index": 0, "message": {"role": "assistant", "content": text}, "finish_reason": "stop"}
        ],
        "usage": {"prompt_tokens": 0, "completion_tokens": 0, "total_tokens": 0}
    }
    return response

@router.post("/v1/embeddings")
async def embeddings(req: EmbeddingsRequest, authorization: Optional[str] = Header(None)):
    if not api_key_ok(authorization):
        raise HTTPException(status_code=401, detail="Unauthorized")
    if not HAS_OLLAMA:
        raise HTTPException(status_code=500, detail="ollama python client not installed")

    model_id = req.model
    if not model_id:
        raise HTTPException(status_code=400, detail="Missing 'model' field for embeddings")

    if not req.input or not isinstance(req.input, list):
        raise HTTPException(status_code=400, detail="'input' must be a list of strings")

    try:
        vectors = await _ollama_embeddings(model=model_id, inputs=req.input)
    except Exception as e:
        logger.exception("Ollama embeddings failed: %s", e)
        raise HTTPException(status_code=503, detail=f"Ollama embeddings failed: {e}")

    data = []
    for vec in vectors:
        data.append({"embedding": vec, "object": "embedding"})

    return {"object": "list", "data": data, "model": model_id}
