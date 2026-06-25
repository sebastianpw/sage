"""
pyapi/services/bang_service.py

BANG! — Comic Panel Composer Render Service
Receives a full scene JSON (elements: images, balloons, sfx, captions, panel borders)
and composites them into a single PNG strip using Pillow.

Endpoint: POST /bang/render
Returns: JSON { status, temp_path, width, height }
"""

import json
import math
import logging
import uuid
import colorsys
from pathlib import Path
from typing import List, Optional, Dict, Any

from fastapi import APIRouter, Body, HTTPException
from fastapi.responses import JSONResponse
from pydantic import BaseModel

from PIL import (
    Image, ImageDraw, ImageFont, ImageOps,
    ImageFilter, ImageChops
)

logger = logging.getLogger(__name__)

router = APIRouter(tags=["bang"])

# ── Paths ─────────────────────────────────────────────────────────────────────
SERVICE_ROOT  = Path(__file__).resolve().parents[1]
PROJECT_ROOT  = SERVICE_ROOT.parent
TEMP_DIR      = PROJECT_ROOT / "services_data" / "bang" / "temp"
FONTS_DIR     = SERVICE_ROOT / "fonts"

TEMP_DIR.mkdir(parents=True, exist_ok=True)
FONTS_DIR.mkdir(parents=True, exist_ok=True)

FONT_MAP: Dict[str, str] = {
    "Bangers":          "Bangers-Regular.ttf",
    "Permanent Marker": "PermanentMarker-Regular.ttf",
    "Oswald":           "Oswald-Bold.ttf",
    "Cinzel":           "Cinzel-Regular.ttf",
    "Space Mono":       "SpaceMono-Regular.ttf",
    "Lora":             "Lora-Regular.ttf",
    "Impact":           "Impact.ttf",
    "Arial Black":      "Arial-Black.ttf",
}

# ── Pydantic models ───────────────────────────────────────────────────────────

class ImageElement(BaseModel):
    type: str = "image"
    uid: Optional[str] = None
    x: float = 0
    y: float = 0
    rotation: float = 0
    scaleX: float = 1.0
    scaleY: float = 1.0
    opacity: float = 1.0
    z_index: int = 0
    isLocked: bool = False
    filename: Optional[str] = None
    abs_path: Optional[str] = None
    frame_id: Optional[int] = None
    img_width: Optional[float] = None
    img_height: Optional[float] = None


class BalloonElement(BaseModel):
    type: str = "balloon"
    uid: Optional[str] = None
    x: float = 0
    y: float = 0
    rotation: float = 0
    scaleX: float = 1.0
    scaleY: float = 1.0
    opacity: float = 1.0
    z_index: int = 0
    isLocked: bool = False
    balloon_shape: str = "classic_oval"
    balloon_w: float = 240
    balloon_h: float = 100
    fill_color: str = "#F5F5F0"
    stroke_color: str = "#222222"
    stroke_width: float = 2
    tail_x: Optional[float] = None
    tail_y: Optional[float] = None
    balloon_padding: str = "12"
    text: str = ""
    font_family: str = "Bangers"
    font_size: float = 16
    text_color: str = "#111111"


class SFXElement(BaseModel):
    type: str = "sfx"
    uid: Optional[str] = None
    x: float = 0
    y: float = 0
    rotation: float = 0
    scaleX: float = 1.0
    scaleY: float = 1.0
    opacity: float = 1.0
    z_index: int = 0
    isLocked: bool = False
    text: str = "POW!"
    font_family: str = "Impact"
    font_size: float = 64
    text_color: str = "#FFFFFF"
    blur_px: float = 0.0


class CaptionElement(BaseModel):
    type: str = "caption"
    uid: Optional[str] = None
    x: float = 0
    y: float = 0
    rotation: float = 0
    scaleX: float = 1.0
    scaleY: float = 1.0
    opacity: float = 1.0
    z_index: int = 0
    isLocked: bool = False
    text: str = ""
    font_family: str = "Lora"
    font_size: float = 15
    text_color: str = "#D4A017"
    fill_color: str = "#1A0A00"
    width: Optional[float] = None
    caption_padding: str = "12 14"
    reflowText: bool = False


class PanelElement(BaseModel):
    type: str = "panel"
    uid: Optional[str] = None
    x: float = 0
    y: float = 0
    rotation: float = 0
    scaleX: float = 1.0
    scaleY: float = 1.0
    opacity: float = 1.0
    z_index: int = 0
    isLocked: bool = False
    panel_type: str = "rectangular"
    width: float = 760
    height: float = 400
    angle_left_deg: float = 0.0
    angle_right_deg: float = 0.0
    stroke_color: str = "#888888"
    stroke_width: float = 2.0
    border_style: str = "solid"
    fill_color: str = "transparent"
    innerImageFilename: Optional[str] = None
    innerImageAbsPath: Optional[str] = None
    innerImageFrameId: Optional[int] = None
    innerImageX: float = 0
    innerImageY: float = 0
    innerImageScale: float = 1.0


