# pyapi/services/pillow_service.py
from fastapi import APIRouter, UploadFile, File, Form, HTTPException, Body
from fastapi.responses import StreamingResponse, JSONResponse
from pydantic import BaseModel
from typing import List, Optional, Union, Dict, Any
from pathlib import Path
from io import BytesIO
from PIL import Image, ImageFilter, ImageEnhance, ImageDraw, ImageFont, ImageOps, UnidentifiedImageError, ImageChops
import json
import math
import random

router = APIRouter(tags=["image"])

# Optional persistent directories
BASE_DIR = Path(__file__).resolve().parents[2]
SERVICE_DATA_DIR = BASE_DIR / "services_data" / "images"
SERVICE_DATA_DIR.mkdir(parents=True, exist_ok=True)


# --- Helpers -----------------------------------------------------------------
def open_image_from_upload(upload: UploadFile) -> Image.Image:
    try:
        upload.file.seek(0)
        image = Image.open(upload.file)
        # Apply EXIF-based orientation correction if present
        image = ImageOps.exif_transpose(image)
        image.load()
        return image
    except UnidentifiedImageError as e:
        raise HTTPException(status_code=400, detail=f"Invalid image upload: {e}")
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Could not open image: {e}")

def image_to_stream_response(img: Image.Image, fmt: Optional[str] = None, filename: Optional[str] = None):
    buf = BytesIO()
    fmt_out = (fmt or (img.format or "PNG")).upper()
    if fmt_out == "JPG":
        fmt_out = "JPEG"
    # Pillow requires mode conversion for some formats (eg JPEG doesn't support RGBA)
    if fmt_out in ("JPEG",) and img.mode in ("RGBA", "LA"):
        background = Image.new("RGB", img.size, (255, 255, 255))
        background.paste(img, mask=img.split()[-1])
        save_img = background
    else:
        save_img = img
    save_img.save(buf, format=fmt_out)
    buf.seek(0)
    content_type = f"image/{fmt_out.lower()}"
    headers = {}
    if filename:
        headers["Content-Disposition"] = f'attachment; filename="{filename}"'
    return StreamingResponse(buf, media_type=content_type, headers=headers)

def parse_color(color_str: str) -> tuple:
    """Parses a hex string into an RGBA tuple."""
    try:
        c = color_str.strip()
        if c.startswith("#"):
            c = c.lstrip("#")
            if len(c) == 6: # RRGGBB
                return (int(c[0:2], 16), int(c[2:4], 16), int(c[4:6], 16), 255)
            elif len(c) == 8: # RRGGBBAA
                return (int(c[0:2], 16), int(c[2:4], 16), int(c[4:6], 16), int(c[6:8], 16))
            elif len(c) == 3: # RGB
                return (int(c[0]*2, 16), int(c[1]*2, 16), int(c[2]*2, 16), 255)
            elif len(c) == 4: # RGBA
                return (int(c[0]*2, 16), int(c[1]*2, 16), int(c[2]*2, 16), int(c[3]*2, 16))
    except Exception:
        pass
    # Default to solid green if parse fails
    return (0, 255, 0, 255)

# --- Models ---
class LayerConfig(BaseModel):
    filepath: str
    x: float
    y: float
    width: float   # Original width
    height: float  # Original height
    scaleX: float
    scaleY: float
    rotation: float
    zIndex: int

class CompositeRequest(BaseModel):
    layers: List[LayerConfig]
    canvas_width: int = 1024
    canvas_height: int = 1024

