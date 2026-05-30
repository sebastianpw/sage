# pyapi/services/daw_service.py
"""
SAGE DAW — Pure FFmpeg Audio Bouncer
Requires ZERO heavy Python libraries. Recreates Envelopes, Gain, Compressors, EQ3, and Limiters natively in C via FFmpeg Filtergraphs.
"""
import logging
import json
import uuid
import shutil
import subprocess
from pathlib import Path
from typing import List, Dict

from fastapi import APIRouter, UploadFile, File, Form, HTTPException, BackgroundTasks
from fastapi.responses import FileResponse

logger = logging.getLogger(__name__)
router = APIRouter(tags=["daw"])

PROJECT_ROOT  = Path(__file__).resolve().parents[1]
RENDER_DIR    = PROJECT_ROOT.parent / "services_data" / "daw_renders"
TEMP_DIR      = PROJECT_ROOT.parent / "services_data" / "daw_temp"
RENDER_DIR.mkdir(parents=True, exist_ok=True)
TEMP_DIR.mkdir(parents=True, exist_ok=True)

TASKS: Dict[str, Dict] = {}

def cleanup(path: Path):
    try:
        if path.exists():
            shutil.rmtree(path) if path.is_dir() else path.unlink()
    except Exception as e:
        logger.warning(f"Cleanup failed {path}: {e}")

def _build_fx_string(curr_out: str, fx_chain: list, p_state: dict, prefix: str) -> tuple:
    """Translates your Tone.js plugin state to FFmpeg native filters"""
    filters = []
    
    for fx_idx, fx in enumerate(fx_chain):
        s = p_state.get(fx, {})
        next_out = f"{prefix}_fx{fx_idx}"
        
        if fx == "gain":
            filters.append(f"[{curr_out}]volume={s.get('gainDb',0)}dB[{next_out}]")
        elif fx == "volume":
            filters.append(f"[{curr_out}]volume={s.get('volumeDb',0)}dB[{next_out}]")
        elif fx == "compressor":
            filters.append(f"[{curr_out}]acompressor=threshold={s.get('threshold',-24)}dB:ratio={s.get('ratio',4)}:attack={s.get('attack',10)}:release={s.get('release',100)}:knee={s.get('knee',6)}[{next_out}]")
        elif fx == "eq3band":
            filters.append(f"[{curr_out}]bass=g={s.get('low',0)}:f=80,equalizer=f=1000:width_type=o:w=1:g={s.get('mid',0)},treble=g={s.get('high',0)}:f=8000[{next_out}]")
        elif fx == "limiter":
            filters.append(f"[{curr_out}]alimiter=limit={s.get('ceiling',-0.3)}dB:attack=5:release={s.get('release',50)}[{next_out}]")
        else:
            filters.append(f"[{curr_out}]anull[{next_out}]")
            
        curr_out = next_out
        
    return curr_out, filters


