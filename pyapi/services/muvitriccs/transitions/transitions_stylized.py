# pyapi/services/muvitriccs/transitions/transitions_stylized.py
"""
MuviTriccs Stylized Transitions
flash, glitch, rgb_split, wave_warp, lens_distortion,
film_burn, light_leak, scanline_tear, vhs_dropout
"""

import math
import random
from typing import Optional

import numpy as np
import cv2

from ..easing import get_easing
from ..primitives import blend, lens_remap, smooth_noise, gaussian_blur_cv


def render_stylized(
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

    # ── flash ─────────────────────────────────────────────────────────────────
    if name == "flash":
        white = np.full_like(a, 255)
        pulse = sin_peak ** 1.5 * intensity
        if t < 0.5:
            return blend(a, white, easing_fn(t * 2) * pulse)
        return blend(white, b, easing_fn((t - 0.5) * 2))

    # ── glitch ────────────────────────────────────────────────────────────────
    elif name == "glitch":
        out = blend(a, b, et).copy()
        for _ in range(int(14 * intensity)):
            row   = rng.randint(0, h - 1)
            rh    = rng.randint(1, max(2, h // 16))
            shift = rng.randint(-int(w * 0.1 * intensity), int(w * 0.1 * intensity))
            out[row:row+rh] = np.roll(out[row:row+rh], shift, axis=1)
        ch_off = int(w * 0.018 * intensity * sin_peak)
        if ch_off > 0:
            out[:, :, 2] = np.roll(out[:, :, 2],  ch_off, axis=1)
            out[:, :, 0] = np.roll(out[:, :, 0], -ch_off, axis=1)
        return out

    # ── rgb_split ─────────────────────────────────────────────────────────────
    elif name == "rgb_split":
        out  = blend(a, b, et).copy()
        hoff = int(w * 0.032 * sin_peak * intensity)
        voff = int(h * 0.008 * sin_peak * intensity)
        if hoff > 0:
            out[:, :, 0] = np.roll(out[:, :, 0],  hoff, axis=1)
            out[:, :, 2] = np.roll(out[:, :, 2], -hoff, axis=1)
        if voff > 0:
            out[:, :, 1] = np.roll(out[:, :, 1], voff, axis=0)
        return out

    # ── wave_warp ─────────────────────────────────────────────────────────────
    elif name == "wave_warp":
        amplitude = int(h * 0.07 * intensity * sin_peak)
        y_idx     = np.arange(h)
        x_shift   = (np.sin(y_idx * 5.0 * math.pi / h + t * math.pi * 3) * amplitude).astype(int)
        fa, fb    = a.copy(), b.copy()
        for row in range(h):
            fa[row] = np.roll(fa[row],  x_shift[row], axis=0)
            fb[row] = np.roll(fb[row], -x_shift[row], axis=0)
        return blend(fa, fb, et)

    # ── lens_distortion ───────────────────────────────────────────────────────
    elif name == "lens_distortion":
        k = 0.65 * sin_peak * intensity
        return blend(lens_remap(a, k), lens_remap(b, -k), et)

    # ── film_burn (CapCut style) ──────────────────────────────────────────────
    elif name == "film_burn":
        noise   = smooth_noise(h, w, 0.12, rng)
        burn_cx = w * (0.45 + rng.uniform(-0.1, 0.1))
        burn_cy = h * (0.45 + rng.uniform(-0.1, 0.1))
        yg, xg  = np.mgrid[0:h, 0:w]
        dist    = np.sqrt(((xg - burn_cx)/w)**2 + ((yg - burn_cy)/h)**2)
        field   = dist / dist.max() + noise * 0.35
        field   = field / field.max()
        radius  = et * intensity * 1.5
        reveal  = field < radius
        edge    = (field >= radius) & (field < radius + 0.12 * intensity)
        out     = a.copy().astype(np.float32)
        out[reveal] = b[reveal].astype(np.float32)
        if edge.any():
            fire_color = np.array([50, 150, 255], np.float32) * (1.0 + sin_peak)
            out[edge] = np.clip(out[edge] + fire_color * 0.8, 0, 255)
        return out.astype(np.uint8)

    # ── light_leak (CapCut style) ─────────────────────────────────────────────
    elif name == "light_leak":
        corner_x = w * rng.uniform(0.0, 0.3)
        corner_y = h * rng.uniform(0.0, 0.3)
        yg, xg   = np.mgrid[0:h, 0:w]
        dist     = np.sqrt(((xg - corner_x)/w)**2 + ((yg - corner_y)/h)**2)
        mask     = (1.0 - np.clip(dist / max(0.01, 1.2 * (1.0 - et + 0.1)), 0, 1)) ** 2
        mask     = mask * sin_peak * intensity * 1.5
        warm     = np.stack([mask*40, mask*120, mask*255], axis=-1).astype(np.float32)
        base     = blend(a, b, et).astype(np.float32)
        return np.clip(base + warm, 0, 255).astype(np.uint8)

    # ── scanline_tear ─────────────────────────────────────────────────────────
    elif name == "scanline_tear":
        out        = blend(a, b, et).copy()
        corruption = sin_peak * intensity
        for _ in range(int(20 * corruption)):
            row   = rng.randint(0, h - 1)
            bh    = rng.randint(1, max(2, int(h * 0.04)))
            if rng.random() < 0.2:
                out[row:row+bh] = 0
            else:
                shift = rng.randint(-int(w * 0.15), int(w * 0.15))
                out[row:row+bh] = np.roll(out[row:row+bh], shift, axis=1)
        for _ in range(int(8 * corruption)):
            row  = rng.randint(0, h - 1)
            line = (np.random.rand(1, w, 3) * 255 * corruption).astype(np.uint8)
            out[row:row+1] = np.clip(
                out[row:row+1].astype(np.int32) + line - 64, 0, 255).astype(np.uint8)
        return out

    # ── vhs_dropout ───────────────────────────────────────────────────────────
    elif name == "vhs_dropout":
        out = blend(a, b, et).astype(np.float32)
        for _ in range(int(12 * sin_peak * intensity)):
            y0  = rng.randint(0, h - 1)
            bh  = rng.randint(1, max(2, h // 30))
            lum = rng.uniform(-40, 40) * intensity
            out[y0:y0+bh] = np.clip(out[y0:y0+bh] + lum, 0, 255)
        desat = sin_peak * 0.55 * intensity
        gray  = cv2.cvtColor(np.clip(out, 0, 255).astype(np.uint8),
                             cv2.COLOR_BGR2GRAY).astype(np.float32)
        out   = out * (1 - desat) + np.stack([gray]*3, axis=-1) * desat
        wobble = int(sin_peak * 4 * intensity)
        if wobble > 0:
            out = np.roll(out, wobble, axis=0)
        return np.clip(out, 0, 255).astype(np.uint8)

    else:
        return blend(a, b, et)
