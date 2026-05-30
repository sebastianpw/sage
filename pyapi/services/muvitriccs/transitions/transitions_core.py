# pyapi/services/muvitriccs/transitions/transitions_core.py
"""
MuviTriccs Core Transitions
cross_dissolve, fade_to_black, fade_to_white, luma_wipe
"""

import math
import random
from typing import Optional

import numpy as np

from ..easing import get_easing
from ..primitives import blend, smooth_noise


def render_core(
    a: np.ndarray,
    b: np.ndarray,
    t: float,
    name: str,
    spec: dict,
    w: int, h: int,
    rng: random.Random,
    flow_ab:  Optional[np.ndarray] = None,
    depth_a:  Optional[np.ndarray] = None,
    depth_b:  Optional[np.ndarray] = None,
) -> np.ndarray:

    easing_fn = get_easing(spec.get("easing", "ease_in_out_cubic"))
    intensity  = float(spec.get("intensity", 1.0))
    et         = easing_fn(t)

    # ── cross_dissolve ────────────────────────────────────────────────────────
    if name == "cross_dissolve":
        return blend(a, b, et)

    # ── fade_to_black ─────────────────────────────────────────────────────────
    elif name == "fade_to_black":
        black = np.zeros_like(a)
        if t < 0.5:
            return blend(a, black, easing_fn(t * 2))
        return blend(black, b, easing_fn((t - 0.5) * 2))

    # ── fade_to_white ─────────────────────────────────────────────────────────
    elif name == "fade_to_white":
        white = np.full_like(a, 255)
        if t < 0.5:
            return blend(a, white, easing_fn(t * 2))
        return blend(white, b, easing_fn((t - 0.5) * 2))

    # ── luma_wipe ─────────────────────────────────────────────────────────────
    # Luminance of A drives the reveal. Pixels where A is bright reveal B first.
    # Smooth noise softens the boundary for an organic edge.
    elif name == "luma_wipe":
        import cv2
        gray     = cv2.cvtColor(a, cv2.COLOR_BGR2GRAY).astype(np.float32) / 255.0
        noise    = smooth_noise(h, w, 0.08, rng)
        softness = 0.14 * intensity
        field    = gray * 0.7 + noise * 0.3
        mask     = np.clip((field - et + softness / 2) / softness, 0.0, 1.0)
        m3       = mask[:, :, np.newaxis]
        return np.clip(a * m3 + b * (1.0 - m3), 0, 255).astype(np.uint8)

    else:
        return blend(a, b, et)
