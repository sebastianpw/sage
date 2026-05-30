# pyapi/services/cnetaux_service.py
"""
ControlNet-Aux helper endpoints.
Termux-Optimized: 
1. Forces Single-Threaded execution for heavy AI (Stability).
2. Serializes requests (One at a time) to prevent RAM OOM (Stability).
3. Aggressive Garbage Collection after every run.
"""

from fastapi import APIRouter, UploadFile, File, Form, HTTPException
from fastapi.responses import StreamingResponse
from fastapi.concurrency import run_in_threadpool
from typing import Optional, Dict, Any
from io import BytesIO
from PIL import Image, ImageOps
import threading
import logging
import os
import contextlib
import gc
import asyncio

logger = logging.getLogger(__name__)

router = APIRouter(prefix="", tags=["cnetaux"])

_DETECTORS: Dict[str, Any] = {}
_DETECTORS_LOCK = threading.Lock()
# Global lock to ensure we NEVER run two heavy map generations at the same time
_INFERENCE_LOCK = asyncio.Lock()

@contextlib.contextmanager
def _android_safe_threading():
    """
    Temporarily force single-threaded mode.
    Crucial for preventing 'corrupted double-linked list' on Android.
    """
    old_env = {}
    vars_to_limit = [
        "OMP_NUM_THREADS", "MKL_NUM_THREADS", "OPENBLAS_NUM_THREADS", 
        "VECLIB_MAXIMUM_THREADS", "NUMEXPR_NUM_THREADS"
    ]
    
    # 1. Set ENV vars to 1
    for var in vars_to_limit:
        old_env[var] = os.environ.get(var)
        os.environ[var] = "1"

    try:
        # 2. Force PyTorch Runtime Limit
        try:
            import torch
            if torch.get_num_threads() > 1:
                torch.set_num_threads(1)
        except ImportError:
            pass
            
        yield
    finally:
        # 3. Restore ENV (So Piper/Others can use full speed later)
        for var, old_val in old_env.items():
            if old_val is None:
                del os.environ[var]
            else:
                os.environ[var] = old_val

def _pil_from_bytes(data: bytes) -> Image.Image:
    img = Image.open(BytesIO(data))
    img = ImageOps.exif_transpose(img)
    if img.mode != "RGB":
        img = img.convert("RGB")
    return img

def _image_response(pil_img: Image.Image) -> StreamingResponse:
    buf = BytesIO()
    pil_img.save(buf, format="PNG")
    buf.seek(0)
    return StreamingResponse(buf, media_type="image/png")

def _ensure_controlnet_aux_imported():
    try:
        with _android_safe_threading():
            from controlnet_aux import (
                OpenposeDetector,
                HEDdetector,
                MLSDdetector,
                MidasDetector,
                CannyDetector,
            )
        return OpenposeDetector, HEDdetector, MLSDdetector, MidasDetector, CannyDetector
    except Exception as e:
        logger.exception("controlnet_aux import failed")
        raise HTTPException(
            status_code=500,
            detail=f"controlnet_aux import failed: {e!s}"
        )

def _init_detector(name: str):
    if name in _DETECTORS:
        return _DETECTORS[name]

    OpenposeDetector, HEDdetector, MLSDdetector, MidasDetector, CannyDetector = _ensure_controlnet_aux_imported()

    logger.info("Initializing detector: %s", name)
    
    with _android_safe_threading():
        try:
            if name == "pose":
                det = OpenposeDetector.from_pretrained("lllyasviel/Annotators")
            elif name == "hed":
                det = HEDdetector.from_pretrained("lllyasviel/Annotators")
            elif name == "mlsd":
                det = MLSDdetector.from_pretrained("lllyasviel/Annotators")
            elif name == "midas":
                det = MidasDetector.from_pretrained("lllyasviel/Annotators")
            elif name == "canny":
                det = CannyDetector()
            else:
                raise ValueError(f"Unknown detector: {name}")
        except Exception as e:
            logger.exception("Failed to initialize detector %s: %s", name, e)
            raise HTTPException(status_code=500, detail=f"Failed to init {name}: {e!s}")

    _DETECTORS[name] = det
    return det