class SpeedLinesElement(BaseModel):
    type: str = "speed_lines"
    uid: Optional[str] = None
    x: float = 0
    y: float = 0
    rotation: float = 0
    scaleX: float = 1.0
    scaleY: float = 1.0
    opacity: float = 0.85
    z_index: int = 0
    isLocked: bool = False
    mode: str = "radial"
    line_count: int = 48
    inner_radius: float = 60
    outer_radius: float = 380
    angle_start_deg: float = 0
    angle_span_deg: float = 360
    line_color: str = "#000000"
    line_width: float = 1.5
    taper: bool = True


class ImpactFrameElement(BaseModel):
    type: str = "impact_frame"
    uid: Optional[str] = None
    x: float = 0
    y: float = 0
    width: float = 760
    height: float = 400
    rotation: float = 0
    scaleX: float = 1.0
    scaleY: float = 1.0
    opacity: float = 1.0
    z_index: int = 0
    isLocked: bool = False
    impact_style: str = "starburst"
    color_primary: str = "#FFFFFF"
    color_secondary: str = "#FFE066"
    spike_count: int = 20


class BangRenderRequest(BaseModel):
    canvas_width:  int = 800
    canvas_height: int = 3200
    bg_color:      str = "#000000"
    elements:      List[Dict[str, Any]] = []


# ── Endpoint ──────────────────────────────────────────────────────────────────

@router.post("/render")
async def bang_render(req: BangRenderRequest):
    try:
        canvas = Image.new("RGBA", (req.canvas_width, req.canvas_height),
                           _parse_color(req.bg_color))

        sorted_elements = sorted(req.elements, key=lambda e: int(e.get("z_index", 0)))

        for raw in sorted_elements:
            el_type = raw.get("type", "image")
            try:
                if el_type == "image":
                    _render_image(canvas, ImageElement(**raw), req.canvas_width, req.canvas_height)
                elif el_type in ("balloon", "shout", "thought", "whisper", "classic_oval", "modern_box", "shout_burst", "fierce_scream", "thought_cloud", "whisper_dash", "scifi_hex", "creepy_wobbly"):
                    raw.setdefault("type", "balloon")
                    _render_balloon(canvas, BalloonElement(**raw))
                elif el_type == "sfx":
                    _render_sfx(canvas, SFXElement(**raw))
                elif el_type == "caption":
                    _render_caption(canvas, CaptionElement(**raw), req.canvas_width)
                elif el_type == "panel":
                    _render_panel(canvas, PanelElement(**raw))
                elif el_type == "speed_lines":
                    _render_speed_lines(canvas, SpeedLinesElement(**raw))
                elif el_type == "impact_frame":
                    _render_impact_frame(canvas, ImpactFrameElement(**raw))
                else:
                    logger.warning(f"BANG! unknown element type: {el_type!r} — skipped")
            except Exception as elem_err:
                logger.error(f"BANG! element render error ({el_type}): {elem_err}", exc_info=True)

        out_name = f"bang_{uuid.uuid4().hex[:12]}.png"
        out_path = TEMP_DIR / out_name
        canvas.save(str(out_path), "PNG", optimize=False)

        return JSONResponse({
            "status":    "success",
            "temp_path": str(out_path),
            "width":     req.canvas_width,
            "height":    req.canvas_height,
        })

    except Exception as e:
        logger.exception("BANG! render failed")
        raise HTTPException(status_code=500, detail=f"BANG! render failed: {e}")


# ── Transform & Pivot Engine ──────────────────────────────────────────────────

def paste_rotated(canvas: Image.Image, img: Image.Image, x: float, y: float, pivot_x: float, pivot_y: float, rotation: float):
    if abs(rotation) < 0.01:
        _alpha_composite_safe(canvas, img, int(x - pivot_x), int(y - pivot_y))
        return
        
    rotated = img.rotate(-rotation, expand=True, resample=Image.Resampling.BICUBIC)
    
    cx = img.width / 2.0
    cy = img.height / 2.0
    dx = cx - pivot_x
    dy = cy - pivot_y
    
    rad = math.radians(rotation)
    cos_a = math.cos(rad)
    sin_a = math.sin(rad)
    
    rot_dx = dx * cos_a - dy * sin_a
    rot_dy = dx * sin_a + dy * cos_a
    
    final_cx = x + rot_dx
    final_cy = y + rot_dy
    
    paste_x = int(final_cx - rotated.width / 2.0)
    paste_y = int(final_cy - rotated.height / 2.0)
    
    _alpha_composite_safe(canvas, rotated, paste_x, paste_y)


# ── Element renderers ─────────────────────────────────────────────────────────

def _render_image(canvas: Image.Image, el: ImageElement, cw: int, ch: int):
    path_str = el.abs_path or (el.filename and _resolve_path(el.filename))
    if not path_str: return
    path = Path(path_str)
    if not path.exists(): return

    img = Image.open(path).convert("RGBA")
    img = ImageOps.exif_transpose(img)

    target_w = max(1, int(img.width * el.scaleX))
    target_h = max(1, int(img.height * el.scaleY))
    if target_w != img.width or target_h != img.height:
        img = img.resize((target_w, target_h), Image.Resampling.LANCZOS)

    if el.opacity < 1.0: img = _apply_opacity(img, el.opacity)

    paste_rotated(canvas, img, el.x, el.y, 0, 0, el.rotation)


