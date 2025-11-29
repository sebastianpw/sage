# services/pillow_service.py
from fastapi import APIRouter, UploadFile, File, Form, HTTPException
from fastapi.responses import StreamingResponse, JSONResponse
from pydantic import BaseModel
from typing import List, Optional
from pathlib import Path
from io import BytesIO
from PIL import Image, ImageFilter, ImageEnhance, ImageDraw, ImageFont, ImageOps, UnidentifiedImageError
import json
import imghdr

router = APIRouter(tags=["image"])

# Optional persistent directories (safe defaults)
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

def safe_int(val, default=0):
    try:
        return int(val)
    except Exception:
        return default

# --- Pydantic models (for JSON endpoints if needed) --------------------------
class EnhanceParams(BaseModel):
    brightness: Optional[float] = 1.0
    contrast: Optional[float] = 1.0
    color: Optional[float] = 1.0
    sharpness: Optional[float] = 1.0

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
    "clarendon": {"contrast": 1.2, "color": 1.35}, # Boosts light and dark, adds vibrance
    "gingham": {"brightness": 1.05, "contrast": 0.9}, # A gentle, hazy, washed-out look
    "moon": {"grayscale": True, "contrast": 1.1, "brightness": 1.1}, # A softer, brighter B&W
    "lark": {"brightness": 1.1, "color_mul": (1.0, 1.0, 1.05)}, # Slightly brighter and cooler
    "reyes": {
        "brightness": 1.1,
        "contrast": 0.85,
        "color": 1.1,
        "color_mul": (1.15, 1.05, 0.95)  # Boost reds slightly, mild green, reduce blue
    },
    "juno": {"contrast": 1.15, "color": 1.1, "color_mul": (1.0, 1.05, 1.0)}, # Punches contrast, boosts greens
    "slumber": {"brightness": 0.95, "color": 0.8}, # Desaturated and dreamy
}



def apply_preset(img: Image.Image, name: str) -> Image.Image:
    cfg = PRESET_FILTERS.get(name)
    if not cfg:
        raise HTTPException(status_code=404, detail="Preset not found")
    out = img.convert("RGBA") if img.mode != "RGBA" else img.copy()
    # Sepia
    if cfg.get("sepia"):
        r, g, b, a = out.split()
        # simple sepia by matrix
        sep = Image.new("RGBA", out.size)
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
    # Color multiplier
    if cfg.get("color_mul"):
        mul = cfg["color_mul"]
        r, g, b = out.split()[:3]
        r = r.point(lambda i, f=mul[0]: min(255, int(i * f)))
        g = g.point(lambda i, f=mul[1]: min(255, int(i * f)))
        b = b.point(lambda i, f=mul[2]: min(255, int(i * f)))
        out = Image.merge("RGB", (r, g, b))
    # Brightness/contrast/color/sharpness via enhancers
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
        # The 'percent' parameter in UnsharpMask is a good target for a slider
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
                              boxes: str = Form(...),  # JSON string: [{"x1":..,"y1":..,"x2":..,"y2":..}, ...] or [[x1,y1,x2,y2],...]
                              color: str = Form("#00FF00"),
                              width: int = Form(5),
                              fill: Optional[bool] = Form(False)):
    """
    boxes: JSON string list of boxes.
    color: hex color like #RRGGBB or CSS names (limited). Defaults to green.
    fill: whether to fill boxes (useful for masks) - if True will fill with color and preserve alpha.
    """
    img = open_image_from_upload(file)
    parsed = parse_boxes(boxes)
    draw = ImageDraw.Draw(img, "RGBA")
    # parse color hex
    try:
        if color.startswith("#") and len(color) in (7, 4):
            # accept #RRGGBB or #RGB
            if len(color) == 7:
                r = int(color[1:3], 16); g = int(color[3:5], 16); b = int(color[5:7], 16)
            else:
                r = int(color[1]*2, 16); g = int(color[2]*2, 16); b = int(color[3]*2, 16)
            rgba = (r, g, b, 255)
        else:
            # fallback basic named colors
            named = {"green": (0,255,0,255), "red": (255,0,0,255), "blue": (0,0,255,255)}
            rgba = named.get(color.lower(), (0,255,0,255))
    except Exception:
        rgba = (0,255,0,255)
    for b in parsed:
        if fill:
            draw.rectangle(b, fill=rgba)
        else:
            # draw border by drawing multiple rectangles for thickness or use Pillow >= 8.2 width param
            try:
                draw.rectangle(b, outline=rgba, width=width)
            except TypeError:
                # fallback for older pillow without width param
                x1,y1,x2,y2 = b
                for i in range(width):
                    draw.rectangle((x1+i, y1+i, x2-i, y2-i), outline=rgba)
    return image_to_stream_response(img, fmt=img.format or "PNG", filename=f"boxes.{(img.format or 'png').lower()}")

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
    # Try to load a font if path given, else default
    try:
        if font_path:
            font = ImageFont.truetype(font_path, size)
        else:
            font = ImageFont.load_default()
    except Exception:
        font = ImageFont.load_default()
    # parse color
    try:
        if color.startswith("#"):
            if len(color) == 7:
                r = int(color[1:3], 16); g = int(color[3:5], 16); b = int(color[5:7], 16)
                clr = (r,g,b)
            else:
                clr = (255,255,255)
        else:
            named = {"white": (255,255,255), "black": (0,0,0)}
            clr = named.get(color.lower(), (255,255,255))
    except Exception:
        clr = (255,255,255)
    draw.text((x,y), text, fill=clr, font=font)
    return image_to_stream_response(img, fmt=img.format or "PNG", filename=f"text.{(img.format or 'png').lower()}")

