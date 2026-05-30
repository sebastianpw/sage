"""
SAGE Restarter - Standalone Service
Runs on Port 8010.
Sole purpose: Execute pyapi/run_server.sh to restart the main API.
"""
import subprocess
import logging
from pathlib import Path
from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware

# ---------------------------
# Configuration
# ---------------------------
# Resolve paths relative to this file
BASE_DIR = Path(__file__).resolve().parent
SCRIPT_NAME = "run_server.sh"
SCRIPT_PATH = BASE_DIR / SCRIPT_NAME

# ---------------------------
# Logging
# ---------------------------
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger("restarter")

# ---------------------------
# FastAPI App
# ---------------------------
app = FastAPI(title="SAGE Restarter", version="1.0.0")

# Allow CORS (useful if calling from a web dashboard)
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

@app.get("/")
def root():
    """Health check"""
    return {
        "status": "online", 
        "service": "restarter", 
        "port": 8010,
        "target": str(SCRIPT_PATH)
    }

@app.post("/restart")
def restart_main_server():
    """
    Triggers the run_server.sh script in a detached process.
    """
    if not SCRIPT_PATH.exists():
        logger.error("Script not found at: %s", SCRIPT_PATH)
        raise HTTPException(status_code=404, detail=f"Script '{SCRIPT_NAME}' not found.")

    try:
        logger.info("Executing: %s", SCRIPT_PATH)
        
        # subprocess.Popen is non-blocking for the API response.
        # start_new_session=True ensures it runs independently/detached.
        subprocess.Popen(
            ["bash", str(SCRIPT_PATH)],
            cwd=str(BASE_DIR),     # Ensure we run from pyapi/ dir
            start_new_session=True
        )
        
        return {
            "status": "ok", 
            "message": f"Executed {SCRIPT_NAME}",
            "path": str(SCRIPT_PATH)
        }
        
    except Exception as e:
        logger.exception("Failed to execute restart script")
        raise HTTPException(status_code=500, detail=str(e))

# ---------------------------
# Entry Point (Port 8010)
# ---------------------------
if __name__ == "__main__":
    import uvicorn
    logger.info("Starting Restarter Service on port 8010...")
    uvicorn.run(app, host="0.0.0.0", port=8010)