def _render_balloon(canvas: Image.Image, el: BalloonElement):
    AA = 4 
    
    base_w = max(10, int(el.balloon_w))
    base_h = max(10, int(el.balloon_h))
    shape = el.balloon_shape or "classic_oval"

    base_sw = max(1, int(el.stroke_width))
    base_tail_h = 40 if shape in ("classic_oval", "oval", "whisper_dash", "whisper", "modern_box", "scifi_hex", "creepy_wobbly") else 0
    base_margin = base_sw + 4
    
    orig_img_w = base_w + base_margin * 2
    orig_img_h = base_h + base_margin * 2 + base_tail_h

    w, h = base_w * AA, base_h * AA
    sw = base_sw * AA
    ox, oy = base_margin * AA, base_margin * AA
    
    tail_x = (el.tail_x * AA) if el.tail_x is not None else w / 2
    tail_y = (el.tail_y * AA) if el.tail_y is not None else h + (30 * AA)

    balloon_img = Image.new("RGBA", (orig_img_w * AA, orig_img_h * AA), (0, 0, 0, 0))
    draw = ImageDraw.Draw(balloon_img)

    fill = _parse_color(el.fill_color)
    stroke = _parse_color(el.stroke_color)

    if shape in ("shout", "shout_burst"):
        _draw_starburst(draw, ox, oy, w, h, fill, stroke, sw, AA)
    elif shape == "fierce_scream":
        _draw_fierce_scream(draw, ox, oy, w, h, fill, stroke, sw, AA)
    elif shape in ("thought", "thought_cloud"):
        _draw_thought(draw, ox, oy, w, h, fill, stroke, sw, AA)
    elif shape in ("whisper", "whisper_dash"):
        _draw_whisper(draw, ox, oy, w, h, fill, stroke, sw, tail_x, tail_y, AA)
    elif shape == "modern_box":
        _draw_box_balloon(draw, ox, oy, w, h, fill, stroke, sw, tail_x, tail_y, AA)
    elif shape == "scifi_hex":
        _draw_scifi_hex(draw, ox, oy, w, h, fill, stroke, sw, tail_x, tail_y, AA)
    elif shape == "creepy_wobbly":
        _draw_wobbly(draw, ox, oy, w, h, fill, stroke, sw, tail_x, tail_y, AA)
    else:
        _draw_oval_balloon(draw, ox, oy, w, h, fill, stroke, sw, tail_x, tail_y, AA)

    if el.text:
        base_font_size = max(6, int(el.font_size))
        font_base = _load_font(el.font_family, base_font_size)
        font_aa = _load_font(el.font_family, base_font_size * AA)
        
        pad = _parse_padding(el.balloon_padding)
        text_w_base = max(4, base_w - pad["left"] - pad["right"])
        wrapped = _wrap_text(el.text, font_base, text_w_base)
        
        dummy = Image.new("RGBA", (1, 1))
        dd = ImageDraw.Draw(dummy)
        bbox = dd.textbbox((0, 0), "Ay", font=font_base)
        line_h_base = int((bbox[3] - bbox[1]) * 1.3)
        line_h_aa = line_h_base * AA
        
        total_h_base = len(wrapped) * line_h_base
        base_text_h = max(4, base_h - pad["top"] - pad["bottom"])
        start_y_base = pad["top"] + max(0, (base_text_h - total_h_base) / 2.0)
        
        y_cursor_aa = int((base_margin + start_y_base) * AA)
        text_color = _parse_color(el.text_color)
        
        for line in wrapped:
            lbbox = dd.textbbox((0, 0), line, font=font_base)
            line_w_base = lbbox[2] - lbbox[0]
            lx_base = pad["left"] + max(0, (text_w_base - line_w_base) / 2.0)
            lx_aa = int((base_margin + lx_base) * AA)
            draw.text((lx_aa, y_cursor_aa), line, font=font_aa, fill=text_color)
            y_cursor_aa += line_h_aa

    target_w = max(1, int(orig_img_w * el.scaleX))
    target_h = max(1, int(orig_img_h * el.scaleY))
    
    balloon_img = balloon_img.resize((target_w, target_h), Image.Resampling.LANCZOS)

    if el.opacity < 1.0: balloon_img = _apply_opacity(balloon_img, el.opacity)

    pivot_x = base_margin * el.scaleX
    pivot_y = base_margin * el.scaleY
    paste_rotated(canvas, balloon_img, el.x, el.y, pivot_x, pivot_y, el.rotation)
    

def _render_sfx(canvas: Image.Image, el: SFXElement):
    AA = 4
    base_font_size = max(6, int(el.font_size))
    base_pad = 20
    
    font_base = _load_font(el.font_family, base_font_size)
    dummy = Image.new("RGBA", (1, 1))
    dd = ImageDraw.Draw(dummy)
    bbox = dd.textbbox((0, 0), el.text, font=font_base)
    base_tw = bbox[2] - bbox[0] + base_pad * 2
    base_th = bbox[3] - bbox[1] + base_pad * 2
    
    font_aa = _load_font(el.font_family, base_font_size * AA)
    sfx_img = Image.new("RGBA", (base_tw * AA, base_th * AA), (0,0,0,0))
    draw = ImageDraw.Draw(sfx_img)
    
    draw.text((base_pad * AA, base_pad * AA), el.text, font=font_aa, fill=_parse_color(el.text_color))

    blur_r = el.blur_px * AA * 0.4
    if blur_r > 0:
        sfx_img = sfx_img.filter(ImageFilter.GaussianBlur(blur_r))

    target_w = max(1, int(base_tw * el.scaleX))
    target_h = max(1, int(base_th * el.scaleY))
    sfx_img = sfx_img.resize((target_w, target_h), Image.Resampling.LANCZOS)

    if el.opacity < 1.0: sfx_img = _apply_opacity(sfx_img, el.opacity)

    pivot_x = base_pad * el.scaleX
    pivot_y = base_pad * el.scaleY
    paste_rotated(canvas, sfx_img, el.x, el.y, pivot_x, pivot_y, el.rotation)


