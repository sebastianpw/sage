# pyapi/services/vidrembg_service.py
import os
from pathlib import Path

# --- CONFIG: Point rembg to local weights folder ---
CURRENT_DIR = Path(__file__).resolve().parent
REMBG_WEIGHTS_DIR = CURRENT_DIR.parent.parent / "rembg"
os.environ["U2NET_HOME"] = str(REMBG_WEIGHTS_DIR)

from fastapi import APIRouter, UploadFile, File, HTTPException, Form, BackgroundTasks
from fastapi.responses import JSONResponse, FileResponse
import shutil
import subprocess
import tempfile
import logging
import uuid
import time
import gc  # <--- NEW: For memory cleanup
from typing import Dict, Any

# AI Imports
from rembg import new_session, remove

# Chroma Key Imports
import cv2
import numpy as np

logger = logging.getLogger(__name__)

router = APIRouter(tags=["video-rembg"])

# Global Task Store
VIDEO_TASKS: Dict[str, Dict[str, Any]] = {}

# ------------------------------------------------------------------------------
# HELPER: CLEANUP
# ------------------------------------------------------------------------------
def cleanup_task_files(task_id: str):
    if task_id in VIDEO_TASKS:
        task = VIDEO_TASKS[task_id]
        temp_dir = task.get("temp_dir")
        if temp_dir and temp_dir.exists():
            try:
                shutil.rmtree(temp_dir, ignore_errors=True)
                logger.info(f"Cleaned up temp dir for task {task_id}")
            except Exception as e:
                logger.error(f"Failed to cleanup task {task_id}: {e}")
        del VIDEO_TASKS[task_id]
        
    # Force generic collection on cleanup too
    gc.collect()

# ------------------------------------------------------------------------------
# LOGIC: CHROMA KEY (LAB Color Space - Robust to Lighting)
# ------------------------------------------------------------------------------
def apply_chroma_key(image_path: Path, output_path: Path, key_color: str, threshold: float, softness: float):
    """
    Applies Chroma Keying using LAB color space.
    """
    img = cv2.imread(str(image_path))
    if img is None: return

    # Hex to LAB
    key_color = key_color.lstrip('#')
    rgb = tuple(int(key_color[i:i+2], 16) for i in (0, 2, 4))
    target_bgr_pixel = np.array([[[rgb[2], rgb[1], rgb[0]]]], dtype=np.uint8)
    target_lab = cv2.cvtColor(target_bgr_pixel, cv2.COLOR_BGR2LAB)[0, 0]
    target_a, target_b = int(target_lab[1]), int(target_lab[2])

    # Image to LAB
    lab_img = cv2.cvtColor(img, cv2.COLOR_BGR2LAB).astype(np.int32)
    a_channel = lab_img[:, :, 1]
    b_channel = lab_img[:, :, 2]

    # Distance
    dist_sq = (a_channel - target_a)**2 + (b_channel - target_b)**2
    dist = np.sqrt(dist_sq)

    # Mask
    scale_factor = 300.0 
    t_val = threshold * scale_factor
    s_val = softness * scale_factor
    mask = (dist - t_val) / (s_val + 1e-5)
    mask = np.clip(mask, 0.0, 1.0)

    # Denoise
    if softness < 0.2:
        kernel = np.ones((3,3), np.uint8)
        mask = cv2.morphologyEx(mask, cv2.MORPH_OPEN, kernel)

    # Despill
    b, g, r = cv2.split(img)
    bg_limit = (b.astype(np.float32) + r.astype(np.float32)) / 2.0
    g_new = np.minimum(g.astype(np.float32), bg_limit * 1.1).astype(np.uint8)
    img_despilled = cv2.merge([b, g_new, r])

    # Save
    alpha = (mask * 255).astype(np.uint8)
    result = np.dstack((img_despilled, alpha))
    cv2.imwrite(str(output_path), result)