# --- New Composite Renderer ---
@router.post("/render_composite")
async def render_composite(req: CompositeRequest):
    """
    Reconstructs a multiplane scene from source images.
    Input: JSON list of layers with transforms.
    Output: Path to the rendered image.
    """
    try:
        # 1. Create Canvas
        canvas = Image.new("RGBA", (req.canvas_width, req.canvas_height), (0, 0, 0, 0))
        
        # 2. Sort layers by Z-Index
        sorted_layers = sorted(req.layers, key=lambda l: l.zIndex)
        
        for layer in sorted_layers:
            path = Path(layer.filepath)
            if not path.exists():
                print(f"Warning: File not found {path}")
                continue
                
            # Load Source
            try:
                img = Image.open(path).convert("RGBA")
            except Exception as e:
                print(f"Error loading {path}: {e}")
                continue

            # 3. Calculate Dimensions & Scale
            target_w = int(layer.width * layer.scaleX)
            target_h = int(layer.height * layer.scaleY)
            
            if target_w <= 0 or target_h <= 0:
                continue
                
            # High-quality resize
            img_resized = img.resize((target_w, target_h), Image.Resampling.BICUBIC)
            
            # 4. Handle Rotation & Positioning
            rotation = layer.rotation # Degrees clockwise
            
            if abs(rotation) > 0.01:
                # Pillow rotates Counter-Clockwise, so we negate.
                # expand=True fits the whole rotated image.
                img_rotated = img_resized.rotate(-rotation, expand=True, resample=Image.Resampling.BICUBIC)
                
                # Math to align Konva Top-Left Anchor with Pillow logic
                # Calculate center of unrotated rect:
                cx = layer.x + (target_w / 2.0)
                cy = layer.y + (target_h / 2.0)
                
                # We need to rotate the center point (cx,cy) around (layer.x, layer.y) by `rotation`.
                rad = math.radians(rotation)
                cos_a = math.cos(rad)
                sin_a = math.sin(rad)
                
                # Vector from pivot to center
                dx = (target_w / 2.0)
                dy = (target_h / 2.0)
                
                # Rotated vector
                rot_dx = dx * cos_a - dy * sin_a
                rot_dy = dx * sin_a + dy * cos_a
                
                # New Center in Canvas Space
                final_cx = layer.x + rot_dx
                final_cy = layer.y + rot_dy
                
                # Paste Pos (Top-Left of the new rotated bounding box)
                paste_x = int(final_cx - (img_rotated.width / 2.0))
                paste_y = int(final_cy - (img_rotated.height / 2.0))
                
                canvas.alpha_composite(img_rotated, (paste_x, paste_y))
                
            else:
                # Simple Placement
                canvas.alpha_composite(img_resized, (int(layer.x), int(layer.y)))

        # 5. Save to Temp
        temp_dir = SERVICE_DATA_DIR / "temp_composites"
        temp_dir.mkdir(parents=True, exist_ok=True)
        filename = f"render_{str(hash(json.dumps([l.dict() for l in req.layers])))[:16]}.png"
        out_path = temp_dir / filename
        canvas.save(str(out_path), "PNG")
        
        return {
            "status": "success",
            "temp_path": str(out_path),
            "width": req.canvas_width,
            "height": req.canvas_height
        }

    except Exception as e:
        import traceback
        traceback.print_exc()
        raise HTTPException(status_code=500, detail=f"Render failed: {str(e)}")


def parse_boxes(boxes_json: str) -> List[tuple]:
    try:
        boxes = json.loads(boxes_json)
        parsed = []
        for b in boxes:
            # Accept either dict with x1,y1,x2,y2 or list/tuple
            if isinstance(b, dict):
                parsed.append((int(b["x1"]), int(b["y1"]), int(b["x2"]), int(b["y2"])))
            elif isinstance(b, (list, tuple)) and len(b) == 4:
                parsed.append((int(b[0]), int(b[1]), int(b[2]), int(b[3])))
            else:
                raise ValueError("Invalid box format")
        return parsed
    except Exception as e:
        raise HTTPException(status_code=400, detail=f"Invalid boxes JSON: {e}")

def parse_polygons(polys_json: str) -> List[List[tuple]]:
    """
    Parses a JSON string of polygons.
    Format: [[[x,y], [x,y], [x,y]], ...]
    """
    try:
        raw = json.loads(polys_json)
        polygons = []
        for p in raw:
            # p should be a list of points
            points = []
            for point in p:
                if isinstance(point, (list, tuple)) and len(point) >= 2:
                    points.append((float(point[0]), float(point[1])))
                elif isinstance(point, dict) and "x" in point and "y" in point:
                    points.append((float(point["x"]), float(point["y"])))
            if len(points) >= 3:
                polygons.append(points)
        return polygons
    except Exception as e:
        raise HTTPException(status_code=400, detail=f"Invalid polygon JSON: {e}")

# --- Basic operations --------------------------------------------------------
@router.post("/resize")
async def resize_image(file: UploadFile = File(...), width: int = Form(...), height: int = Form(...), keep_aspect: bool = Form(False)):
    img = open_image_from_upload(file)
    if keep_aspect:
        img.thumbnail((width, height))
    else:
        img = img.resize((width, height))
    return image_to_stream_response(img, fmt=img.format or "PNG", filename=f"resized.{(img.format or 'png').lower()}")

@router.post("/crop")
async def crop_image(file: UploadFile = File(...), x1: int = Form(...), y1: int = Form(...), x2: int = Form(...), y2: int = Form(...)):
    img = open_image_from_upload(file)
    w, h = img.size
    # Clamp
    x1, y1, x2, y2 = max(0, x1), max(0, y1), min(w, x2), min(h, y2)
    if x2 <= x1 or y2 <= y1:
        raise HTTPException(status_code=400, detail="Invalid crop coordinates")
    cropped = img.crop((x1, y1, x2, y2))
    return image_to_stream_response(cropped, fmt=img.format or "PNG", filename=f"cropped.{(img.format or 'png').lower()}")

