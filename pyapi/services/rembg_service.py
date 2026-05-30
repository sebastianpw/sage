# pyapi/services/rembg_service.py
import os
from pathlib import Path

# --- CONFIG: Point rembg to local weights folder ---
# This must be done BEFORE importing rembg.new_session or invoking remove
# Structure: /var/www/sage/pyapi/services/rembg_service.py -> ... -> /var/www/sage/rembg
CURRENT_DIR = Path(__file__).resolve().parent
REMBG_WEIGHTS_DIR = CURRENT_DIR.parent.parent / "rembg"

# Set the environment variable rembg uses to find models
os.environ["U2NET_HOME"] = str(REMBG_WEIGHTS_DIR)

from fastapi import APIRouter, UploadFile, File, Form, HTTPException, BackgroundTasks
from fastapi.responses import Response, JSONResponse
from typing import Literal, Dict, Any
from PIL import Image
import io
import logging
import time
import uuid
import torch
import traceback
import numpy as np

# rembg imports
from rembg import remove, new_session

logger = logging.getLogger(__name__)

router = APIRouter(tags=["background-removal"])

# --- Global State for Async Tasks ---
TASKS: Dict[str, Dict[str, Any]] = {}

# --- Model Session Cache ---
_SESSIONS = {}

def get_session(model_name: str):
    """Retrieve or create a rembg ONNX session."""
    # rembg usually maps model names (like 'u2net') to filenames.
    # By setting U2NET_HOME, it will look for {model_name}.onnx in that folder.
    
    if model_name in _SESSIONS:
        return _SESSIONS[model_name]

    # Check providers (Termux/Proot is usually CPU, but we check anyway)
    try:
        import onnxruntime as ort
        providers_available = ort.get_available_providers()
    except Exception:
        providers_available = []

    # Prefer CUDA if available, else CPU
    if "CUDAExecutionProvider" in providers_available and torch.cuda.is_available():
        providers = ["CUDAExecutionProvider", "CPUExecutionProvider"]
    else:
        providers = ["CPUExecutionProvider"]

    logger.info(f"Loading rembg model: {model_name}; providers={providers}")
    
    try:
        # Create session
        _SESSIONS[model_name] = new_session(model_name=model_name, providers=providers)
    except Exception as e:
        logger.error(f"Failed to load model '{model_name}'. Check if file exists in {REMBG_WEIGHTS_DIR}")
        raise e

    return _SESSIONS[model_name]


# --- Background Worker Function (AI rembg) ---
def process_removal_task(task_id: str, image_bytes: bytes, model: str, quality: str, output_format: str):
    """
    Background worker that performs the heavy lifting.
    """
    try:
        TASKS[task_id]["status"] = "PROCESSING"
        start_time = time.time()

        # 1. Load Image
        input_image = Image.open(io.BytesIO(image_bytes)).convert("RGB")
        
        # 2. Get Session
        session = get_session(model)

        # 3. Configure Matting
        # BiRefNet creates very high quality masks natively.
        # However, 'alpha_matting' (the algorithm) can still be used for hair/fur edge refinement.
        alpha_matting = (quality == "film")
        
        matting_args = {}
        if alpha_matting:
            matting_args = {
                "alpha_matting": True,
                # Adjusted thresholds for better detailing
                "alpha_matting_foreground_threshold": 240,
                "alpha_matting_background_threshold": 10,
                "alpha_matting_erode_size": 10
            }

        # 4. Inference
        result = remove(
            input_image,
            session=session,
            only_mask=(output_format == "mask"),
            post_process_mask=True,
            **matting_args
        )

        # 5. Save to Buffer
        img_byte_arr = io.BytesIO()
        result.save(img_byte_arr, format="PNG")
        result_bytes = img_byte_arr.getvalue()

        # 6. Update Task State
        duration = (time.time() - start_time) * 1000
        TASKS[task_id]["status"] = "COMPLETED"
        TASKS[task_id]["result"] = result_bytes
        logger.info(f"Task {task_id} finished: {model}/{quality} in {duration:.2f}ms")

    except Exception as e:
        logger.error(f"Task {task_id} failed: {e}")
        traceback.print_exc()
        TASKS[task_id]["status"] = "FAILED"
        TASKS[task_id]["error"] = str(e)


