# pyapi/services/muvitriccs_service.py
"""
SAGE MuviTriccs — Video Transition Rendering Engine (Tablet PyAPI)

Full-stack transition compositor using OpenCV, SciPy, NumPy, and Torch.
Produces rendered MP4 output from two chained media assets via parameterized
visual transition effects in the CapCut vocabulary and beyond.

Architecture: deterministic, manifest-driven, seed-safe, frame-by-frame renderer.

Transition taxonomy:
  Core:        cross_dissolve, fade_to_black, fade_to_white, luma_wipe
  Motion:      slide_left, slide_right, slide_up, slide_down,
               zoom_in, zoom_out, spin_cw, spin_ccw,
               whip_pan_left, whip_pan_right, push_left, push_right
  Optical:     motion_blur_cut, radial_blur_cut, defocus_cut
  Stylized:    flash, glitch, rgb_split, wave_warp, lens_distortion,
               film_burn, light_leak, scanline_tear, vhs_dropout
  Flow-based:  optical_flow_warp  (dense Farneback flow, content-aware)
  Depth-aware: depth_parallax     (MiDaS depth → layered parallax, optional)
"""

import logging
import json
import uuid
import shutil
import subprocess
import math
import random
from pathlib import Path
from typing import List, Dict, Optional

import numpy as np
import cv2

logger = logging.getLogger(__name__)

# ── Torch: availability flag (sourced from analysis submodule) ─────────────────
from .muvitriccs.analysis import TORCH_AVAILABLE

from fastapi import APIRouter, UploadFile, File, Form, HTTPException, BackgroundTasks
from fastapi.responses import FileResponse, JSONResponse
from PIL import Image, ImageOps

# ── Submodule imports ──────────────────────────────────────────────────────────
from .muvitriccs.easing import EASING_MAP, get_easing
from .muvitriccs.primitives import fit_canvas
from .muvitriccs.analysis import compute_flow, estimate_depth
from .muvitriccs.transitions import (
    render_transition_frame,
    FLOW_TRANSITIONS,
    DEPTH_TRANSITIONS,
)

router = APIRouter(tags=["muvitriccs"])

# ── Paths ──────────────────────────────────────────────────────────────────────
PROJECT_ROOT = Path(__file__).resolve().parents[1]
RENDER_DIR   = PROJECT_ROOT.parent / "services_data" / "muvitriccs_renders"
TEMP_DIR     = PROJECT_ROOT.parent / "services_data" / "muvitriccs_temp"
RENDER_DIR.mkdir(parents=True, exist_ok=True)
TEMP_DIR.mkdir(parents=True, exist_ok=True)

TASKS: Dict[str, Dict] = {}

