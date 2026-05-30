# pyapi/backup/destinations/scp_destination.py
"""
SCP destination — uses system ssh/scp binaries via subprocess.
Zero pip dependencies. Works natively on Termux with pkg openssh.

Mirrors the bkpmedia.sh SSH multiplexing pattern:
  - ControlMaster socket for single authentication
  - All subsequent ssh/scp calls reuse the socket (no re-auth)
  - ap0 hotspot auto-detection identical to bkpmedia.sh logic
"""
from __future__ import annotations
import hashlib
import logging
import os
import shutil
import socket
import subprocess
import tempfile
from pathlib import Path
from typing import Optional
import re

logger = logging.getLogger(__name__)


# ── Host discovery ─────────────────────────────────────────────────────────

def _get_ap0_network(run_logger=None) -> Optional[str]:
    """Return the /24 prefix of the ap0 hotspot interface, e.g. '192.168.43'."""
    cmds = [
        "ifconfig 2>/dev/null | awk '/ap0:/ {flag=1; next} /flags=/ && flag==1 {flag=0} flag==1 && /inet / {print $2; exit}'",
        "ip -4 addr show ap0 2>/dev/null | awk '/inet / {print $2}' | cut -d/ -f1"
    ]
    for cmd in cmds:
        try:
            out = subprocess.check_output(['sh', '-c', cmd], text=True).strip()
            if out:
                parts = out.split('.')
                if len(parts) == 4:
                    net = f"{parts[0]}.{parts[1]}.{parts[2]}"
                    if run_logger:
                        run_logger.log(f"Detected ap0 network via shell: {net}.x")
                    return net
        except Exception:
            continue
    return None


def _port_open(host: str, port: int, timeout: float = 1.0) -> bool:
    subprocess.run(
        ['sh', '-c', f'ping -c 1 -W 1 {host}'], 
        stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL
    )
    try:
        with socket.create_connection((host, port), timeout=timeout):
            return True
    except (socket.timeout, ConnectionRefusedError, OSError):
        return False


def discover_tablet_ip(port: int = 8022, run_logger=None) -> Optional[str]:
    """
    Scan ap0 subnet for a device with `port` open.
    Tries common tablet assignments first (.8, .2-.7), then .1-.30.
    """
    network = _get_ap0_network(run_logger)
    if not network:
        if run_logger:
            run_logger.log("ap0 hotspot interface not found or not active")
        logger.error("ap0 hotspot interface not found or not active")
        return None

    if run_logger:
        run_logger.log(f"Scanning {network}.x for port {port}...")
    logger.info("Scanning %s.x:%d for tablet...", network, port)

    priority = [8, 2, 3, 4, 5, 6, 7]
    for last in priority:
        candidate = f"{network}.{last}"
        if _port_open(candidate, port):
            if run_logger:
                run_logger.log(f"Tablet found at {candidate}")
            logger.info("Found tablet at %s", candidate)
            return candidate

    for last in range(1, 31):
        if last in priority:
            continue
        candidate = f"{network}.{last}"
        if _port_open(candidate, port):
            if run_logger:
                run_logger.log(f"Tablet found at {candidate}")
            logger.info("Found tablet at %s", candidate)
            return candidate

    if run_logger:
        run_logger.log("No device found on hotspot")
    logger.error("No device found on %s.x:%d", network, port)
    return None


# ── Shared SSH options ─────────────────────────────────────────────────────

_SSH_OPTS = [
    '-o', 'StrictHostKeyChecking=no',
    '-o', 'BatchMode=yes',        # never prompt - fail fast if no key
    '-o', 'ConnectTimeout=20',
    '-o', 'ServerAliveInterval=30',
]


# ── SCPDestination ─────────────────────────────────────────────────────────

