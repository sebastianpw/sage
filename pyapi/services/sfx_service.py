# services/sfx_service.py
"""
Anime SFX Service — CPU-only, no GPU required.
Provides: speed lines, glow, motion blur, shockwave, chromatic aberration,
          particles, camera shake, impact flash, color grade, debris scatter.

All effects operate on PIL Images or numpy arrays (via OpenCV).
Designed to run fast on CPU (Termux/Debian proot).
"""

import math
import random
import numpy as np
from io import BytesIO
from typing import Optional, List, Tuple
from pathlib import Path

import cv2
from PIL import Image, ImageFilter, ImageDraw, ImageEnhance
from fastapi import APIRouter, UploadFile, File, Form, HTTPException
from fastapi.responses import StreamingResponse

router = APIRouter(tags=["sfx"])


# ─────────────────────────────────────────────────────────────────────────────
# Helpers
# ─────────────────────────────────────────────────────────────────────────────

def pil_to_cv(img: Image.Image) -> np.ndarray:
    """PIL RGBA/RGB → BGR/BGRA numpy array (OpenCV convention)."""
    if img.mode == "RGBA":
        arr = np.array(img)
        return cv2.cvtColor(arr, cv2.COLOR_RGBA2BGRA)
    arr = np.array(img.convert("RGB"))
    return cv2.cvtColor(arr, cv2.COLOR_RGB2BGR)

def cv_to_pil(arr: np.ndarray) -> Image.Image:
    """BGR/BGRA numpy array → PIL Image."""
    if arr.shape[2] == 4:
        return Image.fromarray(cv2.cvtColor(arr, cv2.COLOR_BGRA2RGBA))
    return Image.fromarray(cv2.cvtColor(arr, cv2.COLOR_BGR2RGB))

def load_upload(upload: UploadFile) -> Image.Image:
    upload.file.seek(0)
    return Image.open(upload.file).convert("RGBA")

def stream_pil(img: Image.Image, fmt: str = "PNG") -> StreamingResponse:
    buf = BytesIO()
    img.save(buf, format=fmt)
    buf.seek(0)
    return StreamingResponse(buf, media_type=f"image/{fmt.lower()}")

def blend_screen(base: np.ndarray, top: np.ndarray, alpha: float = 1.0) -> np.ndarray:
    """
    Screen blend: result = 1 - (1-base)*(1-top)
    Expects float32 [0,1] arrays. Returns float32 [0,1].
    """
    b = base.astype(np.float32) / 255.0
    t = top.astype(np.float32) / 255.0 * alpha
    result = 1.0 - (1.0 - b) * (1.0 - t)
    return np.clip(result * 255, 0, 255).astype(np.uint8)

def blend_add(base: np.ndarray, top: np.ndarray, alpha: float = 1.0) -> np.ndarray:
    """Additive blend — brightens. Used for glows, energy effects."""
    t_scaled = (top.astype(np.float32) * alpha).astype(np.uint8)
    return cv2.add(base, t_scaled)


# ─────────────────────────────────────────────────────────────────────────────
# 1. SPEED LINES
# ─────────────────────────────────────────────────────────────────────────────