# ── Transition registry ────────────────────────────────────────────────────────
TRANSITION_REGISTRY = {
    # Core
    "cross_dissolve":    "Core — gamma-correct alpha blend A to B",
    "fade_to_black":     "Core — fade A to black, reveal B from black",
    "fade_to_white":     "Core — fade A to white, reveal B from white",
    "luma_wipe":         "Core — brightness-driven reveal mask with noise edge",
    # Motion
    "slide_left":        "Motion — A exits left, B enters right with motion blur",
    "slide_right":       "Motion — A exits right, B enters left with motion blur",
    "slide_up":          "Motion — A exits up, B enters below with motion blur",
    "slide_down":        "Motion — A exits down, B enters above with motion blur",
    "push_left":         "Motion — both clips move left together",
    "push_right":        "Motion — both clips move right together",
    "zoom_in":           "Motion — A zooms in with radial blur, B fades in",
    "zoom_out":          "Motion — A zooms out, B scales up from small",
    "spin_cw":           "Motion — clockwise rotation with rotational blur",
    "spin_ccw":          "Motion — counter-clockwise rotation with rotational blur",
    "whip_pan_left":     "Motion — fast lateral blur sweep left",
    "whip_pan_right":    "Motion — fast lateral blur sweep right",
    # Optical
    "motion_blur_cut":   "Optical — directional smear conceals the splice",
    "radial_blur_cut":   "Optical — zoom burst at the cut point",
    "defocus_cut":       "Optical — Gaussian lens blur in/out transition",
    # Stylized
    "flash":             "Stylized — luminance spike at cut",
    "glitch":            "Stylized — RGB channel split + scanline block tears",
    "rgb_split":         "Stylized — chromatic aberration dissolve, peak at cut",
    "wave_warp":         "Stylized — sinusoidal row-displacement warp",
    "lens_distortion":   "Stylized — barrel/pincushion radial warp via cv2.remap",
    "film_burn":         "Stylized — smooth-noise radial burn reveal with hot edge",
    "light_leak":        "Stylized — additive warm-light bloom sweep from corner",
    "scanline_tear":     "Stylized — horizontal block corruption + channel drift",
    "vhs_dropout":       "Stylized — VHS tape tracking dropout artifacts",
    # Flow-based
    "optical_flow_warp": "Flow — content-aware Farneback warp morphing A into B",
    # Depth-aware
    "depth_parallax":    "Depth — MiDaS depth map drives per-layer parallax shift",
    # Creative
    "pixel_sort":        "Creative — glitchy column/row sort reveals B through sorted A pixels",
    "ink_wash":          "Creative — diffusion-style organic ink bleed reveal",
    "shatter":           "Creative — A shatters into voronoi shards that fall/spin off",
    "smear_frame":       "Creative — motion-smear echo of A smears into B (anime/action style)",
    "cube_rotate_left":  "Creative — 3-D cube face rotation left (A out, B in)",
    "cube_rotate_right": "Creative — 3-D cube face rotation right",
    "page_curl":         "Creative — flat page-curl peel revealing B underneath",
    "kaleidoscope":      "Creative — kaleidoscopic mirror fold collapse from A to B",
    "ripple_water":      "Creative — concentric water-ripple displacement warp",
    "dream_blur":        "Creative — dreamy glow bloom dissolve with hue rotation",
    # Epic
    "speed_ramp":        "Epic — time-remap freeze-burst: A decelerates to freeze, B explodes from still",
    "shockwave":         "Epic — radial pressure-ring expands from centre, displacing pixels, revealing B inside",
    "strobe_cut":        "Epic — stroboscopic A/B alternation with converging frequency landing on B",
    "motion_trail":      "Epic — luminance ghost trails of A screen-blend over B reveal (anime speed-lines feel)",
    "glare_hit":         "Epic — full-frame directional lens-glare streak sweeps to peak white then clears to B",
    # Movie
    "iris_wipe":         "Movie — circular iris opens from centre revealing B (silent-cinema classic)",
    "venetian_blind":    "Movie — staggered horizontal strip wipe with phase-offset ripple cascade",
    "cross_zoom":        "Movie — A zooms in, B zooms out, collide at cut point with radial blur flash",
    "tilt_shift_cut":    "Movie — rack-focus: A develops tilt-shift miniature look, B racks to sharp focus",
    "cinematic_bars":    "Movie — letterbox bars squeeze A to scope ratio, retract to reveal B full-frame",
    "whip_zoom":         "Movie — directional whip pan + simultaneous radial zoom burst, luminance overexpose at peak",
}


# ── Media reader ───────────────────────────────────────────────────────────────

class MediaReader:
    """
    Extracts video to PNG sequence via FFmpeg (guarantees alpha/VP9 support).
    Applies trim bounds (-ss, -t) and speed changes (-filter:v setpts) natively
    during the fast PNG extraction step to avoid double encoding.
    """

    def __init__(self, path: Path, fps: int, task_dir: Path, 
                 trim_start: float = 0.0, trim_end: float = 0.0, speed: float = 1.0):
        self.path = path
        self.fps  = fps
        self.trim_start = trim_start
        self.trim_end   = trim_end
        self.speed      = speed
        
        self.kind = "video" if path.suffix.lower() in (
            ".mp4", ".webm", ".mov", ".avi", ".mkv", ".ogv") else "image"
            
        self.frames:     List[Path] = []
        self.single_bgr: Optional[np.ndarray] = None
        self.native_w = 1
        self.native_h = 1
        self.frame_count = 0

        if self.kind == "video":
            self._extract_video(task_dir)
        else:
            self._load_image()

    def _load_image(self):
        try:
            pil = Image.open(self.path).convert("RGB")
            pil = ImageOps.exif_transpose(pil)
            self.single_bgr = cv2.cvtColor(np.array(pil), cv2.COLOR_RGB2BGR)
            self.native_h, self.native_w = self.single_bgr.shape[:2]
            self.frame_count = 1
        except Exception as e:
            logger.warning(f"Image load failed: {e}")
            self.single_bgr  = np.zeros((512, 512, 3), dtype=np.uint8)
            self.native_w, self.native_h = 512, 512
            self.frame_count = 1

    def _extract_video(self, task_dir: Path):
        frames_dir = task_dir / f"media_{uuid.uuid4().hex}"
        frames_dir.mkdir(parents=True, exist_ok=True)

        cmd = ["ffmpeg", "-y", "-v", "error"]
        
        if self.trim_start > 0:
            cmd.extend(["-ss", str(self.trim_start)])
            
        if self.trim_end > self.trim_start:
            dur = self.trim_end - self.trim_start
            cmd.extend(["-t", str(dur)])
            
        cmd.extend(["-i", str(self.path)])
        
        if self.speed != 1.0:
            setpts = 1.0 / self.speed
            cmd.extend(["-filter:v", f"setpts={setpts}*PTS"])
            
        cmd.extend(["-r", str(self.fps), "-pix_fmt", "rgb24", str(frames_dir / "%05d.png")])
        
        try:
            subprocess.run(cmd, check=True, capture_output=True, timeout=180)
        except subprocess.CalledProcessError as e:
            logger.error(f"FFmpeg extraction failed: {e.stderr}")
            pass

        self.frames = sorted(frames_dir.glob("*.png"))
        self.frame_count = len(self.frames)
        if self.frame_count > 0:
            sample = cv2.imread(str(self.frames[0]))
            if sample is not None:
                self.native_h, self.native_w = sample.shape[:2]
        else:
            self.native_w, self.native_h = 512, 512

    def get_frame_idx(self, idx: int) -> np.ndarray:
        if self.kind == "image":
            return self.single_bgr.copy()
        if not self.frames:
            return np.zeros((self.native_h, self.native_w, 3), dtype=np.uint8)
        idx = max(0, min(idx, self.frame_count - 1))
        frame = cv2.imread(str(self.frames[idx]))
        return frame if frame is not None else np.zeros(
            (self.native_h, self.native_w, 3), dtype=np.uint8)

    def get_frame_bgr(self, t: float) -> np.ndarray:
        t = max(0.0, min(1.0, t))
        idx = min(int(t * self.frame_count), self.frame_count - 1)
        return self.get_frame_idx(idx)

    def get_last_bgr(self):  return self.get_frame_bgr(1.0)
    def get_first_bgr(self): return self.get_frame_bgr(0.0)