def process_chromakey_task(task_id: str, input_path: Path, temp_dir: Path, key_color: str, threshold: float, softness: float, fps: float = None):
    try:
        VIDEO_TASKS[task_id]["status"] = "PROCESSING"
        if fps is None:
            try:
                cmd = ["ffprobe", "-v", "error", "-select_streams", "v:0", "-show_entries", "stream=r_frame_rate", "-of", "default=noprint_wrappers=1:nokey=1", str(input_path)]
                fps_str = subprocess.check_output(cmd).decode().strip()
                fps = float(fps_str) if '/' not in fps_str else (lambda n,d: n/d)(*map(int, fps_str.split('/')))
            except:
                fps = 30.0

        frames_in_dir = temp_dir / "frames_in"
        frames_in_dir.mkdir(exist_ok=True)
        subprocess.run(["ffmpeg", "-i", str(input_path), str(frames_in_dir / "frame_%05d.png")], check=True, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        if input_path.exists(): os.remove(input_path)

        frames_out_dir = temp_dir / "frames_out"
        frames_out_dir.mkdir(exist_ok=True)
        frame_files = sorted(list(frames_in_dir.glob("*.png")))

        for frame_file in frame_files:
            apply_chroma_key(frame_file, frames_out_dir / frame_file.name, key_color, threshold, softness)

        output_video_path = temp_dir / "output.webm"
        subprocess.run([
            "ffmpeg", "-framerate", str(fps), "-i", str(frames_out_dir / "frame_%05d.png"),
            "-c:v", "libvpx-vp9", "-pix_fmt", "yuva420p", "-b:v", "0", "-crf", "30", "-cpu-used", "2", "-y",
            str(output_video_path)
        ], check=True, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)

        if output_video_path.exists():
            VIDEO_TASKS[task_id]["status"] = "COMPLETED"
            VIDEO_TASKS[task_id]["output_path"] = output_video_path
        else:
            raise Exception("Output video not found.")

    except Exception as e:
        logger.error(f"Task {task_id} failed: {e}")
        VIDEO_TASKS[task_id]["status"] = "FAILED"
        VIDEO_TASKS[task_id]["error"] = str(e)


# ------------------------------------------------------------------------------
# LOGIC: AI (BiRefNet/U2Net) - MEMORY SAFE VERSION
# ------------------------------------------------------------------------------
def process_video_task(task_id: str, input_path: Path, temp_dir: Path, model: str, fps: float = None):
    """
    AI video processing with aggressive memory management to prevent OOM errors.
    """
    session = None  # Placeholder
    try:
        VIDEO_TASKS[task_id]["status"] = "PROCESSING"
        logger.info(f"Task {task_id}: Processing video with model {model}...")

        # 1. Probe FPS
        if fps is None:
            try:
                cmd = ["ffprobe", "-v", "error", "-select_streams", "v:0", "-show_entries", "stream=r_frame_rate", "-of", "default=noprint_wrappers=1:nokey=1", str(input_path)]
                fps_str = subprocess.check_output(cmd).decode().strip()
                fps = float(fps_str) if '/' not in fps_str else (lambda n,d: n/d)(*map(int, fps_str.split('/')))
            except:
                fps = 30.0

        # 2. Extract Frames
        frames_in_dir = temp_dir / "frames_in"
        frames_in_dir.mkdir(exist_ok=True)
        subprocess.run(["ffmpeg", "-i", str(input_path), str(frames_in_dir / "frame_%05d.png")], check=True, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        
        # Cleanup input immediately to save disk
        if input_path.exists(): os.remove(input_path)

        frames_out_dir = temp_dir / "frames_out"
        frames_out_dir.mkdir(exist_ok=True)
        
        # 3. Load Model (New Session)
        logger.info(f"Task {task_id}: Loading AI Session...")
        session = new_session(model_name=model)
        
        frame_files = sorted(list(frames_in_dir.glob("*.png")))
        total_frames = len(frame_files)
        
        # 4. Process Frames
        logger.info(f"Task {task_id}: Inferencing {total_frames} frames...")
        for i, frame_file in enumerate(frame_files):
            with open(frame_file, "rb") as f:
                img_data = f.read()
            
            output_data = remove(img_data, session=session)
            
            with open(frames_out_dir / frame_file.name, "wb") as f:
                f.write(output_data)

            # Optional: Clear frame data from memory explicitly
            del img_data
            del output_data

        # 5. UNLOAD MODEL IMMEDIATELY (Crucial for GPU)
        logger.info(f"Task {task_id}: Unloading AI Session...")
        del session
        session = None
        gc.collect()

        # 6. Stitch Video
        logger.info(f"Task {task_id}: Stitching video...")
        output_video_path = temp_dir / "output.webm"
        subprocess.run([
            "ffmpeg", "-framerate", str(fps), "-i", str(frames_out_dir / "frame_%05d.png"),
            "-c:v", "libvpx-vp9", "-pix_fmt", "yuva420p", "-b:v", "0", "-crf", "30", "-cpu-used", "2", "-y",
            str(output_video_path)
        ], check=True, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)

        if output_video_path.exists():
            VIDEO_TASKS[task_id]["status"] = "COMPLETED"
            VIDEO_TASKS[task_id]["output_path"] = output_video_path
        else:
            raise Exception("Output video not found.")

    except Exception as e:
        logger.error(f"Task {task_id} failed: {e}")
        VIDEO_TASKS[task_id]["status"] = "FAILED"
        VIDEO_TASKS[task_id]["error"] = str(e)
    
    finally:
        # Final Safety Cleanup
        if session is not None:
            del session
        gc.collect()


# ------------------------------------------------------------------------------
# ENDPOINTS
# ------------------------------------------------------------------------------

@router.post("/video/rembg-async")
async def video_remove_background_async(
    background_tasks: BackgroundTasks,
    file: UploadFile = File(...),
    model: str = Form("u2net"),
    fps: float = Form(None)
):
    task_id = str(uuid.uuid4())
    temp_dir = Path(tempfile.mkdtemp(prefix=f"sage_vid_{task_id}_"))
    input_path = temp_dir / "input_video"
    try:
        with open(input_path, "wb") as buffer:
            shutil.copyfileobj(file.file, buffer)
    except Exception as e:
        shutil.rmtree(temp_dir)
        raise HTTPException(status_code=500, detail=f"Upload failed: {e}")

    VIDEO_TASKS[task_id] = {
        "status": "PENDING",
        "created_at": time.time(),
        "temp_dir": temp_dir,
        "output_path": None
    }
    background_tasks.add_task(process_video_task, task_id, input_path, temp_dir, model, fps)
    return {"task_id": task_id, "status": "PENDING"}


@router.post("/video/chromakey-async")
async def video_chromakey_async(
    background_tasks: BackgroundTasks,
    file: UploadFile = File(...),
    color: str = Form("#00FB00"),
    threshold: float = Form(0.15),
    softness: float = Form(0.05),
    fps: float = Form(None)
):
    task_id = str(uuid.uuid4())
    temp_dir = Path(tempfile.mkdtemp(prefix=f"sage_chroma_{task_id}_"))
    input_path = temp_dir / "input_video"
    try:
        with open(input_path, "wb") as buffer:
            shutil.copyfileobj(file.file, buffer)
    except Exception as e:
        shutil.rmtree(temp_dir)
        raise HTTPException(status_code=500, detail=f"Upload failed: {e}")

    VIDEO_TASKS[task_id] = {
        "status": "PENDING",
        "created_at": time.time(),
        "temp_dir": temp_dir,
        "output_path": None
    }
    background_tasks.add_task(process_chromakey_task, task_id, input_path, temp_dir, color, threshold, softness, fps)
    return {"task_id": task_id, "status": "PENDING"}


@router.get("/video/status/{task_id}")
async def get_video_task_status(task_id: str, background_tasks: BackgroundTasks):
    task = VIDEO_TASKS.get(task_id)
    if not task:
        raise HTTPException(status_code=404, detail="Task not found")
    status = task["status"]

    if status == "COMPLETED":
        output_path = task.get("output_path")
        if output_path and output_path.exists():
            background_tasks.add_task(cleanup_task_files, task_id)
            return FileResponse(
                path=output_path, 
                media_type="video/webm", 
                filename="rembg_output.webm"
            )
        else:
            cleanup_task_files(task_id)
            raise HTTPException(status_code=500, detail="Output file missing")
    elif status == "FAILED":
        error = task.get("error", "Unknown error")
        cleanup_task_files(task_id)
        raise HTTPException(status_code=500, detail=f"Processing failed: {error}")
    else:
        return JSONResponse({"task_id": task_id, "status": status})