@router.post("/speed_lines")
async def speed_lines(
    file: UploadFile = File(...),
    cx: float = Form(0.5),           # Center X as fraction 0-1
    cy: float = Form(0.5),           # Center Y as fraction 0-1
    count: int = Form(80),           # Number of lines
    min_length: float = Form(0.2),   # Min line length as fraction of half-diagonal
    max_length: float = Form(0.95),  # Max line length
    thickness_min: int = Form(1),
    thickness_max: int = Form(3),
    color: str = Form("255,255,255"),  # R,G,B
    opacity: float = Form(0.7),        # 0-1
    blend_mode: str = Form("screen"),  # screen | add | normal
):
    """
    Radial speed lines from a center point.
    Creates the classic anime rush/focus effect.
    """
    img = load_upload(file)
    w, h = img.size
    half_diag = math.sqrt((w/2)**2 + (h/2)**2)

    # Parse color
    try:
        r, g, b = [int(x.strip()) for x in color.split(",")]
    except Exception:
        r, g, b = 255, 255, 255

    # Create lines layer (black background, white lines → screen blend)
    lines_layer = np.zeros((h, w, 3), dtype=np.uint8)

    pivot_x = int(cx * w)
    pivot_y = int(cy * h)

    for _ in range(count):
        angle = random.uniform(0, 2 * math.pi)
        start_dist = half_diag * random.uniform(min_length * 0.05, min_length * 0.15)
        end_dist   = half_diag * random.uniform(min_length, max_length)
        thickness  = random.randint(thickness_min, thickness_max)

        x1 = int(pivot_x + math.cos(angle) * start_dist)
        y1 = int(pivot_y + math.sin(angle) * start_dist)
        x2 = int(pivot_x + math.cos(angle) * end_dist)
        y2 = int(pivot_y + math.sin(angle) * end_dist)

        cv2.line(lines_layer, (x1, y1), (x2, y2), (b, g, r), thickness, cv2.LINE_AA)

    # Apply blend
    base = pil_to_cv(img)
    if img.mode == "RGBA":
        base_bgr = base[:, :, :3]
        alpha_ch  = base[:, :, 3:4]
    else:
        base_bgr = base
        alpha_ch  = None

    if blend_mode == "screen":
        blended = blend_screen(base_bgr, lines_layer, alpha=opacity)
    elif blend_mode == "add":
        blended = blend_add(base_bgr, lines_layer, alpha=opacity)
    else:
        # Normal blend with alpha
        mask = lines_layer.astype(np.float32) / 255.0 * opacity
        blended = np.clip(
            base_bgr.astype(np.float32) * (1 - mask) + lines_layer.astype(np.float32) * mask,
            0, 255
        ).astype(np.uint8)

    if alpha_ch is not None:
        result = np.concatenate([blended, alpha_ch], axis=2)
        out = cv_to_pil(result)
    else:
        out = cv_to_pil(blended)

    return stream_pil(out)


# ─────────────────────────────────────────────────────────────────────────────
# 2. GLOW / BLOOM
# ─────────────────────────────────────────────────────────────────────────────

@router.post("/glow")
async def glow_effect(
    file: UploadFile = File(...),
    intensity: float = Form(0.6),       # 0-1
    radius: int = Form(15),             # blur radius (pixels)
    threshold: int = Form(180),         # Only bright areas glow (0-255)
    color_tint: str = Form(""),         # Optional R,G,B tint for the glow
):
    """
    Bloom/glow effect: extracts bright areas, blurs them, adds back.
    Used for energy effects, explosions, magical auras.
    """
    img = load_upload(file)
    arr = pil_to_cv(img)

    if img.mode == "RGBA":
        bgr = arr[:, :, :3]
        alpha_ch = arr[:, :, 3:4]
    else:
        bgr = arr
        alpha_ch = None

    # 1. Extract bright areas
    gray = cv2.cvtColor(bgr, cv2.COLOR_BGR2GRAY)
    _, bright_mask = cv2.threshold(gray, threshold, 255, cv2.THRESH_BINARY)
    bright_layer = cv2.bitwise_and(bgr, bgr, mask=bright_mask)

    # 2. Apply optional color tint to glow
    if color_tint.strip():
        try:
            tr, tg, tb = [int(x.strip()) for x in color_tint.split(",")]
            tint = np.array([tb, tg, tr], dtype=np.float32) / 255.0
            bright_layer = np.clip(
                bright_layer.astype(np.float32) * tint * 2,
                0, 255
            ).astype(np.uint8)
        except Exception:
            pass  # Skip tint if parse fails

    # 3. Blur the bright layer
    blur_r = radius | 1  # Must be odd
    blurred = cv2.GaussianBlur(bright_layer, (blur_r * 2 + 1, blur_r * 2 + 1), 0)

    # 4. Add back to original (screen blend)
    result = blend_screen(bgr, blurred, alpha=intensity)

    if alpha_ch is not None:
        result_rgba = np.concatenate([result, alpha_ch], axis=2)
        out = cv_to_pil(result_rgba)
    else:
        out = cv_to_pil(result)

    return stream_pil(out)


# ─────────────────────────────────────────────────────────────────────────────
# 3. MOTION BLUR
# ─────────────────────────────────────────────────────────────────────────────

