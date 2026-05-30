# pyapi/services/muvitriccs/transitions/transitions_epic.py
"""
MuviTriccs Epic Transitions — Pack 3
High-energy, beat-sync-friendly cuts inspired by CapCut's Epic category.

Transitions:
  speed_ramp    — time-remap: A decelerates to near-freeze, B explodes from freeze
  shockwave     — radial pressure-ring expansion wipes A into B
  strobe_cut    — rapid alternating A/B frames converging on B (stroboscopic flash)
  motion_trail  — A leaves luminance ghost trails as it exits; B materialises through them
  glare_hit     — full-frame directional lens-glare sweep at cut point, peak white → B
"""

import math
import random
from typing import Optional

import numpy as np
import cv2

from ..easing import get_easing
from ..primitives import blend, gaussian_blur_cv, directional_blur, scale_center


# ── helpers ────────────────────────────────────────────────────────────────────

def _additive_screen(base: np.ndarray, layer: np.ndarray, alpha: float) -> np.ndarray:
    """Screen-blend layer onto base with alpha weight. All uint8."""
    b = base.astype(np.float32) / 255.0
    lyr = layer.astype(np.float32) / 255.0 * alpha
    out = 1.0 - (1.0 - b) * (1.0 - lyr)
    return np.clip(out * 255.0, 0, 255).astype(np.uint8)


def _luminance_map(bgr: np.ndarray) -> np.ndarray:
    """Return float32 (h, w) luminance [0, 1]."""
    return cv2.cvtColor(bgr, cv2.COLOR_BGR2GRAY).astype(np.float32) / 255.0


def _radial_ring_mask(h: int, w: int, radius_norm: float,
                      ring_width: float) -> np.ndarray:
    """
    Float32 (h, w) mask: 1.0 inside the ring, 0.0 outside.
    radius_norm in [0, 1] where 1 = corner distance.
    ring_width controls soft edge thickness.
    """
    cx, cy = w / 2.0, h / 2.0
    max_r  = math.sqrt(cx**2 + cy**2)
    yg, xg = np.mgrid[0:h, 0:w].astype(np.float32)
    dist   = np.sqrt((xg - cx)**2 + (yg - cy)**2) / max_r  # [0, ~1]
    r      = radius_norm
    hw     = ring_width / 2.0
    mask   = np.clip(1.0 - np.abs(dist - r) / max(hw, 1e-6), 0.0, 1.0)
    return mask.astype(np.float32)


# ── main renderer ──────────────────────────────────────────────────────────────