async def _run_detector(name: str, img: Image.Image, params: Dict[str, Any]) -> Image.Image:
    # Ensure model is loaded (thread-safe init)
    with _DETECTORS_LOCK:
        if name not in _DETECTORS:
            _init_detector(name)
        detector = _DETECTORS[name]

    def _call():
        # Apply safety lock during execution
        with _android_safe_threading():
            if name == "pose":
                return detector(img, hand_and_face=params.get("hand_and_face", True))
            elif name == "hed":
                return detector(img)
            elif name == "canny":
                kwargs = {}
                if params.get("low_threshold") is not None:
                    kwargs["low_threshold"] = int(params["low_threshold"])
                if params.get("high_threshold") is not None:
                    kwargs["high_threshold"] = int(params["high_threshold"])
                return detector(img, **kwargs)
            elif name == "midas":
                return detector(img)
            elif name == "mlsd":
                return detector(img)
            else:
                raise RuntimeError("Unsupported detector: " + name)

    # Run in threadpool
    result_img = await run_in_threadpool(_call)
    
    # Handle numpy conversion if needed
    if not isinstance(result_img, Image.Image):
        try:
            from PIL import Image as PILImage
            import numpy as np
            arr = np.asarray(result_img)
            result_img = PILImage.fromarray(arr)
        except Exception:
            pass
            
    return result_img

# -----------------------------
# Endpoints
# -----------------------------
@router.get("/capabilities")
def capabilities():
    installed = {}
    try:
        from controlnet_aux import CannyDetector
        installed["controlnet_aux"] = True
    except ImportError:
        installed["controlnet_aux"] = False

    cached = {k: True for k in _DETECTORS.keys()}
    return {"installed": installed, "cached_detectors": cached, "detectors_supported": ["pose", "hed", "canny", "midas", "mlsd"]}

@router.post("/map/{detector_name}")
async def map_generic(
    detector_name: str,
    file: UploadFile = File(...),
    resize_max: Optional[int] = Form(None),
    hand_and_face: Optional[bool] = Form(True),
    low_threshold: Optional[int] = Form(None),
    high_threshold: Optional[int] = Form(None),
):
    """
    Endpoint wrapped with a Global Lock to prevent RAM exhaustion and Threading crashes.
    """
    detector_name = detector_name.lower()
    
    # 1. READ & PROCESS IMAGE
    try:
        file_bytes = await file.read()
        img = _pil_from_bytes(file_bytes)
        
        # Optional resize to save RAM
        if resize_max is not None and resize_max > 0:
            w, h = img.size
            if max(w, h) > resize_max:
                scale = resize_max / float(max(w, h))
                img = img.resize((int(w * scale), int(h * scale)), Image.Resampling.LANCZOS)
    except Exception as e:
        raise HTTPException(status_code=400, detail=f"Image error: {e!s}")

    # 2. PREPARE PARAMS
    params = {}
    if detector_name == "pose":
        params["hand_and_face"] = bool(hand_and_face)
    if detector_name == "canny":
        if low_threshold is not None: params["low_threshold"] = low_threshold
        if high_threshold is not None: params["high_threshold"] = high_threshold

    # 3. EXECUTE WITH GLOBAL LOCK (Critical for Stability)
    async with _INFERENCE_LOCK:
        try:
            result = await _run_detector(detector_name, img, params)
            response = _image_response(result)
            return response
        except Exception as e:
            logger.exception("Detector failed")
            raise HTTPException(status_code=500, detail=str(e))
        finally:
            # 4. AGGRESSIVE CLEANUP
            # Explicitly free memory for the next request
            del img
            if 'result' in locals(): del result
            gc.collect()

# Convenience wrappers
@router.post("/map/pose")
async def map_pose(file: UploadFile = File(...), hand_and_face: Optional[bool] = Form(True), resize_max: Optional[int] = Form(None)):
    return await map_generic("pose", file=file, resize_max=resize_max, hand_and_face=hand_and_face)

@router.post("/map/hed")
async def map_hed(file: UploadFile = File(...), resize_max: Optional[int] = Form(None)):
    return await map_generic("hed", file=file, resize_max=resize_max)

@router.post("/map/canny")
async def map_canny(file: UploadFile = File(...), low_threshold: Optional[int] = Form(None), high_threshold: Optional[int] = Form(None), resize_max: Optional[int] = Form(None)):
    return await map_generic("canny", file=file, resize_max=resize_max, low_threshold=low_threshold, high_threshold=high_threshold)

@router.post("/map/midas")
async def map_midas(file: UploadFile = File(...), resize_max: Optional[int] = Form(None)):
    return await map_generic("midas", file=file, resize_max=resize_max)

@router.post("/map/mlsd")
async def map_mlsd(file: UploadFile = File(...), resize_max: Optional[int] = Form(None)):
    return await map_generic("mlsd", file=file, resize_max=resize_max)

@router.post("/admin/clear-cache")
def clear_cache():
    with _DETECTORS_LOCK:
        _DETECTORS.clear()
    gc.collect()
    return {"status": "ok"}
