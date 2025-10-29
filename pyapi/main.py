"""
SAGE PyAPI - Main FastAPI Application
Termux-compatible Python microservice for Kaggle integration
"""
import os
from pathlib import Path
import logging

from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware

# ---------------------------
# Environment & paths (very early)
# ---------------------------
# PROJECT_ROOT = <path to pyapi>
PROJECT_ROOT = Path(__file__).resolve().parent
# Default kaggle config location: ../token/.kaggle (project root's parent)
DEFAULT_KAGGLE_CONFIG = PROJECT_ROOT.parent / "token" / ".kaggle"

# Honor existing env var if set, otherwise set it to DEFAULT_KAGGLE_CONFIG
os.environ.setdefault("KAGGLE_CONFIG_DIR", str(DEFAULT_KAGGLE_CONFIG))

# Ensure the directory exists and has restrictive permissions where possible
KAGGLE_CONFIG_DIR = Path(os.environ["KAGGLE_CONFIG_DIR"])
KAGGLE_CONFIG_DIR.mkdir(parents=True, exist_ok=True)
try:
    # best-effort permission lock (may not be supported on some storage)
    KAGGLE_CONFIG_DIR.chmod(0o700)
except Exception:
    pass

# ---------------------------
# Logging
# ---------------------------
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)
logger.info("KAGGLE_CONFIG_DIR = %s", str(KAGGLE_CONFIG_DIR))

# ---------------------------
# FastAPI initialization
# ---------------------------
app = FastAPI(
    title="SAGE PyAPI",
    description="Python microservice for Kaggle and AI operations",
    version="1.0.0",
    docs_url="/docs",
    redoc_url="/redoc"
)

# Add CORS middleware
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],  # tighten for production
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Basic endpoints
@app.get("/")
def root():
    """Root endpoint - health check"""
    return {
        "status": "ok",
        "service": "SAGE PyAPI",
        "version": "1.0.0",
        "message": "Termux Python microservice running"
    }

@app.get("/health")
def health_check():
    """Health check endpoint"""
    return {"status": "healthy", "service": "sage-pyapi"}

@app.get("/ping")
def ping():
    """Simple ping endpoint"""
    return {"ok": True, "message": "pong"}

# ---------------------------
# Register service routers (centralized)
# ---------------------------
try:
    from services.kaggle_service import router as kaggle_router
    app.include_router(kaggle_router, prefix="/kaggle", tags=["kaggle"])
    logger.info("Registered kaggle router")
except Exception as e:
    logger.exception("Failed to register kaggle router: %s", e)


# --- Pillow router (new) ---
try:
    from services.pillow_service import router as pillow_router
    app.include_router(pillow_router, prefix="/image", tags=["image"])
    logger.info("Registered pillow router")
except Exception as e:
    logger.exception("Failed to register pillow router: %s", e)



# ---------------------------
# Startup checks (optional)
# ---------------------------
@app.on_event("startup")
def on_startup():
    # Check that kaggle.json exists and warn if not
    kaggle_json = KAGGLE_CONFIG_DIR / "kaggle.json"
    if not kaggle_json.exists():
        logger.warning(
            "Kaggle credentials not found in %s. Place kaggle.json there with chmod 600.",
            str(kaggle_json)
        )
    else:
        logger.info("Kaggle credentials present: %s", str(kaggle_json))

# ---------------------------
# Run (if executed directly)
# ---------------------------
if __name__ == "__main__":
    import uvicorn
    logger.info("Starting SAGE PyAPI server on port 8009...")
    uvicorn.run(
        "main:app",
        host="0.0.0.0",
        port=8009,
        reload=True,
        log_level="info"
    )
