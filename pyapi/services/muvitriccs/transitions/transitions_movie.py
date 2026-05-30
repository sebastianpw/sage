# pyapi/services/muvitriccs/transitions/transitions_movie.py
"""
MuviTriccs Movie Transitions — Pack 4
Cinematic, narrative-quality transitions rooted in real film grammar.
Inspired by CapCut's Movie category and classic filmmaking techniques.

Transitions:
  iris_wipe        — circular iris opens/closes (classic silent-cinema cut)
  venetian_blind   — horizontal bar wipe in strips (classic film/TV transition)
  cross_zoom       — both clips zoom toward each other at the splice point
  tilt_shift_cut   — rack-focus / miniature blur shift: A goes tilt-shift, B comes in sharp
  cinematic_bars   — letterbox bars squeeze A into scope, retract to reveal B full-frame
  whip_zoom        — directional whip pan + simultaneous radial zoom burst (more epic than whip_pan)
"""

import math
import random
from typing import Optional

import numpy as np
import cv2

from ..easing import get_easing
from ..primitives import (
    blend, gaussian_blur_cv, scale_center,
    directional_blur, radial_blur, translate,
)


# ── helpers ────────────────────────────────────────────────────────────────────

def _tilt_shift_blur(frame: np.ndarray, focus_y_norm: float,
                     blur_max: float, band_width: float) -> np.ndarray:
    """
    Apply a tilt-shift lens simulation: a horizontal band centred at focus_y_norm
    stays sharp, everything above/below blurs with increasing sigma.
    focus_y_norm in [0, 1] (0=top, 1=bottom). band_width in [0, 1].
    blur_max: maximum sigma at the extreme edges.
    """
    h, w  = frame.shape[:2]
    out   = frame.astype(np.float32)
    focus_px = focus_y_norm * h
    half_band = band_width * h / 2.0

    # Build blur sigma per row
    sigmas = []
    for row in range(h):
        dist = max(0.0, abs(row - focus_px) - half_band)
        sigma = (dist / max(h * 0.5, 1.0)) * blur_max
        sigmas.append(sigma)

    # Gaussian blur at multiple levels, composite per row
    levels = [0, 2, 5, 10, 18, 28]
    blurred = [frame]
    for sig in levels[1:]:
        real_sig = sig * blur_max / 28.0
        if real_sig >= 0.5:
            blurred.append(gaussian_blur_cv(frame, real_sig))
        else:
            blurred.append(frame)

    result = np.zeros_like(out)
    for row in range(h):
        sig = sigmas[row]
        # find nearest level
        best_idx = 0
        best_diff = abs(levels[0] * blur_max / 28.0 - sig)
        for idx, lv in enumerate(levels):
            real_lv = lv * blur_max / 28.0
            diff = abs(real_lv - sig)
            if diff < best_diff:
                best_diff = diff
                best_idx = idx
        result[row] = blurred[best_idx][row]

    return np.clip(result, 0, 255).astype(np.uint8)


# ── main renderer ──────────────────────────────────────────────────────────────