@router.post("/rotate")
async def rotate_image(file: UploadFile = File(...), degrees: float = Form(0.0), expand: bool = Form(True)):
    img = open_image_from_upload(file)
    rotated = img.rotate(-float(degrees), expand=bool(expand))  # Pillow rotates counter-clockwise; negative for intuitive clockwise degrees
    return image_to_stream_response(rotated, fmt=img.format or "PNG", filename=f"rotated.{(img.format or 'png').lower()}")

@router.post("/flip")
async def flip_image(file: UploadFile = File(...), direction: str = Form(...)):
    img = open_image_from_upload(file)
    dir_lower = direction.lower()
    if dir_lower in ("horizontal", "h", "left-right"):
        out = img.transpose(Image.FLIP_LEFT_RIGHT)
    elif dir_lower in ("vertical", "v", "top-bottom"):
        out = img.transpose(Image.FLIP_TOP_BOTTOM)
    else:
        raise HTTPException(status_code=400, detail="direction must be 'horizontal' or 'vertical'")
    return image_to_stream_response(out, fmt=img.format or "PNG", filename=f"flipped.{(img.format or 'png').lower()}")

# --- Filters & Presets ------------------------------------------------------
PRESET_FILTERS = {
    # Original Presets
    "vintage": {"color": 0.75, "contrast": 1.1, "blur": 0.5, "sepia": True},
    "noir": {"grayscale": True, "contrast": 1.4},
    "sharpen": {"sharpen": True},

    # New Instagram-Style Presets
    "sepia": {"sepia": True, "contrast": 1.1},
    "clarendon": {"contrast": 1.2, "color": 1.35},
    "gingham": {"brightness": 1.05, "contrast": 0.9},
    "moon": {"grayscale": True, "contrast": 1.1, "brightness": 1.1},
    "lark": {"brightness": 1.1, "color_mul": (1.0, 1.0, 1.05)},
    "reyes": {"brightness": 1.1, "contrast": 0.85, "color": 1.1, "color_mul": (1.15, 1.05, 0.95)},
    "juno": {"contrast": 1.15, "color": 1.1, "color_mul": (1.0, 1.05, 1.0)},
    "slumber": {"brightness": 0.95, "color": 0.8},
}

def apply_preset(img: Image.Image, name: str) -> Image.Image:
    cfg = PRESET_FILTERS.get(name)
    if not cfg:
        return img
        
    out = img.convert("RGBA") if img.mode != "RGBA" else img.copy()
    if cfg.get("sepia"):
        r, g, b, a = out.split()
        pixels = out.convert("RGB")
        def sepia_pixel(p):
            r, g, b = p
            tr = int(0.393 * r + 0.769 * g + 0.189 * b)
            tg = int(0.349 * r + 0.686 * g + 0.168 * b)
            tb = int(0.272 * r + 0.534 * g + 0.131 * b)
            return (min(255, tr), min(255, tg), min(255, tb))
        sep = Image.new("RGB", out.size)
        sep_pixels = [sepia_pixel(p) for p in pixels.getdata()]
        sep.putdata(sep_pixels)
        out = sep.convert(img.mode)
    if cfg.get("color_mul"):
        mul = cfg["color_mul"]
        r, g, b = out.split()[:3]
        r = r.point(lambda i, f=mul[0]: min(255, int(i * f)))
        g = g.point(lambda i, f=mul[1]: min(255, int(i * f)))
        b = b.point(lambda i, f=mul[2]: min(255, int(i * f)))
        out = Image.merge("RGB", (r, g, b))
    if cfg.get("brightness"):
        out = ImageEnhance.Brightness(out).enhance(cfg["brightness"])
    if cfg.get("contrast"):
        out = ImageEnhance.Contrast(out).enhance(cfg["contrast"])
    if cfg.get("color"):
        out = ImageEnhance.Color(out).enhance(cfg["color"])
    if cfg.get("blur"):
        out = out.filter(ImageFilter.GaussianBlur(cfg["blur"]))
    if cfg.get("sharpen"):
        out = out.filter(ImageFilter.UnsharpMask(radius=1, percent=150, threshold=3))
    if cfg.get("grayscale"):
        out = out.convert("L").convert("RGB")
    return out

@router.post("/filter/preset")
async def filter_preset(file: UploadFile = File(...), name: str = Form(...)):
    img = open_image_from_upload(file)
    out = apply_preset(img, name)
    return image_to_stream_response(out, fmt=img.format or "PNG", filename=f"{name}.{(img.format or 'png').lower()}")