# ── Render orchestrator ────────────────────────────────────────────────────────

def render_muvitriccs(
    task_id:         str,
    task_dir:        Path,
    asset_a_path:    Path,
    asset_b_path:    Path,
    transition_spec: dict,
    output_w:        int,
    output_h:        int,
    fps:             int,
    duration_frames: int,
    trim_start_a:    float,
    trim_end_a:      float,
    trim_start_b:    float,
    trim_end_b:      float,
    speed_a:         float,
    speed_b:         float,
    tail_a_frames:   int,
    head_b_frames:   int,
):
    try:
        name  = transition_spec.get("name", "cross_dissolve")
        seed  = int(transition_spec.get("seed", 42))
        rng   = random.Random(seed)

        TASKS[task_id]["progress"] = 0

        # Readers extract only the accurately trimmed/speed-modified frames
        reader_a = MediaReader(asset_a_path, fps, task_dir, trim_start_a, trim_end_a, speed_a)
        reader_b = MediaReader(asset_b_path, fps, task_dir, trim_start_b, trim_end_b, speed_b)

        # Automatically calculate the full length of Video A and Video B based on their trimmed bounds
        if reader_a.kind == "video":
            tail_a_frames = max(0, reader_a.frame_count - duration_frames)
        else:
            tail_a_frames = int(fps * 2)  # 2 sec static hold

        if reader_b.kind == "video":
            head_b_frames = max(0, reader_b.frame_count - duration_frames)
        else:
            head_b_frames = int(fps * 2)

        logger.info(f"[{task_id}] MuviTriccs: {name}, {duration_frames}fr, {output_w}x{output_h}")
        logger.info(f"[{task_id}] Extracted A frames: {reader_a.frame_count}, B frames: {reader_b.frame_count}")
        logger.info(f"[{task_id}] Tail A: {tail_a_frames}, Head B: {head_b_frames}")

        a_start_idx = max(0, reader_a.frame_count - tail_a_frames - duration_frames)
        b_start_idx = 0

        # Pre-compute expensive analysis once before the frame loop
        flow_ab = depth_a = depth_b = None
        boundary_a = fit_canvas(reader_a.get_frame_idx(reader_a.frame_count - 1), output_w, output_h)
        boundary_b = fit_canvas(reader_b.get_frame_idx(0), output_w, output_h)

        if name in FLOW_TRANSITIONS:
            logger.info(f"[{task_id}] Computing optical flow…")
            flow_ab = compute_flow(boundary_a, boundary_b, downscale=0.5)

        if name in DEPTH_TRANSITIONS:
            logger.info(f"[{task_id}] Estimating depth maps…")
            depth_a = estimate_depth(boundary_a)
            depth_b = estimate_depth(boundary_b)

        # FFmpeg stdin pipe — bgr24 raw frames in, libx264 out
        out_path = RENDER_DIR / f"muvitriccs_{task_id}.mp4"
        tw = output_w - (output_w % 2)
        th = output_h - (output_h % 2)

        cmd = [
            "ffmpeg", "-y", "-loglevel", "error",
            "-f", "rawvideo", "-vcodec", "rawvideo",
            "-s", f"{output_w}x{output_h}",
            "-pix_fmt", "bgr24", "-r", str(fps), "-i", "-",
            "-c:v", "libx264", "-pix_fmt", "yuv420p",
            "-preset", "medium", "-crf", "20",
            "-movflags", "+faststart",
            "-vf", f"crop={tw}:{th}:0:0",
            str(out_path),
        ]
        proc = subprocess.Popen(cmd, stdin=subprocess.PIPE, stderr=subprocess.PIPE)

        total = tail_a_frames + duration_frames + head_b_frames
        idx   = 0

        # Output pure Tail A (now safely bound by trim parameters)
        for i in range(tail_a_frames):
            fa = fit_canvas(reader_a.get_frame_idx(a_start_idx + i), output_w, output_h)
            proc.stdin.write(fa.tobytes()); idx += 1

        # Output overlapping transition frames
        overlap_start_a = a_start_idx + tail_a_frames
        for i in range(duration_frames):
            t  = i / max(duration_frames - 1, 1)
            fa = fit_canvas(reader_a.get_frame_idx(overlap_start_a + i), output_w, output_h)
            fb = fit_canvas(reader_b.get_frame_idx(b_start_idx + i), output_w, output_h)

            fr = render_transition_frame(
                fa, fb, t, name, transition_spec,
                output_w, output_h, rng,
                flow_ab=flow_ab, depth_a=depth_a, depth_b=depth_b,
            )
            try:
                proc.stdin.write(fr.tobytes())
            except BrokenPipeError:
                break
            idx += 1
            if i % 5 == 0:
                pct = int(100 * idx / max(total, 1))
                TASKS[task_id]["progress"] = pct
                logger.info(f"[{task_id}] {idx}/{total} ({pct}%)")

        # Output pure Head B
        head_start_b = b_start_idx + duration_frames
        for i in range(head_b_frames):
            fb = fit_canvas(reader_b.get_frame_idx(head_start_b + i), output_w, output_h)
            proc.stdin.write(fb.tobytes()); idx += 1

        proc.stdin.close()
        stderr_out = proc.stderr.read() if proc.stderr else b""
        proc.wait()

        if proc.returncode != 0:
            raise RuntimeError(f"FFmpeg: {stderr_out.decode('utf-8', errors='ignore')}")

        manifest = {
            "version": "2.1", "task_id": task_id,
            "transition": name, "spec": transition_spec,
            "asset_a": asset_a_path.name, "asset_b": asset_b_path.name,
            "output_w": output_w, "output_h": output_h, "fps": fps,
            "duration_frames": duration_frames,
            "tail_a_frames": tail_a_frames, "head_b_frames": head_b_frames,
            "total_frames": total, "seed": seed,
            "flow_used": flow_ab is not None, "depth_used": depth_a is not None,
        }
        mp = RENDER_DIR / f"manifest_{task_id}.json"
        mp.write_text(json.dumps(manifest, indent=2))

        TASKS[task_id].update({
            "status": "completed", "result_path": str(out_path),
            "manifest_path": str(mp), "progress": 100,
        })
        logger.info(f"[{task_id}] Done: {out_path}")

    except Exception as e:
        logger.exception(f"[{task_id}] Render failed")
        TASKS[task_id]["status"] = "failed"
        TASKS[task_id]["error"]  = str(e)
    finally:
        try:
            if task_dir.exists():
                shutil.rmtree(task_dir)
        except Exception:
            pass