def _render_caption(canvas: Image.Image, el: CaptionElement, canvas_width: int):
    AA = 4
    base_cap_w = int(el.width) if el.width else canvas_width
    base_font_size = max(6, int(el.font_size))
    pad = _parse_padding(el.caption_padding)

    font_base = _load_font(el.font_family, base_font_size)
    text_w_base = base_cap_w - pad["left"] - pad["right"]
    wrapped = _wrap_text(el.text, font_base, text_w_base)
    
    dummy = Image.new("RGBA", (1, 1))
    dd = ImageDraw.Draw(dummy)
    bbox = dd.textbbox((0, 0), "Ay", font=font_base)
    line_h_base = int((bbox[3] - bbox[1]) * 1.3)
    
    base_cap_h = max(42, int(len(wrapped) * line_h_base + pad["top"] + pad["bottom"]))

    cap_img = Image.new("RGBA", (base_cap_w * AA, base_cap_h * AA), _parse_color(el.fill_color))
    draw = ImageDraw.Draw(cap_img)
    
    font_aa = _load_font(el.font_family, base_font_size * AA)
    line_h_aa = line_h_base * AA
    y_cursor = pad["top"] * AA
    color = _parse_color(el.text_color)
    
    for line in wrapped:
        draw.text((pad["left"] * AA, y_cursor), line, font=font_aa, fill=color)
        y_cursor += line_h_aa

    target_w = max(1, int(base_cap_w * el.scaleX))
    target_h = max(1, int(base_cap_h * el.scaleY))
    cap_img = cap_img.resize((target_w, target_h), Image.Resampling.LANCZOS)

    if el.opacity < 1.0: cap_img = _apply_opacity(cap_img, el.opacity)

    paste_rotated(canvas, cap_img, el.x, el.y, 0, 0, el.rotation)


def _draw_border_style_path(
    draw: "ImageDraw.ImageDraw",
    corners: list,          
    style: str,
    stroke: tuple,
    stroke_width: int,
    close: bool = True,
) -> None:
    if style == "borderless":
        return

    AA = 4.0
    pts = list(corners)
    if close and pts[0] != pts[-1]:
        pts = pts + [pts[0]]

    if style == "solid":
        draw.line(pts, fill=stroke, width=stroke_width)
        return

    if style == "dashed":
        dash_len = 14 * AA
        gap_len  = 7 * AA
        _draw_segmented_path(draw, pts, stroke, stroke_width,
                             lambda d: d % (dash_len + gap_len) < dash_len)
        return

    for i in range(len(pts) - 1):
        p1, p2 = pts[i], pts[i + 1]
        dx = p2[0] - p1[0]
        dy = p2[1] - p1[1]
        seg_len = math.sqrt(dx * dx + dy * dy)
        if seg_len < 1:
            continue
        ux, uy = dx / seg_len, dy / seg_len   
        nx, ny = -uy, ux                        
        steps  = max(2, int(seg_len / 3))      

        seg_pts = []
        for s in range(steps + 1):
            t  = s / steps
            ox, oy = 0.0, 0.0
            td = t * seg_len                   
            unscaled_td = td / AA

            if style == "wavy":
                amp  = stroke_width * 2.0
                wave = math.sin(unscaled_td * 0.08 * math.pi * 2) * amp
                ox, oy = nx * wave, ny * wave

            elif style == "jagged":
                interval = 20
                phase    = int(unscaled_td / interval)
                spike    = stroke_width * 3.0
                local_t  = (unscaled_td % interval) / interval
                direction = 1 if phase % 2 == 0 else -1
                peak      = spike if local_t < 0.5 else -spike
                ox, oy   = nx * peak * direction, ny * peak * direction

            elif style == "organic":
                noise = (math.sin(unscaled_td * 0.15 + i * 1.7) * stroke_width * 1.8
                       + math.sin(unscaled_td * 0.31 + i * 3.1) * stroke_width * 0.9)
                ox, oy = nx * noise, ny * noise

            elif style == "chain":
                link_len = 16
                local_t  = (unscaled_td % link_len) / link_len
                bump     = abs(math.sin(local_t * math.pi)) * stroke_width * 1.5
                ox, oy   = nx * bump, ny * bump

            elif style == "water_ripple":
                amp  = stroke_width * 1.5
                wave = math.sin(unscaled_td * 0.06 * math.pi * 2 + math.pi * 0.25) * amp
                ox, oy = nx * wave, ny * wave

            px = p1[0] + ux * td + ox
            py = p1[1] + uy * td + oy
            seg_pts.append((px, py))

        if len(seg_pts) >= 2:
            draw.line(seg_pts, fill=stroke, width=stroke_width)


