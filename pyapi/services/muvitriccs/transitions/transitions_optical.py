# pyapi/services/muvitriccs/transitions/transitions_optical.py
"""
MuviTriccs Optical Transitions
motion_blur_cut, radial_blur_cut, defocus_cut
"""

import math
import random
from typing import Optional

import numpy as np

from ..easing import get_easing
from ..primitives import (
    blend, scale_center, directional_blur,
    radial_blur, gaussian_blur_cv,
)


def render_optical(
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
    sin_peak   = math.sin(math.pi * t)

    # ── motion_blur_cut ───────────────────────────────────────────────────────
    # Infers blur axis from optical flow mean direction when available.
    if name == "motion_blur_cut":
        if flow_ab is not None:
            mu = float(np.mean(flow_ab[:, :, 0]))
            mv = float(np.mean(flow_ab[:, :, 1]))
            axis = "x" if abs(mu) >= abs(mv) else "y"
        else:
            axis = "x"
        blur = max(3, int(90 * sin_peak * intensity)) | 1
        return blend(directional_blur(a, blur, axis),
                     directional_blur(b, blur, axis), et)

    # ── radial_blur_cut ───────────────────────────────────────────────────────
    elif name == "radial_blur_cut":
        blur_s  = sin_peak * 0.85 * intensity
        scale_a = 1.0 + 0.16 * et * intensity
        scale_b = 0.84 + 0.16 * et
        fa = radial_blur(scale_center(a, scale_a), blur_s)
        fb = radial_blur(scale_center(b, scale_b), blur_s)
        return blend(fa, fb, et)

    # ── defocus_cut ───────────────────────────────────────────────────────────
    elif name == "defocus_cut":
        sigma = sin_peak * 20.0 * intensity
        return blend(gaussian_blur_cv(a, sigma),
                     gaussian_blur_cv(b, sigma), et)

    else:
        return blend(a, b, et)
