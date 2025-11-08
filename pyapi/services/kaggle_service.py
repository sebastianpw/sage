"""
Kaggle Service - Complete Kaggle API wrapper for Termux/venv
No Conda dependencies - uses direct Python Kaggle CLI via subprocess (argument-list style)
"""
from fastapi import APIRouter, HTTPException
from pydantic import BaseModel, Field
from typing import Optional, Dict, Any, Union, List
import subprocess
import logging
from pathlib import Path
import os
import shlex
import csv
import io

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

def _format_cmd_for_log(args: List[str]) -> str:
    """Return a safely-quoted string for logging / returned responses."""
    return " ".join(shlex.quote(a) for a in args)


def _clean_kaggle_stdout(stdout: str) -> str:
    """
    Clean kaggle stdout by removing leading non-CSV lines (warnings) until a CSV header is found.
    Looks for a header line containing 'ref' and 'title' (typical kernels CSV header),
    otherwise falls back to removing pure 'Warning:' lines.
    """
    if not stdout:
        return ""

    lines = stdout.splitlines()
    # quick removal of lines starting with 'Warning:' (case-insensitive)
    stripped = [ln for ln in lines if not ln.strip().lower().startswith("warning:")]

    # If we removed something and the remaining begins with a header-like line, return that.
    if len(stripped) < len(lines):
        # find header index in stripped
        for i, ln in enumerate(stripped):
            if 'ref' in ln.lower() and 'title' in ln.lower():
                return "\n".join(stripped[i:]).strip()
        # otherwise return joined stripped as best-effort
        return "\n".join(stripped).strip()

    # If no 'Warning:' lines, try to find header in original
    for i, ln in enumerate(lines):
        if 'ref' in ln.lower() and 'title' in ln.lower():
            return "\n".join(lines[i:]).strip()

    # fallback: return original stdout (no header found)
    return stdout.strip()


def _parse_csv_to_list(csv_text: str) -> List[Dict[str, str]]:
    """Parse CSV text (first line header) into list of dicts. If parse fails return empty list."""
    if not csv_text:
        return []
    f = io.StringIO(csv_text)
    reader = csv.reader(f)
    try:
        headers = next(reader)
    except StopIteration:
        return []
    items = []
    for row in reader:
        if row == [None] or len(row) == 0:
            continue
        # skip empty first column rows
        if len(row) == 0 or (len(row) > 0 and (row[0] is None or str(row[0]).strip() == "")):
            continue
        # normalize shorter rows
        row += [''] * (len(headers) - len(row))
        item = {headers[i]: row[i] for i in range(len(headers))}
        items.append(item)
    return items


def run_kaggle_command(command: Union[str, List[str]], timeout: int = 300) -> Dict[str, Any]:
    """
    Execute a Kaggle CLI command using subprocess with an argv list (safe).
    Accepts either a string (will be split) or a list of args. Ensures KAGGLE_CONFIG_DIR
    is present in env. Returns stdout/stderr/returncode/command (display string) and
    also raw_stdout/raw_stderr for debugging. stdout is cleaned of leading warnings.
    """
    try:
        # normalize to list of args
        if isinstance(command, str):
            args = shlex.split(command)
        else:
            args = list(command)

        # ensure the tool name is the first token
        if not args or args[0] != "kaggle":
            args = ["kaggle"] + args

        display_cmd = _format_cmd_for_log(args)
        logger.info("Executing: %s", display_cmd)

        env = os.environ.copy()
        # ensure KAGGLE_CONFIG_DIR is always present in the environment the subprocess sees
        env["KAGGLE_CONFIG_DIR"] = str(KAGGLE_CONFIG_DIR)

        result = subprocess.run(
            args,
            capture_output=True,
            text=True,
            timeout=timeout,
            env=env,
            check=False
        )

        raw_stdout = (result.stdout or "").rstrip("\n")
        raw_stderr = (result.stderr or "").rstrip("\n")

        # Clean Kaggle stdout: remove permission-warning lines and any other leading non-CSV scaffolding
        stdout = _clean_kaggle_stdout(raw_stdout)

        if raw_stdout:
            logger.debug("kaggle raw_stdout: %s", raw_stdout)
        if raw_stderr:
            logger.info("kaggle raw_stderr: %s", raw_stderr)

        return {
            "stdout": stdout,
            "raw_stdout": raw_stdout,
            "stderr": raw_stderr,
            "raw_stderr": raw_stderr,
            "returncode": result.returncode,
            "command": display_cmd
        }

    except subprocess.TimeoutExpired:
        logger.exception("Command timed out after %s seconds: %s", timeout, command)
        return {
            "stdout": "",
            "raw_stdout": "",
            "stderr": f"Command timed out after {timeout} seconds",
            "raw_stderr": "",
            "returncode": -1,
            "command": _format_cmd_for_log(["kaggle"] + (shlex.split(command) if isinstance(command, str) else list(command)))
        }
    except Exception as e:
        logger.exception("Error executing kaggle command: %s", e)
        return {
            "stdout": "",
            "raw_stdout": "",
            "stderr": str(e),
            "raw_stderr": "",
            "returncode": -1,
            "command": _format_cmd_for_log(["kaggle"] + (shlex.split(command) if isinstance(command, str) else list(command)))
        }