def _draw_segmented_path(draw, pts, stroke, width, predicate):
    cumulative = 0.0
    for i in range(len(pts) - 1):
        p1, p2 = pts[i], pts[i + 1]
        dx, dy = p2[0] - p1[0], p2[1] - p1[1]
        seg_len = math.sqrt(dx*dx + dy*dy)
        if seg_len < 1:
            continue
        steps = max(2, int(seg_len / 3))
        run   = []
        for s in range(steps + 1):
            t  = s / steps
            d  = cumulative + t * seg_len
            px = p1[0] + dx * t
            py = p1[1] + dy * t
            if predicate(d):
                run.append((px, py))
            else:
                if len(run) >= 2:
                    draw.line(run, fill=stroke, width=width)
                run = []
        if len(run) >= 2:
            draw.line(run, fill=stroke, width=width)
        cumulative += seg_len


def _render_panel(canvas: Image.Image, el: PanelElement):
    AA = 4
    base_w = max(1, int(el.width))
    base_h = max(1, int(el.height))
    base_sw = max(1, int(el.stroke_width))
    
    if el.panel_type == 'diagonal':
        off_l = int(math.tan(math.radians(el.angle_left_deg)) * base_h)
        off_r = int(math.tan(math.radians(el.angle_right_deg)) * base_h)
        local_corners = [
            (off_l, 0),
            (base_w + off_r, 0),
            (base_w, base_h),
            (0, base_h),
        ]
    else:
        local_corners = [
            (0, 0),
            (base_w, 0),
            (base_w, base_h),
            (0, base_h),
        ]
        
    all_x = [c[0] for c in local_corners]
    all_y = [c[1] for c in local_corners]
    min_cx, max_cx = min(all_x), max(all_x)
    min_cy, max_cy = min(all_y), max(all_y)

    margin = base_sw + 2
    bbox_w = (max_cx - min_cx) + margin * 2
    bbox_h = (max_cy - min_cy) + margin * 2

    shape_img = Image.new("RGBA", (bbox_w * AA, bbox_h * AA), (0, 0, 0, 0))
    draw = ImageDraw.Draw(shape_img)

    tx = margin - min_cx
    ty = margin - min_cy
    aa_corners = tuple((int((c[0] + tx) * AA), int((c[1] + ty) * AA)) for c in local_corners)

    fill = (0, 0, 0, 0) if el.fill_color in ("transparent", "", "none") else _parse_color(el.fill_color)
    stroke = _parse_color(el.stroke_color)
    sw = max(1, base_sw * AA)

    draw.polygon(aa_corners, fill=fill)

    inner_path = el.innerImageAbsPath or (el.innerImageFilename and _resolve_path(el.innerImageFilename))

    if inner_path and Path(inner_path).exists():
        try:
            inner_src = Image.open(inner_path).convert("RGBA")
            inner_src = ImageOps.exif_transpose(inner_src)

            scale = el.innerImageScale
            t_w = max(1, int(inner_src.width * scale * AA))
            t_h = max(1, int(inner_src.height * scale * AA))
            inner_r = inner_src.resize((t_w, t_h), Image.Resampling.LANCZOS)

            mask = Image.new("L", shape_img.size, 0)
            ImageDraw.Draw(mask).polygon(aa_corners, fill=255)

            inner_layer = Image.new("RGBA", shape_img.size, (0, 0, 0, 0))
            ix = int((tx + el.innerImageX) * AA)
            iy = int((ty + el.innerImageY) * AA)
            inner_layer.paste(inner_r, (ix, iy))

            r_ch, g_ch, b_ch, a_ch = inner_layer.split()
            a_ch = ImageChops.multiply(a_ch, mask)
            inner_layer = Image.merge("RGBA", (r_ch, g_ch, b_ch, a_ch))

            shape_img = Image.alpha_composite(shape_img, inner_layer)
            draw = ImageDraw.Draw(shape_img)
        except Exception as e:
            logger.warning(f"BANG! panel inner image failed: {e}")

    border_style = getattr(el, 'border_style', 'solid') or 'solid'
    _draw_border_style_path(draw, list(aa_corners), border_style, stroke, sw, close=True)

    target_w = max(1, int(bbox_w * el.scaleX))
    target_h = max(1, int(bbox_h * el.scaleY))
    shape_img = shape_img.resize((target_w, target_h), Image.Resampling.LANCZOS)

    if el.opacity < 1.0:
        shape_img = _apply_opacity(shape_img, el.opacity)

    paste_x = int(el.x + (min_cx - margin) * el.scaleX)
    paste_y = int(el.y + (min_cy - margin) * el.scaleY)
    paste_rotated(canvas, shape_img, paste_x, paste_y, 0, 0, el.rotation)