class SCPDestination:
    """
    Manages a persistent SSH ControlMaster socket so authentication
    happens once and all subsequent calls reuse it - identical to the
    SSH_SOCKET pattern in bkpmedia.sh.

    Usage:
        with SCPDestination(dest_cfg) as dest:
            dest.ensure_remote_dir("sage_backup/media")
            dest.upload("/local/file.tar", "sage_backup/media/file.tar")
            ok = dest.verify_sha256("/local/file.tar", "sage_backup/media/file.tar")
    """

    def __init__(self, dest_cfg, password: Optional[str] = None, run_logger=None):
        self.cfg        = dest_cfg
        self.password   = password   # reserved - prefer key auth on Termux
        self.run_logger = run_logger
        self._host      = None
        self._socket    = None       # ControlMaster socket path
        self._tmpdir    = None

    # ── Context manager ────────────────────────────────────────────────────

    def __enter__(self):
        self.connect()
        return self

    def __exit__(self, *_):
        self.disconnect()

    # ── Connection ─────────────────────────────────────────────────────────

    def connect(self):
        # Resolve host
        if self.cfg.host_mode == 'ap0_scan':
            host = discover_tablet_ip(self.cfg.port, self.run_logger)
            if not host:
                raise ConnectionError("Could not discover tablet IP via ap0 scan")
        else:
            host = self.cfg.host
            if not host:
                raise ConnectionError("Destination has no static host configured")

        self._host   = host
        self._tmpdir = tempfile.mkdtemp(prefix='bkpforge_ssh_')
        self._socket = os.path.join(self._tmpdir, 'ctrl.sock')

        if self.run_logger:
            self.run_logger.log(f"Opening SSH ControlMaster -> {host}:{self.cfg.port}")

        # Open ControlMaster connection (background, persists 60 min)
        cmd = [
            'ssh',
            '-p', str(self.cfg.port),
            '-M',                        # ControlMaster
            '-S', self._socket,
            '-fN',                       # background, no command
            '-o', 'ControlPersist=60m',
            *_SSH_OPTS,
            host,
        ]
        logger.info("Opening SSH ControlMaster -> %s:%d", host, self.cfg.port)
        result = subprocess.run(cmd, capture_output=True, text=True, timeout=30)
        if result.returncode != 0:
            raise ConnectionError(
                f"SSH ControlMaster failed (rc={result.returncode}): "
                f"{result.stderr.strip()[:300]}"
            )
        logger.info("SSH ControlMaster established: %s", self._socket)

    def disconnect(self):
        if self._socket and os.path.exists(self._socket):
            try:
                subprocess.run(
                    ['ssh', '-S', self._socket, '-O', 'exit', self._host],
                    capture_output=True, timeout=5
                )
            except Exception:
                pass
        if self._tmpdir and os.path.exists(self._tmpdir):
            try:
                shutil.rmtree(self._tmpdir, ignore_errors=True)
            except Exception:
                pass
        self._socket = None
        self._tmpdir = None

    # ── Remote operations ──────────────────────────────────────────────────

    def ensure_remote_dir(self, remote_path: str):
        self._ssh(f'mkdir -p "{remote_path}"')
        logger.info("Remote dir ready: %s", remote_path)

    def upload(self, local_path: str, remote_path: str):
        """Upload a single file via scp, reusing the ControlMaster socket."""
        size = os.path.getsize(local_path)
        logger.info("Uploading %s -> %s (%s bytes)",
                    Path(local_path).name, remote_path, f"{size:,}")

        cmd = [
            'scp',
            '-P', str(self.cfg.port),
            '-o', f'ControlPath={self._socket}',
            *_SSH_OPTS,
            local_path,
            f"{self._host}:{remote_path}",
        ]
        result = subprocess.run(cmd, capture_output=True, text=True, timeout=3600)
        if result.returncode != 0:
            raise RuntimeError(
                f"scp failed (rc={result.returncode}): {result.stderr.strip()[:300]}"
            )
        logger.info("Upload complete: %s", Path(local_path).name)

    def verify_sha256(self, local_path: str, remote_path: str) -> bool:
        """Compare local sha256 with remote sha256sum output."""
        h = hashlib.sha256()
        with open(local_path, 'rb') as f:
            for chunk in iter(lambda: f.read(65536), b''):
                h.update(chunk)
        local_sha = h.hexdigest()

        stdout    = self._ssh(f'sha256sum "{remote_path}"')
        remote_sha = stdout.strip().split()[0] if stdout.strip() else ''

        ok = local_sha == remote_sha
        if ok:
            logger.info("SHA256 verified OK: %s", Path(local_path).name)
        else:
            logger.error("SHA256 MISMATCH: local=%s remote=%s", local_sha, remote_sha)
        return ok

    # ── Internal ssh exec ──────────────────────────────────────────────────

    def _ssh(self, remote_cmd: str) -> str:
        """Run a command on the remote via the ControlMaster socket."""
        cmd = [
            'ssh',
            '-p', str(self.cfg.port),
            '-S', self._socket,
            *_SSH_OPTS,
            self._host,
            remote_cmd,
        ]
        result = subprocess.run(cmd, capture_output=True, text=True, timeout=120)
        if result.returncode != 0:
            logger.warning("Remote cmd failed (rc=%d): %s",
                           result.returncode, result.stderr.strip()[:200])
        return result.stdout