def render_movie(
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

    # ── iris_wipe ─────────────────────────────────────────────────────────────
    # Classic circular iris: a hard-edged circle grows from the centre,
    # revealing B inside while A remains outside. Soft feathered border.
    # Nod to Buster Keaton, Chaplin, and every silent-era film.
    if name == "iris_wipe":
        cx, cy  = w / 2.0, h / 2.0
        max_r   = math.sqrt(cx**2 + cy**2) * 1.05   # slightly beyond corners
        yg, xg  = np.mgrid[0:h, 0:w].astype(np.float32)
        dist    = np.sqrt((xg - cx)**2 + (yg - cy)**2)

        # Iris radius grows with easing
        iris_r  = et * max_r * intensity
        # Soft feather band
        feather = max_r * 0.04 * intensity
        mask    = np.clip((iris_r - dist) / max(feather, 1.0), 0.0, 1.0)
        m3      = mask[:, :, np.newaxis]

        # Optional slight vignette on A as it gets squeezed out
        vignette = 1.0 - (1.0 - mask) * 0.35 * intensity
        a_dark   = (a.astype(np.float32) * vignette[:, :, np.newaxis])
        a_dark   = np.clip(a_dark, 0, 255).astype(np.uint8)

        return (a_dark.astype(np.float32) * (1.0 - m3) +
                b.astype(np.float32) * m3).clip(0, 255).astype(np.uint8)

    # ── venetian_blind ────────────────────────────────────────────────────────
    # N horizontal strips wipe progressively from left to right, each offset
    # by a small phase delay so they ripple in staggered succession.
    # Classic TV/film wipe; also resembles a venetian blind closing.
    elif name == "venetian_blind":
        n_strips = max(4, int(10 * intensity))
        strip_h  = max(1, h // n_strips)
        out      = a.copy().astype(np.float32)

        for i in range(n_strips):
            # Phase offset: bottom strips start later for a ripple cascade
            phase  = i / n_strips * 0.4   # 0→0.4 delay spread
            local_t = max(0.0, min(1.0, (t - phase) / max(0.01, 1.0 - phase)))
            local_et = easing_fn(local_t)
            wipe_x   = int(w * local_et)

            y0 = i * strip_h
            y1 = min(h, y0 + strip_h)
            if wipe_x > 0:
                out[y0:y1, :wipe_x] = b[y0:y1, :wipe_x].astype(np.float32)
            # Thin bright edge line at the wipe front
            edge_x = min(wipe_x, w - 1)
            edge_w = max(1, int(w * 0.008))
            x0e = max(0, edge_x - edge_w)
            bright = 0.7 * sin_peak * intensity
            out[y0:y1, x0e:edge_x] = np.clip(
                out[y0:y1, x0e:edge_x] + bright * 255, 0, 255)

        return out.astype(np.uint8)

    # ── cross_zoom ────────────────────────────────────────────────────────────
    # Both clips zoom toward each other simultaneously. A zooms *in* fast
    # (rushing toward viewer), B zooms *out* from extreme close, converging
    # at the splice point. A brief radial blur at t=0.5 hides the join.
    elif name == "cross_zoom":
        # A zooms in: scale grows from 1→2.2
        scale_a = 1.0 + 1.2 * et * intensity
        # B zooms out: scale starts at 2.2→1
        scale_b = 1.0 + 1.2 * (1.0 - et) * intensity
        # Radial blur peaks at cut midpoint
        blur_s  = sin_peak * 0.9 * intensity

        za = radial_blur(scale_center(a, scale_a), blur_s)
        zb = radial_blur(scale_center(b, scale_b), blur_s)

        # Brief white flash at the zoom collision point
        flash = max(0.0, 1.0 - abs(t - 0.5) / 0.06) * 0.65 * intensity
        out   = blend(za, zb, et).astype(np.float32)
        return np.clip(out + flash * 255, 0, 255).astype(np.uint8)

    # ── tilt_shift_cut ────────────────────────────────────────────────────────
    # Rack-focus / miniature lens simulation.
    # Phase 1 (t<0.5): A develops a tilt-shift miniature look (narrow depth
    #   of field, blurred top+bottom, sharp middle band that narrows).
    # Phase 2 (t>0.5): B enters with extreme tilt-shift that clears to full
    #   sharp focus as the "lens racks" to the new focal distance.
    elif name == "tilt_shift_cut":
        focus_y = float(spec.get("focus_y", 0.5))    # 0=top 1=bottom; default centre

        if t < 0.5:
            # A: tilt-shift intensifies
            progress    = easing_fn(t * 2)             # 0→1
            blur_max    = progress * 32.0 * intensity
            band_width  = max(0.05, 0.5 - progress * 0.4)  # narrows from 0.5→0.1
            fa = _tilt_shift_blur(a, focus_y, blur_max, band_width)
            # Slight colour desaturation toward miniature look
            gray_a = cv2.cvtColor(a, cv2.COLOR_BGR2GRAY)
            gray_a = cv2.cvtColor(gray_a, cv2.COLOR_GRAY2BGR).astype(np.float32)
            desat  = progress * 0.30 * intensity
            fa_f   = fa.astype(np.float32) * (1.0 - desat) + gray_a * desat
            # Saturation boost for miniature feel (slightly increased colours)
            hsv    = cv2.cvtColor(np.clip(fa_f, 0, 255).astype(np.uint8),
                                  cv2.COLOR_BGR2HSV).astype(np.float32)
            hsv[:, :, 1] = np.clip(hsv[:, :, 1] * (1.0 + 0.3 * progress * intensity), 0, 255)
            fa_final = cv2.cvtColor(hsv.astype(np.uint8), cv2.COLOR_HSV2BGR)
            return blend(fa_final, b, et)
        else:
            # B: rack focus clears
            progress   = easing_fn((t - 0.5) * 2)     # 0→1
            blur_max   = (1.0 - progress) * 32.0 * intensity
            band_width = 0.1 + progress * 0.4          # widens from 0.1→0.5
            fb = _tilt_shift_blur(b, focus_y, blur_max, band_width)
            gray_b = cv2.cvtColor(b, cv2.COLOR_BGR2GRAY)
            gray_b = cv2.cvtColor(gray_b, cv2.COLOR_GRAY2BGR).astype(np.float32)
            desat  = (1.0 - progress) * 0.30 * intensity
            fb_f   = fb.astype(np.float32) * (1.0 - desat) + gray_b * desat
            return blend(a, np.clip(fb_f, 0, 255).astype(np.uint8), et)

    # ── cinematic_bars ────────────────────────────────────────────────────────
    # Black letterbox bars grow from top+bottom squeezing A into 2.39:1 scope,
    # then the bars retract revealing B in full frame.
    # This replicates the "cinematic scope" transition used in CapCut's Movie pack
    # and in film trailers worldwide.
    elif name == "cinematic_bars":
        # Target scope ratio: bars occupy top+bottom combined = 1 - h_scope/h
        # For 2.39:1 in a 1:1 canvas: scope_h = w / 2.39, bars = (h - scope_h) / 2
        scope_ratio  = float(spec.get("scope_ratio", 2.39))
        scope_h_norm = min(0.9, (w / scope_ratio) / max(h, 1))
        bar_h_norm   = max(0.0, (1.0 - scope_h_norm) / 2.0)   # fraction of h per bar

        # Animation:
        # t 0→0.4: bars close in on A (A shrinks to scope)
        # t 0.4→0.6: dissolve A→B inside scope
        # t 0.6→1.0: bars retract revealing B full-frame
        if t < 0.4:
            local_t  = easing_fn(t / 0.4)
            bar_px   = int(bar_h_norm * h * local_t * intensity)
            frame    = a.copy()
        elif t < 0.6:
            local_t  = (t - 0.4) / 0.2
            bar_px   = int(bar_h_norm * h * intensity)
            frame    = blend(a, b, local_t)
        else:
            local_t  = easing_fn((t - 0.6) / 0.4)
            bar_px   = int(bar_h_norm * h * (1.0 - local_t) * intensity)
            frame    = b.copy()

        out = frame.copy()
        bar_px = max(0, min(bar_px, h // 2 - 1))
        if bar_px > 0:
            out[:bar_px, :]  = 0   # top bar
            out[-bar_px:, :] = 0   # bottom bar
            # Thin warm line at bar edge — like a film frame gate
            line_h = max(1, int(h * 0.003))
            out[bar_px:bar_px + line_h, :] = [30, 60, 100]
            out[-(bar_px + line_h):-bar_px, :] = [30, 60, 100]

        return out

    # ── whip_zoom ─────────────────────────────────────────────────────────────
    # Directional whip pan combined with simultaneous radial zoom burst.
    # More dramatic than whip_pan_left/right: the zoom makes it feel like the
    # camera is both panning AND lunging forward at the same time.
    # Used heavily in film trailers and CapCut's cinematic action templates.
    elif name == "whip_zoom":
        direction = spec.get("whip_zoom_dir", "left")   # left / right / up / down

        # Pan component
        if direction in ("left", "right"):
            pan_axis = "x"
            dx = int(w * et * intensity)
            dy = 0
            sign_a = -1 if direction == "left" else 1
            sign_b = 1  if direction == "left" else -1
        else:
            pan_axis = "y"
            dx = 0
            dy = int(h * et * intensity)
            sign_a = -1 if direction == "up" else 1
            sign_b = 1  if direction == "up" else -1

        # Directional blur (motion smear)
        blur_k = max(1, int((w if pan_axis == "x" else h) * 0.18 * sin_peak * intensity)) | 1

        fa = directional_blur(translate(a, sign_a * dx, sign_a * dy), blur_k, pan_axis)
        fb = directional_blur(translate(b, sign_b * (w - dx) if pan_axis == "x" else 0,
                                           sign_b * (h - dy) if pan_axis == "y" else 0),
                              blur_k, pan_axis)

        # Zoom burst layer: radial expansion on A, contraction on B
        zoom_s  = sin_peak * 0.55 * intensity
        scale_a = 1.0 + 0.4 * et * intensity
        za      = radial_blur(scale_center(a, scale_a), zoom_s)
        zb      = radial_blur(scale_center(b, 1.0 + 0.4 * (1.0 - et) * intensity), zoom_s)

        # Composite: blend pan layer with zoom layer (zoom dominates at peak)
        zoom_weight = sin_peak * 0.6 * intensity
        pan_a  = fa.astype(np.float32)
        pan_b  = fb.astype(np.float32)
        zoom_a = za.astype(np.float32)
        zoom_b = zb.astype(np.float32)

        comp_a = pan_a * (1.0 - zoom_weight) + zoom_a * zoom_weight
        comp_b = pan_b * (1.0 - zoom_weight) + zoom_b * zoom_weight

        # Assemble split canvas (pan seam)
        out = comp_a.copy()
        if pan_axis == "x":
            split = max(0, w - dx) if direction == "left" else min(w, dx)
            if direction == "left" and split < w:
                out[:, split:] = comp_b[:, split:]
            elif direction == "right" and split > 0:
                out[:, :split] = comp_b[:, :split]
        else:
            split = max(0, h - dy) if direction == "up" else min(h, dy)
            if direction == "up" and split < h:
                out[split:, :] = comp_b[split:, :]
            elif direction == "down" and split > 0:
                out[:split, :] = comp_b[:split, :]

        # Luminance boost at peak — the classic "overexpose at whip" look
        boost = sin_peak * 45.0 * intensity
        return np.clip(out + boost, 0, 255).astype(np.uint8)

    # ── fallback ──────────────────────────────────────────────────────────────
    else:
        return blend(a, b, et)
