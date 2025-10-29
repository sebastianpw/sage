"""
Kaggle Service - Complete Kaggle API wrapper for Termux/venv
No Conda dependencies - uses direct Python Kaggle CLI via subprocess
"""
from fastapi import APIRouter, HTTPException
from pydantic import BaseModel, Field
from typing import Optional, Dict, Any
import subprocess
import logging
from pathlib import Path
import os

logger = logging.getLogger(__name__)

# Create router (no prefix here; prefix is applied in main.py)
router = APIRouter(tags=["kaggle"])

# Configuration: directories relative to pyapi/
PROJECT_ROOT = Path(__file__).resolve().parents[1]  # pyapi/
DATASETS_DIR = PROJECT_ROOT / "datasets"
KERNELS_DIR = PROJECT_ROOT / "kernels"
MODELS_DIR = PROJECT_ROOT / "models"

# Use the KAGGLE_CONFIG_DIR from the environment (set by main.py)
KAGGLE_CONFIG_DIR = Path(os.environ.get("KAGGLE_CONFIG_DIR", str(PROJECT_ROOT.parent / "token" / ".kaggle")))

# Ensure directories exist
for d in (DATASETS_DIR, KERNELS_DIR, MODELS_DIR, KAGGLE_CONFIG_DIR):
    d.mkdir(parents=True, exist_ok=True)

# =============================================================================
# HELPER FUNCTIONS
# =============================================================================

def run_kaggle_command(command: str, timeout: int = 300) -> Dict[str, Any]:
    """
    Execute a Kaggle CLI command using the system-kaggle command inside venv.
    Returns stdout/stderr/returncode/command.
    """
    try:
        full_command = f"kaggle {command}"
        logger.info("Executing: %s", full_command)

        # Use a copy of current env to ensure KAGGLE_CONFIG_DIR is passed
        env = os.environ.copy()

        # Run the command
        result = subprocess.run(
            full_command,
            shell=True,
            capture_output=True,
            text=True,
            timeout=timeout,
            env=env
        )

        # Log outputs for debugging
        stdout = (result.stdout or "").strip()
        stderr = (result.stderr or "").strip()
        if stdout:
            logger.debug("kaggle stdout: %s", stdout)
        if stderr:
            logger.info("kaggle stderr: %s", stderr)

        return {
            "stdout": stdout,
            "stderr": stderr,
            "returncode": result.returncode,
            "command": full_command
        }

    except subprocess.TimeoutExpired:
        logger.error("Command timed out after %s seconds: %s", timeout, command)
        return {
            "stdout": "",
            "stderr": f"Command timed out after {timeout} seconds",
            "returncode": -1,
            "command": command
        }
    except Exception as e:
        logger.exception("Error executing kaggle command: %s", e)
        return {
            "stdout": "",
            "stderr": str(e),
            "returncode": -1,
            "command": command
        }

# =============================================================================
# Pydantic request models
# =============================================================================
class CompetitionListRequest(BaseModel):
    search: Optional[str] = None
    sort_by: str = "latestDeadline"
    category: Optional[str] = None
    page: int = Field(1, ge=1)

class CompetitionDownloadRequest(BaseModel):
    competition: str
    file_name: Optional[str] = None
    force: bool = False
    quiet: bool = False

class DatasetListRequest(BaseModel):
    search: Optional[str] = None
    sort_by: str = "hottest"
    size: Optional[str] = None
    file_type: Optional[str] = None
    license: Optional[str] = None
    tags: Optional[str] = None
    user: Optional[str] = None
    mine: bool = False
    page: int = Field(1, ge=1)
    max_size: int = Field(20, ge=1, le=100)

class DatasetDownloadRequest(BaseModel):
    dataset: str
    file_name: Optional[str] = None
    unzip: bool = True
    force: bool = False
    output_dir: Optional[str] = None

class KernelListRequest(BaseModel):
    search: Optional[str] = None
    mine: bool = False
    user: Optional[str] = None
    dataset: Optional[str] = None
    competition: Optional[str] = None
    parent_kernel: Optional[str] = None
    sort_by: str = "hotness"
    page: int = Field(1, ge=1)

class KernelPushRequest(BaseModel):
    path: str

class KernelPullRequest(BaseModel):
    kernel: str
    path: Optional[str] = None
    metadata: bool = False

class KernelOutputRequest(BaseModel):
    kernel: str
    path: Optional[str] = None
    force: bool = False
    quiet: bool = False

class KernelStatusRequest(BaseModel):
    kernel: str

# =============================================================================
# Endpoints (unchanged behaviour, with debug-friendly returns)
# =============================================================================

