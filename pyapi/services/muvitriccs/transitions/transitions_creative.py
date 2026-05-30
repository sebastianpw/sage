# pyapi/services/muvitriccs/transitions/transitions_creative.py
"""
MuviTriccs Creative Transitions — Pack 2
Inspired by CapCut's advanced transition vocabulary, Uppbeat motion graphics,
and professional VFX pipelines.

New transitions:
  pixel_sort       — glitchy column/row sort reveals B through sorted A pixels
  ink_wash         — diffusion-style organic ink bleed reveal
  shatter          — A shatters into voronoi shards that fall/spin off
  smear_frame      — motion-smear echo of A smears into B (anime/action style)
  cube_rotate_left — 3-D cube face rotation left (A out, B in)
  cube_rotate_right— 3-D cube face rotation right
  page_curl        — flat page-curl peel revealing B underneath
  kaleidoscope     — kaleidoscopic mirror fold collapse from A to B
  ripple_water     — concentric water-ripple displacement warp
  dream_blur       — dreamy glow bloom dissolve with hue rotation
"""

import math
import random
from typing import Optional

import numpy as np
import cv2

from ..easing import get_easing
from ..primitives import blend, gaussian_blur_cv, smooth_noise


# ── helpers local to this module ──────────────────────────────────────────────

def _pixel_sort_cols(frame: np.ndarray, threshold: float,
                     direction: str = "up") -> np.ndarray:
    """
    Sort pixels within each column by luminance above a threshold.
    direction: 'up' sorts bright pixels upward, 'down' sorts them downward.
    """
    gray  = cv2.cvtColor(frame, cv2.COLOR_BGR2GRAY).astype(np.float32) / 255.0
    out   = frame.copy()
    h, w  = frame.shape[:2]
    for col in range(w):
        lum = gray[:, col]
        mask = lum > threshold
        if not mask.any():
            continue
        idxs = np.where(mask)[0]
        sorted_pixels = frame[idxs, col, :]
        # sort by luminance of extracted pixels
        lum_sorted_order = np.argsort(lum[idxs])
        if direction == "down":
            lum_sorted_order = lum_sorted_order[::-1]
        out[idxs, col, :] = sorted_pixels[lum_sorted_order]
    return out


def _voronoi_mask(h: int, w: int, n_seeds: int,
                  rng: random.Random) -> np.ndarray:
    """
    Build a (h, w) int32 label map of n_seeds voronoi cells.
    Uses fast numpy distance approach (no scipy needed).
    """
    seeds_x = np.array([rng.randint(0, w - 1) for _ in range(n_seeds)])
    seeds_y = np.array([rng.randint(0, h - 1) for _ in range(n_seeds)])
    yg, xg  = np.mgrid[0:h, 0:w]
    # shape (h, w, n_seeds)
    dy = (yg[:, :, np.newaxis] - seeds_y[np.newaxis, np.newaxis, :]) ** 2
    dx = (xg[:, :, np.newaxis] - seeds_x[np.newaxis, np.newaxis, :]) ** 2
    labels = np.argmin(dx + dy, axis=2).astype(np.int32)
    return labels, seeds_x, seeds_y


def _perspective_warp(frame: np.ndarray,
                      src_pts: np.ndarray,
                      dst_pts: np.ndarray,
                      out_w: int, out_h: int) -> np.ndarray:
    """Perspective warp frame from src_pts quad to dst_pts quad."""
    M = cv2.getPerspectiveTransform(
        src_pts.astype(np.float32), dst_pts.astype(np.float32))
    return cv2.warpPerspective(frame, M, (out_w, out_h),
                               borderMode=cv2.BORDER_CONSTANT,
                               borderValue=(0, 0, 0))


