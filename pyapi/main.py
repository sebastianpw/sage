# pyapi/main.py

"""
SAGE PyAPI - Main FastAPI Application
"""
import os
import json
import logging
import importlib
import importlib.util
from pathlib import Path
from typing import Dict, Optional, Any

from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware

# ---------------------------
# Environment & paths (very early)
# ---------------------------
PROJECT_ROOT = Path(__file__).resolve().parent

# Config File Path
SERVICE_CONFIG_FILE = PROJECT_ROOT / "serv.conf.json"

# --- 1. CONFIG: ControlNet Models ---
CN_MODELS_DIR = PROJECT_ROOT.parent / "cnmodels"
CN_MODELS_DIR.mkdir(parents=True, exist_ok=True)
os.environ["HF_HOME"] = str(CN_MODELS_DIR)

# --- 2. CONFIG: Kaggle ---
DEFAULT_KAGGLE_CONFIG = PROJECT_ROOT.parent / "token" / ".kaggle"
os.environ.setdefault("KAGGLE_CONFIG_DIR", str(DEFAULT_KAGGLE_CONFIG))
KAGGLE_CONFIG_DIR = Path(os.environ["KAGGLE_CONFIG_DIR"])
KAGGLE_CONFIG_DIR.mkdir(parents=True, exist_ok=True)
try:
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
logger.info("HF_HOME (Models)  = %s", str(CN_MODELS_DIR))


# ---------------------------
# Service Registry Class
# ---------------------------
class ServiceRegistry:
    """
    Manages loading services based on configuration and 
    resolving their IP addresses.
    """
    def __init__(self, config_path: Path):
        self.config_path = config_path
        self.services_config = {}
        self.resolved_addresses: Dict[str, str] = {}
        self.default_ip = "127.0.0.1"
        
        self.load_config()

    def load_config(self):
        """Loads the serv.conf.json file."""
        if not self.config_path.exists():
            logger.error(f"Config file not found: {self.config_path}")
            return

        try:
            with open(self.config_path, 'r') as f:
                data = json.load(f)
                self.services_config = data.get("services", {})
                defaults = data.get("defaults", {})
                self.default_ip = defaults.get("default_ip", "127.0.0.1")
        except Exception as e:
            logger.error(f"Failed to parse config file: {e}")

    def get_service_address(self, service_name: str) -> str:
        """
        Unified internal method to retrieve service address.
        Returns cached address if already resolved.
        """
        # If already resolved, return it
        if service_name in self.resolved_addresses:
            return self.resolved_addresses[service_name]

        cfg = self.services_config.get(service_name)
        
        # If service doesn't exist in config, return default
        if not cfg:
            return self.default_ip

        # 1. Check for static IP in config
        if cfg.get("address"):
            addr = cfg["address"]
            self.resolved_addresses[service_name] = addr
            return addr

        # 2. Check for route script
        route_script_name = cfg.get("route_script")
        if route_script_name:
            addr = self._execute_route_script(route_script_name)
            self.resolved_addresses[service_name] = addr
            return addr

        # 3. Fallback to localhost
        self.resolved_addresses[service_name] = self.default_ip
        return self.default_ip

    def _execute_route_script(self, script_filename: str) -> str:
        """
        Dynamically loads a python script from /routes folder 
        and calls get_address().
        """
        try:
            routes_dir = PROJECT_ROOT / "routes"
            script_path = routes_dir / script_filename
            
            if not script_path.exists():
                logger.warning(f"Route script {script_filename} not found. Using default.")
                return self.default_ip

            # Dynamic import
            spec = importlib.util.spec_from_file_location("dynamic_route", script_path)
            if spec and spec.loader:
                module = importlib.util.module_from_spec(spec)
                spec.loader.exec_module(module)
                
                if hasattr(module, "get_address"):
                    return module.get_address()
                else:
                    logger.warning(f"Script {script_filename} has no get_address() function.")
        
        except Exception as e:
            logger.error(f"Failed to execute route script {script_filename}: {e}")
        
        return self.default_ip

    def register_active_services(self, fastapi_app: FastAPI):
        """
        Iterates through config, imports active modules, and includes routers.
        """
        for name, cfg in self.services_config.items():
            if not cfg.get("active", False):
                logger.info(f"Service '{name}' is disabled in config.")
                continue

            module_path = cfg.get("module")
            if not module_path:
                continue

            try:
                # Import module dynamically
                mod = importlib.import_module(module_path)
                
                # Get router variable (defaulting to 'router')
                router_var_name = cfg.get("router_var", "router")
                router = getattr(mod, router_var_name)
                
                # Include in FastAPI
                prefix = cfg.get("prefix", "")
                tags = cfg.get("tags", [])
                
                fastapi_app.include_router(router, prefix=prefix, tags=tags)
                logger.info(f"Registered service: {name} ({module_path})")
                
                # Pre-resolve address logic (optional, but good for logs)
                addr = self.get_service_address(name)
                logger.debug(f"Service '{name}' resolved to IP: {addr}")

            except ImportError as ie:
                logger.error(f"Could not import module {module_path} for service {name}: {ie}")
            except AttributeError as ae:
                logger.error(f"Module {module_path} has no router named '{router_var_name}': {ae}")
            except Exception as e:
                logger.exception(f"Unexpected error loading service {name}: {e}")

