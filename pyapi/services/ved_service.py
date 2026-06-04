# pyapi/services/ved_service.py
"""
SAGE VED — Video Compositor Service
Receives a timeline state_json + video asset files via multipart POST.
Flattens the timeline into non-overlapping segments, injecting full Python-rendered 
MuviTriccs transitions where connectors are defined, then pipes everything through 
FFmpeg filter_complex via 'concat'.
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
from PIL import Image, ImageOps

from fastapi import APIRouter, UploadFile, File, Form, HTTPException, BackgroundTasks
from fastapi.responses import FileResponse

# Import MuviTriccs render engines natively
from .muvitriccs.transitions import render_transition_frame, FLOW_TRANSITIONS, DEPTH_TRANSITIONS
from .muvitriccs.analysis import compute_flow, estimate_depth
from .muvitriccs.primitives import fit_canvas

logger = logging.getLogger(__name__)
router = APIRouter(tags=["ved"])

PROJECT_ROOT = Path(__file__).resolve().parents[1]
RENDER_DIR   = PROJECT_ROOT.parent / "services_data" / "ved_renders"
TEMP_DIR     = PROJECT_ROOT.parent / "services_data" / "ved_temp"
RENDER_DIR.mkdir(parents=True, exist_ok=True)
TEMP_DIR.mkdir(parents=True, exist_ok=True)

# ---------------------------------------------------------------------------
# Job store — file-backed dictionary to survive Uvicorn reloads in Codespaces
# ---------------------------------------------------------------------------
TASKS: Dict[str, Dict] = {}

def _save_task(task_id: str, data: dict) -> None:
    TASKS[task_id] = data
    state_file = TEMP_DIR / task_id / "state.json"
    try:
        if state_file.parent.exists():
            state_file.write_text(json.dumps(data))
    except Exception as e:
        logger.warning(f"Could not save VED task state to disk for {task_id}: {e}")

def _get_task(task_id: str) -> Optional[dict]:
    if task_id in TASKS:
        return TASKS[task_id]
    state_file = TEMP_DIR / task_id / "state.json"
    if state_file.exists():
        try:
            data = json.loads(state_file.read_text())
            TASKS[task_id] = data
            return data
        except Exception:
            pass
    return None

def cleanup(path: Path):
    try:
        if path.exists():
            shutil.rmtree(path) if path.is_dir() else path.unlink()
    except Exception as e:
        logger.warning(f"Cleanup failed {path}: {e}")


def _probe_duration(path: Path) -> float:
    try:
        cmd = [
            "ffprobe", "-v", "error",
            "-show_entries", "format=duration",
            "-of", "default=noprint_wrappers=1:nokey=1",
            str(path)
        ]
        out = subprocess.check_output(cmd, stderr=subprocess.DEVNULL)
        return float(out.strip())
    except Exception:
        return 0.0


def _has_audio(path: Path) -> bool:
    try:
        cmd = [
            "ffprobe", "-v", "error",
            "-select_streams", "a:0",
            "-show_entries", "stream=codec_type",
            "-of", "default=noprint_wrappers=1:nokey=1",
            str(path)
        ]
        out = subprocess.check_output(cmd, stderr=subprocess.DEVNULL).decode().strip()
        return "audio" in out
    except Exception:
        return False


def _atempo_chain(speed: float) -> str:
    if speed == 1.0:
        return "anull"
    nodes = []
    remaining = speed
    while remaining > 100.0:
        nodes.append("atempo=100.0")
        remaining /= 100.0
    while remaining < 0.5:
        nodes.append("atempo=0.5")
        remaining /= 0.5
    nodes.append(f"atempo={remaining:.6f}")
    return ",".join(nodes)


def _get_connector(raw_a: dict, raw_b: dict, state: dict):
    """
    Directly extracts the pre-calculated stableHash from the JSON payload.
    This entirely bypasses Python's float rounding discrepancies!
    """
    hash_a = raw_a.get("stableHash")
    hash_b = raw_b.get("stableHash")
    if not hash_a or not hash_b:
        return None
    key = f"conn_{hash_a}_{hash_b}"
    return state.get("connectors", {}).get(key)


class MediaReader:
    """Extracts frame sequence natively for transition rendering."""
    def __init__(self, path: Path, fps: int, task_dir: Path, trim_start: float, trim_end: float, speed: float):
        self.path = path
        self.fps = fps
        self.trim_start = trim_start
        self.trim_end = trim_end
        self.speed = speed
        self.kind = "video" if path.suffix.lower() in (".mp4", ".webm", ".mov", ".avi", ".mkv", ".ogv") else "image"
        self.frames = []
        self.single_bgr = None
        self.native_w = 512
        self.native_h = 512
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
        except Exception:
            self.single_bgr = np.zeros((512, 512, 3), dtype=np.uint8)
            self.frame_count = 1

    def _extract_video(self, task_dir: Path):
        frames_dir = task_dir / f"media_{uuid.uuid4().hex}"
        frames_dir.mkdir(parents=True, exist_ok=True)
        cmd = ["ffmpeg", "-y", "-v", "error"]
        if self.trim_start > 0: cmd.extend(["-ss", str(self.trim_start)])
        if self.trim_end > self.trim_start: cmd.extend(["-t", str(self.trim_end - self.trim_start)])
        cmd.extend(["-i", str(self.path)])
        if self.speed != 1.0: cmd.extend(["-filter:v", f"setpts={1.0/self.speed}*PTS"])
        cmd.extend(["-r", str(self.fps), "-pix_fmt", "rgb24", str(frames_dir / "%05d.png")])
        
        subprocess.run(cmd, capture_output=True)
        self.frames = sorted(frames_dir.glob("*.png"))
        self.frame_count = len(self.frames)
        if self.frame_count > 0:
            sample = cv2.imread(str(self.frames[0]))
            if sample is not None:
                self.native_h, self.native_w = sample.shape[:2]

    def get_frame_idx(self, idx: int) -> np.ndarray:
        if self.kind == "image": return self.single_bgr.copy()
        if not self.frames: return np.zeros((self.native_h, self.native_w, 3), dtype=np.uint8)
        idx = max(0, min(idx, self.frame_count - 1))
        frame = cv2.imread(str(self.frames[idx]))
        return frame if frame is not None else np.zeros((self.native_h, self.native_w, 3), dtype=np.uint8)


def _render_temp_transition(seg: dict, fps: int, w: int, h: int, task_dir: Path) -> Path:
    """Renders transition segment via PyAPI OpenCV pipeline, returns path to MP4."""
    clip_a = seg["clip_a"]
    clip_b = seg["clip_b"]
    conn   = seg["conn"]
    
    trans_name = conn.get("transitionName", "cross_dissolve")
    dur_sec    = seg["dur"]
    frames_cnt = max(2, int(dur_sec * fps))
    
    # Calculate exact handle bounds required for overlapping media, allowing negative start offset
    a_src_start_unclamped = clip_a["trimStart"] + (seg["t0"] - clip_a["start"]) * clip_a["speed"]
    a_src_end             = a_src_start_unclamped + dur_sec * clip_a["speed"]
    a_src_start_clamped   = max(0.0, a_src_start_unclamped)

    b_src_start_unclamped = clip_b["trimStart"] + (seg["t0"] - clip_b["start"]) * clip_b["speed"]
    b_src_end             = b_src_start_unclamped + dur_sec * clip_b["speed"]
    b_src_start_clamped   = max(0.0, b_src_start_unclamped)
    
    reader_a = MediaReader(clip_a["path"], fps, task_dir, a_src_start_clamped, a_src_end, clip_a["speed"])
    reader_b = MediaReader(clip_b["path"], fps, task_dir, b_src_start_clamped, b_src_end, clip_b["speed"])

    # Handle freeze frames if transition overextends source bounds
    a_missing_frames = int(abs(a_src_start_unclamped) * fps / clip_a["speed"]) if a_src_start_unclamped < 0 else 0
    b_missing_frames = int(abs(b_src_start_unclamped) * fps / clip_b["speed"]) if b_src_start_unclamped < 0 else 0
    
    # Pre-compute flow / depth
    boundary_a = fit_canvas(reader_a.get_frame_idx(frames_cnt - 1), w, h)
    boundary_b = fit_canvas(reader_b.get_frame_idx(0), w, h)
    
    flow_ab = depth_a = depth_b = None
    if trans_name in FLOW_TRANSITIONS: flow_ab = compute_flow(boundary_a, boundary_b, downscale=0.5)
    if trans_name in DEPTH_TRANSITIONS:
        depth_a = estimate_depth(boundary_a)
        depth_b = estimate_depth(boundary_b)
        
    rng = random.Random(int(conn.get("seed", 42)))
    spec = {
        "name": trans_name,
        "intensity": float(conn.get("intensity", 1.0)),
        "easing": conn.get("easing", "ease_in_out_cubic"),
        "seed": int(conn.get("seed", 42))
    }
    
    # Pipe raw video to h264 encoder
    tmp_v = task_dir / f"trans_v_{uuid.uuid4().hex}.mp4"
    cmd = [
        "ffmpeg", "-y", "-loglevel", "error",
        "-f", "rawvideo", "-vcodec", "rawvideo",
        "-s", f"{w}x{h}", "-pix_fmt", "bgr24", "-r", str(fps), "-i", "-",
        "-c:v", "libx264", "-pix_fmt", "yuv420p", "-preset", "ultrafast", "-crf", "18",
        str(tmp_v)
    ]
    proc = subprocess.Popen(cmd, stdin=subprocess.PIPE, stderr=subprocess.PIPE)
    
    for i in range(frames_cnt):
        t = i / max(frames_cnt - 1, 1)
        a_idx = i - a_missing_frames
        b_idx = i - b_missing_frames
        
        fa = fit_canvas(reader_a.get_frame_idx(a_idx), w, h)
        fb = fit_canvas(reader_b.get_frame_idx(b_idx), w, h)
        fr = render_transition_frame(fa, fb, t, trans_name, spec, w, h, rng, flow_ab, depth_a, depth_b)
        proc.stdin.write(fr.tobytes())
        
    proc.stdin.close()
    proc.wait()
    
    # FFmpeg audio crossfade multiplexing
    tmp_final = task_dir / f"trans_final_{uuid.uuid4().hex}.mp4"
    a_atempo = _atempo_chain(clip_a["speed"])
    b_atempo = _atempo_chain(clip_b["speed"])
    a_vol = clip_a["vol"] if not clip_a["muted"] else 0.0
    b_vol = clip_b["vol"] if not clip_b["muted"] else 0.0
    
    af_cmd = ["ffmpeg", "-y", "-loglevel", "error", "-i", str(tmp_v)]
    filter_complex = []
    has_a = clip_a["has_audio"] and a_vol > 0
    has_b = clip_b["has_audio"] and b_vol > 0
    mix_inputs = []
    input_idx = 1
    
    if has_a:
        af_cmd.extend(["-i", str(clip_a["path"])])
        cmd_str = f"[{input_idx}:a]atrim=start={a_src_start_clamped}:end={a_src_end},asetpts=PTS-STARTPTS,{a_atempo}"
        if a_src_start_unclamped < 0:
            delay_ms = int(abs(a_src_start_unclamped) * 1000)
            cmd_str += f",adelay={delay_ms}|{delay_ms}"
        cmd_str += f",volume={a_vol},afade=t=out:st=0:d={dur_sec}[audA]"
        filter_complex.append(cmd_str)
        mix_inputs.append("[audA]")
        input_idx += 1
        
    if has_b:
        af_cmd.extend(["-i", str(clip_b["path"])])
        cmd_str = f"[{input_idx}:a]atrim=start={b_src_start_clamped}:end={b_src_end},asetpts=PTS-STARTPTS,{b_atempo}"
        if b_src_start_unclamped < 0:
            delay_ms = int(abs(b_src_start_unclamped) * 1000)
            cmd_str += f",adelay={delay_ms}|{delay_ms}"
        cmd_str += f",volume={b_vol},afade=t=in:st=0:d={dur_sec}[audB]"
        filter_complex.append(cmd_str)
        mix_inputs.append("[audB]")
        input_idx += 1
        
    if not mix_inputs:
        filter_complex.append(f"anullsrc=r=44100:cl=stereo:d={dur_sec}[aout]")
    elif len(mix_inputs) == 1:
        filter_complex.append(f"{mix_inputs[0]}aresample=44100[aout]")
    else:
        filter_complex.append(f"{mix_inputs[0]}{mix_inputs[1]}amix=inputs=2:duration=longest,aresample=44100[aout]")
        
    af_cmd.extend([
        "-filter_complex", ";".join(filter_complex),
        "-map", "0:v", "-map", "[aout]",
        "-c:v", "copy", "-c:a", "aac", "-b:a", "192k",
        str(tmp_final)
    ])
    subprocess.run(af_cmd, check=True)
    return tmp_final


def render_ved(
    task_id:  str,
    task_dir: Path,
    files_map: Dict[str, Path],
    state:    dict,
    canvas_w: int,
    canvas_h: int,
):
    job_data = _get_task(task_id)
    if not job_data: return

    try:
        # Force even dimensions for libx264 yuv420p
        canvas_w = canvas_w if canvas_w % 2 == 0 else canvas_w - 1
        canvas_h = canvas_h if canvas_h % 2 == 0 else canvas_h - 1
        fps = int(state.get("fps", 30))
        
        out_path = RENDER_DIR / f"ved_{task_id}.mp4"
        vcodec   = ["-c:v", "libx264", "-pix_fmt", "yuv420p", "-preset", "medium", "-crf", "20"]
        acodec   = ["-c:a", "aac", "-b:a", "192k"]

        raw_clips = state.get("clips", [])
        tracks    = state.get("tracks", [])

        if not raw_clips:
            cmd = [
                "ffmpeg", "-y", "-f", "lavfi",
                "-i", f"color=c=black:s={canvas_w}x{canvas_h}:d=1",
                "-f", "lavfi", "-i", "anullsrc=r=44100:cl=stereo:d=1",
            ]
            cmd.extend(vcodec + acodec + ["-shortest", str(out_path)])
            subprocess.run(cmd, check=True, capture_output=True)
            job_data["status"] = "completed"
            job_data["result_path"] = str(out_path)
            _save_task(task_id, job_data)
            return

        track_vol  = {t["id"]: float(t.get("vol", 1.0)) for t in tracks}
        track_mute = {t["id"]: bool(t.get("muted", False)) for t in tracks}

        # 1. Parse and standardize clips
        parsed_clips = []
        for c in raw_clips:
            fname = c.get("bounce_filename")
            if not fname or fname not in files_map:
                continue
            path = files_map[fname]
            
            start_t    = float(c.get("startTime", 0))
            trim_start = float(c.get("trimStart", 0) or 0)
            speed      = float(c.get("playbackSpeed", 1.0) or 1.0)
            trim_end   = c.get("trimEnd")
            
            if trim_end is not None:
                media_end = float(trim_end)
            else:
                media_end = _probe_duration(path)
                if media_end <= trim_start:
                    media_end = trim_start + float(c.get("duration", 5.0))
            
            vis_dur = max(0.05, (media_end - trim_start) / speed)
            end_t   = start_t + vis_dur
            
            parsed_clips.append({
                "trackId": int(c.get("trackId", 0)),
                "start": start_t,
                "end": end_t,
                "trimStart": trim_start,
                "speed": speed,
                "path": path,
                "has_audio": _has_audio(path),
                "vol": track_vol.get(c.get("trackId", 0), 1.0),
                "muted": track_mute.get(c.get("trackId", 0), False),
                "raw": c
            })

        # 2. Apply Overlap Rule (Clean Cut): Latter clip truncates the end of the previous clip.
        track_groups = {}
        for pc in parsed_clips:
            track_groups.setdefault(pc["trackId"], []).append(pc)

        cleaned_clips = []
        for tid, tclips in track_groups.items():
            tclips.sort(key=lambda x: x["start"])
            for i in range(len(tclips)):
                if i < len(tclips) - 1:
                    # If this clip gets overlapped by the next clip on the same lane, truncate it
                    if tclips[i]["end"] > tclips[i+1]["start"]:
                        tclips[i]["end"] = tclips[i+1]["start"]
                # Only keep clips that still exist
                if tclips[i]["end"] > tclips[i]["start"]:
                    cleaned_clips.append(tclips[i])
        parsed_clips = cleaned_clips

        # 3. Extract boundaries to flatten the timeline
        boundaries = {0.0}
        for pc in parsed_clips:
            boundaries.add(pc["start"])
            boundaries.add(pc["end"])
        boundaries = sorted(list(boundaries))

        # 4. Create non-overlapping sequential segments
        base_segments = []
        for i in range(len(boundaries) - 1):
            t0  = boundaries[i]
            t1  = boundaries[i+1]
            dur = t1 - t0
            if dur < 0.001: 
                continue
                
            mid = t0 + (dur / 2.0)
            winner = None
            
            for pc in parsed_clips:
                if pc["start"] <= mid < pc["end"]:
                    if winner is None or pc["trackId"] > winner["trackId"]:
                        winner = pc
                    elif pc["trackId"] == winner["trackId"] and pc["start"] > winner["start"]:
                        winner = pc
                            
            base_segments.append({"t0": t0, "t1": t1, "dur": dur, "clip": winner, "is_trans": False})

        # 5. Merge contiguous fragments of the exact same clip using Python object identity (`is`)
        merged_segments = []
        for seg in base_segments:
            if not merged_segments:
                merged_segments.append(seg)
            else:
                prev = merged_segments[-1]
                # Compare object reference to see if it's identical clip data
                if prev["clip"] and seg["clip"] and prev["clip"] is seg["clip"]:
                    prev["t1"]  = seg["t1"]
                    prev["dur"] = prev["t1"] - prev["t0"]
                elif prev["clip"] is None and seg["clip"] is None:
                    prev["t1"]  = seg["t1"]
                    prev["dur"] = prev["t1"] - prev["t0"]
                else:
                    merged_segments.append(seg)
        base_segments = merged_segments

        # 6. Inject MuviTriccs Transitions via Connectors
        final_segments = []
        i = 0
        while i < len(base_segments):
            seg = base_segments[i]
            
            if i < len(base_segments) - 1:
                next_seg = base_segments[i+1]
                
                # If adjacent segments both belong to the same track, check for connector
                if seg["clip"] and next_seg["clip"] and seg["clip"]["trackId"] == next_seg["clip"]["trackId"]:
                    conn = _get_connector(seg["clip"]["raw"], next_seg["clip"]["raw"], state)
                    
                    if conn:
                        trans_frames = int(conn.get("durationFrames", 24))
                        trans_dur    = trans_frames / fps
                        
                        # Cap transition to safely fit within clip boundaries (now accurate due to merging!)
                        max_d = min(seg["dur"], next_seg["dur"]) * 0.95
                        trans_dur = min(trans_dur, max_d)
                        
                        if trans_dur > 0.05:
                            # Shrink adjacent clips
                            seg["dur"] -= trans_dur / 2.0
                            seg["t1"]  -= trans_dur / 2.0
                            next_seg["dur"] -= trans_dur / 2.0
                            next_seg["t0"]  += trans_dur / 2.0
                            
                            final_segments.append(seg)
                            
                            # Add transition segment bridging them
                            final_segments.append({
                                "is_trans": True,
                                "t0": seg["t1"],
                                "t1": next_seg["t0"],
                                "dur": trans_dur,
                                "clip_a": seg["clip"],
                                "clip_b": next_seg["clip"],
                                "conn": conn
                            })
                            i += 1
                            continue
            
            final_segments.append(seg)
            i += 1

        # 7. Pre-Render all TransSegments
        for seg in final_segments:
            if seg.get("is_trans"):
                logger.info(f"[{task_id}] Pre-rendering inline transition: {seg['conn'].get('transitionName')}")
                tmp_path = _render_temp_transition(seg, fps, canvas_w, canvas_h, task_dir)
                
                # Transform segment back into a 'standard' clip pointing to the pre-rendered temp mp4
                seg["is_trans"] = False
                seg["clip"] = {
                    "path": tmp_path,
                    "start": seg["t0"],
                    "end": seg["t1"],
                    "trimStart": 0.0,
                    "speed": 1.0,
                    "has_audio": True,
                    "vol": 1.0,
                    "muted": False,
                    "raw": {}
                }

        # 8. Build FFmpeg concat instruction for the finalized sequential segments
        input_paths = []
        filters = []
        v_labels = []
        a_labels = []

        for i, seg in enumerate(final_segments):
            dur = seg["dur"]
            pc  = seg["clip"]
            
            if pc is None:
                filters.append(f"color=c=black:s={canvas_w}x{canvas_h}:r={fps}:d={dur:.4f},setsar=1[v{i}]")
                filters.append(f"anullsrc=r=44100:cl=stereo:d={dur:.4f}[a{i}]")
            else:
                input_paths.append(pc["path"])
                idx = len(input_paths) - 1
                
                local_s = seg["t0"] - pc["start"]
                local_e = seg["t1"] - pc["start"]
                
                src_s = pc["trimStart"] + (local_s * pc["speed"])
                src_e = pc["trimStart"] + (local_e * pc["speed"])
                
                vf = (f"[{idx}:v]trim=start={src_s:.4f}:end={src_e:.4f},"
                      f"setpts={1/pc['speed']:.4f}*(PTS-STARTPTS),"
                      f"scale={canvas_w}:{canvas_h}:force_original_aspect_ratio=decrease,"
                      f"pad={canvas_w}:{canvas_h}:(ow-iw)/2:(oh-ih)/2:black,"
                      f"fps={fps},setsar=1,trim=duration={dur:.4f}[v{i}]")
                filters.append(vf)
                
                if pc["has_audio"] and not pc["muted"]:
                    atempo = _atempo_chain(pc["speed"])
                    af = (f"[{idx}:a]atrim=start={src_s:.4f}:end={src_e:.4f},"
                          f"asetpts=PTS-STARTPTS,{atempo},"
                          f"volume={pc['vol']:.4f},"
                          f"apad,atrim=duration={dur:.4f},aresample=44100[a{i}]")
                    filters.append(af)
                else:
                    filters.append(f"anullsrc=r=44100:cl=stereo:d={dur:.4f}[a{i}]")
                    
            v_labels.append(f"[v{i}]")
            a_labels.append(f"[a{i}]")

        concat_labels = "".join(f"{v}{a}" for v, a in zip(v_labels, a_labels))
        concat_str = f"{concat_labels}concat=n={len(final_segments)}:v=1:a=1[vout][aout]"
        filters.append(concat_str)

        ffmpeg_cmd = ["ffmpeg", "-y"]
        for p in input_paths:
            ffmpeg_cmd.extend(["-i", str(p)])
            
        ffmpeg_cmd.extend([
            "-filter_complex", ";".join(filters),
            "-map", "[vout]",
            "-map", "[aout]",
        ])
        ffmpeg_cmd.extend(vcodec)
        ffmpeg_cmd.extend(acodec)
        ffmpeg_cmd.append(str(out_path))

        logger.info(f"[{task_id}] Running FFmpeg sequence concat ({len(final_segments)} segments)…")
        proc = subprocess.run(ffmpeg_cmd, capture_output=True, text=True)
        if proc.returncode != 0:
            raise RuntimeError(f"FFmpeg error:\n{proc.stderr[-2000:]}")

        job_data["status"]      = "completed"
        job_data["result_path"] = str(out_path)
        _save_task(task_id, job_data)
        logger.info(f"[{task_id}] Render complete → {out_path}")

    except Exception as e:
        logger.exception(f"[{task_id}] Render failed")
        job_data["status"] = "failed"
        job_data["error"]  = str(e)
        _save_task(task_id, job_data)
    finally:
        cleanup(task_dir)

# ─── Endpoints ────────────────────────────────────────────────────────────────

@router.post("/compose-async")
async def compose_async(
    background_tasks: BackgroundTasks,
    files:      List[UploadFile] = File(...),
    state_json: str              = Form(...),
    canvas_w:   int              = Form(1024),
    canvas_h:   int              = Form(1024),
):
    task_id  = str(uuid.uuid4())
    task_dir = TEMP_DIR / task_id
    task_dir.mkdir(parents=True, exist_ok=True)

    try:
        files_map: Dict[str, Path] = {}
        for uf in files:
            dest = task_dir / (uf.filename or f"file_{len(files_map)}")
            with open(dest, "wb") as f:
                while True:
                    chunk = await uf.read(1024 * 1024)
                    if not chunk:
                        break
                    f.write(chunk)
            files_map[uf.filename] = dest

        state = json.loads(state_json)
        
        # Save initial state to disk
        job_data = {"status": "processing"}
        _save_task(task_id, job_data)

        background_tasks.add_task(
            render_ved,
            task_id, task_dir, files_map, state, canvas_w, canvas_h
        )
        return {"status": "queued", "task_id": task_id}

    except Exception as e:
        cleanup(task_dir)
        raise HTTPException(status_code=500, detail=str(e))


@router.get("/status/{task_id}")
async def task_status(task_id: str):
    task = _get_task(task_id)
    if not task:
        raise HTTPException(status_code=404, detail="Task not found")
    return {
        "status": task["status"],
        "error":  task.get("error", ""),
    }


@router.get("/download/{task_id}")
async def download_result(task_id: str):
    task = _get_task(task_id)
    if not task:
        raise HTTPException(status_code=404, detail="Task not found")
    if task["status"] != "completed":
        raise HTTPException(status_code=400, detail="Video not ready yet")
    path = Path(task["result_path"])
    if not path.exists():
        raise HTTPException(status_code=500, detail="Result file missing")
    return FileResponse(path, media_type="video/mp4", filename=path.name)

@router.delete("/cleanup/{task_id}")
async def cleanup_ved_task(task_id: str):
    task = TASKS.pop(task_id, None)
    try:
        shutil.rmtree(str(TEMP_DIR / task_id), ignore_errors=True)
    except Exception:
        pass
    return JSONResponse({"deleted": task_id})