@router.post("/motion_blur")
async def motion_blur(
    file: UploadFile = File(...),
    angle: float = Form(0.0),      # Degrees: 0=horizontal, 90=vertical
    strength: int = Form(25),       # Kernel size (pixels)
    intensity: float = Form(1.0),  # 0-1 blend with original
):
    """
    Directional motion blur.
    angle=0: horizontal (right-moving subject)
    angle=90: vertical (falling)
    angle=45: diagonal
    """
    img = load_upload(file)
    arr = pil_to_cv(img)

    if img.mode == "RGBA":
        bgr = arr[:, :, :3]
        alpha_ch = arr[:, :, 3:4]
    else:
        bgr = arr
        alpha_ch = None

    # Build directional kernel
    size = max(3, strength | 1)  # Must be odd
    kernel = np.zeros((size, size))
    center = size // 2
    kernel[center, :] = 1.0 / size  # Horizontal kernel baseline

    # Rotate kernel to desired angle
    M = cv2.getRotationMatrix2D((center, center), angle, 1.0)
    kernel_rotated = cv2.warpAffine(kernel, M, (size, size))
    kernel_sum = kernel_rotated.sum()
    if kernel_sum > 0:
        kernel_rotated /= kernel_sum

    blurred = cv2.filter2D(bgr, -1, kernel_rotated)

    # Blend with original based on intensity
    if intensity < 1.0:
        result = cv2.addWeighted(bgr, 1.0 - intensity, blurred, intensity, 0)
    else:
        result = blurred

    if alpha_ch is not None:
        result_rgba = np.concatenate([result, alpha_ch], axis=2)
        out = cv_to_pil(result_rgba)
    else:
        out = cv_to_pil(result)

    return stream_pil(out)


# ─────────────────────────────────────────────────────────────────────────────
# 4. SHOCKWAVE RING
# ─────────────────────────────────────────────────────────────────────────────

@router.post("/shockwave")
async def shockwave(
    file: UploadFile = File(...),
    cx: float = Form(0.5),
    cy: float = Form(0.5),
    radius: float = Form(0.3),           # Ring radius as fraction of image width
    ring_width: int = Form(8),           # Ring thickness in pixels
    distort_strength: float = Form(12.0),# Pixels of radial warp at ring edge
    opacity: float = Form(0.85),
    color: str = Form("220,235,255"),    # Ring color R,G,B (pale blue-white)
):
    """
    Shockwave / pressure wave ring.
    Two effects combined:
    1. A translucent ring drawn on the image
    2. A radial distortion warp along the ring edge
    """
    img = load_upload(file)
    w, h = img.size
    arr = pil_to_cv(img)

    if img.mode == "RGBA":
        bgr = arr[:, :, :3].astype(np.float32)
        alpha_ch = arr[:, :, 3:4]
    else:
        bgr = arr.astype(np.float32)
        alpha_ch = None

    cx_px = int(cx * w)
    cy_px = int(cy * h)
    r_px  = int(radius * w)

    try:
        cr, cg, cb = [int(x.strip()) for x in color.split(",")]
    except Exception:
        cr, cg, cb = 220, 235, 255

    # ── 1. Radial distortion warp ──────────────────────────────────────────
    ys, xs = np.mgrid[0:h, 0:w].astype(np.float32)
    dx = xs - cx_px
    dy = ys - cy_px
    dist = np.sqrt(dx**2 + dy**2) + 1e-6

    # Gaussian falloff centered at the ring radius
    sigma = ring_width * 1.5
    warp_falloff = np.exp(-((dist - r_px)**2) / (2 * sigma**2))
    warp_amount  = warp_falloff * distort_strength

    # Normalize direction and apply warp
    map_x = xs + (dx / dist) * warp_amount
    map_y = ys + (dy / dist) * warp_amount

    map_x = map_x.astype(np.float32)
    map_y = map_y.astype(np.float32)

    bgr_u8 = np.clip(bgr, 0, 255).astype(np.uint8)
    warped = cv2.remap(bgr_u8, map_x, map_y, cv2.INTER_LINEAR, borderMode=cv2.BORDER_REPLICATE)

    # ── 2. Draw the ring ───────────────────────────────────────────────────
    ring_layer = np.zeros((h, w, 3), dtype=np.uint8)
    cv2.circle(ring_layer, (cx_px, cy_px), r_px, (cb, cg, cr), ring_width, cv2.LINE_AA)

    # Soft blur the ring for translucency
    ring_layer = cv2.GaussianBlur(ring_layer, (ring_width | 1, ring_width | 1), 0)

    # Blend ring onto warped image
    result = blend_screen(warped, ring_layer, alpha=opacity)

    if alpha_ch is not None:
        result_rgba = np.concatenate([result, alpha_ch], axis=2)
        out = cv_to_pil(result_rgba)
    else:
        out = cv_to_pil(result)

    return stream_pil(out)


