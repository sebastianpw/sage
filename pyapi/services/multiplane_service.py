"""
Multiplane Service - Layer-based Parallax Compositor
Async version with robust system FFmpeg fallback for Termux/Linux.
Refactored to support UI Arrangements (Position, Scale, Rotation).
"""
import logging
import json
import uuid
import shutil
import subprocess
from pathlib import Path
from typing import List, Dict, Optional
from fastapi import APIRouter, UploadFile, File, Form, HTTPException, BackgroundTasks
from fastapi.responses import FileResponse, JSONResponse
from PIL import Image, ImageOps

logger = logging.getLogger(__name__)

router = APIRouter(tags=["multiplane"])

# -----------------------------------------------------------------------------
# Configuration & Paths
# -----------------------------------------------------------------------------
PROJECT_ROOT = Path(__file__).resolve().parents[1]
ANIMATION_DIR = PROJECT_ROOT.parent / "services_data" / "animations"
TEMP_DIR = PROJECT_ROOT.parent / "services_data" / "temp"

ANIMATION_DIR.mkdir(parents=True, exist_ok=True)
TEMP_DIR.mkdir(parents=True, exist_ok=True)

TASKS: Dict[str, Dict] = {}

# -----------------------------------------------------------------------------
# HELPERS
# -----------------------------------------------------------------------------

def cleanup_task_files(task_temp_dir: Path):
    try:
        if task_temp_dir.exists():
            shutil.rmtree(task_temp_dir)
    except Exception as e:
        logger.error(f"Failed to cleanup temp dir {task_temp_dir}: {e}")

def save_video_subprocess(frames: List[Image.Image], fps: int, output_path: Path):
    if not frames:
        raise ValueError("No frames to save")
    
    orig_w, orig_h = frames[0].size
    target_w = orig_w if orig_w % 2 == 0 else orig_w - 1
    target_h = orig_h if orig_h % 2 == 0 else orig_h - 1
    
    cmd = [
        "ffmpeg", "-y", "-f", "rawvideo", "-vcodec", "rawvideo",
        "-s", f"{orig_w}x{orig_h}", "-pix_fmt", "rgb24", "-r", str(fps),
        "-i", "-", "-c:v", "libx264", "-pix_fmt", "yuv420p",
        "-preset", "medium", "-crf", "23",
        "-vf", f"crop={target_w}:{target_h}:0:0",
        str(output_path)
    ]
    
    try:
        process = subprocess.Popen(cmd, stdin=subprocess.PIPE, stderr=subprocess.PIPE)
        for img in frames:
            try:
                process.stdin.write(img.tobytes())
            except BrokenPipeError:
                break
        stdout, stderr = process.communicate()
        if process.returncode != 0:
            raise RuntimeError(f"FFmpeg command failed: {stderr.decode()[-200:]}")
            
    except Exception as e:
        logger.error(f"Subprocess FFmpeg exception: {e}")
        raise

# -----------------------------------------------------------------------------
# CORE LOGIC
# -----------------------------------------------------------------------------

