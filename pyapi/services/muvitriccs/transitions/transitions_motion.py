# pyapi/services/muvitriccs/transitions/transitions_motion.py
"""
MuviTriccs Motion Transitions
slide_left, slide_right, slide_up, slide_down, push_left, push_right,
zoom_in, zoom_out, spin_cw, spin_ccw, whip_pan_left, whip_pan_right
"""

import math
import random
from typing import Optional

import numpy as np

from ..easing import get_easing
from ..primitives import (
    blend, translate, scale_center, rotate_frame,
    directional_blur, radial_blur, rotational_blur,
)


def render_motion(
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
    sin_peak   = math.sin(math.pi * t)   # bell 0->1->0

    # ── slide_left ────────────────────────────────────────────────────────────
    if name == "slide_left":
        dx   = int(w * et)
        blur = max(1, int(dx * 0.055 * intensity)) | 1
        fa   = directional_blur(translate(a,   -dx, 0), blur, "x")
        fb   = directional_blur(translate(b, w-dx, 0), blur, "x")
        out  = fa.copy()
        if w - dx < w:
            out[:, max(0, w-dx):] = fb[:, max(0, w-dx):]
        return out

    # ── slide_right ───────────────────────────────────────────────────────────
    elif name == "slide_right":
        dx   = int(w * et)
        blur = max(1, int(dx * 0.055 * intensity)) | 1
        fa   = directional_blur(translate(a,     dx, 0), blur, "x")
        fb   = directional_blur(translate(b, -(w-dx), 0), blur, "x")
        out  = fa.copy()
        if dx > 0:
            out[:, :dx] = fb[:, :dx]
        return out

    # ── slide_up ──────────────────────────────────────────────────────────────
    elif name == "slide_up":
        dy   = int(h * et)
        blur = max(1, int(dy * 0.055 * intensity)) | 1
        fa   = directional_blur(translate(a,   0,   -dy), blur, "y")
        fb   = directional_blur(translate(b,   0, h-dy), blur, "y")
        out  = fa.copy()
        if h - dy < h:
            out[max(0, h-dy):, :] = fb[max(0, h-dy):, :]
        return out

    # ── slide_down ────────────────────────────────────────────────────────────
    elif name == "slide_down":
        dy   = int(h * et)
        blur = max(1, int(dy * 0.055 * intensity)) | 1
        fa   = directional_blur(translate(a, 0,    dy), blur, "y")
        fb   = directional_blur(translate(b, 0, -(h-dy)), blur, "y")
        out  = fa.copy()
        if dy > 0:
            out[:dy, :] = fb[:dy, :]
        return out

    # ── push_left ─────────────────────────────────────────────────────────────
    elif name == "push_left":
        dx  = int(w * et)
        fa  = translate(a,   -dx, 0)
        fb  = translate(b, w-dx, 0)
        out = fa.copy()
        out[:, max(0, w-dx):] = fb[:, max(0, w-dx):]
        return out

    # ── push_right ────────────────────────────────────────────────────────────
    elif name == "push_right":
        dx  = int(w * et)
        fa  = translate(a,      dx, 0)
        fb  = translate(b, -(w-dx), 0)
        out = fa.copy()
        if dx > 0:
            out[:, :dx] = fb[:, :dx]
        return out

    # ── zoom_in (CapCut style) ────────────────────────────────────────────────
    elif name == "zoom_in":
        scale_a = 1.0 + 1.2 * (et ** 2) * intensity
        blur_s  = sin_peak * 0.6 * intensity
        za      = radial_blur(scale_center(a, scale_a), blur_s)
        out     = blend(za, b, et).astype(np.float32)
        return np.clip(out + sin_peak * 30 * intensity, 0, 255).astype(np.uint8)

    # ── zoom_out (CapCut style) ───────────────────────────────────────────────
    elif name == "zoom_out":
        scale_a = max(0.2, 1.0 - 0.4 * et * intensity)
        scale_b = 0.5 + 0.5 * et
        blur_s  = sin_peak * 0.6 * intensity
        za = radial_blur(scale_center(a, scale_a), blur_s)
        zb = radial_blur(scale_center(b, scale_b), blur_s)
        out = blend(za, zb, et).astype(np.float32)
        return np.clip(out + sin_peak * 20 * intensity, 0, 255).astype(np.uint8)

    # ── spin_cw ───────────────────────────────────────────────────────────────
    elif name == "spin_cw":
        overscan = 1.0 + 0.1 * sin_peak
        ra = rotational_blur(
            scale_center(rotate_frame(a,  85.0 * et * intensity), overscan),
            6.0 * sin_peak * intensity)
        rb = rotational_blur(
            scale_center(rotate_frame(b, -85.0 * (1-et) * intensity), overscan),
            6.0 * sin_peak * intensity)
        return blend(ra, rb, et)

    # ── spin_ccw ──────────────────────────────────────────────────────────────
    elif name == "spin_ccw":
        overscan = 1.0 + 0.1 * sin_peak
        ra = rotational_blur(
            scale_center(rotate_frame(a, -85.0 * et * intensity), overscan),
            6.0 * sin_peak * intensity)
        rb = rotational_blur(
            scale_center(rotate_frame(b,  85.0 * (1-et) * intensity), overscan),
            6.0 * sin_peak * intensity)
        return blend(ra, rb, et)

    # ── whip_pan_left (CapCut style) ──────────────────────────────────────────
    elif name == "whip_pan_left":
        dx = int(w * et * intensity)
        blur = max(1, int(w * 0.15 * sin_peak * intensity)) | 1
        fa = directional_blur(translate(a, -dx, 0), blur, "x")
        fb = directional_blur(translate(b, w - dx, 0), blur, "x")
        out = fa.copy().astype(np.float32)
        split = max(0, w - dx)
        if split < w:
            out[:, split:] = fb[:, split:]
        return np.clip(out + sin_peak * 35 * intensity, 0, 255).astype(np.uint8)

    # ── whip_pan_right (CapCut style) ─────────────────────────────────────────
    elif name == "whip_pan_right":
        dx = int(w * et * intensity)
        blur = max(1, int(w * 0.15 * sin_peak * intensity)) | 1
        fa = directional_blur(translate(a, dx, 0), blur, "x")
        fb = directional_blur(translate(b, -(w - dx), 0), blur, "x")
        out = fa.copy().astype(np.float32)
        split = min(w, dx)
        if split > 0:
            out[:, :split] = fb[:, :split]
        return np.clip(out + sin_peak * 35 * intensity, 0, 255).astype(np.uint8)

    else:
        return blend(a, b, et)