@router.post("/filter/custom")
async def filter_custom(file: UploadFile = File(...), 
                        blur_radius: Optional[float] = Form(None), 
                        sharpen_amount: Optional[float] = Form(None)):
    img = open_image_from_upload(file)
    out = img
    if blur_radius is not None and blur_radius > 0:
        out = out.filter(ImageFilter.GaussianBlur(float(blur_radius)))
    if sharpen_amount is not None and sharpen_amount > 0:
        out = out.filter(ImageFilter.UnsharpMask(radius=1, percent=int(sharpen_amount), threshold=3))
    return image_to_stream_response(out, fmt=img.format or "PNG", filename=f"filtered.{(img.format or 'png').lower()}")

# --- Enhancement -------------------------------------------------------------
@router.post("/enhance")
async def enhance(file: UploadFile = File(...),
                  brightness: Optional[float] = Form(1.0),
                  contrast: Optional[float] = Form(1.0),
                  color: Optional[float] = Form(1.0),
                  sharpness: Optional[float] = Form(1.0)):
    img = open_image_from_upload(file)
    out = img
    if brightness is not None:
        out = ImageEnhance.Brightness(out).enhance(float(brightness))
    if contrast is not None:
        out = ImageEnhance.Contrast(out).enhance(float(contrast))
    if color is not None:
        out = ImageEnhance.Color(out).enhance(float(color))
    if sharpness is not None:
        out = ImageEnhance.Sharpness(out).enhance(float(sharpness))
    return image_to_stream_response(out, fmt=img.format or "PNG", filename=f"enhanced.{(img.format or 'png').lower()}")

# --- Draw / Masking ---------------------------------------------------------
@router.post("/draw/boxes")
async def draw_boxes_endpoint(file: UploadFile = File(...),
                              boxes: str = Form(...),
                              color: str = Form("#00FF00"),
                              width: int = Form(5),
                              fill: Optional[bool] = Form(False)):
    img = open_image_from_upload(file).convert("RGBA")
    parsed = parse_boxes(boxes)
    rgba = parse_color(color)
    overlay = Image.new("RGBA", img.size, (0, 0, 0, 0))
    draw = ImageDraw.Draw(overlay)
    for b in parsed:
        if fill:
            draw.rectangle(b, fill=rgba)
        else:
            draw.rectangle(b, outline=rgba, width=width)
    out = Image.alpha_composite(img, overlay)
    return image_to_stream_response(out, fmt=img.format or "PNG", filename=f"boxes.{(img.format or 'png').lower()}")

@router.post("/draw/polygons")
async def draw_polygons_endpoint(file: UploadFile = File(...),
                                 polygons: str = Form(...),
                                 color: str = Form("#00FF00"),
                                 fill: Optional[bool] = Form(False)):
    img = open_image_from_upload(file).convert("RGBA")
    parsed = parse_polygons(polygons)
    rgba = parse_color(color)
    overlay = Image.new("RGBA", img.size, (0, 0, 0, 0))
    draw = ImageDraw.Draw(overlay)
    for poly in parsed:
        if fill:
            draw.polygon(poly, fill=rgba)
        else:
            draw.polygon(poly, outline=rgba)
    out = Image.alpha_composite(img, overlay)
    return image_to_stream_response(out, fmt=img.format or "PNG", filename="polygons.png")

@router.post("/draw/text")
async def draw_text(file: UploadFile = File(...),
                    text: str = Form(...),
                    x: int = Form(10),
                    y: int = Form(10),
                    size: int = Form(20),
                    color: str = Form("#FFFFFF"),
                    font_path: Optional[str] = Form(None)):
    img = open_image_from_upload(file)
    draw = ImageDraw.Draw(img)
    try:
        if font_path: font = ImageFont.truetype(font_path, size)
        else: font = ImageFont.load_default()
    except Exception:
        font = ImageFont.load_default()
    rgba = parse_color(color)[:3]
    draw.text((x,y), text, fill=rgba, font=font)
    return image_to_stream_response(img, fmt=img.format or "PNG", filename=f"text.{(img.format or 'png').lower()}")

# --- Composite / Mask -------------------------------------------------------
@router.post("/composite")
async def composite_images(base: UploadFile = File(...), overlay: UploadFile = File(...), mask: Optional[UploadFile] = File(None), x: int = Form(0), y: int = Form(0)):
    base_img = open_image_from_upload(base).convert("RGBA")
    overlay_img = open_image_from_upload(overlay).convert("RGBA")
    if mask:
        mask_img = open_image_from_upload(mask).convert("L")
        if mask_img.size != base_img.size:
            mask_img = mask_img.resize(base_img.size)
    else:
        mask_img = None
    tmp = Image.new("RGBA", base_img.size)
    tmp.paste(overlay_img, (x, y), overlay_img)
    if mask_img:
        result = Image.composite(tmp, base_img, mask_img)
    else:
        result = Image.alpha_composite(base_img, tmp)
    return image_to_stream_response(result.convert(base_img.mode), fmt=base_img.format or "PNG", filename="composite.png")