@router.post("/competitions/list")
def list_competitions(req: CompetitionListRequest):
    try:
        cmd = f"competitions list --page {req.page}"
        if req.search:
            cmd += f" --search '{req.search}'"
        if req.category:
            cmd += f" --category {req.category}"
        cmd += f" --sort-by {req.sort_by}"

        result = run_kaggle_command(cmd)
        if result["returncode"] == 0:
            return {"status": "success", "data": result["stdout"], "page": req.page}
        raise HTTPException(status_code=500, detail={"error": result["stderr"], "cmd": result["command"]})
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

@router.post("/competitions/download")
def download_competition(req: CompetitionDownloadRequest):
    try:
        output_path = DATASETS_DIR / f"competitions/{req.competition}"
        output_path.mkdir(parents=True, exist_ok=True)

        cmd = f"competitions download -c {req.competition} -p {output_path}"
        if req.file_name:
            cmd += f" -f {req.file_name}"
        if req.force:
            cmd += " --force"
        if req.quiet:
            cmd += " --quiet"

        result = run_kaggle_command(cmd)
        if result["returncode"] == 0:
            return {
                "status": "success",
                "competition": req.competition,
                "output_path": str(output_path),
                "stdout": result["stdout"]
            }
        raise HTTPException(status_code=500, detail={"error": result["stderr"], "cmd": result["command"]})
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

@router.post("/competitions/submit")
def submit_to_competition(req: dict):
    try:
        cmd = f"competitions submit -c {req['competition']} -f {req['file_path']} -m '{req['message']}'"
        result = run_kaggle_command(cmd)
        if result["returncode"] == 0:
            return {"status": "success", "competition": req['competition'], "message": "Submission successful", "stdout": result["stdout"]}
        raise HTTPException(status_code=500, detail={"error": result["stderr"], "cmd": result["command"]})
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

@router.post("/datasets/list")
def list_datasets(req: DatasetListRequest):
    try:
        cmd = f"datasets list --sort-by {req.sort_by} --page {req.page} --max-size {req.max_size}"
        if req.search:
            cmd += f" --search '{req.search}'"
        if req.size:
            cmd += f" --size {req.size}"
        if req.file_type:
            cmd += f" --file-type {req.file_type}"
        if req.license:
            cmd += f" --license '{req.license}'"
        if req.tags:
            cmd += f" --tags '{req.tags}'"
        if req.user:
            cmd += f" --user {req.user}"
        if req.mine:
            cmd += " --mine"

        result = run_kaggle_command(cmd)
        if result["returncode"] == 0:
            return {"status": "success", "data": result["stdout"], "page": req.page}
        raise HTTPException(status_code=500, detail={"error": result["stderr"], "cmd": result["command"]})
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

@router.post("/datasets/files")
def list_dataset_files(req: dict):
    try:
        cmd = f"datasets files {req['dataset']}"
        result = run_kaggle_command(cmd)
        if result["returncode"] == 0:
            return {"status": "success", "dataset": req['dataset'], "files": result["stdout"]}
        raise HTTPException(status_code=500, detail={"error": result["stderr"], "cmd": result["command"]})
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

@router.post("/datasets/download")
def download_dataset(req: DatasetDownloadRequest):
    try:
        output_path = DATASETS_DIR / (req.output_dir or req.dataset.replace('/', '_'))
        output_path.mkdir(parents=True, exist_ok=True)

        cmd = f"datasets download {req.dataset} -p {output_path}"
        if req.file_name:
            cmd += f" -f {req.file_name}"
        if req.unzip:
            cmd += " --unzip"
        if req.force:
            cmd += " --force"

        result = run_kaggle_command(cmd)
        if result["returncode"] == 0:
            return {"status": "success", "dataset": req.dataset, "output_path": str(output_path), "stdout": result["stdout"]}
        raise HTTPException(status_code=500, detail={"error": result["stderr"], "cmd": result["command"]})
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

@router.post("/datasets/create")
def create_dataset(req: dict):
    try:
        cmd = f"datasets create -p {req['path']} --dir-mode {req.get('dir_mode', 'skip')}"
        if req.get('public', False):
            cmd += " --public"
        if req.get('quiet', False):
            cmd += " --quiet"

        result = run_kaggle_command(cmd)
        if result["returncode"] == 0:
            return {"status": "success", "message": "Dataset created", "stdout": result["stdout"]}
        raise HTTPException(status_code=500, detail={"error": result["stderr"], "cmd": result["command"]})
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

