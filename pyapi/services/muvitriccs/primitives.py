# pyapi/services/muvitriccs/primitives.py
"""
MuviTriccs Image Primitives
Low-level frame manipulation utilities shared by all transition families.
"""

import math
import random

import numpy as np
import cv2
from scipy.ndimage import gaussian_filter


def fit_canvas(bgr: np.ndarray, w: int, h: int) -> np.ndarray:
    """Scale-and-crop (cover mode) to exactly w x h."""
    ih, iw = bgr.shape[:2]
    if iw == w and ih == h:
        return bgr.copy()
    scale   = max(w / iw, h / ih)
    nw      = max(w, int(math.ceil(iw * scale)))
    nh      = max(h, int(math.ceil(ih * scale)))
    resized = cv2.resize(bgr, (nw, nh), interpolation=cv2.INTER_LANCZOS4)
    x0      = max(0, (nw - w) // 2)
    y0      = max(0, (nh - h) // 2)
    out     = resized[y0:y0+h, x0:x0+w]
    if out.shape[:2] != (h, w):
        out = cv2.resize(out, (w, h), interpolation=cv2.INTER_LANCZOS4)
    return out


def blend(a: np.ndarray, b: np.ndarray, alpha: float) -> np.ndarray:
    """
    Gamma-correct linear blend. alpha=0 -> a, alpha=1 -> b.
    Uses gamma 2.2 encode/decode for perceptually smooth dissolves.
    """
    af = (a.astype(np.float32) / 255.0) ** 2.2
    bf = (b.astype(np.float32) / 255.0) ** 2.2
    out = np.clip(af * (1.0 - alpha) + bf * alpha, 0.0, 1.0) ** (1.0 / 2.2)
    return (out * 255.0).astype(np.uint8)


def translate(frame: np.ndarray, dx: int, dy: int,
              fill=(0, 0, 0)) -> np.ndarray:
    h, w = frame.shape[:2]
    M    = np.float32([[1, 0, dx], [0, 1, dy]])
    return cv2.warpAffine(frame, M, (w, h),
                          borderMode=cv2.BORDER_CONSTANT, borderValue=fill)


def scale_center(frame: np.ndarray, s: float) -> np.ndarray:
    h, w = frame.shape[:2]
    M    = cv2.getRotationMatrix2D((w / 2.0, h / 2.0), 0, s)
    return cv2.warpAffine(frame, M, (w, h),
                          borderMode=cv2.BORDER_CONSTANT, borderValue=(0, 0, 0))


def rotate_frame(frame: np.ndarray, deg: float) -> np.ndarray:
    h, w = frame.shape[:2]
    M    = cv2.getRotationMatrix2D((w / 2.0, h / 2.0), -deg, 1.0)
    return cv2.warpAffine(frame, M, (w, h),
                          borderMode=cv2.BORDER_CONSTANT, borderValue=(0, 0, 0))


def directional_blur(frame: np.ndarray, ksize: int, axis: str = "x") -> np.ndarray:
    ksize  = max(1, ksize | 1)
    kernel = np.ones((1, ksize), np.float32) / ksize if axis == "x" \
             else np.ones((ksize, 1), np.float32) / ksize
    return cv2.filter2D(frame, -1, kernel)


def radial_blur(frame: np.ndarray, strength: float) -> np.ndarray:
    """Zoom-burst via stacked scaled blends. strength in [0, 1]."""
    if strength <= 0:
        return frame
    steps = 6
    acc   = frame.astype(np.float32)
    for i in range(1, steps):
        acc += scale_center(frame, 1.0 + (i / steps) * strength * 0.18).astype(np.float32)
    return np.clip(acc / (steps + 1), 0, 255).astype(np.uint8)


def rotational_blur(frame: np.ndarray, angle: float) -> np.ndarray:
    """Accumulate rotated copies to simulate rotational motion blur."""
    if abs(angle) < 0.5:
        return frame
    steps = 8
    acc   = frame.astype(np.float32)
    for i in range(1, steps):
        acc += rotate_frame(frame, angle * (i / steps)).astype(np.float32)
    return np.clip(acc / (steps + 1), 0, 255).astype(np.uint8)


def gaussian_blur_cv(frame: np.ndarray, sigma: float) -> np.ndarray:
    if sigma < 0.5:
        return frame
    ksize = int(sigma * 6) | 1
    return cv2.GaussianBlur(frame, (ksize, ksize), sigma)


def lens_remap(frame: np.ndarray, k: float) -> np.ndarray:
    """Barrel (k>0) or pincushion (k<0) distortion via cv2.remap."""
    h, w   = frame.shape[:2]
    cx, cy = w / 2.0, h / 2.0
    fl     = max(w, h) * 0.9
    cam    = np.array([[fl, 0, cx], [0, fl, cy], [0, 0, 1]], dtype=np.float32)
    dist   = np.array([k, 0, 0, 0], dtype=np.float32)
    new_cam, _ = cv2.getOptimalNewCameraMatrix(cam, dist, (w, h), 1)
    m1, m2     = cv2.initUndistortRectifyMap(cam, dist, None, new_cam,
                                              (w, h), cv2.CV_32FC1)
    return cv2.remap(frame, m1, m2, cv2.INTER_LINEAR,
                     borderMode=cv2.BORDER_CONSTANT, borderValue=(0, 0, 0))


def smooth_noise(h: int, w: int, scale: float, rng: random.Random) -> np.ndarray:
    """
    Gaussian-blurred white noise field normalised to [0, 1].
    Used for organic mask edges in film_burn, light_leak.
    scale controls spatial frequency (0.05 = large blobs, 0.2 = fine detail).
    """
    seed    = rng.randint(0, 2**31)
    np_rng  = np.random.default_rng(seed)
    raw     = np_rng.random((h, w)).astype(np.float32)
    sigma   = max(h, w) * scale
    blurred = gaussian_filter(raw, sigma=sigma)
    lo, hi  = blurred.min(), blurred.max()
    if hi > lo:
        blurred = (blurred - lo) / (hi - lo)
    return blurred