# =============================================================================
# Pydantic request models (unchanged except optional page_size)
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
    page_size: Optional[int] = None  # maps to --page-size
    format: Optional[str] = "csv"     # 'csv' (default) or 'json' (parsed)


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
# Endpoints (use args lists, treat rc==0 as success even if stdout empty)
# =============================================================================

@router.post("/competitions/list")
def list_competitions(req: CompetitionListRequest):
    try:
        args = ["competitions", "list", "--page", str(req.page), "--sort-by", req.sort_by]
        if req.search:
            args += ["--search", req.search]
        if req.category:
            args += ["--category", req.category]

        result = run_kaggle_command(args)
        if result["returncode"] != 0:
            raise HTTPException(status_code=500, detail={"error": result["stderr"], "cmd": result["command"], "raw_stdout": result.get("raw_stdout")})
        return {"status": "success", "data": result["stdout"], "page": req.page, "cmd": result["command"], "raw_stdout": result.get("raw_stdout")}
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/competitions/download")
def download_competition(req: CompetitionDownloadRequest):
    try:
        output_path = DATASETS_DIR / f"competitions/{req.competition}"
        output_path.mkdir(parents=True, exist_ok=True)

        args = ["competitions", "download", "-c", req.competition, "-p", str(output_path)]
        if req.file_name:
            args += ["-f", req.file_name]
        if req.force:
            args.append("--force")
        if req.quiet:
            args.append("--quiet")

        result = run_kaggle_command(args)
        if result["returncode"] != 0:
            raise HTTPException(status_code=500, detail={"error": result["stderr"], "cmd": result["command"], "raw_stdout": result.get("raw_stdout")})
        return {
            "status": "success",
            "competition": req.competition,
            "output_path": str(output_path),
            "stdout": result["stdout"],
            "cmd": result["command"],
            "raw_stdout": result.get("raw_stdout")
        }
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/competitions/submit")
def submit_to_competition(req: dict):
    try:
        args = ["competitions", "submit", "-c", req["competition"], "-f", req["file_path"], "-m", req["message"]]
        result = run_kaggle_command(args)
        if result["returncode"] != 0:
            raise HTTPException(status_code=500, detail={"error": result["stderr"], "cmd": result["command"], "raw_stdout": result.get("raw_stdout")})
        return {"status": "success", "competition": req['competition'], "message": "Submission successful", "stdout": result["stdout"], "cmd": result["command"], "raw_stdout": result.get("raw_stdout")}
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/datasets/list")
def list_datasets(req: DatasetListRequest):
    try:
        args = ["datasets", "list", "--sort-by", req.sort_by, "--page", str(req.page), "--max-size", str(req.max_size)]
        if req.search:
            args += ["--search", req.search]
        if req.size:
            args += ["--size", req.size]
        if req.file_type:
            args += ["--file-type", req.file_type]
        if req.license:
            args += ["--license", req.license]
        if req.tags:
            args += ["--tags", req.tags]
        if req.user:
            args += ["--user", req.user]
        if req.mine:
            args.append("--mine")

        result = run_kaggle_command(args)
        if result["returncode"] != 0:
            raise HTTPException(status_code=500, detail={"error": result["stderr"], "cmd": result["command"], "raw_stdout": result.get("raw_stdout")})
        return {"status": "success", "data": result["stdout"], "page": req.page, "cmd": result["command"], "raw_stdout": result.get("raw_stdout")}
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/datasets/files")
def list_dataset_files(req: dict):
    try:
        args = ["datasets", "files", req["dataset"]]
        result = run_kaggle_command(args)
        if result["returncode"] != 0:
            raise HTTPException(status_code=500, detail={"error": result["stderr"], "cmd": result["command"], "raw_stdout": result.get("raw_stdout")})
        return {"status": "success", "dataset": req['dataset'], "files": result["stdout"], "cmd": result["command"], "raw_stdout": result.get("raw_stdout")}
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/datasets/download")
def download_dataset(req: DatasetDownloadRequest):
    try:
        output_path = DATASETS_DIR / (req.output_dir or req.dataset.replace('/', '_'))
        output_path.mkdir(parents=True, exist_ok=True)

        args = ["datasets", "download", req.dataset, "-p", str(output_path)]
        if req.file_name:
            args += ["-f", req.file_name]
        if req.unzip:
            args.append("--unzip")
        if req.force:
            args.append("--force")

        result = run_kaggle_command(args)
        if result["returncode"] != 0:
            raise HTTPException(status_code=500, detail={"error": result["stderr"], "cmd": result["command"], "raw_stdout": result.get("raw_stdout")})
        return {"status": "success", "dataset": req.dataset, "output_path": str(output_path), "stdout": result["stdout"], "cmd": result["command"], "raw_stdout": result.get("raw_stdout")}
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/datasets/create")
def create_dataset(req: dict):
    try:
        args = ["datasets", "create", "-p", req['path'], "--dir-mode", req.get('dir_mode', 'skip')]
        if req.get('public', False):
            args.append("--public")
        if req.get('quiet', False):
            args.append("--quiet")

        result = run_kaggle_command(args)
        if result["returncode"] != 0:
            raise HTTPException(status_code=500, detail={"error": result["stderr"], "cmd": result["command"], "raw_stdout": result.get("raw_stdout")})
        return {"status": "success", "message": "Dataset created", "stdout": result["stdout"], "cmd": result["command"], "raw_stdout": result.get("raw_stdout")}
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/kernels/list")
def list_kernels(req: KernelListRequest):
    """List Kaggle kernels (return CSV so PHP can parse reliably).
    Returns:
      - data: cleaned CSV string (backwards compatible)
      - parsed: optional list of dicts (if parsing succeeds)
      - raw_stdout, raw_stderr, cmd for debugging
    """
    try:
        args = ["kernels", "list", "--sort-by", req.sort_by, "--page", str(req.page), "--csv"]
        if req.search:
            args += ["--search", req.search]
        if req.mine:
            args.append("--mine")
        if req.user:
            args += ["--user", req.user]
        if req.dataset:
            args += ["--dataset", req.dataset]
        if req.competition:
            args += ["--competition", req.competition]
        if req.parent_kernel:
            # CLI expects --parent <owner/slug>
            args += ["--parent", req.parent_kernel]
        if req.page_size:
            args += ["--page-size", str(req.page_size)]

        result = run_kaggle_command(args)
        if result["returncode"] != 0:
            raise HTTPException(status_code=500, detail={"error": result["stderr"], "cmd": result["command"], "raw_stdout": result.get("raw_stdout")})

        csv_text = result.get("stdout", "")  # cleaned CSV
        parsed = None
        try:
            parsed = _parse_csv_to_list(csv_text)
        except Exception:
            parsed = None

        # Keep backward-compatible `data` (CSV) and add parsed JSON if we could parse it.
        resp = {
            "status": "success",
            "data": csv_text,
            "page": req.page,
            "cmd": result["command"],
            "raw_stdout": result.get("raw_stdout"),
            "raw_stderr": result.get("raw_stderr")
        }
        if parsed is not None and len(parsed) > 0:
            resp["parsed"] = parsed
        else:
            resp["parsed"] = []

        return resp
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/kernels/push")
def push_kernel(req: KernelPushRequest):
    try:
        args = ["kernels", "push", "-p", req.path]
        result = run_kaggle_command(args)
        if result["returncode"] != 0:
            raise HTTPException(status_code=500, detail={"error": result["stderr"], "cmd": result["command"], "raw_stdout": result.get("raw_stdout")})
        return {"status": "success", "message": "Kernel pushed successfully", "stdout": result["stdout"], "cmd": result["command"], "raw_stdout": result.get("raw_stdout")}
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/kernels/pull")
def pull_kernel(req: KernelPullRequest):
    try:
        path = req.path or str(KERNELS_DIR / req.kernel.replace('/', '_'))
        Path(path).mkdir(parents=True, exist_ok=True)

        args = ["kernels", "pull", req.kernel, "-p", path]
        if req.metadata:
            args.append("--metadata")

        result = run_kaggle_command(args)
        if result["returncode"] != 0:
            raise HTTPException(status_code=500, detail={"error": result["stderr"], "cmd": result["command"], "raw_stdout": result.get("raw_stdout")})
        return {"status": "success", "kernel": req.kernel, "path": path, "stdout": result["stdout"], "cmd": result["command"], "raw_stdout": result.get("raw_stdout")}
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/kernels/output")
def download_kernel_output(req: KernelOutputRequest):
    try:
        path = req.path or str(KERNELS_DIR / f"{req.kernel.replace('/', '_')}_output")
        Path(path).mkdir(parents=True, exist_ok=True)

        args = ["kernels", "output", req.kernel, "-p", path]
        if req.force:
            args.append("--force")
        if req.quiet:
            args.append("--quiet")

        result = run_kaggle_command(args)
        if result["returncode"] != 0:
            raise HTTPException(status_code=500, detail={"error": result["stderr"], "cmd": result["command"], "raw_stdout": result.get("raw_stdout")})
        return {"status": "success", "kernel": req.kernel, "output_path": path, "stdout": result["stdout"], "cmd": result["command"], "raw_stdout": result.get("raw_stdout")}
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/kernels/status")
def get_kernel_status(req: KernelStatusRequest):
    try:
        args = ["kernels", "status", req.kernel]
        result = run_kaggle_command(args)
        if result["returncode"] != 0:
            raise HTTPException(status_code=500, detail={"error": result["stderr"], "cmd": result["command"], "raw_stdout": result.get("raw_stdout")})
        return {"status": "success", "kernel": req.kernel, "info": result["stdout"], "cmd": result["command"], "raw_stdout": result.get("raw_stdout")}
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@router.get("/config/view")
def view_config():
    try:
        args = ["config", "view"]
        result = run_kaggle_command(args)
        if result["returncode"] != 0:
            raise HTTPException(status_code=500, detail={"error": result["stderr"], "cmd": result["command"], "raw_stdout": result.get("raw_stdout")})
        return {"status": "success", "config": result["stdout"], "cmd": result["command"], "raw_stdout": result.get("raw_stdout")}
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/config/set")
def set_config(req: dict):
    try:
        args = ["config", "set", "-n", req['key'], "-v", req['value']]
        result = run_kaggle_command(args)
        if result["returncode"] != 0:
            raise HTTPException(status_code=500, detail={"error": result["stderr"], "cmd": result["command"], "raw_stdout": result.get("raw_stdout")})
        return {"status": "success", "message": f"Set {req['key']}={req['value']}", "stdout": result["stdout"], "cmd": result["command"], "raw_stdout": result.get("raw_stdout")}
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@router.get("/status")
def kaggle_status():
    try:
        result = run_kaggle_command(["--version"])
        if result["returncode"] == 0:
            return {
                "status": "operational",
                "message": "Kaggle API is configured and accessible",
                "version": result["raw_stdout"].strip() if result.get("raw_stdout") else result.get("stdout", "").strip(),
                "environment": "venv (Termux)",
                "kaggle_config_dir": str(KAGGLE_CONFIG_DIR),
                "endpoints": {
                    "competitions": ["list", "download", "submit"],
                    "datasets": ["list", "files", "download", "create"],
                    "kernels": ["list", "push", "pull", "output", "status"],
                    "config": ["view", "set"]
                },
                "last_cmd": result["command"]
            }
        else:
            return {"status": "error", "message": "Kaggle CLI not accessible", "error": result["stderr"], "cmd": result["command"], "raw_stdout": result.get("raw_stdout")}
    except Exception as e:
        return {"status": "error", "message": str(e)}
