"""
Liplab Service - Automated Lip Syncing with Transparency
Combines Feature Alignment (ORB) and Audio Analysis (Librosa).
Outputs WebM (VP9) to preserve transparency with small file size.
"""
import logging
import json
import uuid
import shutil
import subprocess
import os
import numpy as np
import cv2
import librosa
from pathlib import Path
from typing import List, Dict
from fastapi import APIRouter, UploadFile, File, Form, HTTPException, BackgroundTasks
from fastapi.responses import FileResponse, JSONResponse
from PIL import Image

logger = logging.getLogger(__name__)

router = APIRouter(tags=["liplab"])

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
# LOGIC
# -----------------------------------------------------------------------------

def load_image_cv2_alpha(path: str):
    # Load with Alpha channel (IMREAD_UNCHANGED)
    img = cv2.imread(path, cv2.IMREAD_UNCHANGED)
    if img is None:
        raise ValueError(f"Could not read image: {path}")
    
    # Ensure we have 4 channels (BGRA) if it was loaded as RGB
    if img.shape[2] == 3:
        img = cv2.cvtColor(img, cv2.COLOR_BGR2BGRA)
    return img

def align_images_orb(base_path: str, input_paths: List[str], temp_out_dir: Path) -> List[str]:
    """
    Aligns images using ORB Feature Matching.
    Preserves input transparency exactly by using BORDER_CONSTANT (0,0,0,0).
    """
    base_img = load_image_cv2_alpha(base_path)
    base_gray = cv2.cvtColor(base_img[:,:,:3], cv2.COLOR_BGR2GRAY)
    h, w = base_gray.shape

    # ORB Detector
    orb = cv2.ORB_create(nfeatures=5000)
    kp_base, desc_base = orb.detectAndCompute(base_gray, None)
    matcher = cv2.BFMatcher(cv2.NORM_HAMMING, crossCheck=True)

    aligned_paths = []
    
    # Save base frame
    base_out = temp_out_dir / f"aligned_000_base.png"
    cv2.imwrite(str(base_out), base_img)
    aligned_paths.append(str(base_out))

    for idx, f_path in enumerate(input_paths):
        target_img = load_image_cv2_alpha(f_path)
        target_gray = cv2.cvtColor(target_img[:,:,:3], cv2.COLOR_BGR2GRAY)

        kp_target, desc_target = orb.detectAndCompute(target_gray, None)
        
        # Helper: Save original (resized) if alignment fails
        def save_fallback():
            resized = cv2.resize(target_img, (w, h), interpolation=cv2.INTER_LANCZOS4)
            out_p = temp_out_dir / f"aligned_{idx+1:03d}.png"
            cv2.imwrite(str(out_p), resized)
            aligned_paths.append(str(out_p))

        if desc_target is None:
            save_fallback()
            continue

        matches = matcher.match(desc_base, desc_target)
        matches = sorted(matches, key=lambda x: x.distance)
        
        # Filter matches (RANSAC needs decent data)
        num_keep = int(len(matches) * 0.15)
        if num_keep < 4:
            save_fallback()
            continue
            
        matches = matches[:num_keep]
        
        pts_base = np.zeros((len(matches), 2), dtype=np.float32)
        pts_target = np.zeros((len(matches), 2), dtype=np.float32)

        for i, m in enumerate(matches):
            pts_base[i, :] = kp_base[m.queryIdx].pt
            pts_target[i, :] = kp_target[m.trainIdx].pt

        homography, mask = cv2.findHomography(pts_target, pts_base, cv2.RANSAC, 5.0)

        if homography is None:
            save_fallback()
            continue

        # Warp Perspective
        # borderValue=(0,0,0,0) ensures any new empty space is fully transparent
        aligned_img = cv2.warpPerspective(
            target_img, 
            homography, 
            (w, h),
            borderMode=cv2.BORDER_CONSTANT,
            borderValue=(0, 0, 0, 0) 
        )
        
        out_p = temp_out_dir / f"aligned_{idx+1:03d}.png"
        cv2.imwrite(str(out_p), aligned_img)
        aligned_paths.append(str(out_p))

    return aligned_paths