# --- Convert / Info / Metadata ----------------------------------------------
@router.post("/convert")
async def convert_image(file: UploadFile = File(...), format: str = Form("PNG")):
    img = open_image_from_upload(file)
    fmt = format.strip().upper()
    if fmt == "JPG": fmt = "JPEG"
    if fmt not in ("PNG", "JPEG", "WEBP", "BMP", "GIF", "TIFF"):
        raise HTTPException(status_code=400, detail=f"Unsupported format: {fmt}")
    return image_to_stream_response(img, fmt=fmt, filename=f"converted.{fmt.lower()}")

@router.post("/info")
async def info_image(file: UploadFile = File(...)):
    try:
        img = open_image_from_upload(file)
        info = {
            "width": img.width, "height": img.height, "mode": img.mode, "format": img.format,
            "info": {k: str(v) for k, v in (img.info or {}).items()}
        }
        try:
            exif = img.getexif()
            if exif: info["exif"] = {k: str(exif.get(k)) for k in exif}
        except Exception: pass
        return JSONResponse({"status": "success", "image_info": info})
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

# --- Outpaint / Green Screen (NEW) -------------------------------------------
@router.post("/outpaint")
async def outpaint(
    file: UploadFile = File(...), width: int = Form(...), height: Optional[int] = Form(None),
    x: Optional[int] = Form(None), y: Optional[int] = Form(None), color: str = Form("#00FF00")
):
    img = open_image_from_upload(file).convert("RGBA")
    final_w = width
    final_h = height if height is not None else width
    bg_rgba = parse_color(color)
    canvas = Image.new("RGBA", (final_w, final_h), bg_rgba)
    img_w, img_h = img.size
    pos_x = x if x is not None else (final_w - img_w) // 2
    pos_y = y if y is not None else (final_h - img_h) // 2
    canvas.paste(img, (int(pos_x), int(pos_y)), img)
    return image_to_stream_response(canvas, fmt="PNG", filename="outpainted.png")

# --- Utility endpoint: save to server and return path ------------------------
@router.post("/save")
async def save_image(file: UploadFile = File(...), folder: Optional[str] = Form("uploads"), name: Optional[str] = Form(None)):
    img = open_image_from_upload(file)
    target_folder = SERVICE_DATA_DIR / folder
    target_folder.mkdir(parents=True, exist_ok=True)
    chosen_name = name or f"img_{str(hash(file.filename))}.{(img.format or 'png').lower()}"
    out_path = target_folder / chosen_name
    try:
        img.save(str(out_path))
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Could not save image: {e}")
    return {"status": "success", "path": str(out_path)}

@router.get("/_health")
async def health():
    return {"status": "ok", "service": "pillow_service"}

# ─────────────────────────────────────────────────────────────────────────────
# NEW: Color Grade Endpoint (Pure Python / Pillow, NO Numpy/Scipy Required)
# ─────────────────────────────────────────────────────────────────────────────
import colorsys

def clamp8(v: float) -> int:
    return max(0, min(255, int(round(v))))

def build_curve_lut(points):
    pts = sorted(points, key=lambda p: p[0])
    lut = [0] * 256
    for i in range(256):
        lo, hi = pts[0], pts[-1]
        for j in range(len(pts) - 1):
            if pts[j][0] <= i <= pts[j + 1][0]:
                lo, hi = pts[j], pts[j + 1]
                break
        if lo[0] == hi[0]:
            lut[i] = clamp8(lo[1])
            continue
        t = (i - lo[0]) / float(hi[0] - lo[0])
        v = lo[1] + (hi[1] - lo[1]) * (t * t * (3 - 2 * t))
        lut[i] = clamp8(v)
    return lut

def rgb_to_hsl_255(r, g, b):
    rn, gn, bn = r / 255.0, g / 255.0, b / 255.0
    mx = max(rn, gn, bn)
    mn = min(rn, gn, bn)
    l = (mx + mn) / 2.0
    d = mx - mn

    if d == 0:
        return 0.0, 0.0, l

    s = d / (1.0 - abs(2.0 * l - 1.0)) if (1.0 - abs(2.0 * l - 1.0)) != 0 else 0.0

    if mx == rn:
        h = ((gn - bn) / d) % 6.0
    elif mx == gn:
        h = ((bn - rn) / d) + 2.0
    else:
        h = ((rn - gn) / d) + 4.0

    h *= 60.0
    return h, s, l

def hsl_to_rgb_255(h, s, l):
    h = (h % 360.0) / 360.0
    # Python colorsys uses HLS order, not HSL
    r, g, b = colorsys.hls_to_rgb(h, l, s)
    return clamp8(r * 255.0), clamp8(g * 255.0), clamp8(b * 255.0)