@router.post("/kernels/list")
def list_kernels(req: KernelListRequest):
    """List Kaggle kernels (return CSV so PHP can parse reliably)"""
    try:
        # Always request CSV output so clients expecting CSV (old PHP parser) work unchanged
        cmd = f"kernels list --sort-by {req.sort_by} --page {req.page} --csv"

        if req.search:
            cmd += f" --search '{req.search}'"
        if req.mine:
            cmd += " --mine"
        if req.user:
            cmd += f" --user {req.user}"
        if req.dataset:
            cmd += f" --dataset {req.dataset}"
        if req.competition:
            cmd += f" --competition {req.competition}"
        if req.parent_kernel:
            cmd += f" --parent-kernel {req.parent_kernel}"

        result = run_kaggle_command(cmd)

        if result["returncode"] == 0:
            return {"status": "success", "data": result["stdout"], "page": req.page}
        raise HTTPException(status_code=500, detail={"error": result["stderr"]})
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

@router.post("/kernels/push")
def push_kernel(req: KernelPushRequest):
    try:
        cmd = f"kernels push -p {req.path}"
        result = run_kaggle_command(cmd)
        if result["returncode"] == 0:
            return {"status": "success", "message": "Kernel pushed successfully", "stdout": result["stdout"]}
        raise HTTPException(status_code=500, detail={"error": result["stderr"], "cmd": result["command"]})
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

@router.post("/kernels/pull")
def pull_kernel(req: KernelPullRequest):
    try:
        path = req.path or str(KERNELS_DIR / req.kernel.replace('/', '_'))
        Path(path).mkdir(parents=True, exist_ok=True)

        cmd = f"kernels pull {req.kernel} -p {path}"
        if req.metadata:
            cmd += " --metadata"

        result = run_kaggle_command(cmd)
        if result["returncode"] == 0:
            return {"status": "success", "kernel": req.kernel, "path": path, "stdout": result["stdout"]}
        raise HTTPException(status_code=500, detail={"error": result["stderr"], "cmd": result["command"]})
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

@router.post("/kernels/output")
def download_kernel_output(req: KernelOutputRequest):
    try:
        path = req.path or str(KERNELS_DIR / f"{req.kernel.replace('/', '_')}_output")
        Path(path).mkdir(parents=True, exist_ok=True)

        cmd = f"kernels output {req.kernel} -p {path}"
        if req.force:
            cmd += " --force"
        if req.quiet:
            cmd += " --quiet"

        result = run_kaggle_command(cmd)
        if result["returncode"] == 0:
            return {"status": "success", "kernel": req.kernel, "output_path": path, "stdout": result["stdout"]}
        raise HTTPException(status_code=500, detail={"error": result["stderr"], "cmd": result["command"]})
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

@router.post("/kernels/status")
def get_kernel_status(req: KernelStatusRequest):
    try:
        cmd = f"kernels status {req.kernel}"
        result = run_kaggle_command(cmd)
        if result["returncode"] == 0:
            return {"status": "success", "kernel": req.kernel, "info": result["stdout"]}
        raise HTTPException(status_code=500, detail={"error": result["stderr"], "cmd": result["command"]})
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

@router.get("/config/view")
def view_config():
    try:
        cmd = "config view"
        result = run_kaggle_command(cmd)
        if result["returncode"] == 0:
            return {"status": "success", "config": result["stdout"]}
        raise HTTPException(status_code=500, detail={"error": result["stderr"], "cmd": result["command"]})
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

@router.post("/config/set")
def set_config(req: dict):
    try:
        cmd = f"config set -n {req['key']} -v {req['value']}"
        result = run_kaggle_command(cmd)
        if result["returncode"] == 0:
            return {"status": "success", "message": f"Set {req['key']}={req['value']}", "stdout": result["stdout"]}
        raise HTTPException(status_code=500, detail={"error": result["stderr"], "cmd": result["command"]})
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

@router.get("/status")
def kaggle_status():
    try:
        result = run_kaggle_command("--version")
        if result["returncode"] == 0:
            return {
                "status": "operational",
                "message": "Kaggle API is configured and accessible",
                "version": result["stdout"].strip(),
                "environment": "venv (Termux)",
                "kaggle_config_dir": str(KAGGLE_CONFIG_DIR),
                "endpoints": {
                    "competitions": ["list", "download", "submit"],
                    "datasets": ["list", "files", "download", "create"],
                    "kernels": ["list", "push", "pull", "output", "status"],
                    "config": ["view", "set"]
                }
            }
        else:
            return {"status": "error", "message": "Kaggle CLI not accessible", "error": result["stderr"]}
    except Exception as e:
        return {"status": "error", "message": str(e)}
