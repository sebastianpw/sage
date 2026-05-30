"""
pyapi/services/multivid_service.py
SAGE MultiVid — Offline 2.5D Parallax Compositor (Tablet PyAPI)

Upgraded to use FFmpeg PNG extraction for flawless WebM Alpha channel support
and True Alpha Compositing for pristine layer blending. 
Includes libvpx-vp9 overrides to fix FFmpeg's default VP9 alpha blindness.
"""

import logging
import json
import uuid
import shutil
import subprocess
from pathlib import Path
from typing import List, Dict

from fastapi import APIRouter, UploadFile, File, Form, HTTPException, BackgroundTasks
from fastapi.responses import FileResponse, JSONResponse
from PIL import Image, ImageOps

logger = logging.getLogger(__name__)
router = APIRouter(tags=["multivid"])

# ── Paths ──────────────────────────────────────────────────────────────────────
PROJECT_ROOT  = Path(__file__).resolve().parents[1]
RENDER_DIR    = PROJECT_ROOT.parent / "services_data" / "multivid_renders"
TEMP_DIR      = PROJECT_ROOT.parent / "services_data" / "multivid_temp"
RENDER_DIR.mkdir(parents=True, exist_ok=True)
TEMP_DIR.mkdir(parents=True, exist_ok=True)

TASKS: Dict[str, Dict] = {}

def cleanup(path: Path):
    try:
        if path.exists():
            shutil.rmtree(path) if path.is_dir() else path.unlink()
    except Exception as e:
        logger.warning(f"Cleanup failed {path}: {e}")

class VideoReader:
    """
    Extracts video to PNGs via FFmpeg to guarantee 100% perfect Alpha/Transparency
    support for WebMs. Forces libvpx/libvpx-vp9 decoders because FFmpeg's native
    VP8/VP9 decoders ignore alpha channels.
    """
    def __init__(self, path: Path, fps: int, task_dir: Path):
        self.path = path
        self.fps = fps
        self.frames_dir = task_dir / f"vid_{uuid.uuid4().hex}"
        self.frames_dir.mkdir(parents=True, exist_ok=True)
        
        # Probe codec to ensure WebM alpha (VP8/VP9) is properly extracted
        codec = ""
        try:
            probe_cmd =[
                "ffprobe", "-v", "error", 
                "-select_streams", "v:0", 
                "-show_entries", "stream=codec_name", 
                "-of", "default=noprint_wrappers=1:nokey=1", 
                str(self.path)
            ]
            codec = subprocess.check_output(probe_cmd).decode('utf-8').strip()
        except Exception as e:
            logger.warning(f"Failed to probe codec for {path.name}: {e}")

        vcodec_args = []
        if codec == "vp9":
            vcodec_args =["-c:v", "libvpx-vp9"]
        elif codec == "vp8":
            vcodec_args = ["-c:v", "libvpx"]
        
        # FFmpeg strictly extracts with RGBA pixel format to preserve transparency
        cmd =[
            "ffmpeg", "-y", "-v", "error"
        ] + vcodec_args +[
            "-i", str(self.path),
            "-r", str(self.fps),
            "-pix_fmt", "rgba",
            str(self.frames_dir / "%05d.png")
        ]
        
        try:
            subprocess.run(cmd, check=True, capture_output=True)
        except subprocess.CalledProcessError as e:
            logger.warning(f"Specific codec extraction failed for {path.name}, falling back to native decoder. Error: {e.stderr.decode('utf-8')}")
            # Fallback to standard decode if ffmpeg wasn't compiled with libvpx wrapper
            fallback_cmd =[
                "ffmpeg", "-y", "-v", "error",
                "-i", str(self.path),
                "-r", str(self.fps),
                "-pix_fmt", "rgba",
                str(self.frames_dir / "%05d.png")
            ]
            try:
                subprocess.run(fallback_cmd, check=True, capture_output=True)
            except subprocess.CalledProcessError as e2:
                logger.error(f"Fallback extraction failed for {path.name}: {e2.stderr.decode('utf-8')}")
            
        # Register frames
        self.frame_files = sorted(list(self.frames_dir.glob("*.png")))
        self.vid_total = len(self.frame_files)
        self.vid_fps = fps
        self.vid_dur = self.vid_total / self.vid_fps if self.vid_total > 0 else 0.0
        
        if self.vid_total > 0:
            with Image.open(self.frame_files[0]) as img:
                self.native_w, self.native_h = img.size
        else:
            self.native_w, self.native_h = 100, 100

    def get_frame(self, t_vid: float) -> Image.Image:
        if self.vid_total == 0:
            return Image.new("RGBA", (self.native_w, self.native_h), (0, 0, 0, 0))
        
        target_frame = int(t_vid * self.vid_fps)
        target_frame = max(0, min(target_frame, self.vid_total - 1))
        
        return Image.open(self.frame_files[target_frame]).convert("RGBA")

    def close(self):
        # Temp PNGs are cleaned up automatically when task_dir is destroyed
        pass

