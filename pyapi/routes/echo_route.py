# pyapi/routes/echo_route.py
import subprocess
import logging
from pathlib import Path

logger = logging.getLogger(__name__)

def get_address() -> str:
    """
    Determines the IP address by calling a sibling shell script.
    Expected location: pyapi/../bash/pyapi_echo.sh
    """
    try:
        # Determine paths
        # This file is in pyapi/routes/
        current_file = Path(__file__).resolve()
        pyapi_dir = current_file.parent.parent
        project_root = pyapi_dir.parent # Parent of pyapi
        
        bash_script = project_root / "bash" / "pyapi_echo.sh"

        if not bash_script.exists():
            logger.warning(f"Route script not found at: {bash_script}")
            return "127.0.0.1"

        # Execute the bash script
        result = subprocess.run(
            ["bash", str(bash_script)], 
            capture_output=True, 
            text=True, 
            timeout=5
        )
        
        if result.returncode == 0:
            ip = result.stdout.strip()
            # Basic validation
            if ip:
                return ip
                
        logger.error(f"Bash script failed: {result.stderr}")
        return "127.0.0.1"

    except Exception as e:
        logger.error(f"Error executing route script: {e}")
        return "127.0.0.1"
