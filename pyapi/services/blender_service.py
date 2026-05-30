import os
import json
import uuid
import subprocess
import shutil
import logging
from pathlib import Path
from fastapi import APIRouter, UploadFile, File, Form, HTTPException, BackgroundTasks
from fastapi.responses import FileResponse, JSONResponse
from typing import List

logger = logging.getLogger(__name__)
router = APIRouter(tags=["blender"])

# --- CONFIG ---
# Storage on Tablet
BASE_STORAGE = Path("/var/www/sage/blender_workspace")
BASE_STORAGE.mkdir(parents=True, exist_ok=True)

BLENDER_EXEC = "/usr/bin/blender"
SCRIPT_PATH = Path(__file__).resolve().parents[1] / "sage_blender.py"

TASKS = {}

def cleanup_job(job_dir: Path):
    try:
        shutil.rmtree(job_dir)
    except:
        pass

def run_blender_task(task_id: str, job_dir: Path):
    try:
        cmd = [
            BLENDER_EXEC,
            "--background",
            "--python", str(SCRIPT_PATH),
            "--",
            str(job_dir)
        ]
        
        logger.info(f"Task {task_id}: Starting Blender...")
        result = subprocess.run(cmd, capture_output=True, text=True)
        
        output_dir = job_dir / "output"
        # Find the resulting file (Blender adds frame numbers e.g. _0001-0060.mp4)
        # We look for the first mp4 in output dir
        found_video = None
        if output_dir.exists():
            for f in output_dir.iterdir():
                if f.suffix == ".mp4":
                    found_video = f
                    break
        
        if result.returncode == 0 and found_video:
            TASKS[task_id]["status"] = "completed"
            TASKS[task_id]["video_path"] = str(found_video)
            logger.info(f"Task {task_id}: Success")
        else:
            TASKS[task_id]["status"] = "failed"
            TASKS[task_id]["error"] = result.stderr
            logger.error(f"Task {task_id}: Failed.\n{result.stderr}")

    except Exception as e:
        TASKS[task_id]["status"] = "failed"
        TASKS[task_id]["error"] = str(e)

@router.post("/render/blender-async")
async def render_blender_async(
    background_tasks: BackgroundTasks,
    files: List[UploadFile] = File(...),
    job_data: str = Form(...) # JSON String
):
    task_id = str(uuid.uuid4())
    job_dir = BASE_STORAGE / task_id
    assets_dir = job_dir / "assets"
    assets_dir.mkdir(parents=True, exist_ok=True)
    
    try:
        # 1. Save Assets
        for file in files:
            file_path = assets_dir / file.filename
            with open(file_path, "wb") as buffer:
                shutil.copyfileobj(file.file, buffer)
        
        # 2. Save JSON Config
        # Inject job_id into data
        parsed_data = json.loads(job_data)
        parsed_data['job_id'] = task_id
        
        with open(job_dir / "job.json", "w") as f:
            json.dump(parsed_data, f, indent=2)
            
        # 3. Queue
        TASKS[task_id] = {"status": "processing", "job_dir": str(job_dir)}
        background_tasks.add_task(run_blender_task, task_id, job_dir)
        
        return {"status": "queued", "task_id": task_id}
        
    except Exception as e:
        cleanup_job(job_dir)
        raise HTTPException(500, str(e))

@router.get("/render/status/{task_id}")
async def get_status(task_id: str):
    if task_id not in TASKS:
        raise HTTPException(404, "Task not found")
        
    task = TASKS[task_id]
    if task['status'] == 'completed':
        return FileResponse(task['video_path'], media_type="video/mp4", filename=f"render_{task_id}.mp4")
    elif task['status'] == 'failed':
        return JSONResponse(status_code=500, content=task)
    else:
        return {"status": "processing"}