# ─────────────────────────────────────────────────────────────────────────────
# 5. CHROMATIC ABERRATION
# ─────────────────────────────────────────────────────────────────────────────

@router.post("/chromatic_aberration")
async def chromatic_aberration(
    file: UploadFile = File(...),
    shift_x: int = Form(4),    # Pixels to shift red channel horizontally
    shift_y: int = Form(0),    # Pixels to shift red channel vertically
    blue_shift_x: int = Form(-4),  # Blue channel shift (opposite direction)
    blue_shift_y: int = Form(0),
    radial: bool = Form(False),    # If True, shift increases toward edges
    radial_strength: float = Form(0.02),
):
    """
    Chromatic aberration / color fringing.
    Used for: impact moments, lens effects, glitch effects,
              screen damage, camera shake aftermath.
    """
    img = load_upload(file)
    w, h = img.size
    arr = np.array(img)  # RGBA, uint8

    r = arr[:, :, 0].astype(np.float32)
    g = arr[:, :, 1].astype(np.float32)
    b = arr[:, :, 2].astype(np.float32)
    a = arr[:, :, 3]

    def shift_channel(channel, dx, dy):
        M = np.float32([[1, 0, dx], [0, 1, dy]])
        return cv2.warpAffine(channel, M, (w, h), borderMode=cv2.BORDER_REPLICATE)

    if radial:
        # Build per-pixel shift maps based on distance from center
        cx, cy = w / 2, h / 2
        ys, xs = np.mgrid[0:h, 0:w].astype(np.float32)
        norm_x = (xs - cx) / (w / 2)
        norm_y = (ys - cy) / (h / 2)
        mag = np.sqrt(norm_x**2 + norm_y**2)

        # Red shifts outward, blue shifts inward (classic lens CA)
        r_map_x = (xs + norm_x * mag * radial_strength * w).astype(np.float32)
        r_map_y = (ys + norm_y * mag * radial_strength * h).astype(np.float32)
        b_map_x = (xs - norm_x * mag * radial_strength * w * 0.6).astype(np.float32)
        b_map_y = (ys - norm_y * mag * radial_strength * h * 0.6).astype(np.float32)
        identity_map_x = xs
        identity_map_y = ys

        r = cv2.remap(r, r_map_x, r_map_y, cv2.INTER_LINEAR, borderMode=cv2.BORDER_REPLICATE)
        b = cv2.remap(b, b_map_x, b_map_y, cv2.INTER_LINEAR, borderMode=cv2.BORDER_REPLICATE)
        # g stays
    else:
        r = shift_channel(r, shift_x, shift_y)
        b = shift_channel(b, blue_shift_x, blue_shift_y)

    result = np.stack([
        np.clip(r, 0, 255).astype(np.uint8),
        np.clip(g, 0, 255).astype(np.uint8),
        np.clip(b, 0, 255).astype(np.uint8),
        a
    ], axis=2)

    out = Image.fromarray(result, "RGBA")
    return stream_pil(out)


# ─────────────────────────────────────────────────────────────────────────────
# 6. PARTICLE OVERLAY
# ─────────────────────────────────────────────────────────────────────────────