def render_bounce(task_id: str, task_dir: Path, files_map: Dict[str, Path], state: dict):
    try:
        out_path = RENDER_DIR / f"bounce_{task_id}.wav"
        
        ffmpeg_cmd = ["ffmpeg", "-y"]
        file_indexes = {}
        idx = 0
        for filename, path in files_map.items():
            ffmpeg_cmd.extend(["-i", str(path)])
            file_indexes[filename] = idx
            idx += 1
            
        has_solo = any(t.get("solo", False) for t in state.get("tracks", []))
        
        filters = []
        track_outs = []
        
        for t in state.get("tracks", []):
            if t.get("muted") or (has_solo and not t.get("solo")):
                continue
                
            c_outs = []
            for c_idx_local, c in enumerate(state.get("clips", [])):
                if c.get("trackId") == t.get("id"):
                    fname = c.get("bounce_filename")
                    if fname not in file_indexes: continue
                    
                    c_idx = file_indexes[fname]
                    start_ms = int(float(c.get("startTime", 0)) * 1000)
                    
                    # New: Extract Cut Coordinates
                    trim_start = float(c.get("trimStart", 0))
                    duration   = float(c.get("duration", 0))
                    
                    d_out = f"c_{c_idx_local}_d"
                    
                    if duration > 0:
                        # Applies trim and resets PTS so the envelope aligns with the visual clip correctly!
                        filters.append(f"[{c_idx}:a]atrim=start={trim_start}:duration={duration},asetpts=PTS-STARTPTS,adelay={start_ms}:all=1,aformat=sample_fmts=fltp:channel_layouts=stereo[{d_out}]")
                    else:
                        filters.append(f"[{c_idx}:a]adelay={start_ms}:all=1,aformat=sample_fmts=fltp:channel_layouts=stereo[{d_out}]")
                    
                    # Envelope Processor
                    pts = sorted(c.get("envelopePoints", []), key=lambda x: float(x["time"]))
                    if pts:
                        if float(pts[0]["time"]) > 0:
                            pts.insert(0, {"time": 0, "volume": pts[0]["volume"]})
                        
                        expr = str(pts[-1]["volume"])
                        c_start = float(c["startTime"])
                        
                        for i in range(len(pts)-2, -1, -1):
                            t_curr, v_curr = float(pts[i]["time"]), float(pts[i]["volume"])
                            t_next, v_next = float(pts[i+1]["time"]), float(pts[i+1]["volume"])
                            if t_next == t_curr: continue
                            
                            # Linear Interpolator matching Tone.js Envelope Math
                            interp = f"({v_curr}+({v_next}-{v_curr})*(t-{c_start}-{t_curr})/({t_next}-{t_curr}))"
                            expr = f"if(lt(t-{c_start},{t_next}),{interp},{expr})"
                            
                        e_out = f"c_{c_idx_local}_env"
                        filters.append(f"[{d_out}]volume=eval=frame:volume='{expr}'[{e_out}]")
                        c_outs.append(f"[{e_out}]")
                    else:
                        c_outs.append(f"[{d_out}]")
                        
            if not c_outs:
                continue
                
            t_mixed = f"t_{t['id']}_m"
            if len(c_outs) == 1:
                filters.append(f"{c_outs[0]}volume=1.0[{t_mixed}]")
            else:
                inputs_str = "".join(c_outs)
                # Modern FFmpeg normalize=0 ensures clipping/volume drops don't happen automatically
                filters.append(f"{inputs_str}amix=inputs={len(c_outs)}:duration=longest:normalize=0[{t_mixed}]")
                
            # Track Vol + Track FX
            t_vol = float(t.get("vol", 1.0))
            curr_out = f"t_{t['id']}_v"
            filters.append(f"[{t_mixed}]volume={t_vol}[{curr_out}]")
            
            curr_out, fx_filters = _build_fx_string(curr_out, t.get("fxChain", []), t.get("pluginState", {}), f"t_{t['id']}")
            filters.extend(fx_filters)
            
            track_outs.append(f"[{curr_out}]")
            
        if not track_outs:
            # Generate 1 sec of pure silence if the mix is entirely empty
            ffmpeg_cmd = ["ffmpeg", "-y", "-f", "lavfi", "-i", "anullsrc=r=44100:cl=stereo", "-t", "1", str(out_path)]
        else:
            m_mixed = "master_m"
            if len(track_outs) == 1:
                filters.append(f"{track_outs[0]}volume=1.0[{m_mixed}]")
            else:
                inputs_str = "".join(track_outs)
                filters.append(f"{inputs_str}amix=inputs={len(track_outs)}:duration=longest:normalize=0[{m_mixed}]")
                
            # Master Vol + Master FX
            m_vol = float(state.get("master", {}).get("vol", 1.0))
            curr_out = "master_v"
            filters.append(f"[{m_mixed}]volume={m_vol}[{curr_out}]")
            
            curr_out, fx_filters = _build_fx_string(curr_out, state.get("master", {}).get("fxChain", []), state.get("master", {}).get("pluginState", {}), "master")
            filters.extend(fx_filters)
            
            filters.append(f"[{curr_out}]anull[out]")
            
            filter_str = ";".join(filters)
            ffmpeg_cmd.extend(["-filter_complex", filter_str, "-map", "[out]", str(out_path)])

        logger.info(f"[{task_id}] Executing Bounce...")
        proc = subprocess.run(ffmpeg_cmd, capture_output=True, text=True)
        
        if proc.returncode != 0:
            raise RuntimeError(f"FFmpeg failed: {proc.stderr}")

        TASKS[task_id]["status"] = "completed"
        TASKS[task_id]["result_path"] = str(out_path)

    except Exception as e:
        logger.exception(f"[{task_id}] Render failed")
        TASKS[task_id]["status"] = "failed"
        TASKS[task_id]["error"]  = str(e)
    finally:
        cleanup(task_dir)

@router.post("/bounce-async")
async def bounce_async(
    background_tasks: BackgroundTasks,
    files: List[UploadFile] = File(...),
    state_json: str = Form(...)
):
    task_id  = str(uuid.uuid4())
    task_dir = TEMP_DIR / task_id
    task_dir.mkdir(parents=True, exist_ok=True)

    try:
        files_map: Dict[str, Path] = {}
        for uf in files:
            dest = task_dir / uf.filename
            with open(dest, "wb") as f: shutil.copyfileobj(uf.file, f)
            files_map[uf.filename] = dest

        state = json.loads(state_json)
        TASKS[task_id] = {"status": "processing"}

        background_tasks.add_task(
            render_bounce,
            task_id, task_dir, files_map, state
        )
        return {"status": "queued", "task_id": task_id}

    except Exception as e:
        cleanup(task_dir)
        raise HTTPException(status_code=500, detail=str(e))

@router.get("/status/{task_id}")
async def task_status(task_id: str):
    if task_id not in TASKS:
        raise HTTPException(status_code=404, detail="Task not found")
    return {"status": TASKS[task_id]["status"], "error": TASKS[task_id].get("error", "")}

@router.get("/download/{task_id}")
async def download_result(task_id: str):
    if task_id not in TASKS:
        raise HTTPException(status_code=404, detail="Task not found")
    task = TASKS[task_id]
    if task["status"] != "completed":
        raise HTTPException(status_code=400, detail="Audio is not ready yet.")
    path = Path(task["result_path"])
    if not path.exists():
        raise HTTPException(status_code=500, detail="Result file missing from server.")
    return FileResponse(path, media_type="audio/wav", filename="sage_mixdown.wav")