def render_epic(
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

    # ── speed_ramp ────────────────────────────────────────────────────────────
    # Time-remap illusion: first half A decelerates (freeze hold + radial blur),
    # second half B accelerates in from a frozen state.
    # The "freeze" is simulated by holding the boundary frame with increasing
    # radial blur that suggests extreme slow-motion, then snapping to B which
    # starts with the same blur quickly clearing.
    if name == "speed_ramp":
        freeze_zone = 0.18 * intensity   # fraction around t=0.5 that is "frozen"
        t_norm = (t - 0.5) / max(freeze_zone, 0.01)   # <-1 … 0 … >1
        t_norm = max(-1.0, min(1.0, t_norm))

        if t < 0.5:
            # A slows: blur increases as t approaches 0.5
            slow_factor = 1.0 - easing_fn(t * 2)         # 1→0 as t→0.5
            blur_s = (1.0 - slow_factor) * 18.0 * intensity
            fa = gaussian_blur_cv(a, blur_s)
            # slight desaturation as it "freezes"
            gray_a = cv2.cvtColor(a, cv2.COLOR_BGR2GRAY)
            gray_a = cv2.cvtColor(gray_a, cv2.COLOR_GRAY2BGR).astype(np.float32)
            desat  = (1.0 - slow_factor) * 0.55 * intensity
            fa_f   = fa.astype(np.float32) * (1.0 - desat) + gray_a * desat
            # freeze frame scale — very slight zoom-in as it slows
            zoom_s = 1.0 + (1.0 - slow_factor) * 0.04 * intensity
            fa_out = scale_center(np.clip(fa_f, 0, 255).astype(np.uint8), zoom_s)
            return blend(fa_out, b, et)
        else:
            # B explodes: blur clears, colours pop back
            accel  = easing_fn((t - 0.5) * 2)            # 0→1 as t→1
            blur_s = (1.0 - accel) * 18.0 * intensity
            fb = gaussian_blur_cv(b, blur_s)
            gray_b = cv2.cvtColor(b, cv2.COLOR_BGR2GRAY)
            gray_b = cv2.cvtColor(gray_b, cv2.COLOR_GRAY2BGR).astype(np.float32)
            desat  = (1.0 - accel) * 0.55 * intensity
            fb_f   = fb.astype(np.float32) * (1.0 - desat) + gray_b * desat
            zoom_s = 1.0 + (1.0 - accel) * 0.06 * intensity
            fb_out = scale_center(np.clip(fb_f, 0, 255).astype(np.uint8), zoom_s)
            # brief white flash right at t=0.5
            flash  = max(0.0, 1.0 - abs(t - 0.5) / 0.08) * 0.55 * intensity
            out    = blend(a, fb_out, et).astype(np.float32)
            return np.clip(out + flash * 255, 0, 255).astype(np.uint8)

    # ── shockwave ─────────────────────────────────────────────────────────────
    # A radial pressure ring expands from centre. Everything inside the ring
    # shows B; everything outside still shows A. The ring itself displaces pixels
    # outward (compression wave) with a bright rim.
    elif name == "shockwave":
        cx, cy  = w / 2.0, h / 2.0
        max_r   = math.sqrt(cx**2 + cy**2)
        yg, xg  = np.mgrid[0:h, 0:w].astype(np.float32)
        dx, dy  = xg - cx, yg - cy
        dist    = np.sqrt(dx**2 + dy**2)
        safe_d  = np.where(dist > 0, dist, 1.0)

        # Ring radius expands 0→max_r * 1.3 over transition
        ring_r  = et * max_r * 1.35 * intensity
        ring_w  = max_r * 0.10 * intensity   # ring thickness in pixels

        # Displacement: pixels near the ring front get pushed outward
        rel_dist = dist - ring_r
        push_amt = np.clip(1.0 - np.abs(rel_dist) / max(ring_w, 1.0), 0.0, 1.0)
        push_px  = push_amt * ring_w * 0.6 * intensity
        norm_x   = dx / safe_d
        norm_y   = dy / safe_d
        src_x    = np.clip(xg + norm_x * push_px, 0, w - 1).astype(np.float32)
        src_y    = np.clip(yg + norm_y * push_px, 0, h - 1).astype(np.float32)

        wa = cv2.remap(a, src_x, src_y, cv2.INTER_LINEAR,
                       borderMode=cv2.BORDER_REFLECT_101)
        wb = cv2.remap(b, src_x, src_y, cv2.INTER_LINEAR,
                       borderMode=cv2.BORDER_REFLECT_101)

        # Reveal mask: B inside the ring, A outside
        reveal  = np.clip((ring_r - dist) / max(ring_w * 0.5, 1.0), 0.0, 1.0)
        reveal3 = reveal[:, :, np.newaxis]

        # Bright rim highlight
        rim     = push_amt * 0.9 * intensity
        rim3    = rim[:, :, np.newaxis]

        out = wa.astype(np.float32) * (1.0 - reveal3) + wb.astype(np.float32) * reveal3
        out = np.clip(out + rim3 * 220, 0, 255)
        return out.astype(np.uint8)

    # ── strobe_cut ────────────────────────────────────────────────────────────
    # Stroboscopic alternation: early frames mostly A with brief B flashes;
    # frequency converges so last frames are mostly B. The strobe interval
    # shrinks with easing, giving a rapid-fire feel that lands on B.
    elif name == "strobe_cut":
        # Number of strobe cycles scales with intensity
        cycles    = max(3, int(8 * intensity))
        # Phase within strobe cycle
        phase     = (t * cycles) % 1.0
        # Duty cycle: starts narrow (brief B flashes) → widens (more B)
        duty      = et * 0.85
        in_b      = phase < duty
        # Blend amount at non-strobe times — smooth dissolve underneath
        base_mix  = et * 0.4
        if in_b:
            mix = min(1.0, base_mix + 0.85)
        else:
            mix = base_mix
        # Slight exposure boost during strobe flash
        boost = 1.0 + (0.18 * intensity if in_b else 0.0)
        out   = blend(a, b, mix).astype(np.float32) * boost
        # add a thin white scanline at the strobe boundary for a flash artifact
        if abs(phase - duty) < 0.06 * intensity:
            scanline_row = rng.randint(0, h - 1)
            scan_h       = max(1, int(h * 0.012))
            out[scanline_row:scanline_row + scan_h] = np.clip(
                out[scanline_row:scanline_row + scan_h] + 180, 0, 255)
        return np.clip(out, 0, 255).astype(np.uint8)

    # ── motion_trail ──────────────────────────────────────────────────────────
    # A leaves luminance-weighted ghost echo frames as it "exits". The ghosts
    # persist with decreasing opacity and are screen-blended onto B revealing
    # underneath. Effect peaks at t=0.5 then ghosts fade as B takes over.
    elif name == "motion_trail":
        n_trails  = max(3, int(7 * intensity))
        trail_dir = spec.get("trail_dir", "right")   # left/right/up/down
        max_offset = int((w if trail_dir in ("left", "right") else h) * 0.22 * intensity)

        # Luminance mask of A — brighter pixels trail more
        lum_a = _luminance_map(a)

        # Base: dissolve A into B
        base  = blend(a, b, et).astype(np.float32)

        # Accumulate ghost trails — peak in middle of transition
        trail_strength = sin_peak * intensity
        for i in range(1, n_trails + 1):
            frac   = i / (n_trails + 1)
            offset = int(max_offset * frac * (1.0 - abs(t - 0.5) * 1.5))
            alpha  = trail_strength * (1.0 - frac) ** 1.2 * 0.55

            if trail_dir == "right":
                shifted = np.roll(a, offset, axis=1)
            elif trail_dir == "left":
                shifted = np.roll(a, -offset, axis=1)
            elif trail_dir == "down":
                shifted = np.roll(a, offset, axis=0)
            else:
                shifted = np.roll(a, -offset, axis=0)

            # Weight trail by luminance — bright areas trail most
            lum_weight = lum_a[:, :, np.newaxis] * alpha
            base = np.clip(
                base + shifted.astype(np.float32) * lum_weight, 0, 255)

        # Chromatic split on trails at peak
        ch_off = int(w * 0.012 * sin_peak * intensity)
        if ch_off > 0:
            base_u8  = np.clip(base, 0, 255).astype(np.uint8)
            base_u8[:, :, 2] = np.roll(base_u8[:, :, 2],  ch_off, axis=1)
            base_u8[:, :, 0] = np.roll(base_u8[:, :, 0], -ch_off, axis=1)
            return base_u8
        return np.clip(base, 0, 255).astype(np.uint8)

    # ── glare_hit ─────────────────────────────────────────────────────────────
    # A full-frame directional lens-flare streak sweeps across at the cut point.
    # Unlike light_leak (corner bloom), this is a hard horizontal or diagonal
    # glare bar that peaks to near-white then clears to reveal B.
    # Inspired by CapCut's "Glare" and "Light Hit" transitions.
    elif name == "glare_hit":
        direction = spec.get("glare_dir", "horizontal")  # horizontal / diagonal

        # Glare position sweeps from left→right (or top-left→bottom-right)
        glare_pos = et  # 0=far left, 1=far right

        # Build glare mask
        yg, xg = np.mgrid[0:h, 0:w].astype(np.float32)
        if direction == "diagonal":
            # Diagonal sweep: combined x+y
            sweep = (xg / w + yg / h) / 2.0
        else:
            sweep = xg / w   # horizontal sweep

        # Glare is a soft Gaussian band centred at glare_pos
        glare_width = 0.18 * intensity
        glare_mask  = np.exp(-0.5 * ((sweep - glare_pos) / max(glare_width, 0.01)) ** 2)
        glare_mask  = (glare_mask ** 0.7).astype(np.float32)

        # Intensity envelope: peaks at t=0.5
        envelope    = sin_peak * intensity * 1.3
        glare_mask  *= envelope

        # Base: dissolve A→B (glare masks the cut)
        base = blend(a, b, et).astype(np.float32)

        # Additive warm-white glare
        warm_white = np.array([200.0, 220.0, 255.0], dtype=np.float32)
        gm3        = glare_mask[:, :, np.newaxis]
        out        = np.clip(base + gm3 * warm_white, 0, 255)

        # Slight horizontal blur in the glare band (motion smear)
        blur_k = max(1, int(w * 0.04 * sin_peak * intensity)) | 1
        blurred = cv2.filter2D(out.astype(np.uint8), -1,
                               np.ones((1, blur_k), np.float32) / blur_k)
        # blend blurred only where glare is strong
        glare_clamped = np.clip(gm3 * 1.5, 0.0, 1.0)
        final = out * (1.0 - glare_clamped) + blurred.astype(np.float32) * glare_clamped
        return np.clip(final, 0, 255).astype(np.uint8)

    # ── fallback ──────────────────────────────────────────────────────────────
    else:
        return blend(a, b, et)