def process_liplab_task(
    task_id: str,
    mouth_files: List[str],
    audio_path: str,
    fps: int,
    sensitivity: float,
    max_width: int,
    max_height: int
):
    task_dir = TEMP_DIR / task_id
    try:
        logger.info(f"Task {task_id}: Starting Liplab Process")
        
        # 1. ALIGNMENT
        base_img_path = mouth_files[0]
        other_imgs_paths = mouth_files[1:]
        aligned_paths = align_images_orb(base_img_path, other_imgs_paths, task_dir)
        
        # 2. AUDIO ANALYSIS
        y, sr = librosa.load(audio_path, sr=None, mono=True)
        duration = librosa.get_duration(y=y, sr=sr)
        hop_length = int(sr / fps)
        rms = librosa.feature.rms(y=y, frame_length=hop_length*2, hop_length=hop_length)[0]
        
        # 3. THRESHOLDS
        min_vol = np.min(rms)
        max_vol = np.max(rms) * sensitivity
        num_images = len(aligned_paths)
        thresholds = np.linspace(min_vol, max_vol, num_images + 1)[1:]
        
        # 4. LOAD & RESIZE
        mouth_imgs = []
        first_pil = Image.open(aligned_paths[0]).convert("RGBA")
        w, h = first_pil.size
        
        # Scale logic
        scale_w = max_width / w
        scale_h = max_height / h
        scale = min(scale_w, scale_h)
        
        if scale < 1.0:
            final_size = (int(w * scale), int(h * scale))
        else:
            final_size = (w, h)
            
        # WebM usually prefers even dimensions
        if final_size[0] % 2 != 0: final_size = (final_size[0] - 1, final_size[1])
        if final_size[1] % 2 != 0: final_size = (final_size[0], final_size[1] - 1)

        for p in aligned_paths:
            img = Image.open(p).convert("RGBA")
            if img.size != final_size:
                img = img.resize(final_size, Image.Resampling.LANCZOS)
            
            # NOTE: Alpha Cleaning REMOVED by request.
            # We trust the input images have correct transparency.
            mouth_imgs.append(img)
            
        # 5. GENERATE FRAMES
        total_frames = int(duration * fps)
        # Pad rms if audio slightly shorter than video frames
        if len(rms) < total_frames:
            rms = np.pad(rms, (0, total_frames - len(rms)))
            
        frames_dir = task_dir / "frames"
        frames_dir.mkdir(exist_ok=True)
        
        for i in range(total_frames):
            val = rms[i]
            selected_idx = 0
            for idx, limit in enumerate(thresholds):
                if val < limit:
                    selected_idx = idx
                    break
                selected_idx = num_images - 1
            
            final_frame = mouth_imgs[selected_idx]
            out_name = frames_dir / f"frame_{i:05d}.png"
            final_frame.save(out_name)
            
        # 6. RENDER VIDEO (WebM VP9)
        output_filename = f"liplab_{task_id}.webm"
        output_path = ANIMATION_DIR / output_filename
        frames_pattern = str(frames_dir / "frame_%05d.png")
        
        # FFmpeg for Transparent WebM
        cmd = [
            "ffmpeg", "-y",
            "-framerate", str(fps),
            "-i", frames_pattern,
            "-i", audio_path,
            "-c:v", "libvpx-vp9",
            "-pix_fmt", "yuva420p",  # 'a' stands for Alpha
            "-crf", "30",            # Quality factor
            "-b:v", "0",             # Required for CRF to work in VP9
            "-c:a", "libvorbis",
            "-auto-alt-ref", "0",    # Sometimes helps with alpha artifacts
            "-shortest",
            str(output_path)
        ]
        
        logger.info(f"Task {task_id}: Rendering WebM...")
        subprocess.run(cmd, check=True, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
        
        TASKS[task_id]["status"] = "completed"
        TASKS[task_id]["result_path"] = str(output_path)
        logger.info(f"Task {task_id}: Completed.")

    except Exception as e:
        logger.exception(f"Task {task_id}: Failed")
        TASKS[task_id]["status"] = "failed"
        TASKS[task_id]["error"] = str(e)
    finally:
        try:
            shutil.rmtree(task_dir)
        except Exception:
            pass

# -----------------------------------------------------------------------------
# ENDPOINTS
# -----------------------------------------------------------------------------

@router.post("/process-async")
async def process_async(
    background_tasks: BackgroundTasks,
    files: List[UploadFile] = File(...),
    audio: UploadFile = File(...),
    fps: int = Form(24),
    sensitivity: float = Form(1.0),
    max_width: int = Form(1024),
    max_height: int = Form(1024)
):
    task_id = str(uuid.uuid4())
    task_dir = TEMP_DIR / task_id
    task_dir.mkdir(parents=True, exist_ok=True)
    
    mouth_paths = []
    try:
        audio_path = task_dir / "input_audio.wav"
        with open(audio_path, "wb") as buffer:
            shutil.copyfileobj(audio.file, buffer)
            
        for idx, file in enumerate(files):
            # Clean filename logic or index usage
            ext = os.path.splitext(file.filename)[1]
            if not ext: ext = ".png"
            fname = f"mouth_input_{idx:03d}{ext}"
            fpath = task_dir / fname
            with open(fpath, "wb") as buffer:
                shutil.copyfileobj(file.file, buffer)
            mouth_paths.append(str(fpath))
        
        TASKS[task_id] = {"status": "processing"}
        background_tasks.add_task(
            process_liplab_task,
            task_id,
            mouth_paths,
            str(audio_path),
            fps,
            sensitivity,
            max_width,
            max_height
        )
        return {"status": "queued", "task_id": task_id}
        
    except Exception as e:
        shutil.rmtree(task_dir)
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
        return FileResponse(path, media_type="video/webm", filename=path.name)
    
    elif task["status"] == "failed":
        return JSONResponse(status_code=500, content={"status": "failed", "detail": task.get("error")})
        
    else:
        return {"status": "processing"}