def process_multiplane_task(
    task_id: str,
    layer_files: List[Dict], # List of {path: Path, filename: str}
    config_map: Dict,        # Keyed by filename: {x, y, scaleX, scaleY, rotation, speed, zIndex}
    frames: int, 
    fps: int,
    cam_move_x: int, 
    cam_move_y: int,
    zoom_start: float,
    zoom_end: float
):
    try:
        logger.info(f"Task {task_id}: Starting Multiplane Render with Arrangement")
        
        # 1. Prepare Layers
        # We need to sort them by zIndex provided in config_map, or default to 0
        loaded_layers = []
        
        for f_info in layer_files:
            fname = f_info['filename']
            fpath = f_info['path']
            
            # Get config or defaults
            # Default speed 0.5 if not found, Default Scale 1, etc.
            cfg = config_map.get(fname, {})
            
            # Load Image
            img = Image.open(fpath).convert("RGBA")
            img = ImageOps.exif_transpose(img)
            
            loaded_layers.append({
                "image": img,
                "x": float(cfg.get("x", 0)),
                "y": float(cfg.get("y", 0)),
                "scaleX": float(cfg.get("scaleX", 1.0)),
                "scaleY": float(cfg.get("scaleY", 1.0)),
                "rotation": float(cfg.get("rotation", 0)),
                "zIndex": int(cfg.get("zIndex", 0)),
                "speed": float(cfg.get("speed", 0.5)) # Parallax Speed
            })

        # Sort by Z-Index (ascending: background -> foreground)
        loaded_layers.sort(key=lambda x: x["zIndex"])
        
        # Define Canvas Size (Default to 1024x1024 as used in UI, or derive from Background?)
        # For consistency with the UI, we assume a 1024x1024 stage.
        CANVAS_W, CANVAS_H = 1024, 1024

        output_frames = []

        # 2. Render Loop
        for i in range(frames):
            t = i / (frames - 1) if frames > 1 else 0
            
            # Create transparent canvas
            canvas = Image.new("RGBA", (CANVAS_W, CANVAS_H), (0, 0, 0, 0)) # or white background?
            # canvas = Image.new("RGB", (CANVAS_W, CANVAS_H), (0, 0, 0)) # Black background

            # Calculate Global Camera Position (The camera moves RIGHT, so the world moves LEFT)
            cam_x = cam_move_x * t
            cam_y = cam_move_y * t
            current_zoom = zoom_start + (zoom_end - zoom_start) * t
            
            for layer in loaded_layers:
                img = layer["image"]
                
                # 1. Apply Local Transforms (Scale/Rotate from UI)
                # Resampling: LANCZOS is high quality
                if layer["scaleX"] != 1.0 or layer["scaleY"] != 1.0:
                    new_w = int(img.width * layer["scaleX"])
                    new_h = int(img.height * layer["scaleY"])
                    if new_w > 0 and new_h > 0:
                        img_trans = img.resize((new_w, new_h), Image.LANCZOS)
                    else:
                        continue
                else:
                    img_trans = img.copy()

                if layer["rotation"] != 0:
                    img_trans = img_trans.rotate(-layer["rotation"], expand=True, resample=Image.BICUBIC)

                # 2. Calculate Parallax Position
                # Base Position (from UI)
                base_x = layer["x"]
                base_y = layer["y"]
                
                # Parallax Offset
                # Objects move opposite to camera. 
                # Speed 0 (Background) = No movement relative to canvas? 
                # Actually in standard multiplane:
                # Speed 0 = Infinite depth (Moves with camera? No, usually static if camera pans).
                # Let's use standard logic: Pixel Shift = -(CameraMove * Speed)
                shift_x = -(cam_x * layer["speed"])
                shift_y = -(cam_y * layer["speed"])
                
                final_x = int(base_x + shift_x)
                final_y = int(base_y + shift_y)
                
                # Paste (handle transparency)
                canvas.paste(img_trans, (final_x, final_y), img_trans)

            # 3. Apply Global Camera Zoom (Center Zoom)
            if current_zoom != 1.0:
                # To zoom in, we scale up the canvas and crop the center
                zw = int(CANVAS_W * current_zoom)
                zh = int(CANVAS_H * current_zoom)
                canvas_zoomed = canvas.resize((zw, zh), Image.LANCZOS)
                
                left = (zw - CANVAS_W) // 2
                top = (zh - CANVAS_H) // 2
                canvas = canvas_zoomed.crop((left, top, left + CANVAS_W, top + CANVAS_H))

            # Ensure strict output size
            if canvas.size != (CANVAS_W, CANVAS_H):
                canvas = canvas.resize((CANVAS_W, CANVAS_H), Image.LANCZOS)

            # Convert to RGB for video
            output_frames.append(canvas.convert("RGB"))

        # 3. Save
        filename = f"multiplane_{task_id}.mp4"
        out_path = ANIMATION_DIR / filename
        save_video_subprocess(output_frames, fps, out_path)

        TASKS[task_id]["status"] = "completed"
        TASKS[task_id]["result_path"] = str(out_path)
        logger.info(f"Task {task_id}: Completed.")

    except Exception as e:
        logger.exception(f"Task {task_id}: Failed")
        TASKS[task_id]["status"] = "failed"
        TASKS[task_id]["error"] = str(e)
    finally:
        cleanup_task_files(TEMP_DIR / task_id)

# -----------------------------------------------------------------------------
# ENDPOINTS
# -----------------------------------------------------------------------------

@router.post("/compose-async")
async def compose_async(
    background_tasks: BackgroundTasks,
    files: List[UploadFile] = File(...),
    # layer_config: JSON mapping filename -> {x,y,scale..., speed}
    layer_config: str = Form(...), 
    frames: int = Form(60),
    fps: int = Form(30),
    move_x: int = Form(100),
    move_y: int = Form(0),
    zoom_start: float = Form(1.0),
    zoom_end: float = Form(1.05)
):
    task_id = str(uuid.uuid4())
    task_dir = TEMP_DIR / task_id
    task_dir.mkdir(parents=True, exist_ok=True)
    
    file_info_list = []
    
    try:
        # Save files and keep track of filenames
        for file in files:
            file_path = task_dir / file.filename
            with open(file_path, "wb") as buffer:
                shutil.copyfileobj(file.file, buffer)
            file_info_list.append({"path": file_path, "filename": file.filename})
            
        config_map = json.loads(layer_config)
        
        TASKS[task_id] = {"status": "processing"}
        
        background_tasks.add_task(
            process_multiplane_task,
            task_id,
            file_info_list,
            config_map,
            frames,
            fps,
            move_x,
            move_y,
            zoom_start,
            zoom_end
        )
        
        return {"status": "queued", "task_id": task_id}
        
    except Exception as e:
        cleanup_task_files(task_dir)
        raise HTTPException(status_code=500, detail=str(e))

@router.get("/status/{task_id}")
async def get_task_status(task_id: str):
    if task_id not in TASKS:
        raise HTTPException(status_code=404, detail="Task not found")
    
    task = TASKS[task_id]
    
    if task["status"] == "completed":
        path = Path(task["result_path"])
        if not path.exists():
             return JSONResponse(status_code=500, content={"status": "error", "detail": "Result file missing"})
        return FileResponse(path, media_type="video/mp4", filename=path.name)
    
    elif task["status"] == "failed":
        return JSONResponse(status_code=500, content={"status": "failed", "detail": task.get("error")})
        
    else:
        return {"status": "processing"}