@router.post("/particles")
async def particles(
    file: UploadFile = File(...),
    count: int = Form(60),
    particle_type: str = Form("spark"),   # spark | circle | square | star
    color: str = Form("255,200,80"),      # R,G,B base color
    color_variance: int = Form(30),       # Random ± variance per channel
    size_min: int = Form(2),
    size_max: int = Form(8),
    cx: float = Form(0.5),               # Origin center X (fraction)
    cy: float = Form(0.5),               # Origin center Y (fraction)
    spread_x: float = Form(0.8),         # Spread radius X (fraction of width)
    spread_y: float = Form(0.8),         # Spread radius Y (fraction of height)
    glow: bool = Form(True),             # Apply glow to bright particles
    seed: int = Form(42),
):
    """
    Scatter particles over the image.
    Useful for: embers, sparkles, energy fragments, debris dust,
                magic particles, aftermath of explosion.
    """
    img = load_upload(file)
    w, h = img.size
    rng = random.Random(seed)

    try:
        base_r, base_g, base_b = [int(x.strip()) for x in color.split(",")]
    except Exception:
        base_r, base_g, base_b = 255, 200, 80

    # Draw on RGBA overlay
    overlay = Image.new("RGBA", (w, h), (0, 0, 0, 0))
    draw = ImageDraw.Draw(overlay)

    for _ in range(count):
        # Position: biased toward center with spread
        angle = rng.uniform(0, 2 * math.pi)
        dist_x = rng.gauss(0, spread_x * w * 0.3)
        dist_y = rng.gauss(0, spread_y * h * 0.3)

        px = int(cx * w + dist_x)
        py = int(cy * h + dist_y)

        size = rng.randint(size_min, size_max)
        alpha = rng.randint(140, 255)

        # Color variance
        pr = max(0, min(255, base_r + rng.randint(-color_variance, color_variance)))
        pg = max(0, min(255, base_g + rng.randint(-color_variance, color_variance)))
        pb = max(0, min(255, base_b + rng.randint(-color_variance, color_variance)))
        color_rgba = (pr, pg, pb, alpha)

        if particle_type == "circle":
            draw.ellipse([px - size, py - size, px + size, py + size], fill=color_rgba)

        elif particle_type == "square":
            draw.rectangle([px - size, py - size, px + size, py + size], fill=color_rgba)

        elif particle_type == "star":
            # Draw a simple 4-point star
            pts = []
            for i in range(8):
                a = math.pi / 4 * i
                r = size if i % 2 == 0 else size * 0.4
                pts.append((px + math.cos(a) * r, py + math.sin(a) * r))
            draw.polygon(pts, fill=color_rgba)

        else:  # spark — elongated line
            spark_len = size * rng.uniform(2, 5)
            dx = math.cos(angle) * spark_len
            dy = math.sin(angle) * spark_len
            draw.line([(px, py), (px + dx, py + dy)], fill=color_rgba, width=max(1, size // 2))

    # Composite
    result = Image.alpha_composite(img, overlay)

    # Optional glow pass
    if glow:
        pil_arr = np.array(result.convert("RGB"))
        bgr = cv2.cvtColor(pil_arr, cv2.COLOR_RGB2BGR)
        # Simple bloom on the particle layer
        particle_bgr = cv2.cvtColor(np.array(overlay.convert("RGB")), cv2.COLOR_RGB2BGR)
        blurred = cv2.GaussianBlur(particle_bgr, (9, 9), 0)
        glowed = blend_screen(bgr, blurred, alpha=0.5)
        rgb_out = cv2.cvtColor(glowed, cv2.COLOR_BGR2RGB)
        result_rgb = Image.fromarray(rgb_out)
        # Restore alpha
        result = result_rgb.convert("RGBA")
        r_arr = np.array(result)
        orig_alpha = np.array(img)[:, :, 3:4] if img.mode == "RGBA" else np.ones((h, w, 1), dtype=np.uint8) * 255
        r_arr[:, :, 3:4] = orig_alpha
        result = Image.fromarray(r_arr)

    return stream_pil(result)


# ─────────────────────────────────────────────────────────────────────────────
# 7. CAMERA SHAKE (single frame)
# ─────────────────────────────────────────────────────────────────────────────

@router.post("/camera_shake")
async def camera_shake(
    file: UploadFile = File(...),
    offset_x: int = Form(8),
    offset_y: int = Form(-5),
    rotation: float = Form(1.5),       # Degrees of rotation
    scale: float = Form(1.02),          # Slight zoom during shake
):
    """
    Single-frame camera shake transform.
    Call this endpoint with varying offset_x/y values to build a shake sequence.

    For a standard impact shake (7 frames), use offsets like:
    Frame 0: (0, 0, 0°, 1.0)      — pre-impact
    Frame 1: (12, -8, 2°, 1.03)   — impact peak
    Frame 2: (-9, 6, -1.5°, 1.02) — rebound
    Frame 3: (5, -4, 1°, 1.01)    — settling
    Frame 4: (-3, 2, -0.5°, 1.0)  — settling
    Frame 5: (1, -1, 0.2°, 1.0)   — nearly still
    Frame 6: (0, 0, 0°, 1.0)      — settled
    """
    img = load_upload(file)
    w, h = img.size
    arr = pil_to_cv(img)

    cx, cy = w / 2.0, h / 2.0

    # Build combined transform: rotate + translate + scale
    M = cv2.getRotationMatrix2D((cx, cy), rotation, scale)
    M[0, 2] += offset_x
    M[1, 2] += offset_y

    if img.mode == "RGBA":
        bgr = arr[:, :, :3]
        alpha_ch = arr[:, :, 3]
        warped_bgr = cv2.warpAffine(bgr, M, (w, h), borderMode=cv2.BORDER_REPLICATE)
        warped_alpha = cv2.warpAffine(alpha_ch, M, (w, h), borderMode=cv2.BORDER_CONSTANT, borderValue=0)
        warped = np.dstack([warped_bgr, warped_alpha])
        out = cv_to_pil(warped)
    else:
        warped = cv2.warpAffine(arr, M, (w, h), borderMode=cv2.BORDER_REPLICATE)
        out = cv_to_pil(warped)

    return stream_pil(out)


# ─────────────────────────────────────────────────────────────────────────────
# 8. IMPACT FLASH
# ─────────────────────────────────────────────────────────────────────────────

@router.post("/impact_flash")
async def impact_flash(
    file: UploadFile = File(...),
    intensity: float = Form(0.6),          # 0-1: how much white overlay
    radial: bool = Form(True),              # If True, flash brighter at center
    cx: float = Form(0.5),
    cy: float = Form(0.5),
    radius: float = Form(0.6),             # Falloff radius as fraction of width
    color: str = Form("255,255,255"),       # Flash color (white default, yellow for explosion)
):
    """
    Impact/explosion flash effect.
    A white (or colored) screen flash that fades radially from center.
    Used for: the 1-2 frame peak of any impact.
    """
    img = load_upload(file)
    w, h = img.size

    try:
        fr, fg, fb = [int(x.strip()) for x in color.split(",")]
    except Exception:
        fr, fg, fb = 255, 255, 255

    arr = np.array(img).astype(np.float32)
    original_alpha = arr[:, :, 3:4] if img.mode == "RGBA" else None

    if radial:
        # Build radial gradient: bright at center, fades to edge
        cx_px = cx * w
        cy_px = cy * h
        ys, xs = np.mgrid[0:h, 0:w].astype(np.float32)
        dist = np.sqrt((xs - cx_px)**2 + (ys - cy_px)**2)
        max_dist = radius * w
        falloff = np.clip(1.0 - dist / max_dist, 0, 1) ** 0.7  # Softer falloff
        flash_alpha = (falloff * intensity * 255).astype(np.uint8)
    else:
        # Uniform flash
        flash_alpha = np.full((h, w), int(intensity * 255), dtype=np.uint8)

    # Create flash layer
    flash_rgba = np.dstack([
        np.full((h, w), fr, dtype=np.uint8),
        np.full((h, w), fg, dtype=np.uint8),
        np.full((h, w), fb, dtype=np.uint8),
        flash_alpha
    ])

    flash_img = Image.fromarray(flash_rgba, "RGBA")
    result = Image.alpha_composite(img.convert("RGBA"), flash_img)

    return stream_pil(result)


# ─────────────────────────────────────────────────────────────────────────────
# 9. COLOR GRADE (cinematic)
# ─────────────────────────────────────────────────────────────────────────────

@router.post("/color_grade")
async def color_grade(
    file: UploadFile = File(...),
    preset: str = Form("impact"),     # impact | aftermath | power_up | cold | warm | noir
    strength: float = Form(1.0),      # 0-1: blend between original and graded
):
    """
    Cinematic color grades tuned for anime SFX moments.

    Presets:
    - impact:    High contrast, warm highlights, slightly desaturated (punch/explosion feel)
    - aftermath: Desaturated, cooler, slight blue, dust haze
    - power_up:  High saturation, warm/gold highlights, slight brightness boost
    - cold:      Blue tint, reduced saturation (freezing/void/death)
    - warm:      Orange/gold tint (fire, sunset, heroic)
    - noir:      Near-monochrome, high contrast, dark shadows
    """
    img = load_upload(file).convert("RGB")
    arr = np.array(img).astype(np.float32) / 255.0

    if preset == "impact":
        # Warm highlights + crushed blacks
        arr = np.power(arr, 0.9)  # Slightly lighten midtones
        arr[:, :, 0] = np.clip(arr[:, :, 0] * 1.1, 0, 1)  # Boost red
        arr[:, :, 2] = np.clip(arr[:, :, 2] * 0.88, 0, 1)  # Reduce blue
        # Crush shadows
        arr = np.clip((arr - 0.05) * 1.1, 0, 1)

    elif preset == "aftermath":
        # Cooler, dusty, slightly desaturated
        gray = np.mean(arr, axis=2, keepdims=True)
        arr = arr * 0.6 + gray * 0.4  # Partial desaturation
        arr[:, :, 2] = np.clip(arr[:, :, 2] * 1.08, 0, 1)  # Slight blue boost
        arr = np.clip(arr * 0.9 + 0.05, 0, 1)  # Slight haze (lifted blacks)

    elif preset == "power_up":
        # Vivid, warm, energetic
        gray = np.mean(arr, axis=2, keepdims=True)
        arr = arr * 1.3 - gray * 0.3  # Boost saturation
        arr[:, :, 0] = np.clip(arr[:, :, 0] * 1.15, 0, 1)  # Warm reds
        arr[:, :, 1] = np.clip(arr[:, :, 1] * 1.05, 0, 1)  # Slight green
        arr = np.clip(arr, 0, 1)

    elif preset == "cold":
        # Blue-shifted, desaturated
        gray = np.mean(arr, axis=2, keepdims=True)
        arr = arr * 0.4 + gray * 0.6
        arr[:, :, 2] = np.clip(arr[:, :, 2] * 1.2 + 0.05, 0, 1)  # Strong blue
        arr[:, :, 0] = np.clip(arr[:, :, 0] * 0.85, 0, 1)  # Reduce red

    elif preset == "warm":
        arr[:, :, 0] = np.clip(arr[:, :, 0] * 1.15 + 0.04, 0, 1)
        arr[:, :, 1] = np.clip(arr[:, :, 1] * 1.05, 0, 1)
        arr[:, :, 2] = np.clip(arr[:, :, 2] * 0.80, 0, 1)

    elif preset == "noir":
        gray = np.mean(arr, axis=2, keepdims=True)
        arr = arr * 0.1 + gray * 0.9  # Near-monochrome
        arr = np.power(arr, 1.3)  # Higher contrast (crush shadows)

    # Blend with original
    if strength < 1.0:
        original = np.array(img).astype(np.float32) / 255.0
        arr = arr * strength + original * (1.0 - strength)

    result = Image.fromarray((np.clip(arr, 0, 1) * 255).astype(np.uint8), "RGB")
    return stream_pil(result, "PNG")


# ─────────────────────────────────────────────────────────────────────────────
# 10. VIGNETTE
# ─────────────────────────────────────────────────────────────────────────────

@router.post("/vignette")
async def vignette(
    file: UploadFile = File(...),
    strength: float = Form(0.7),       # 0-1
    radius: float = Form(0.7),         # Inner radius as fraction of image diagonal
    color: str = Form("0,0,0"),        # Vignette color (black default)
    feather: float = Form(0.3),        # Softness of falloff
):
    """
    Vignette — darkens edges to focus attention on center.
    Also useful for: cinematic letterbox mood, focus on character.
    """
    img = load_upload(file)
    w, h = img.size

    try:
        vr, vg, vb = [int(x.strip()) for x in color.split(",")]
    except Exception:
        vr, vg, vb = 0, 0, 0

    cx, cy = w / 2.0, h / 2.0
    ys, xs = np.mgrid[0:h, 0:w].astype(np.float32)

    # Normalized distance from center (0 = center, 1 = corner)
    dist = np.sqrt(((xs - cx) / (w / 2))**2 + ((ys - cy) / (h / 2))**2)

    # Build alpha: 0 inside radius, increases outward
    inner = radius - feather / 2
    outer = radius + feather / 2
    vignette_alpha = np.clip((dist - inner) / (outer - inner), 0, 1)
    vignette_alpha = vignette_alpha ** 1.5  # Subtle curve for natural falloff
    vignette_alpha = (vignette_alpha * strength * 255).astype(np.uint8)

    vignette_layer = np.dstack([
        np.full((h, w), vr, dtype=np.uint8),
        np.full((h, w), vg, dtype=np.uint8),
        np.full((h, w), vb, dtype=np.uint8),
        vignette_alpha
    ])

    vig_img = Image.fromarray(vignette_layer, "RGBA")
    result = Image.alpha_composite(img.convert("RGBA"), vig_img)

    return stream_pil(result)


# ─────────────────────────────────────────────────────────────────────────────
# HEALTH
# ─────────────────────────────────────────────────────────────────────────────

@router.get("/_health")
async def health():
    return {"status": "ok", "service": "sfx_service", "gpu": False, "backend": "opencv+pillow"}