# --- Composite / Mask -------------------------------------------------------
@router.post("/composite")
async def composite_images(base: UploadFile = File(...), overlay: UploadFile = File(...), mask: Optional[UploadFile] = File(None), x: int = Form(0), y: int = Form(0)):
    base_img = open_image_from_upload(base).convert("RGBA")
    overlay_img = open_image_from_upload(overlay).convert("RGBA")
    # Optional mask to control alpha - must be same size or will be resized
    if mask:
        mask_img = open_image_from_upload(mask).convert("L")
        if mask_img.size != base_img.size:
            mask_img = mask_img.resize(base_img.size)
    else:
        mask_img = None
    # Paste overlay at x,y with mask (or use overlay alpha)
    tmp = Image.new("RGBA", base_img.size)
    tmp.paste(overlay_img, (x, y), overlay_img)
    if mask_img:
        result = Image.composite(tmp, base_img, mask_img)
    else:
        result = Image.alpha_composite(base_img, tmp)
    return image_to_stream_response(result.convert(base_img.mode), fmt=base_img.format or "PNG", filename=f"composite.png")

# --- Convert / Info / Metadata ----------------------------------------------
@router.post("/convert")
async def convert_image(file: UploadFile = File(...), format: str = Form("PNG")):
    img = open_image_from_upload(file)
    fmt = format.strip().upper()
    if fmt == "JPG":
        fmt = "JPEG"
    if fmt not in ("PNG", "JPEG", "WEBP", "BMP", "GIF", "TIFF"):
        raise HTTPException(status_code=400, detail=f"Unsupported format: {fmt}")
    return image_to_stream_response(img, fmt=fmt, filename=f"converted.{fmt.lower()}")

@router.post("/info")
async def info_image(file: UploadFile = File(...)):
    try:
        img = open_image_from_upload(file)
        info = {
            "width": img.width,
            "height": img.height,
            "mode": img.mode,
            "format": img.format,
            "info": {k: str(v) for k, v in (img.info or {}).items()}
        }
        # EXIF (if present)
        try:
            exif = img.getexif()
            if exif:
                info["exif"] = {k: str(exif.get(k)) for k in exif}
        except Exception:
            pass
        return JSONResponse({"status": "success", "image_info": info})
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

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

# --- Basic health check endpoint for this service ---------------------------
@router.get("/_health")
async def health():
    return {"status": "ok", "service": "pillow_service"}