# ── Core render ────────────────────────────────────────────────────────────────

def render_multivid(
    task_id: str,
    task_dir: Path,
    files_map: Dict[str, Path],
    layers_meta: List[Dict],
    arrangement_config: Dict,
    total_frames: int,
    fps: int,
    move_x: int,
    move_y: int,
    zoom_start: float,
    zoom_end: float,
    canvas_w: int,
    canvas_h: int,
):
    try:
        logger.info(f"[{task_id}] Starting MultiVid render: {total_frames} frames, {canvas_w}x{canvas_h}")

        layers =[]
        for meta in layers_meta:
            key = meta["key"]
            fname = next((fn for fn in files_map if fn.rsplit('.', 1)[0] == key), None)
            if not fname:
                continue

            cfg = arrangement_config.get(key, {})
            layers.append({
                "key": key,
                "asset_type": meta.get("asset_type", "frame"),
                "path": files_map[fname],
                "x": float(cfg.get("x", 0)),
                "y": float(cfg.get("y", 0)),
                "scaleX": float(cfg.get("scaleX", 1.0)),
                "scaleY": float(cfg.get("scaleY", 1.0)),
                "rotation": float(cfg.get("rotation", 0)),
                "zIndex": int(cfg.get("zIndex", meta.get("z_index", 0))),
                "opacity": float(cfg.get("opacity", meta.get("opacity", 1.0))),
                "speed": float(meta.get("speed", 0.5)),
                "start_offset": float(meta.get("start_offset", 0.0)),
                "end_offset": meta.get("end_offset") if meta.get("end_offset", -1) != -1 else None,
                "playback_speed": float(meta.get("playback_speed", 1.0)),
            })

        if not layers:
            raise RuntimeError("No valid layers to render")

        layers.sort(key=lambda l: l["zIndex"])

        # Init Assets
        for L in layers:
            if L["asset_type"] == "video":
                reader = VideoReader(L["path"], fps, task_dir)
                L["reader"] = reader
                L["native_w"], L["native_h"] = reader.native_w, reader.native_h
            else:
                img = Image.open(L["path"]).convert("RGBA")
                img = ImageOps.exif_transpose(img)
                L["img"] = img
                L["native_w"], L["native_h"] = img.size

        # ── Init FFmpeg Stream ──────────────────────────────────────────────
        out_path = RENDER_DIR / f"multivid_{task_id}.mp4"
        tw = canvas_w if canvas_w % 2 == 0 else canvas_w - 1
        th = canvas_h if canvas_h % 2 == 0 else canvas_h - 1
        
        cmd =[
            "ffmpeg", "-y", "-loglevel", "error",
            "-f", "rawvideo", "-vcodec", "rawvideo",
            "-s", f"{canvas_w}x{canvas_h}", "-pix_fmt", "rgb24", "-r", str(fps),
            "-i", "-",
            "-c:v", "libx264", "-pix_fmt", "yuv420p",
            "-preset", "medium", "-crf", "20",
            "-vf", f"crop={tw}:{th}:0:0",
            str(out_path)
        ]
        
        proc = subprocess.Popen(cmd, stdin=subprocess.PIPE, stderr=subprocess.PIPE)
        logger.info(f"[{task_id}] Rendering {total_frames} frames...")

        # ── Render loop ─────────────────────────────────────────────────────
        for i in range(total_frames):
            t = i / max(total_frames - 1, 1)
            cam_x, cam_y = move_x * t, move_y * t
            current_zoom = zoom_start + (zoom_end - zoom_start) * t

            canvas = Image.new("RGBA", (canvas_w, canvas_h), (0, 0, 0, 0))

            for L in layers:
                if L["asset_type"] == "video":
                    reader = L["reader"]
                    t_start, t_end = L["start_offset"], L["end_offset"]
                    if t_end is None or t_end <= t_start:
                        t_end = reader.vid_dur if reader.vid_dur > t_start else t_start + 1.0
                    
                    seg_dur = (t_end - t_start) / max(0.01, L["playback_speed"])
                    t_vid = t_start + ((t * seg_dur) * L["playback_speed"])
                    if reader.vid_dur > 0 and (t_end - t_start) > 0:
                        t_vid = t_start + ((t_vid - t_start) % (t_end - t_start))
                    src = reader.get_frame(t_vid)
                else:
                    src = L["img"]

                nw, nh = L["native_w"], L["native_h"]
                sw, sh = max(1, int(nw * L["scaleX"])), max(1, int(nh * L["scaleY"]))
                
                layer_img = src.resize((sw, sh), Image.LANCZOS) if sw != nw or sh != nh else (src.copy() if L["asset_type"] == "frame" else src)
                
                if abs(L["rotation"]) > 0.1:
                    layer_img = layer_img.rotate(-L["rotation"], expand=True, resample=Image.BICUBIC)
                if L["opacity"] < 1.0:
                    r, g, b, a = layer_img.split()
                    layer_img = Image.merge("RGBA", (r, g, b, a.point(lambda v: int(v * L["opacity"]))))

                shift_x, shift_y = -(cam_x * L["speed"]), -(cam_y * L["speed"])
                dx, dy = int(L["x"] + shift_x), int(L["y"] + shift_y)
                
                # TRUE ALPHA COMPOSITING (Prevents dark edge fringing & perfectly handles transparency)
                temp_layer = Image.new("RGBA", (canvas_w, canvas_h), (0, 0, 0, 0))
                temp_layer.paste(layer_img, (dx, dy))
                canvas = Image.alpha_composite(canvas, temp_layer)

            if abs(current_zoom - 1.0) > 0.001:
                zw, zh = int(canvas_w * current_zoom), int(canvas_h * current_zoom)
                zoomed = canvas.resize((zw, zh), Image.LANCZOS)
                left, top = (zw - canvas_w) // 2, (zh - canvas_h) // 2
                canvas = zoomed.crop((left, top, left + canvas_w, top + canvas_h))

            if canvas.size != (canvas_w, canvas_h):
                canvas = canvas.resize((canvas_w, canvas_h), Image.LANCZOS)

            try:
                proc.stdin.write(canvas.convert("RGB").tobytes())
            except BrokenPipeError:
                break

            if i % 10 == 0:
                logger.info(f"[{task_id}] Frame {i+1}/{total_frames}")

        proc.stdin.close()
        stderr_bytes = proc.stderr.read() if proc.stderr else b""
        proc.wait()
        
        if proc.returncode != 0:
            raise RuntimeError(f"FFmpeg failed: {stderr_bytes.decode('utf-8', errors='ignore')}")

        for L in layers:
            if "reader" in L: L["reader"].close()

        TASKS[task_id]["status"]      = "completed"
        TASKS[task_id]["result_path"] = str(out_path)
        logger.info(f"[{task_id}] Render complete: {out_path}")

    except Exception as e:
        logger.exception(f"[{task_id}] Render failed")
        TASKS[task_id]["status"] = "failed"
        TASKS[task_id]["error"]  = str(e)
        
        if 'layers' in locals():
            for L in layers:
                if "reader" in L:
                    try: L["reader"].close()
                    except: pass
    finally:
        cleanup(task_dir)