def apply_grain_rgba(img: Image.Image, amount: float) -> Image.Image:
    """
    Matches the JS logic:
      const strength = amount * 1.2;
      n = (Math.random() - 0.5) * strength;
    Noise is added equally to R/G/B, alpha unchanged.
    """
    if amount <= 0:
        return img

    img = img.convert("RGBA")
    data = bytearray(img.tobytes())
    strength = float(amount) * 1.2

    for i in range(0, len(data), 4):
        n = (random.random() - 0.5) * strength
        data[i]     = clamp8(data[i]     + n)
        data[i + 1] = clamp8(data[i + 1] + n)
        data[i + 2] = clamp8(data[i + 2] + n)
        # alpha unchanged

    return Image.frombytes("RGBA", img.size, bytes(data))

def apply_vignette_rgba(img: Image.Image, strength: float) -> Image.Image:
    """
    Canvas version draws a black radial gradient overlay after putImageData.
    This equivalent implementation darkens RGB only and preserves alpha.
    """
    if strength <= 0:
        return img

    img = img.convert("RGBA")
    w, h = img.size
    cx, cy = w / 2.0, h / 2.0
    r = math.sqrt(cx * cx + cy * cy)

    mask = Image.new("L", (w, h), 255)
    px = mask.load()

    max_dark = min(0.85, strength * 0.85)

    for y in range(h):
        for x in range(w):
            dist = math.sqrt((x - cx) ** 2 + (y - cy) ** 2)
            inner = r * 0.4
            if dist <= inner:
                darken = 1.0
            else:
                t = min(1.0, (dist - inner) / (r - inner))
                darken = 1.0 - t * max_dark
            px[x, y] = clamp8(darken * 255.0)

    rch, gch, bch, ach = img.split()
    rch = ImageChops.multiply(rch, mask)
    gch = ImageChops.multiply(gch, mask)
    bch = ImageChops.multiply(bch, mask)
    return Image.merge("RGBA", (rch, gch, bch, ach))

