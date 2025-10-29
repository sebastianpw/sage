import os
import signal
import subprocess

PORT = 8009
SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
PID_FILE = os.path.join(SCRIPT_DIR, "pyapi_server.pid")


def kill_pid(pid, source):
    try:
        os.kill(int(pid), signal.SIGTERM)
        print(f"Killed PID {pid} (found via {source})")
        return True
    except ProcessLookupError:
        print(f"PID {pid} not found (already stopped?)")
        return False
    except Exception as e:
        print(f"Failed to kill PID {pid} from {source}: {e}")
        return False


def remove_pid_file():
    if os.path.exists(PID_FILE):
        try:
            os.remove(PID_FILE)
            print(f"Removed PID file: {PID_FILE}")
        except Exception as e:
            print(f"Failed to remove PID file: {e}")


def try_pid_file():
    """Try killing the process listed in the PID file."""
    if os.path.exists(PID_FILE):
        with open(PID_FILE) as f:
            pid = f.read().strip()
        if pid:
            print(f"Found PID file: {PID_FILE}")
            if kill_pid(pid, "pid file"):
                remove_pid_file()
                return True
            else:
                # Remove stale PID file even if process not found
                remove_pid_file()
        else:
            print("PID file was empty — removing.")
            remove_pid_file()
    return False


def try_lsof():
    """Try finding process by port using lsof."""
    try:
        result = subprocess.run(["lsof", "-t", f"-i:{PORT}"],
                                capture_output=True, text=True)
        pids = [p.strip() for p in result.stdout.splitlines() if p.strip()]
        if pids:
            for pid in pids:
                kill_pid(pid, "lsof")
            remove_pid_file()
            return True
    except FileNotFoundError:
        print("lsof not found, skipping.")
    return False


def try_ps():
    """Fallback for Termux: detect uvicorn process manually."""
    try:
        ps = subprocess.run(["ps", "-A"], capture_output=True, text=True)
        found = False
        for line in ps.stdout.splitlines():
            if "uvicorn" in line and str(PORT) in line:
                parts = line.split()
                if len(parts) >= 2:
                    found = True
                    kill_pid(parts[1], "ps")
        if found:
            remove_pid_file()
        return found
    except Exception as e:
        print(f"Error using ps fallback: {e}")
    return False


# ========================
# Main logic
# ========================
if try_pid_file():
    pass
elif try_lsof():
    pass
elif try_ps():
    pass
else:
    print(f"No existing server found on port {PORT}")
    remove_pid_file()
