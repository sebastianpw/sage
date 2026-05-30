import os
import subprocess
import uuid
import logging
import shutil
import re
import time
from pathlib import Path
from typing import List, Dict, Any
from pydantic import BaseModel
from fastapi import APIRouter, HTTPException, BackgroundTasks
from fastapi.responses import Response, JSONResponse

# Setup logging
logger = logging.getLogger(__name__)

router = APIRouter(tags=["voicepool"])

# -----------------------------------------------------------------------------
# Configuration (SIBLING DIRECTORY LOGIC)
# -----------------------------------------------------------------------------
# file: .../pyapi/services/voicepool_service.py
# parents[0]: services
# parents[1]: pyapi
# parents[2]: The root container (where models is located)
BASE_ROOT = Path(__file__).resolve().parents[2]
MODELS_DIR = BASE_ROOT / "models"

# -----------------------------------------------------------------------------
# Global State (Task Queue)
# -----------------------------------------------------------------------------
# Structure: { "task_id": { "status": "PENDING"|"PROCESSING"|"COMPLETED"|"FAILED", "result": bytes, "error": str } }
TASKS: Dict[str, Dict[str, Any]] = {}

# -----------------------------------------------------------------------------
# Models
# -----------------------------------------------------------------------------
class VoiceModelInfo(BaseModel):
    id: str
    name: str
    language: str
    quality: str
    path: str

class VoiceListResponse(BaseModel):
    count: int
    models: List[VoiceModelInfo]

class SynthesizeRequest(BaseModel):
    text: str
    model: str = "en_US-amy-medium"

# -----------------------------------------------------------------------------
# Helper Functions
# -----------------------------------------------------------------------------
def find_model_path(model_name: str) -> Path:
    if not MODELS_DIR.exists():
        raise FileNotFoundError(f"Models directory not found at: {MODELS_DIR}")

    # Remove non-alphanumeric for looser matching
    query_clean = model_name.lower().replace("_", "").replace("-", "")
    candidates = []
    
    # rglob ensures we find files even in nested structures (en/en_US/x.onnx)
    for f in MODELS_DIR.rglob("*.onnx"):
        f_name_clean = f.name.lower().replace("_", "").replace("-", "")
        if f_name_clean.startswith(query_clean):
            candidates.append(f)

    if not candidates:
        raise FileNotFoundError(f"Model '{model_name}' not found.")
    
    # Sort by length to prefer exact matches (e.g. 'amy' vs 'amy-low')
    candidates.sort(key=lambda x: len(str(x)))
    return candidates[0]

def sanitize_for_tts(text: str) -> str:
    """
    Cleans text for Piper, ensuring newlines are real control characters.
    """
    if not text:
        return ""

    text = text.replace("\\", "")
    text = text.replace("=", " equals ")
    text = text.replace(":#.", ".\n")
    text = text.replace("#.", ".\n")
    text = text.replace("#", " ")

    def insert_newline(match):
        return match.group(1) + "\n"

    # Insert newline after punctuation
    text = re.sub(r'([.?!])\s+', insert_newline, text)
    text = re.sub(r'\n+', '\n', text)
    return text.strip()