def _hue_rotate(frame: np.ndarray, degrees: float) -> np.ndarray:
    """Rotate hue channel by degrees in HSV space."""
    hsv = cv2.cvtColor(frame, cv2.COLOR_BGR2HSV).astype(np.float32)
    hsv[:, :, 0] = (hsv[:, :, 0] + degrees / 2.0) % 180.0
    return cv2.cvtColor(np.clip(hsv, 0, 255).astype(np.uint8),
                        cv2.COLOR_HSV2BGR)


# ── main renderer ──────────────────────────────────────────────────────────────

def render_creative(
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

    # ── pixel_sort ────────────────────────────────────────────────────────────
    # Luminance-sorted columns sweep upward through A, revealing B underneath.
    # At t=0 A is untouched; at t=1 B is fully sorted-in. Mid-transition shows
    # the signature glitchy "sorted" aesthetic made famous by Kim Asendorf.
    elif_guard = False  # just for structure below — all are if/elif/else
    if name == "pixel_sort":
        # threshold sweeps from 1.0 (no pixels sorted) to 0.0 (all sorted)
        threshold = max(0.0, 1.0 - et * intensity * 1.1)
        direction = "up" if t < 0.5 else "down"
        sorted_a  = _pixel_sort_cols(a, threshold, direction)
        return blend(sorted_a, b, et)

    # ── ink_wash ──────────────────────────────────────────────────────────────
    # Organic ink bleed: diffusion front expands from multiple seed points,
    # dissolving A into B. Smooth noise controls the uneven bleed front edge.
    elif name == "ink_wash":
        noise   = smooth_noise(h, w, 0.06, rng)
        # multiple seed points give organic multi-origin diffusion feel
        n_seeds = max(1, int(4 * intensity))
        dist_field = np.ones((h, w), dtype=np.float32)
        yg, xg = np.mgrid[0:h, 0:w]
        for _ in range(n_seeds):
            sx = rng.randint(int(w * 0.1), int(w * 0.9))
            sy = rng.randint(int(h * 0.1), int(h * 0.9))
            d  = np.sqrt(((xg - sx) / w) ** 2 + ((yg - sy) / h) ** 2)
            dist_field = np.minimum(dist_field, d)
        dist_field = dist_field / dist_field.max()
        # combine distance with noise for organic edge
        field    = dist_field * 0.65 + noise * 0.35
        field    = field / field.max()
        softness = 0.10 * intensity
        mask     = np.clip((field - et + softness / 2) / softness, 0.0, 1.0)
        # darken the bleed edge slightly (wet ink look)
        edge_dark = (1.0 - np.abs(mask - 0.5) * 2) * 0.3 * sin_peak
        m3 = mask[:, :, np.newaxis]
        ed3 = edge_dark[:, :, np.newaxis]
        out = a * m3 + b * (1.0 - m3)
        out = np.clip(out * (1.0 - ed3), 0, 255)
        return out.astype(np.uint8)

    # ── shatter ───────────────────────────────────────────────────────────────
    # A breaks into ~32 Voronoi shards. Each shard translates and fades out
    # at a slightly randomised offset driven by t, revealing B underneath.
    elif name == "shatter":
        n_shards = max(8, int(32 * intensity))
        labels, sx, sy = _voronoi_mask(h, w, n_shards, rng)
        out = b.copy().astype(np.float32)
        # each shard gets a unique fall vector seeded deterministically
        for i in range(n_shards):
            shard_rng = random.Random(rng.randint(0, 2**31))
            mask_i = (labels == i)
            if not mask_i.any():
                continue
            dx = shard_rng.uniform(-1.0, 1.0) * w * 0.6 * et * intensity
            dy = shard_rng.uniform(0.4, 1.0)  * h * 0.8 * et * intensity
            # shard opacity fades out in second half of t
            alpha_i = max(0.0, 1.0 - et * 1.8)
            if alpha_i <= 0:
                continue
            rows, cols = np.where(mask_i)
            new_rows = np.clip((rows + int(dy)).astype(int), 0, h - 1)
            new_cols = np.clip((cols + int(dx)).astype(int), 0, w - 1)
            out[new_rows, new_cols] = (
                out[new_rows, new_cols] * (1.0 - alpha_i) +
                a[rows, cols].astype(np.float32) * alpha_i
            )
        return np.clip(out, 0, 255).astype(np.uint8)

    # ── smear_frame ───────────────────────────────────────────────────────────
    # Anime/action smear: A leaves motion-echo "smear" copies that persist
    # into B. Multiple directional echo frames are blended with diminishing
    # weight, giving the hand-drawn inbetween smear aesthetic.
    elif name == "smear_frame":
        n_echoes  = max(2, int(6 * intensity))
        smear_dir = spec.get("smear_dir", "right")  # left/right/up/down
        echo_dist = int((w if smear_dir in ("left", "right") else h) * 0.18 * intensity)
        out       = b.astype(np.float32) * et
        weight_sum = et
        for i in range(1, n_echoes + 1):
            frac   = i / (n_echoes + 1)
            t_echo = max(0.0, t - frac * 0.35)
            alpha  = (1.0 - et) * (1.0 - frac) ** 1.5
            offset = int(echo_dist * frac * (1.0 - et))
            if smear_dir == "right":
                shifted = np.roll(a, offset, axis=1)
            elif smear_dir == "left":
                shifted = np.roll(a, -offset, axis=1)
            elif smear_dir == "up":
                shifted = np.roll(a, -offset, axis=0)
            else:
                shifted = np.roll(a, offset, axis=0)
            # horizontal stretch on smear frames (squash & stretch)
            out += shifted.astype(np.float32) * alpha
            weight_sum += alpha
        if weight_sum > 0:
            out /= weight_sum
        return np.clip(out, 0, 255).astype(np.uint8)

    # ── cube_rotate_left ──────────────────────────────────────────────────────
    # Simulates a 3-D cube face rotating left: A face shrinks/skews out right,
    # B face grows/skews in from left, perspective applied via warpPerspective.
    elif name == "cube_rotate_left":
        # Angle in [0, 90] degrees
        angle  = et * 90.0
        rad    = math.radians(angle)
        # A face: right edge squishes as it rotates away
        a_right_scale = math.cos(rad)
        a_w = max(1, int(w * a_right_scale))
        # src: full frame -> dst: right-aligned shrinking strip
        src_a = np.array([[0,0],[w-1,0],[w-1,h-1],[0,h-1]], np.float32)
        dst_a = np.array([[w - a_w, 0],[w-1, 0],[w-1, h-1],[w - a_w, h-1]], np.float32)
        fa = _perspective_warp(a, src_a, dst_a, w, h)

        # B face: left edge grows as it rotates into view
        b_left_scale = math.sin(rad)
        b_w = max(1, int(w * b_left_scale))
        src_b = np.array([[0,0],[w-1,0],[w-1,h-1],[0,h-1]], np.float32)
        dst_b = np.array([[0, 0],[b_w-1, 0],[b_w-1, h-1],[0, h-1]], np.float32)
        fb = _perspective_warp(b, src_b, dst_b, w, h)

        canvas = np.zeros((h, w, 3), dtype=np.uint8)
        # B goes behind A
        fb_mask = (fb.sum(axis=2) > 0)
        canvas[fb_mask] = fb[fb_mask]
        fa_mask = (fa.sum(axis=2) > 0)
        canvas[fa_mask] = fa[fa_mask]
        # add dark edge crease between faces
        crease_x = w - a_w
        crease_w = max(2, int(w * 0.015))
        if 0 <= crease_x < w:
            x0 = max(0, crease_x - crease_w // 2)
            x1 = min(w, crease_x + crease_w // 2)
            canvas[:, x0:x1] = (canvas[:, x0:x1].astype(np.float32) * 0.55).astype(np.uint8)
        return canvas

    # ── cube_rotate_right ─────────────────────────────────────────────────────
    elif name == "cube_rotate_right":
        angle  = et * 90.0
        rad    = math.radians(angle)
        a_left_scale = math.cos(rad)
        a_w = max(1, int(w * a_left_scale))
        src_a = np.array([[0,0],[w-1,0],[w-1,h-1],[0,h-1]], np.float32)
        dst_a = np.array([[0, 0],[a_w-1, 0],[a_w-1, h-1],[0, h-1]], np.float32)
        fa = _perspective_warp(a, src_a, dst_a, w, h)

        b_right_scale = math.sin(rad)
        b_w = max(1, int(w * b_right_scale))
        src_b = np.array([[0,0],[w-1,0],[w-1,h-1],[0,h-1]], np.float32)
        dst_b = np.array([[w - b_w, 0],[w-1, 0],[w-1, h-1],[w - b_w, h-1]], np.float32)
        fb = _perspective_warp(b, src_b, dst_b, w, h)

        canvas = np.zeros((h, w, 3), dtype=np.uint8)
        fb_mask = (fb.sum(axis=2) > 0)
        canvas[fb_mask] = fb[fb_mask]
        fa_mask = (fa.sum(axis=2) > 0)
        canvas[fa_mask] = fa[fa_mask]
        crease_x = a_w
        crease_w = max(2, int(w * 0.015))
        if 0 <= crease_x < w:
            x0 = max(0, crease_x - crease_w // 2)
            x1 = min(w, crease_x + crease_w // 2)
            canvas[:, x0:x1] = (canvas[:, x0:x1].astype(np.float32) * 0.55).astype(np.uint8)
        return canvas

    # ── page_curl ─────────────────────────────────────────────────────────────
    # Right-side page curl peel. The curl shadow and highlight on the rolled
    # edge give the illusion of paper thickness. B is revealed underneath.
    elif name == "page_curl":
        curl_x = int(w * (1.0 - et * intensity))  # curl front moves left
        curl_x = max(0, min(w - 1, curl_x))

        # B is the base layer
        out = b.copy().astype(np.float32)

        # A is drawn up to curl_x with a perspective squeeze near the edge
        for col in range(curl_x):
            # squish factor: columns near curl edge get squeezed horizontally
            dist_to_curl = curl_x - col
            squeeze = min(1.0, dist_to_curl / max(1, int(w * 0.2 * intensity)))
            src_col = int(col / max(0.01, squeeze)) if squeeze > 0 else col
            src_col = min(w - 1, src_col)
            # slight darkening as page curves away from light
            brightness = 0.7 + 0.3 * squeeze
            out[:, col] = a[:, src_col].astype(np.float32) * brightness

        # curl edge: a thin bright highlight + dark shadow
        edge_w = max(2, int(w * 0.025))
        for off in range(edge_w):
            col = curl_x + off
            if col >= w:
                break
            rel = off / edge_w
            # shadow gradient on B side
            shadow = 1.0 - (1.0 - rel) * 0.5 * intensity
            out[:, col] = b[:, col].astype(np.float32) * shadow
        # specular highlight on the curl itself
        if curl_x > 0 and curl_x < w:
            hl_w = max(1, int(w * 0.008))
            x0 = max(0, curl_x - hl_w)
            out[:, x0:curl_x] = np.clip(
                out[:, x0:curl_x] * 1.0 + 60, 0, 255)

        return np.clip(out, 0, 255).astype(np.uint8)

    # ── kaleidoscope ──────────────────────────────────────────────────────────
    # Mirrors the frame into N triangular wedges, rotates, then cross-dissolves
    # to B. Creates the jewel-box rotating mirror effect seen in CapCut.
    elif name == "kaleidoscope":
        n_blades = max(4, int(8 * intensity))
        cx, cy   = w / 2.0, h / 2.0
        rotation = et * (360.0 / n_blades)  # one full blade sweep

        def make_kaleido(frame):
            f32 = frame.astype(np.float32)
            yg, xg = np.mgrid[0:h, 0:w].astype(np.float32)
            dx, dy = xg - cx, yg - cy
            angle  = (np.arctan2(dy, dx) * 180.0 / math.pi + rotation) % (360.0 / n_blades)
            angle  = np.where(angle > (180.0 / n_blades),
                              (360.0 / n_blades) - angle, angle)
            radius = np.sqrt(dx**2 + dy**2)
            angle_rad = angle * math.pi / 180.0
            src_x  = np.clip(cx + radius * np.cos(angle_rad), 0, w - 1).astype(np.float32)
            src_y  = np.clip(cy + radius * np.sin(angle_rad), 0, h - 1).astype(np.float32)
            return cv2.remap(frame, src_x, src_y, cv2.INTER_LINEAR,
                             borderMode=cv2.BORDER_REFLECT)

        ka = make_kaleido(a)
        kb = make_kaleido(b)
        return blend(ka, kb, et)

    # ── ripple_water ──────────────────────────────────────────────────────────
    # Concentric ripple waves emanate from a centre point, warping A then B.
    # The ripple amplitude peaks at the middle of the transition.
    elif name == "ripple_water":
        cx, cy  = w / 2.0, h / 2.0
        yg, xg  = np.mgrid[0:h, 0:w].astype(np.float32)
        dx, dy  = xg - cx, yg - cy
        radius  = np.sqrt(dx**2 + dy**2)
        max_r   = math.sqrt(cx**2 + cy**2)
        # wave parameters
        freq    = 6.0 * intensity
        speed   = t * 4.0 * math.pi
        amp     = sin_peak * max_r * 0.06 * intensity
        # ripple displacement along radius direction
        ripple  = amp * np.sin(freq * radius / max_r * math.pi - speed)
        safe_r  = np.where(radius > 0, radius, 1.0)
        disp_x  = (dx / safe_r) * ripple
        disp_y  = (dy / safe_r) * ripple
        map_x_a = np.clip(xg + disp_x, 0, w - 1).astype(np.float32)
        map_y_a = np.clip(yg + disp_y, 0, h - 1).astype(np.float32)
        map_x_b = np.clip(xg - disp_x, 0, w - 1).astype(np.float32)
        map_y_b = np.clip(yg - disp_y, 0, h - 1).astype(np.float32)
        wa = cv2.remap(a, map_x_a, map_y_a, cv2.INTER_LINEAR,
                       borderMode=cv2.BORDER_REFLECT_101)
        wb = cv2.remap(b, map_x_b, map_y_b, cv2.INTER_LINEAR,
                       borderMode=cv2.BORDER_REFLECT_101)
        return blend(wa, wb, et)

    # ── dream_blur ────────────────────────────────────────────────────────────
    # Dreamy romantic dissolve: A blooms with additive glow + hue rotation,
    # B fades in through the glow. Inspired by CapCut's "Dream" and "Memory"
    # transition styles.
    elif name == "dream_blur":
        # Bloom: blur A heavily and add back additively
        sigma_a  = sin_peak * 28.0 * intensity
        glow_a   = gaussian_blur_cv(a, sigma_a).astype(np.float32)
        base_a   = a.astype(np.float32)
        bloomed_a = np.clip(base_a * 0.6 + glow_a * 0.7, 0, 255).astype(np.uint8)
        # hue rotate A for dreamy colour shift
        hue_deg  = sin_peak * 40.0 * intensity
        bloomed_a = _hue_rotate(bloomed_a, hue_deg)
        # B fades in through bloom
        sigma_b  = (1.0 - et) * 18.0 * intensity
        bloomed_b = gaussian_blur_cv(b, sigma_b)
        # white flash at peak (optional, subtle)
        white_flash = sin_peak * 0.18 * intensity
        out = blend(bloomed_a, bloomed_b, et).astype(np.float32)
        out = np.clip(out + white_flash * 255, 0, 255)
        return out.astype(np.uint8)

    # ── fallback ──────────────────────────────────────────────────────────────
    else:
        return blend(a, b, et)
