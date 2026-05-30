import os
import io
import uuid
import time
import torch
import logging
import soundfile as sf
from pathlib import Path
from typing import Dict, Any, Optional
from fastapi import APIRouter, HTTPException, BackgroundTasks
from fastapi.responses import Response, JSONResponse
from pydantic import BaseModel

# Try importing Qwen
try:
    from qwen_tts import Qwen3TTSModel
    QWEN_AVAILABLE = True
except ImportError:
    QWEN_AVAILABLE = False

logger = logging.getLogger(__name__)

router = APIRouter(tags=["qwen3tts"])

# -----------------------------------------------------------------------------
# Configuration
# -----------------------------------------------------------------------------
# On Kaggle: /kaggle/working/pyapi/services/qwen3tts_service.py
# parents[2] resolves to /kaggle/working
BASE_ROOT = Path(__file__).resolve().parents[2]
MODEL_DIR = BASE_ROOT / "qwen3tts" 
VOICES_DIR = BASE_ROOT / "qwen3tts_voices"

# Global State
MODEL_INSTANCE = None
TASKS: Dict[str, Dict[str, Any]] = {}

# -----------------------------------------------------------------------------
# Request Models
# -----------------------------------------------------------------------------
class QwenCloneRequest(BaseModel):
    text: str
    ref_audio: str          # Filename in qwen3tts_voices
    ref_text: Optional[str] = None
    language: str = "auto" 

# -----------------------------------------------------------------------------
# Helper Functions
# -----------------------------------------------------------------------------
def load_model():
    """
    Singleton model loader.
    Optimized for Kaggle GPU (T4) stability.
    """
    global MODEL_INSTANCE
    if MODEL_INSTANCE is not None:
        return MODEL_INSTANCE

    if not QWEN_AVAILABLE:
        raise HTTPException(status_code=500, detail="qwen-tts package not installed.")

    if not MODEL_DIR.exists():
        raise HTTPException(status_code=500, detail=f"Model directory not found: {MODEL_DIR}")

    logger.info(f"Loading Qwen3-TTS Model from {MODEL_DIR}...")
    
    try:
        # GPU CONFIGURATION FOR T4 STABILITY
        # 1. torch_dtype=torch.float32: Prevents numerical overflow/NaNs/Crashes on T4
        # 2. attn_implementation="eager": Prevents FlashAttn driver errors on T4
        MODEL_INSTANCE = Qwen3TTSModel.from_pretrained(
            str(MODEL_DIR),
            device_map="auto",
            torch_dtype=torch.float32, 
            attn_implementation="eager"
        )
        logger.info("Qwen3-TTS Model loaded successfully (GPU/Float32/Eager).")
    except Exception as e:
        logger.error(f"Failed to load Qwen3-TTS: {e}")
        MODEL_INSTANCE = None 
        raise HTTPException(status_code=500, detail=f"Model load failed: {str(e)}")
    
    return MODEL_INSTANCE

def process_clone_task(task_id: str, text: str, ref_audio_name: str, ref_text: Optional[str], language: str):
    try:
        TASKS[task_id]["status"] = "PROCESSING"
        
        # 1. Resolve Audio
        ref_audio_path = VOICES_DIR / ref_audio_name
        if not ref_audio_path.exists():
            raise FileNotFoundError(f"Ref audio '{ref_audio_name}' not found in {VOICES_DIR}")

        # 2. Load Model
        model = load_model()

        # 3. Mode Selection
        use_x_vector = False
        if not ref_text or not ref_text.strip():
            use_x_vector = True
            logger.info(f"Task {task_id}: X-Vector Mode (No text provided)")
        else:
            logger.info(f"Task {task_id}: ICL Mode (High Quality)")

        # 4. Inference
        inference_kwargs = {}
        if use_x_vector:
             inference_kwargs["x_vector_only_mode"] = True

        output, sr = model.generate_voice_clone(
            text=text,
            language=language,
            ref_audio=str(ref_audio_path),
            ref_text=ref_text,
            **inference_kwargs
        )
        
        audio_data = output[0]

        # 5. Save to buffer
        buffer = io.BytesIO()
        sf.write(buffer, audio_data, sr, format='WAV')
        buffer.seek(0)
        wav_bytes = buffer.read()

        TASKS[task_id]["result"] = wav_bytes
        TASKS[task_id]["status"] = "COMPLETED"
        logger.info(f"Task {task_id}: Success.")

    except Exception as e:
        logger.exception(f"Task {task_id} failed.")
        TASKS[task_id]["status"] = "FAILED"
        TASKS[task_id]["error"] = str(e)

# -----------------------------------------------------------------------------
# Endpoints
# -----------------------------------------------------------------------------
@router.get("/qwen3tts/voices")
def list_voices():
    if not VOICES_DIR.exists(): return []
    return [f.name for f in VOICES_DIR.glob("*.wav")]

@router.post("/qwen3tts/clone")
async def clone_async(req: QwenCloneRequest, background_tasks: BackgroundTasks):
    # Fail fast if file missing
    if not (VOICES_DIR / req.ref_audio).exists():
        raise HTTPException(status_code=404, detail=f"File '{req.ref_audio}' not found.")

    task_id = str(uuid.uuid4())
    TASKS[task_id] = {"status": "PENDING", "created_at": time.time()}

    background_tasks.add_task(
        process_clone_task, 
        task_id, req.text, req.ref_audio, req.ref_text, req.language
    )
    return JSONResponse({"task_id": task_id, "status": "PENDING"})

@router.get("/qwen3tts/status/{task_id}")
async def get_task_status(task_id: str):
    task = TASKS.get(task_id)
    if not task: raise HTTPException(status_code=404, detail="Task not found")

    status = task["status"]
    if status == "COMPLETED":
        return Response(content=task["result"], media_type="audio/wav")
    elif status == "FAILED":
        err = task.get("error", "Unknown")
        del TASKS[task_id]
        return JSONResponse({"task_id": task_id, "status": "FAILED", "error": err}, status_code=500)
    else:
        return JSONResponse({"task_id": task_id, "status": status})