def process_synthesis_task(task_id: str, text: str, model_name: str):
    """
    Background worker. Runs piper subprocess.
    """
    temp_wav = Path(f"/tmp/{task_id}.wav")
    
    try:
        TASKS[task_id]["status"] = "PROCESSING"
        clean_text = sanitize_for_tts(text)

        # 1. Locate Model
        try:
            onnx_path = find_model_path(model_name)
        except FileNotFoundError as e:
            raise Exception(str(e))
            
        # 2. Locate Piper Binary
        piper_bin = shutil.which("piper")
        if not piper_bin:
            # Fallback for manual installs if not in PATH
            possible_bins = [
                BASE_ROOT / "piper_bin" / "piper",
                Path("/kaggle/working/piper_bin/piper") # fallback specific to kaggle
            ]
            for p in possible_bins:
                if p.exists():
                    piper_bin = str(p)
                    break
        
        if not piper_bin:
            raise Exception("Piper binary not found in PATH or standard locations.")

        cmd = [piper_bin, "--model", str(onnx_path), "--output_file", str(temp_wav)]
        
        logger.info(f"Task {task_id}: Synthesizing {len(clean_text)} chars with {model_name}...")

        # 3. Run Piper
        process = subprocess.Popen(
            cmd,
            stdin=subprocess.PIPE,
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
            text=True
        )
        
        _, stderr = process.communicate(input=clean_text)

        if process.returncode != 0:
            logger.error(f"Piper failed: {stderr}")
            if process.returncode == -9:
                raise Exception("TTS Server Out Of Memory.")
            raise Exception(f"TTS Generation failed: {stderr}")

        if not temp_wav.exists() or temp_wav.stat().st_size == 0:
            raise Exception("TTS Engine produced empty file.")

        # 4. Success - Load to memory
        with open(temp_wav, "rb") as f:
            audio_data = f.read()

        TASKS[task_id]["result"] = audio_data
        TASKS[task_id]["status"] = "COMPLETED"
        logger.info(f"Task {task_id}: Completed.")

    except Exception as e:
        logger.exception(f"Task {task_id} failed")
        TASKS[task_id]["status"] = "FAILED"
        TASKS[task_id]["error"] = str(e)
    finally:
        if temp_wav.exists():
            try:
                temp_wav.unlink()
            except:
                pass

# -----------------------------------------------------------------------------
# Endpoints
# -----------------------------------------------------------------------------

@router.get("/models", response_model=VoiceListResponse)
async def list_models():
    if not MODELS_DIR.exists():
        return {"count": 0, "models": []}
    
    results = []
    for model_path in MODELS_DIR.rglob("*.onnx"):
        filename = model_path.stem
        parts = filename.split('-')
        
        # Naive parsing of standard piper filenames
        if len(parts) >= 3:
            lang_code, voice_name, quality = parts[0], parts[1], parts[2]
        else:
            lang_code = "unknown"
            voice_name = filename
            quality = "unknown"
            # Try to recover lang from filename like en_US-amy-medium
            if "_" in filename:
                try:
                    lang_code = filename.split("_")[0] + "_" + filename.split("_")[1].split("-")[0]
                except:
                    pass

        results.append({
            "id": filename,
            "name": voice_name.title(),
            "language": lang_code,
            "quality": quality,
            "path": str(model_path.relative_to(MODELS_DIR))
        })

    results.sort(key=lambda x: (x["language"], x["name"]))
    return {"count": len(results), "models": results}

@router.post("/synthesize")
async def synthesize_async(
    request: SynthesizeRequest, 
    background_tasks: BackgroundTasks
):
    """
    Starts synthesis and returns a task_id immediately.
    """
    task_id = str(uuid.uuid4())
    TASKS[task_id] = {
        "status": "PENDING",
        "created_at": time.time()
    }

    background_tasks.add_task(process_synthesis_task, task_id, request.text, request.model)

    return JSONResponse({"task_id": task_id, "status": "PENDING"})

@router.get("/status/{task_id}")
async def get_task_status(task_id: str):
    """
    Polls status. Returns Audio if COMPLETED.
    """
    task = TASKS.get(task_id)
    if not task:
        raise HTTPException(status_code=404, detail="Task not found")

    status = task["status"]

    if status == "COMPLETED":
        audio_bytes = task.get("result")
        return Response(content=audio_bytes, media_type="audio/wav")

    elif status == "FAILED":
        error_msg = task.get("error", "Unknown error")
        del TASKS[task_id]
        raise HTTPException(status_code=500, detail=f"Task failed: {error_msg}")

    else:
        return JSONResponse({"task_id": task_id, "status": status})
