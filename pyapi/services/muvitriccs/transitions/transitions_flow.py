# pyapi/services/muvitriccs/transitions/transitions_flow.py
"""
MuviTriccs Flow-Based Transitions
optical_flow_warp — content-aware Farneback warp morphing A into B
"""

import math
import random
from typing import Optional

import numpy as np

from ..easing import get_easing
from ..primitives import blend, gaussian_blur_cv
from ..analysis import warp_with_flow


def render_flow(
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

    # ── optical_flow_warp ─────────────────────────────────────────────────────
    # Farneback flow morphs A toward B and B back toward A, blended at each t.
    # The result feels genuinely content-aware: motion follows scene structure.
    if name == "optical_flow_warp":
        if flow_ab is None:
            return blend(a, b, et)
        wa = warp_with_flow(a, flow_ab,  et)
        wb = warp_with_flow(b, flow_ab, -(1.0 - et))
        blur_s = max(0.0, (0.5 - abs(t - 0.5)) * 8 * intensity)
        if blur_s > 0.5:
            wa = gaussian_blur_cv(wa, blur_s)
            wb = gaussian_blur_cv(wb, blur_s)
        return blend(wa, wb, et)

    else:
        return blend(a, b, et)