def _render_speed_lines(canvas: Image.Image, el: SpeedLinesElement) -> None:
    cw, ch = canvas.size
    layer  = Image.new("RGBA", (cw, ch), (0, 0, 0, 0))
    draw   = ImageDraw.Draw(layer)
    color  = _parse_color(el.line_color)
    lw     = max(1, int(el.line_width))
    lw_fat = max(1, int(el.line_width * 1.8))  

    cx = int(el.x)
    cy = int(el.y)

    if el.mode in ("radial", "sector"):
        span   = el.angle_span_deg if el.mode == "sector" else 360.0
        start  = el.angle_start_deg
        for i in range(el.line_count):
            angle_deg = start + (span / el.line_count) * i
            rad       = math.radians(angle_deg)
            x1 = cx + el.inner_radius * math.cos(rad)
            y1 = cy + el.inner_radius * math.sin(rad)
            x2 = cx + el.outer_radius * math.cos(rad)
            y2 = cy + el.outer_radius * math.sin(rad)
            if el.taper:
                mid_x = (x1 + x2) / 2
                mid_y = (y1 + y2) / 2
                draw.line([(x1, y1), (mid_x, mid_y)], fill=color, width=lw)
                draw.line([(mid_x, mid_y), (x2, y2)], fill=color, width=lw_fat)
            else:
                draw.line([(x1, y1), (x2, y2)], fill=color, width=lw)

    elif el.mode == "directional":
        spacing = (el.outer_radius * 2) / max(1, el.line_count)
        for i in range(el.line_count):
            px = cx - el.outer_radius + i * spacing
            draw.line(
                [(int(px), int(cy - el.outer_radius)),
                 (int(px), int(cy + el.outer_radius))],
                fill=color, width=lw,
            )

    if el.opacity < 1.0:
        layer = _apply_opacity(layer, el.opacity)

    canvas.alpha_composite(layer)


def _render_impact_frame(canvas: Image.Image, el: ImpactFrameElement) -> None:
    w    = max(1, int(el.width))
    h    = max(1, int(el.height))
    AA   = 4
    layer = Image.new("RGBA", (w * AA, h * AA), (0, 0, 0, 0))
    draw  = ImageDraw.Draw(layer)

    cx   = (w * AA) // 2
    cy   = (h * AA) // 2
    c1   = _parse_color(el.color_primary)
    c2   = _parse_color(el.color_secondary)

    if el.impact_style == "starburst":
        r_outer  = math.sqrt(cx*cx + cy*cy) + 20 * AA
        r_inner  = r_outer * 0.55
        sx       = (w * AA) / (h * AA)
        n_spikes = el.spike_count
        pts = []
        for i in range(n_spikes * 2):
            angle = (i * math.pi) / n_spikes
            r     = r_outer if i % 2 == 0 else r_inner
            pts.append((
                cx + r * math.cos(angle) * sx,
                cy + r * math.sin(angle),
            ))
        draw.polygon(pts, fill=c1)

    elif el.impact_style == "manga_radial":
        r_max = math.sqrt(cx*cx + cy*cy) + 30 * AA
        for i in range(120):
            angle = (i / 120) * 2 * math.pi
            x2    = cx + r_max * math.cos(angle)
            y2    = cy + r_max * math.sin(angle)
            draw.line([(cx, cy), (x2, y2)], fill=c1, width=max(1, AA))

    elif el.impact_style == "halftone_burst":
        rings   = 8
        max_r   = min(cx, cy)
        for ring_i in range(rings):
            ratio     = 1 - ring_i / rings
            ring_r    = int(max_r * (ring_i + 1) / rings)
            dot_count = int(6 + ring_i * 4)
            alpha     = int(ratio * 220)
            col       = c1[:3] + (alpha,)
            dot_r     = max(1, int(max_r / rings * ratio * 0.5))
            for d in range(dot_count):
                a  = (d / dot_count) * 2 * math.pi
                dx_ = int(cx + ring_r * math.cos(a))
                dy_ = int(cy + ring_r * math.sin(a))
                draw.ellipse(
                    [dx_ - dot_r, dy_ - dot_r, dx_ + dot_r, dy_ + dot_r],
                    fill=col,
                )

    elif el.impact_style == "energy_wave":
        for ring_i in range(6, 0, -1):
            ratio   = ring_i / 6
            rx      = int(cx * ratio * 1.1)
            ry      = int(cy * ratio * 1.1)
            alpha   = int((1 - ratio) * 0.8 * 255 + 0.1 * 255)
            col     = c1[:3] + (alpha,)
            pts = []
            for i in range(61):
                angle = (i / 60) * 2 * math.pi
                noise = math.sin(angle * 7) * 8 * ratio * AA
                pts.append((
                    cx + (rx + noise) * math.cos(angle),
                    cy + (ry + noise) * math.sin(angle),
                ))
            draw.line(pts, fill=col, width=max(2, AA))

    final = layer.resize((w, h), Image.Resampling.LANCZOS)

    if el.opacity < 1.0:
        final = _apply_opacity(final, el.opacity)

    paste_rotated(canvas, final, int(el.x), int(el.y), 0, 0, el.rotation)


# ── Balloon shape sub-renderers (Accepts AA scaler) ───────────────────────────

def _draw_oval_balloon(draw, ox, oy, w, h, fill, stroke, sw, tail_x, tail_y, AA):
    draw.ellipse([ox, oy, ox + w, oy + h], fill=fill, outline=stroke, width=sw)
    cx = ox + w // 2
    bot_y = oy + h - sw
    poly = [(cx - 14 * AA, bot_y), (ox + int(tail_x), oy + int(tail_y)), (cx + 14 * AA, bot_y)]
    draw.polygon(poly, fill=fill)
    draw.line([poly[0], poly[1]], fill=stroke, width=sw)
    draw.line([poly[1], poly[2]], fill=stroke, width=sw)

