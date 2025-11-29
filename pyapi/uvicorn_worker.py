# pyapi/uvicorn_worker.py

from uvicorn.workers import UvicornWorker

class CustomUvicornWorker(UvicornWorker):
    """
    A custom Uvicorn worker class that overrides the default timeout settings.
    """
    CONFIG_KWARGS = {
        "loop": "auto",
        "http": "auto",
        "timeout_keep_alive": 300,  # <-- This is the setting that will fix the problem
        "timeout_notify": 300,
    }