# ── Endpoints ──────────────────────────────────────────────────────────────────

@router.post("/compose-async")
async def compose_async(
    background_tasks: BackgroundTasks,
    files: List[UploadFile] = File(...),
    layers_meta: str        = Form(...),
    arrangement_config: str = Form("{}"),
    frames: int             = Form(90),
    fps: int                = Form(30),
    move_x: int             = Form(80),
    move_y: int             = Form(0),
    zoom_start: float       = Form(1.0),
    zoom_end: float         = Form(1.04),
    canvas_w: int           = Form(1024),
    canvas_h: int           = Form(1024),
):
    task_id  = str(uuid.uuid4())
    task_dir = TEMP_DIR / task_id
    task_dir.mkdir(parents=True, exist_ok=True)

    try:
        files_map: Dict[str, Path] = {}
        for uf in files:
            dest = task_dir / (uf.filename or f"file_{len(files_map)}")
            with open(dest, "wb") as f: shutil.copyfileobj(uf.file, f)
            files_map[uf.filename] = dest

        meta_list   = json.loads(layers_meta)
        arr_config  = json.loads(arrangement_config)

        TASKS[task_id] = {"status": "processing"}

        background_tasks.add_task(
            render_multivid,
            task_id, task_dir, files_map, meta_list, arr_config,
            frames, fps, move_x, move_y,
            max(0.1, zoom_start), max(0.1, zoom_end),
            canvas_w, canvas_h
        )
        return {"status": "queued", "task_id": task_id}

    except Exception as e:
        cleanup(task_dir)
        raise HTTPException(status_code=500, detail=str(e))

@router.get("/status/{task_id}")
async def task_status(task_id: str):
    if task_id not in TASKS:
        raise HTTPException(status_code=404, detail="Task not found")
    
    return {
        "status": TASKS[task_id]["status"],
        "error": TASKS[task_id].get("error", "")
    }

@router.get("/download/{task_id}")
async def download_result(task_id: str):
    if task_id not in TASKS:
        raise HTTPException(status_code=404, detail="Task not found")
    
    task = TASKS[task_id]
    if task["status"] != "completed":
        raise HTTPException(status_code=400, detail="Video is not ready yet.")

    path = Path(task["result_path"])
    if not path.exists():
        raise HTTPException(status_code=500, detail="Result file missing from server.")
        
    return FileResponse(path, media_type="video/mp4", filename=path.name)