def _draw_box_balloon(draw, ox, oy, w, h, fill, stroke, sw, tail_x, tail_y, AA):
    r = 16 * AA
    draw.rounded_rectangle([ox, oy, ox + w, oy + h], radius=r, fill=fill, outline=stroke, width=sw)
    poly = [(ox + w//2 - 20 * AA, oy + h - sw), (ox + int(tail_x), oy + int(tail_y)), (ox + w//2, oy + h - sw)]
    draw.polygon(poly, fill=fill)
    draw.line([poly[0], poly[1]], fill=stroke, width=sw)
    draw.line([poly[1], poly[2]], fill=stroke, width=sw)

def _draw_starburst(draw, ox, oy, w, h, fill, stroke, sw, AA):
    points = 16
    cx, cy = ox + w // 2, oy + h // 2
    r_outer = min(w, h) / 2 + 15 * AA
    r_inner = r_outer * 0.65
    scale_x = w / h
    pts = []
    for i in range(points * 2):
        angle = (i * math.pi) / points - math.pi / 2
        r = r_outer if i % 2 == 0 else r_inner
        pts.append((cx + r * math.cos(angle) * scale_x, cy + r * math.sin(angle)))
    draw.polygon(pts, fill=fill, outline=stroke, width=sw)

def _draw_fierce_scream(draw, ox, oy, w, h, fill, stroke, sw, AA):
    points = 18
    cx, cy = ox + w // 2, oy + h // 2
    r_base_out = min(w, h) / 2 + 25 * AA
    r_base_in = r_base_out * 0.5
    scale_x = w / h
    pts = []
    for i in range(points * 2):
        angle = (i * math.pi) / points - math.pi / 2
        noise = math.sin(i * 1234.5) * 15 * AA
        r = (r_base_out + noise) if i % 2 == 0 else (r_base_in - abs(noise))
        pts.append((cx + r * math.cos(angle) * scale_x, cy + r * math.sin(angle)))
    draw.polygon(pts, fill=fill, outline=stroke, width=sw)

def _draw_thought(draw, ox, oy, w, h, fill, stroke, sw, AA):
    points = 120
    bumps = 11
    cx, cy = ox + w // 2, oy + h // 2
    rx, ry = w / 2 * 0.85, h / 2 * 0.85
    bulge = 0.22
    pts = []
    for i in range(points):
        angle = (i / points) * math.pi * 2
        mod = 1 + bulge * abs(math.sin(bumps * angle / 2))
        pts.append((cx + (rx * mod) * math.cos(angle), cy + (ry * mod) * math.sin(angle)))
    draw.polygon(pts, fill=fill, outline=stroke, width=sw)
    for dx, dy, r in [(-w/2 + 20*AA, h/2 + 15*AA, 10*AA), (-w/2 - 5*AA, h/2 + 35*AA, 6*AA), (-w/2 - 20*AA, h/2 + 50*AA, 3*AA)]:
        draw.ellipse([cx + dx - r, cy + dy - r, cx + dx + r, cy + dy + r], fill=fill, outline=stroke, width=sw)

def _draw_whisper(draw, ox, oy, w, h, fill, stroke, sw, tail_x, tail_y, AA):
    draw.ellipse([ox, oy, ox + w, oy + h], fill=fill)
    cx = ox + w // 2
    bot_y = oy + h - sw
    poly = [(cx - 14 * AA, bot_y), (ox + int(tail_x), oy + int(tail_y)), (cx + 14 * AA, bot_y)]
    draw.polygon(poly, fill=fill)

    pts = 120
    rx, ry = w/2, h/2
    c_x, c_y = ox + w/2, oy + h/2
    path = []
    for i in range(pts + 1):
        a = (i / pts) * math.pi * 2
        path.append((c_x + rx * math.cos(a), c_y + ry * math.sin(a)))
    
    dash, gap = 8 * AA, 6 * AA
    dist = 0
    drawing = True
    for i in range(len(path) - 1):
        p1, p2 = path[i], path[i+1]
        d = math.hypot(p2[0]-p1[0], p2[1]-p1[1])
        if drawing: draw.line([p1, p2], fill=stroke, width=sw)
        dist += d
        if drawing and dist >= dash: drawing = False; dist = 0
        elif not drawing and dist >= gap: drawing = True; dist = 0
            
    draw.line([poly[0], poly[1]], fill=stroke, width=sw)
    draw.line([poly[1], poly[2]], fill=stroke, width=sw)

def _draw_scifi_hex(draw, ox, oy, w, h, fill, stroke, sw, tail_x, tail_y, AA):
    cut = 20 * AA
    hw, hh = w/2, h/2
    cx, cy = ox + hw, oy + hh
    pts = [
        (cx - hw + cut, cy - hh), (cx + hw - cut, cy - hh),
        (cx + hw, cy - hh + cut), (cx + hw, cy + hh - cut),
        (cx + hw - cut, cy + hh), (cx + 15 * AA, cy + hh),
        (ox + tail_x, oy + tail_y), (cx - 5 * AA, cy + hh),
        (cx - hw + cut, cy + hh), (cx - hw, cy + hh - cut),
        (cx - hw, cy - hh + cut)
    ]
    draw.polygon(pts, fill=fill, outline=stroke, width=sw)

def _draw_wobbly(draw, ox, oy, w, h, fill, stroke, sw, tail_x, tail_y, AA):
    points = 60
    rx, ry = w/2, h/2
    cx, cy = ox + rx, oy + ry
    freq, amp = 8, 6 * AA
    pts = []
    for i in range(points):
        angle = (i / points) * math.pi * 2
        wave = math.sin(angle * freq) * amp
        pts.append((cx + (rx + wave) * math.cos(angle), cy + (ry + wave) * math.sin(angle)))
    draw.polygon(pts, fill=fill, outline=stroke, width=sw)
    
    poly = [(cx - 10 * AA, cy + ry), (ox + tail_x, oy + tail_y), (cx + 20 * AA, cy + ry)]
    draw.polygon(poly, fill=fill)
    draw.line([poly[0], poly[1]], fill=stroke, width=sw)
    draw.line([poly[1], poly[2]], fill=stroke, width=sw)


# ── Text helpers ───────────────────────────────────────────────────────────────

def _load_font(family: str, size: int) -> ImageFont.FreeTypeFont:
    filename = FONT_MAP.get(family)
    if filename:
        path = FONTS_DIR / filename
        if path.exists():
            try:
                return ImageFont.truetype(str(path), size)
            except Exception as e:
                logger.warning(f"BANG! font load failed ({path}): {e}")

    fallbacks = [
        "/system/fonts/DroidSans.ttf",
        "/data/fonts/DroidSans.ttf",
        "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf",
        "/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf",
    ]
    for fb in fallbacks:
        if Path(fb).exists():
            try:
                return ImageFont.truetype(fb, size)
            except Exception:
                pass

    return ImageFont.load_default()

def _wrap_text(text: str, font: ImageFont.FreeTypeFont, max_width: int) -> List[str]:
    words   = text.split()
    lines   = []
    current = ""
    dummy   = Image.new("RGBA", (1, 1))
    draw    = ImageDraw.Draw(dummy)

    for word in words:
        test = (current + " " + word).strip()
        bbox = draw.textbbox((0, 0), test, font=font)
        if bbox[2] - bbox[0] <= max_width:
            current = test
        else:
            if current:
                lines.append(current)
            current = word

    if current:
        lines.append(current)
    return lines or [""]


def _alpha_composite_safe(canvas: Image.Image, layer: Image.Image, x: int, y: int):
    if layer.width <= 0 or layer.height <= 0: return
    cw, ch = canvas.size
    lw, lh = layer.size

    src_x1 = max(0, -x)
    src_y1 = max(0, -y)
    src_x2 = min(lw, cw - x)
    src_y2 = min(lh, ch - y)

    if src_x2 <= src_x1 or src_y2 <= src_y1: return

    dst_x = max(0, x)
    dst_y = max(0, y)

    cropped = layer.crop((src_x1, src_y1, src_x2, src_y2))
    region = canvas.crop((dst_x, dst_y, dst_x + cropped.width, dst_y + cropped.height))
    composited = Image.alpha_composite(region.convert("RGBA"), cropped.convert("RGBA"))
    canvas.paste(composited, (dst_x, dst_y))


def _apply_opacity(img: Image.Image, opacity: float) -> Image.Image:
    img = img.convert("RGBA")
    r, g, b, a = img.split()
    a = a.point(lambda v: int(v * opacity))
    return Image.merge("RGBA", (r, g, b, a))


def _resolve_path(filename: str) -> Optional[str]:
    fn = filename.lstrip("/")
    candidates = [
        PROJECT_ROOT / "public" / fn,
        PROJECT_ROOT.parent / "public" / fn,
        Path("/") / fn,
    ]
    for c in candidates:
        if c.exists():
            return str(c)
    return None

def _parse_padding(padding_str: str) -> dict:
    try:
        parts = [float(p) for p in str(padding_str or "12").strip().split()]
        if len(parts) == 1:
            return {"top": parts[0], "right": parts[0], "bottom": parts[0], "left": parts[0]}
        if len(parts) == 2:
            return {"top": parts[0], "right": parts[1], "bottom": parts[0], "left": parts[1]}
        if len(parts) == 3:
            return {"top": parts[0], "right": parts[1], "bottom": parts[2], "left": parts[1]}
        return {"top": parts[0], "right": parts[1], "bottom": parts[2], "left": parts[3]}
    except Exception:
        return {"top": 12.0, "right": 12.0, "bottom": 12.0, "left": 12.0}
        
def _parse_color(color_str: str) -> tuple:
    try:
        c = color_str.strip().lstrip("#")
        if len(c) == 6:
            return (int(c[0:2], 16), int(c[2:4], 16), int(c[4:6], 16), 255)
        if len(c) == 8:
            return (int(c[0:2], 16), int(c[2:4], 16), int(c[4:6], 16), int(c[6:8], 16))
        if len(c) == 3:
            return (int(c[0]*2, 16), int(c[1]*2, 16), int(c[2]*2, 16), 255)
    except Exception:
        pass
    return (0, 0, 0, 255)