# ── FastAPI endpoints ──────────────────────────────────────────────────────────

@router.post("/render")
async def render_transition(
    background_tasks: BackgroundTasks,
    asset_a:          UploadFile = File(...),
    asset_b:          UploadFile = File(...),
    transition_name:  str   = Form("cross_dissolve"),
    duration_frames:  int   = Form(24),
    fps:              int   = Form(30),
    output_w:         int   = Form(1080),
    output_h:         int   = Form(1080),
    intensity:        float = Form(1.0),
    easing:           str   = Form("ease_in_out_cubic"),
    seed:             int   = Form(42),
    trim_start_a:     float = Form(0.0),
    trim_end_a:       float = Form(0.0),
    trim_start_b:     float = Form(0.0),
    trim_end_b:       float = Form(0.0),
    speed_a:          float = Form(1.0),
    speed_b:          float = Form(1.0),
    tail_a_frames:    int   = Form(-1),
    head_b_frames:    int   = Form(-1),
):
    if transition_name not in TRANSITION_REGISTRY:
        raise HTTPException(400,
            f"Unknown transition '{transition_name}'. "
            f"Valid: {sorted(TRANSITION_REGISTRY)}")

    task_id  = str(uuid.uuid4())
    task_dir = TEMP_DIR / task_id
    task_dir.mkdir(parents=True, exist_ok=True)

    try:
        ext_a  = Path(asset_a.filename or "a.mp4").suffix or ".mp4"
        ext_b  = Path(asset_b.filename or "b.mp4").suffix or ".mp4"
        path_a = task_dir / f"asset_a{ext_a}"
        path_b = task_dir / f"asset_b{ext_b}"
        with open(path_a, "wb") as f: shutil.copyfileobj(asset_a.file, f)
        with open(path_b, "wb") as f: shutil.copyfileobj(asset_b.file, f)

        spec = {
            "name":      transition_name,
            "intensity": max(0.1, min(3.0, intensity)),
            "easing":    easing if easing in EASING_MAP else "ease_in_out_cubic",
            "seed":      seed,
        }
        TASKS[task_id] = {"status": "processing", "progress": 0}
        
        background_tasks.add_task(
            render_muvitriccs,
            task_id, task_dir, path_a, path_b, spec,
            max(64, output_w), max(64, output_h),
            max(10, min(60, fps)),
            max(2, min(120, duration_frames)),
            trim_start_a, trim_end_a, trim_start_b, trim_end_b, speed_a, speed_b,
            tail_a_frames, head_b_frames,
        )
        return {"status": "queued", "task_id": task_id, "transition": transition_name}

    except Exception as e:
        shutil.rmtree(task_dir, ignore_errors=True)
        raise HTTPException(500, str(e))


