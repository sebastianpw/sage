import os
# CRITICAL: Force headless backend to prevent Kaggle environment crash
os.environ["MPLBACKEND"] = "Agg"

import uuid
import time
import io
import traceback
import logging
import torch
import glob
import numpy as np
from typing import Dict, Any

# Audio processing
import librosa
import soundfile as sf
from pydub import AudioSegment

from fastapi import APIRouter, UploadFile, File, Form, HTTPException, BackgroundTasks
from fastapi.responses import Response, JSONResponse

# The SVC Inference Core
from so_vits_svc_fork.inference.core import Svc

logger = logging.getLogger(__name__)

router = APIRouter(tags=["voice-conversion"])

# --- CONFIG ---
MODELS_DIR = "/kaggle/working/svc_models"
TASKS: Dict[str, Dict[str, Any]] = {}

# --- Cache Loaded Models ---
_LOADED_SVC = None
_CURRENT_MODEL_NAME = None

def get_svc_instance(model_name: str):
    """Loads the SVC model if not already loaded"""
    global _LOADED_SVC, _CURRENT_MODEL_NAME

    if _LOADED_SVC is not None and _CURRENT_MODEL_NAME == model_name:
        return _LOADED_SVC

    logger.info(f"Loading SVC Model: {model_name}")
    
    model_path = os.path.join(MODELS_DIR, model_name)
    if not os.path.exists(model_path):
        raise Exception(f"Model folder not found: {model_path}")

    # Find G_*.pth and config.json
    g_files = glob.glob(os.path.join(model_path, "G_*.pth"))
    config_files = glob.glob(os.path.join(model_path, "*.json"))

    if not g_files: raise Exception("No G_*.pth file found")
    if not config_files: raise Exception("No config.json found")

    g_files.sort()
    generator_path = g_files[-1] 
    config_path = config_files[0]

    device = "cuda" if torch.cuda.is_available() else "cpu"
    
    svc_model = Svc(
        net_g_path=generator_path,
        config_path=config_path,
        device=device,
        cluster_model_path=None
    )
    
    _LOADED_SVC = svc_model
    _CURRENT_MODEL_NAME = model_name
    return svc_model


def process_svc_task(task_id, audio_bytes, model_name, pitch_shift):
    infile = f"/tmp/{task_id}_in.mp3"
    outfile = f"/tmp/{task_id}_out.wav"
    
    try:
        TASKS[task_id]["status"] = "PROCESSING"
        start_time = time.time()

        # 1. Save Input
        with open(infile, "wb") as f:
            f.write(audio_bytes)

        # 2. Load Model
        svc = get_svc_instance(model_name)

        # 3. Load Audio using Librosa
        # Uses 'target_sample' (integer Hz)
        logger.info(f"Loading audio {infile} at {svc.target_sample}Hz...")
        raw_audio, _ = librosa.load(infile, sr=svc.target_sample)

        # 4. Inference
        logger.info(f"Inferencing (Pitch: {pitch_shift})...")
        
        # 'speaker' arg, not speaker_id
        audio_out = svc.infer(
            speaker=0, 
            transpose=pitch_shift,
            audio=raw_audio,
            auto_predict_f0=True, 
            cluster_infer_ratio=0,
            noise_scale=0.4
        )
        
        # 5. Output Handling (Tuple Unpacking & GPU Detach)
        # Unwrap tuple if present
        if isinstance(audio_out, tuple):
            audio_out = audio_out[0]

        # Move from GPU to CPU safely
        if isinstance(audio_out, torch.Tensor):
            audio_out = audio_out.detach().cpu().float().numpy()
        elif hasattr(audio_out, "cpu"):
            audio_out = audio_out.cpu().numpy()
            
        # Ensure flat shape for soundfile
        if isinstance(audio_out, np.ndarray) and len(audio_out.shape) > 1:
            audio_out = audio_out.flatten()
        
        # 6. Save Raw Output
        sf.write(outfile, audio_out, svc.target_sample)

        # 7. Convert to MP3 (320k)
        seg = AudioSegment.from_wav(outfile)
        buf = io.BytesIO()
        seg.export(buf, format="mp3", bitrate="320k")

        TASKS[task_id]["result"] = buf.getvalue()
        TASKS[task_id]["status"] = "COMPLETED"
        
        dur = time.time() - start_time
        logger.info(f"Task {task_id} done in {dur:.2f}s")

    except Exception as e:
        logger.error(f"Task {task_id} failed: {e}")
        traceback.print_exc()
        TASKS[task_id]["status"] = "FAILED"
        TASKS[task_id]["error"] = str(e)
    finally:
        if os.path.exists(infile): os.remove(infile)
        if os.path.exists(outfile): os.remove(outfile)


@router.post("/convert-async")
async def convert_voice(
    background_tasks: BackgroundTasks,
    file: UploadFile = File(...),
    model_name: str = Form(...),
    pitch: int = Form(0)
):
    task_id = str(uuid.uuid4())
    content = await file.read()
    
    TASKS[task_id] = {"status": "PENDING"}
    background_tasks.add_task(process_svc_task, task_id, content, model_name, pitch)
    
    return {"task_id": task_id, "status": "PENDING"}

@router.get("/status/{task_id}")
async def get_status(task_id: str):
    task = TASKS.get(task_id)
    if not task: raise HTTPException(404)
    
    if task["status"] == "COMPLETED":
        return Response(content=task["result"], media_type="audio/mpeg")
    elif task["status"] == "FAILED":
        raise HTTPException(500, detail=task.get("error"))
    
    return JSONResponse({"task_id": task_id, "status": task["status"]})
