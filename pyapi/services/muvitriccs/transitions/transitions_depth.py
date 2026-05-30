# pyapi/services/muvitriccs/transitions/transitions_depth.py
"""
MuviTriccs Depth-Aware Transitions
depth_parallax — MiDaS depth map drives per-layer parallax shift
"""

import math
import random
from typing import Optional

import numpy as np
import cv2

from ..easing import get_easing
from ..primitives import blend
from ..analysis import _synthetic_depth


def render_depth(
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

    # ── depth_parallax ────────────────────────────────────────────────────────
    # Near pixels (high depth value) shift faster than far pixels.
    # A slides out left, B enters from right, both depth-modulated.
    if name == "depth_parallax":
        da      = depth_a if depth_a is not None else _synthetic_depth(a)
        db      = depth_b if depth_b is not None else _synthetic_depth(b)
        max_sh  = int(w * 0.20 * intensity)
        yg, xg  = np.mgrid[0:h, 0:w].astype(np.float32)

        shift_a = (da * max_sh * et).astype(np.float32)
        map_a_x = np.clip(xg - shift_a, 0, w - 1)
        wa      = cv2.remap(a, map_a_x, yg, cv2.INTER_LINEAR,
                            borderMode=cv2.BORDER_REFLECT_101)

        shift_b = (db * max_sh * (1.0 - et)).astype(np.float32)
        map_b_x = np.clip(xg + shift_b, 0, w - 1)
        wb      = cv2.remap(b, map_b_x, yg, cv2.INTER_LINEAR,
                            borderMode=cv2.BORDER_REFLECT_101)

        return blend(wa, wb, et)

    else:
        return blend(a, b, et)
