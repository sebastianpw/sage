# pyapi/services/muvitriccs/analysis.py
"""
MuviTriccs Analysis Utilities
Optical flow (Farneback) and depth estimation (MiDaS) used by advanced transitions.
"""

import logging

import numpy as np
import cv2

logger = logging.getLogger(__name__)

# ── Torch: optional, used only for depth_parallax ─────────────────────────────
try:
    import torch
    TORCH_AVAILABLE = True
    logger.info("Torch available — depth_parallax enabled")
except ImportError:
    TORCH_AVAILABLE = False
    logger.info("Torch not found — depth_parallax will fall back to zoom_in")


# ── Optical flow (OpenCV Farneback) ───────────────────────────────────────────

def compute_flow(a: np.ndarray, b: np.ndarray,
                 downscale: float = 0.5) -> np.ndarray:
    """
    Dense Farneback flow A->B computed at reduced resolution for speed.
    Returns float32 (h, w, 2) at full resolution.
    """
    h, w  = a.shape[:2]
    dw    = max(64, int(w * downscale))
    dh    = max(64, int(h * downscale))
    ag    = cv2.cvtColor(cv2.resize(a, (dw, dh)), cv2.COLOR_BGR2GRAY)
    bg    = cv2.cvtColor(cv2.resize(b, (dw, dh)), cv2.COLOR_BGR2GRAY)
    flow  = cv2.calcOpticalFlowFarneback(
        ag, bg, None,
        pyr_scale=0.5, levels=4, winsize=15,
        iterations=3, poly_n=5, poly_sigma=1.2,
        flags=cv2.OPTFLOW_FARNEBACK_GAUSSIAN,
    )
    flow_full = cv2.resize(flow, (w, h)) * (1.0 / downscale)
    return flow_full.astype(np.float32)


def warp_with_flow(frame: np.ndarray, flow: np.ndarray,
                   alpha: float) -> np.ndarray:
    """Warp frame by flow * alpha using cv2.remap."""
    h, w = frame.shape[:2]
    yc, xc = np.mgrid[0:h, 0:w].astype(np.float32)
    mx = xc + flow[:, :, 0] * alpha
    my = yc + flow[:, :, 1] * alpha
    return cv2.remap(frame, mx, my, cv2.INTER_LINEAR,
                     borderMode=cv2.BORDER_REFLECT_101)


# ── Depth estimation (MiDaS via Torch) ────────────────────────────────────────

_midas_model     = None
_midas_transform = None


def get_midas():
    global _midas_model, _midas_transform
    if _midas_model is not None:
        return _midas_model, _midas_transform
    try:
        model      = torch.hub.load("intel-isl/MiDaS", "MiDaS_small", trust_repo=True)
        model.eval()
        transforms = torch.hub.load("intel-isl/MiDaS", "transforms", trust_repo=True)
        _midas_model     = model
        _midas_transform = transforms.small_transform
        logger.info("MiDaS loaded")
        return _midas_model, _midas_transform
    except Exception as e:
        logger.warning(f"MiDaS load failed: {e}")
        return None, None


def _synthetic_depth(bgr: np.ndarray) -> np.ndarray:
    h, w = bgr.shape[:2]
    y, x = np.ogrid[:h, :w]
    dist = np.sqrt(((x - w/2)/w)**2 + ((y - h/2)/h)**2)
    return (1.0 - dist / dist.max()).astype(np.float32)


def estimate_depth(bgr: np.ndarray) -> np.ndarray:
    """
    Return float32 (h, w) depth map normalised [0,1]; 1 = nearest.
    Falls back to radial synthetic depth if Torch / MiDaS unavailable.
    """
    if not TORCH_AVAILABLE:
        return _synthetic_depth(bgr)
    model, transform = get_midas()
    if model is None:
        return _synthetic_depth(bgr)

    h, w = bgr.shape[:2]
    rgb  = cv2.cvtColor(bgr, cv2.COLOR_BGR2RGB)
    inp  = transform(rgb).unsqueeze(0)
    with torch.no_grad():
        pred = model(inp)
        pred = torch.nn.functional.interpolate(
            pred.unsqueeze(1), size=(h, w),
            mode="bicubic", align_corners=False).squeeze()
    depth = pred.cpu().numpy().astype(np.float32)
    lo, hi = depth.min(), depth.max()
    if hi > lo:
        depth = (depth - lo) / (hi - lo)
    return depth