# ---------------------------
# FastAPI initialization
# ---------------------------
app = FastAPI(
    title="SAGE PyAPI",
    description="Python microservice",
    version="1.1.0",
    docs_url="/docs",
    redoc_url="/redoc"
)

# Initialize Service Registry
service_registry = ServiceRegistry(SERVICE_CONFIG_FILE)

# Store registry in app state if other endpoints need to access it via Request
app.state.service_registry = service_registry

# Add CORS middleware
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# ---------------------------
# Basic endpoints
# ---------------------------
@app.get("/")
def root():
    return {
        "status": "ok",
        "service": "SAGE PyAPI",
        "version": "1.1.0",
        "message": "Termux Python microservice running"
    }

@app.get("/health")
def health_check():
    return {"status": "healthy", "service": "sage-pyapi"}

@app.get("/ping")
def ping():
    return {"ok": True, "message": "pong"}

from fastapi import HTTPException

@app.get("/service-discovery/{service_name}")
def get_service_info(service_name: str):
    """
    Returns the active IP/URL for a specific service so the client 
    can connect directly.
    """
    # 1. Check if service exists in config
    cfg = service_registry.services_config.get(service_name)
    if not cfg:
        raise HTTPException(status_code=404, detail="Service not configured")

    # 2. Get the address (resolves via script if needed)
    ip = service_registry.get_service_address(service_name)
    
    # 3. Determine if it is running locally on this PyAPI instance
    is_local = cfg.get("active", False)
    
    
    return {
        "service": service_name,
        "active_locally": is_local,
        "address": service_registry.get_service_address(service_name),
        "tags": cfg.get("tags", [])
    }


# ---------------------------
# Load Services Dynamically
# ---------------------------
service_registry.register_active_services(app)

# ---------------------------
# Helper: Unified Method (as requested)
# ---------------------------
def get_service_ip(service_name: str) -> str:
    """
    Global helper function to get a service IP.
    Can be imported by other modules if necessary.
    """
    return service_registry.get_service_address(service_name)

# ---------------------------
# Startup checks
# ---------------------------
@app.on_event("startup")
def on_startup():
    kaggle_json = KAGGLE_CONFIG_DIR / "kaggle.json"
    if not kaggle_json.exists():
        logger.warning(
            "Kaggle credentials not found in %s. Place kaggle.json there with chmod 600.",
            str(kaggle_json)
        )
    else:
        logger.info("Kaggle credentials present: %s", str(kaggle_json))

# ---------------------------
# Run
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