@router.get("/status/{task_id}")
async def transition_status(task_id: str):
    if task_id not in TASKS:
        raise HTTPException(404, "Task not found")
    t = TASKS[task_id]
    return {"status": t["status"], "progress": t.get("progress", 0),
            "error": t.get("error", "")}


@router.get("/download/{task_id}")
async def download_transition(task_id: str):
    if task_id not in TASKS:
        raise HTTPException(404, "Task not found")
    t = TASKS[task_id]
    if t["status"] != "completed":
        raise HTTPException(400, "Render not complete")
    path = Path(t["result_path"])
    if not path.exists():
        raise HTTPException(500, "Result file missing")
    return FileResponse(path, media_type="video/mp4", filename=path.name)


@router.get("/manifest/{task_id}")
async def get_manifest(task_id: str):
    if task_id not in TASKS:
        raise HTTPException(404, "Task not found")
    mp = TASKS[task_id].get("manifest_path")
    if not mp or not Path(mp).exists():
        raise HTTPException(404, "Manifest not ready")
    return JSONResponse(json.loads(Path(mp).read_text()))


@router.get("/transitions")
async def list_transitions():
    families = {
        "core":     ["cross_dissolve","fade_to_black","fade_to_white","luma_wipe"],
        "motion":   ["slide_left","slide_right","slide_up","slide_down",
                     "push_left","push_right","zoom_in","zoom_out",
                     "spin_cw","spin_ccw","whip_pan_left","whip_pan_right"],
        "optical":  ["motion_blur_cut","radial_blur_cut","defocus_cut"],
        "stylized": ["flash","glitch","rgb_split","wave_warp","lens_distortion",
                     "film_burn","light_leak","scanline_tear","vhs_dropout"],
        "flow":     ["optical_flow_warp"],
        "depth":    ["depth_parallax"],
        "epic":  ["speed_ramp", "shockwave", "strobe_cut", "motion_trail", "glare_hit"],
        "movie": ["iris_wipe", "venetian_blind", "cross_zoom"],
        "creative": ["pixel_sort","ink_wash","shatter","smear_frame",
                     "cube_rotate_left","cube_rotate_right","page_curl",
                     "kaleidoscope","ripple_water","dream_blur"],
    }
    return {
        "transitions": [
            {"name": k, "description": v,
             "family": next((f for f, ns in families.items() if k in ns), "other")}
            for k, v in TRANSITION_REGISTRY.items()
        ],
        "torch_available": TORCH_AVAILABLE,
    }


@router.get("/_health")
async def health():
    return {
        "status":  "ok",
        "service": "muvitriccs_service",
        "torch":   TORCH_AVAILABLE,
        "opencv":  cv2.__version__,
    }