# --- Background Worker Function (Chromakey for images) ---
def process_chromakey_image_task(task_id: str, image_bytes: bytes, key_color: str, threshold: float, softness: float):
    """
    Background worker: applies LAB-space chroma key to a single image frame.
    Produces a RGBA PNG with the keyed color replaced by transparency.
    Reuses the same LAB-space algorithm as vidrembg_service for consistency.
    """
    try:
        import cv2

        TASKS[task_id]["status"] = "PROCESSING"
        start_time = time.time()

        # Decode image bytes to OpenCV BGR array
        nparr = np.frombuffer(image_bytes, np.uint8)
        img = cv2.imdecode(nparr, cv2.IMREAD_COLOR)
        if img is None:
            raise ValueError("Could not decode image")

        # Hex to LAB target
        key_color_clean = key_color.lstrip('#')
        rgb = tuple(int(key_color_clean[i:i+2], 16) for i in (0, 2, 4))
        target_bgr_pixel = np.array([[[rgb[2], rgb[1], rgb[0]]]], dtype=np.uint8)
        target_lab = cv2.cvtColor(target_bgr_pixel, cv2.COLOR_BGR2LAB)[0, 0]
        target_a, target_b = int(target_lab[1]), int(target_lab[2])

        # Image to LAB
        lab_img = cv2.cvtColor(img, cv2.COLOR_BGR2LAB).astype(np.int32)
        a_channel = lab_img[:, :, 1]
        b_channel = lab_img[:, :, 2]

        # Distance in LAB a/b plane
        dist_sq = (a_channel - target_a) ** 2 + (b_channel - target_b) ** 2
        dist = np.sqrt(dist_sq.astype(np.float32))

        # Mask: 0 = fully transparent (keyed), 1 = fully opaque (keep)
        scale_factor = 300.0
        t_val = threshold * scale_factor
        s_val = softness * scale_factor
        mask = (dist - t_val) / (s_val + 1e-5)
        mask = np.clip(mask, 0.0, 1.0)

        # Morphological denoise for hard keys
        if softness < 0.2:
            kernel = np.ones((3, 3), np.uint8)
            mask_u8 = (mask * 255).astype(np.uint8)
            mask_u8 = cv2.morphologyEx(mask_u8, cv2.MORPH_OPEN, kernel)
            mask = mask_u8.astype(np.float32) / 255.0

        # Despill: reduce green fringe on kept edges
        b_ch, g_ch, r_ch = cv2.split(img)
        bg_limit = (b_ch.astype(np.float32) + r_ch.astype(np.float32)) / 2.0
        g_new = np.minimum(g_ch.astype(np.float32), bg_limit * 1.1).astype(np.uint8)
        img_despilled = cv2.merge([b_ch, g_new, r_ch])

        # Build RGBA output
        alpha = (mask * 255).astype(np.uint8)
        result_bgra = np.dstack((img_despilled, alpha))

        # Encode to PNG bytes
        success, encoded = cv2.imencode('.png', result_bgra)
        if not success:
            raise ValueError("Failed to encode result PNG")

        result_bytes = encoded.tobytes()

        duration = (time.time() - start_time) * 1000
        TASKS[task_id]["status"] = "COMPLETED"
        TASKS[task_id]["result"] = result_bytes
        logger.info(f"Chromakey image task {task_id} finished in {duration:.2f}ms")

    except Exception as e:
        logger.error(f"Chromakey image task {task_id} failed: {e}")
        traceback.print_exc()
        TASKS[task_id]["status"] = "FAILED"
        TASKS[task_id]["error"] = str(e)


# --- Endpoints ---

@router.post("/remove-bg-async")
async def remove_background_async(
    background_tasks: BackgroundTasks,
    file: UploadFile = File(...),
    quality: Literal["fast", "film"] = Form("fast", description="Film enables alpha matting post-processing"),
    # Added birefnet options here
    model: Literal["u2net", "isnet-general-use", "birefnet-general", "birefnet-general-lite"] = Form("birefnet-general", description="Model backbone"),
    output_format: Literal["rgba", "mask"] = Form("rgba", alias="output", description="Output type")
):
    """
    Starts a background removal task using u2net, isnet, or birefnet.
    """
    task_id = str(uuid.uuid4())
    
    try:
        content = await file.read()
    except Exception as e:
        raise HTTPException(status_code=400, detail=f"Failed to read file: {e}")

    TASKS[task_id] = {
        "status": "PENDING",
        "created_at": time.time()
    }

    background_tasks.add_task(process_removal_task, task_id, content, model, quality, output_format)

    return {"task_id": task_id, "status": "PENDING"}


@router.post("/image/chromakey-async")
async def image_chromakey_async(
    background_tasks: BackgroundTasks,
    file: UploadFile = File(...),
    color: str = Form("#00FB00", description="Target chroma key color as hex, e.g. #00FF00"),
    threshold: float = Form(0.15, description="Key threshold (0.0–1.0); lower = tighter key"),
    softness: float = Form(0.05, description="Edge softness (0.0–1.0)")
):
    """
    Starts an async chroma key removal task for a single image/frame.
    Uses the same LAB-space algorithm as the video chromakey service.
    Returns task_id immediately; poll /status/{task_id} for the result PNG.
    """
    task_id = str(uuid.uuid4())

    try:
        content = await file.read()
    except Exception as e:
        raise HTTPException(status_code=400, detail=f"Failed to read file: {e}")

    TASKS[task_id] = {
        "status": "PENDING",
        "created_at": time.time()
    }

    background_tasks.add_task(
        process_chromakey_image_task,
        task_id, content, color, threshold, softness
    )

    return {"task_id": task_id, "status": "PENDING"}


@router.get("/status/{task_id}")
async def get_task_status(task_id: str):
    """
    Polls task status.
    """
    task = TASKS.get(task_id)
    if not task:
        raise HTTPException(status_code=404, detail="Task not found")

    status = task["status"]

    if status == "COMPLETED":
        image_bytes = task.get("result")
        # Cleanup logic could go here
        return Response(content=image_bytes, media_type="image/png")

    elif status == "FAILED":
        error_msg = task.get("error", "Unknown error")
        del TASKS[task_id]
        raise HTTPException(status_code=500, detail=f"Task failed: {error_msg}")

    else:
        return JSONResponse({"task_id": task_id, "status": status})


@router.get("/env-info")
def env_info():
    info = {
        "rembg_weights_dir": str(REMBG_WEIGHTS_DIR), 
        "weights_exist": REMBG_WEIGHTS_DIR.exists(),
        "models_found": [f.name for f in REMBG_WEIGHTS_DIR.glob("*.onnx")] if REMBG_WEIGHTS_DIR.exists() else []
    }
    return info