# ─────────────────────────────────────────────────────────────────────────────
# NEW: Color Grade Endpoint (Pure Python / Pillow, NO Numpy/Scipy Required)
# ─────────────────────────────────────────────────────────────────────────────
@router.post("/grade")
async def grade_image(
    file:          UploadFile = File(...),
    settings_json: str        = Form(...),
):
    """
    Full colour grade pipeline. All operations applied in exactly the same order
    as the Canvas2D preview engine in the UI, ensuring the saved result perfectly 
    matches the preview.
    
    Optimized for pure Python + Pillow (No numpy or scipy needed).
    Uses C-accelerated ImageChops instead of ImageMath for complete compatibility.
    """
    try:
        settings = json.loads(settings_json)
    except Exception as e:
        raise HTTPException(status_code=400, detail=f"Invalid settings_json: {e}")

    img = open_image_from_upload(file)
    original_mode = img.mode
    img = img.convert("RGBA")

    # ── 1. Pre-compute Global Lookup Table (LUT) ──
    # Operations that are strictly per-channel can be baked into a single pass LUT
    # (Lift, Gain, Brightness, Contrast, Gamma, Temp, Tint). This is instantly applied in C.
    bFactor  = 1 + float(settings.get("brightness", 0)) / 100.0
    cFactor  = 1 + float(settings.get("contrast", 0)) / 100.0
    gamma    = float(settings.get("gamma", 0))
    gFactor  = 1 / (1 + gamma / 100.0) if gamma > -100 else 10.0
    liftV    = float(settings.get("lift", 0)) * 0.5
    gainV    = 1 + float(settings.get("gain", 0)) / 100.0
    
    temp     = float(settings.get("temperature", 0))
    tint     = float(settings.get("tint", 0))
    tempShift = temp / 2.0
    tintShift = tint / 4.0

    def make_base_lut(add_val):
        lut = []
        for i in range(256):
            v = float(i)
            # Lift
            if liftV != 0: v += liftV * (255 - v) / 255.0 * 128.0
            # Gain
            if gainV != 1.0: v *= gainV
            # Brightness
            if bFactor != 1.0: v *= bFactor
            # Contrast
            if cFactor != 1.0: v = (v - 128.0) * cFactor + 128.0
            # Gamma
            if gFactor != 1.0: v = 255.0 * math.pow(max(0, min(255, v)) / 255.0, gFactor)
            # Addition (Temp/Tint)
            v += add_val
            lut.append(max(0, min(255, int(round(v)))))
        return lut
    
    base_lut_r = make_base_lut(tempShift)
    base_lut_g = make_base_lut(tintShift)
    base_lut_b = make_base_lut(-tempShift)
    base_lut_a = list(range(256))

    # Apply base LUT (Lightning fast)
    img = img.point(base_lut_r + base_lut_g + base_lut_b + base_lut_a)

    # ── 2. Cross-Channel / Non-linear (Shadows, Whites, Saturation, Hue) ──
    # These operations depend on cross-channel math (luminance, max/min values).
    # We loop over the bytearray only if these settings are actually used.
    shadows    = float(settings.get("shadows", 0))
    highlights = float(settings.get("highlights", 0))
    whites     = float(settings.get("whites", 0))
    blacks     = float(settings.get("blacks", 0))
    saturation = float(settings.get("saturation", 0))
    vibrance   = float(settings.get("vibrance", 0))
    hue_rotate = float(settings.get("hue_rotate", 0))

    do_sh   = shadows != 0 or highlights != 0 or whites != 0 or blacks != 0
    do_sat  = saturation != 0
    do_vib  = vibrance != 0
    do_hue  = hue_rotate != 0

    if do_sh or do_sat or do_vib or do_hue:
        sb = shadows / 200.0
        hb = highlights / 200.0
        wb = whites / 200.0
        bb = blacks / 200.0
        satF = 1.0 + saturation / 100.0
        vibF = vibrance / 100.0
        
        # Precalc hue matrix (standard RGB YIQ rotation)
        if do_hue:
            hrad = hue_rotate * math.pi / 180.0
            cos_a, sin_a = math.cos(hrad), math.sin(hrad)
            m00 = 0.213 + cos_a*0.787 - sin_a*0.213
            m01 = 0.213 - cos_a*0.213 - sin_a*0.143
            m02 = 0.213 - cos_a*0.213 + sin_a*0.140
            m10 = 0.715 - cos_a*0.715 - sin_a*0.715
            m11 = 0.715 + cos_a*0.285 + sin_a*0.140
            m12 = 0.715 - cos_a*0.715 + sin_a*0.140
            m20 = 0.072 - cos_a*0.072 + sin_a*0.928
            m21 = 0.072 - cos_a*0.072 - sin_a*0.283
            m22 = 0.072 + cos_a*0.928 + sin_a*0.283

        data = bytearray(img.tobytes())
        
        # Fast byte processing
        for i in range(0, len(data), 4):
            r, g, b = data[i], data[i+1], data[i+2]
            
            # Shadow & Highlight recovery
            if do_sh:
                lum0 = (r*0.299 + g*0.587 + b*0.114) / 255.0
                if sb != 0:
                    sw = 1.0 - lum0 * 3.0
                    if sw > 0:
                        d = sb * sw * 255.0
                        r += d; g += d; b += d
                if hb != 0:
                    hw = lum0 * 3.0 - 2.0
                    if hw > 0:
                        d = hb * hw * 255.0
                        r += d; g += d; b += d
                if bb != 0:
                    bw = 1.0 - lum0 * 5.0
                    if bw > 0:
                        d = bb * bw * 255.0
                        r += d; g += d; b += d
                if wb != 0:
                    ww = lum0 * 5.0 - 4.0
                    if ww > 0:
                        d = wb * ww * 255.0
                        r += d; g += d; b += d
                r = max(0, min(255, r)); g = max(0, min(255, g)); b = max(0, min(255, b))
            
            # Saturation
            if do_sat:
                lum = r*0.299 + g*0.587 + b*0.114
                r = lum + (r - lum) * satF
                g = lum + (g - lum) * satF
                b = lum + (b - lum) * satF
                
            # Vibrance
            if do_vib:
                mx = r if (r > g and r > b) else (g if g > b else b)
                mn = r if (r < g and r < b) else (g if g < b else b)
                sat_val = (mx - mn) / mx if mx > 0 else 0
                vw = 1.0 - sat_val
                lum = r*0.299 + g*0.587 + b*0.114
                vf = 1.0 + vibF * vw
                r = lum + (r - lum) * vf
                g = lum + (g - lum) * vf
                b = lum + (b - lum) * vf
                r = max(0, min(255, r)); g = max(0, min(255, g)); b = max(0, min(255, b))
                
            # Hue Rotate
            if do_hue:
                r_new = r * m00 + g * m01 + b * m02
                g_new = r * m10 + g * m11 + b * m12
                b_new = r * m20 + g * m21 + b * m22
                r, g, b = r_new, g_new, b_new
                r = max(0, min(255, r)); g = max(0, min(255, g)); b = max(0, min(255, b))
                
            data[i] = int(r)
            data[i+1] = int(g)
            data[i+2] = int(b)
            
        img = Image.frombytes("RGBA", img.size, bytes(data))

    # ── 3. Curves (LUT) ──
    def build_curve_lut(points):
        # Pure Python PCHIP / Hermite fallback replacing Scipy
        pts = sorted(points, key=lambda p: p[0])
        lut = [0] * 256
        for i in range(256):
            lo, hi = pts[0], pts[-1]
            for j in range(len(pts) - 1):
                if pts[j][0] <= i <= pts[j+1][0]:
                    lo, hi = pts[j], pts[j+1]
                    break
            if lo[0] == hi[0]:
                lut[i] = int(round(lo[1]))
                continue
            t = (i - lo[0]) / float(hi[0] - lo[0])
            # Hermite smoothstep (matches JS frontend exact logic)
            v = lo[1] + (hi[1] - lo[1]) * (t * t * (3 - 2 * t))
            lut[i] = max(0, min(255, int(round(v))))
        return lut
        
    curves_cfg = settings.get("curves", {})
    def_curve = [[0,0],[64,64],[128,128],[192,192],[255,255]]
    
    lut_rgb = build_curve_lut(curves_cfg.get("rgb", def_curve))
    lut_r   = build_curve_lut(curves_cfg.get("r",   def_curve))
    lut_g   = build_curve_lut(curves_cfg.get("g",   def_curve))
    lut_b   = build_curve_lut(curves_cfg.get("b",   def_curve))

    # Combine per-channel curves with the master RGB curve for one mapping step
    final_lut_r = [lut_rgb[lut_r[i]] for i in range(256)]
    final_lut_g = [lut_rgb[lut_g[i]] for i in range(256)]
    final_lut_b = [lut_rgb[lut_b[i]] for i in range(256)]

    img = img.point(final_lut_r + final_lut_g + final_lut_b + base_lut_a)

    # ── 4. Blur & Sharpen ──
    blur_radius = float(settings.get("blur", 0))
    if blur_radius > 0:
        img = img.filter(ImageFilter.GaussianBlur(blur_radius))

    sharpen = float(settings.get("sharpen", 0))
    if sharpen > 0:
        img = img.filter(ImageFilter.UnsharpMask(radius=1, percent=int(sharpen), threshold=3))

    # ── 5. Grain ──
    # Using ImageChops to safely blend noise natively in C without `eval`
    grain = float(settings.get("grain", 0))
    if grain > 0:
        noise_w, noise_h = 256, 256
        noise_pos = bytearray(noise_w * noise_h)
        noise_neg = bytearray(noise_w * noise_h)
        for i in range(noise_w * noise_h):
            val = (random.random() - 0.5) * (grain / 100.0) * 1.2 * 255
            if val > 0:
                noise_pos[i] = min(255, int(val))
            else:
                noise_neg[i] = min(255, int(-val))
        
        # Scale noise up to image size
        img_pos = Image.frombytes("L", (noise_w, noise_h), bytes(noise_pos)).resize(img.size, Image.Resampling.BILINEAR)
        img_neg = Image.frombytes("L", (noise_w, noise_h), bytes(noise_neg)).resize(img.size, Image.Resampling.BILINEAR)
        
        # Create RGBA noise overlays where Alpha is 0 (preserves the original image's alpha channel)
        empty_alpha = Image.new("L", img.size, 0)
        img_pos_rgba = Image.merge("RGBA", (img_pos, img_pos, img_pos, empty_alpha))
        img_neg_rgba = Image.merge("RGBA", (img_neg, img_neg, img_neg, empty_alpha))
        
        img = ImageChops.add(img, img_pos_rgba)
        img = ImageChops.subtract(img, img_neg_rgba)

    # ── 6. Vignette ──
    # Created mathematically on a tiny mask, scaled up, and multiplied in C
    vignette = float(settings.get("vignette", 0))
    if vignette > 0:
        mask_w, mask_h = 256, 256
        mask_data = bytearray(mask_w * mask_h)
        cx, cy = mask_w / 2.0, mask_h / 2.0
        max_r = math.sqrt(cx*cx + cy*cy)
        inner = max_r * 0.4
        outer = max_r
        
        for y in range(mask_h):
            for x in range(mask_w):
                dist = math.sqrt((x-cx)**2 + (y-cy)**2)
                val = 0.0 if dist <= inner else min(1.0, (dist - inner) / (outer - inner))
                darken = 1.0 - val * (vignette / 100.0) * 0.85
                mask_data[y*mask_w + x] = int(darken * 255)
        
        mask_img = Image.frombytes("L", (mask_w, mask_h), bytes(mask_data)).resize(img.size, Image.Resampling.BICUBIC)
        
        # Merge mask into RGBA where Alpha is solid 255 to perfectly preserve original Alpha during multiplication
        solid_alpha = Image.new("L", img.size, 255)
        mask_rgba = Image.merge("RGBA", (mask_img, mask_img, mask_img, solid_alpha))
        img = ImageChops.multiply(img, mask_rgba)

    # Convert back to the original format before returning
    if original_mode not in ("RGBA", "LA"):
        img = img.convert("RGB")

    return image_to_stream_response(img, fmt="PNG", filename="graded.png")