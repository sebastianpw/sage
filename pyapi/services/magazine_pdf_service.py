# pyapi/services/magazine_pdf_service.py
"""
SAGE Magazine PDF Export Service
---------------------------------
Receives a multipart POST with:
  - job_meta   : JSON string describing the issue (see MagazineJobMeta)
  - image_*    : uploaded image files keyed as "image_{frame_id}"

Returns a job_id immediately.  Client polls /magazine-pdf/status/{job_id}.
When done, /magazine-pdf/download/{job_id} streams the ZIP.

PDF library: reportlab (pip install reportlab)
  — pure Python, no native compilation, works on Termux and Debian proot.

Fonts: Cinzel (titles) + Lora (body) — downloaded once on first use and
  cached next to this file in pyapi/assets/fonts/.
"""

import io
import json
import logging
import os
import shutil
import tempfile
import threading
import urllib.request
import uuid
import zipfile
from pathlib import Path
from typing import Dict, List, Optional, Any

from fastapi import APIRouter, File, Form, HTTPException, UploadFile, Request
from fastapi.responses import FileResponse, JSONResponse
from pydantic import BaseModel

logger = logging.getLogger(__name__)

router = APIRouter()

# ---------------------------------------------------------------------------
# Font bootstrap — downloads Cinzel + Lora variable TTFs once, caches them
# ---------------------------------------------------------------------------
_FONT_DIR = Path(__file__).resolve().parent.parent / "assets" / "fonts"

_FONT_URLS: Dict[str, str] = {
    "Cinzel.ttf":        "https://raw.githubusercontent.com/google/fonts/main/ofl/cinzel/Cinzel%5Bwght%5D.ttf",
    "Lora.ttf":          "https://raw.githubusercontent.com/google/fonts/main/ofl/lora/Lora%5Bwght%5D.ttf",
    "Lora-Italic.ttf":   "https://raw.githubusercontent.com/google/fonts/main/ofl/lora/Lora-Italic%5Bwght%5D.ttf",
}


def _ensure_fonts() -> bool:
    """
    Downloads any missing font files into _FONT_DIR.
    Returns True if all fonts are present after the call.
    """
    _FONT_DIR.mkdir(parents=True, exist_ok=True)
    all_ok = True
    for filename, url in _FONT_URLS.items():
        dest = _FONT_DIR / filename
        if dest.exists() and dest.stat().st_size > 10_000:
            continue  # already cached
        logger.info("Downloading font %s …", filename)
        try:
            req = urllib.request.Request(url, headers={"User-Agent": "curl/7.0"})
            with urllib.request.urlopen(req, timeout=30) as resp:
                data = resp.read()
            dest.write_bytes(data)
            logger.info("Font saved: %s (%d bytes)", filename, len(data))
        except Exception as exc:
            logger.warning("Could not download font %s: %s — falling back to Helvetica", filename, exc)
            all_ok = False
    return all_ok


def _register_fonts() -> bool:
    """
    Registers Cinzel and Lora with ReportLab.
    Called once per process (subsequent calls are no-ops).
    Returns True if custom fonts are available.
    """
    from reportlab.pdfbase import pdfmetrics
    from reportlab.pdfbase.ttfonts import TTFont
    from reportlab.lib.fonts import addMapping

    if "Cinzel" in pdfmetrics.getRegisteredFontNames():
        return True  # already registered

    if not _ensure_fonts():
        return False

    try:
        cinzel_path      = str(_FONT_DIR / "Cinzel.ttf")
        lora_path        = str(_FONT_DIR / "Lora.ttf")
        lora_italic_path = str(_FONT_DIR / "Lora-Italic.ttf")

        pdfmetrics.registerFont(TTFont("Cinzel",          cinzel_path))
        pdfmetrics.registerFont(TTFont("Cinzel-Bold",     cinzel_path))      # variable; bold axis baked in
        pdfmetrics.registerFont(TTFont("Lora",            lora_path))
        pdfmetrics.registerFont(TTFont("Lora-Bold",       lora_path))        # bold weight from variable
        pdfmetrics.registerFont(TTFont("Lora-Italic",     lora_italic_path))
        pdfmetrics.registerFont(TTFont("Lora-BoldItalic", lora_italic_path)) # bold italic from italic variable

        addMapping("Cinzel", 0, 0, "Cinzel")
        addMapping("Cinzel", 1, 0, "Cinzel-Bold")
        addMapping("Lora",   0, 0, "Lora")
        addMapping("Lora",   1, 0, "Lora-Bold")
        addMapping("Lora",   0, 1, "Lora-Italic")
        addMapping("Lora",   1, 1, "Lora-BoldItalic")

        # --- NEW: Load the comic fonts from pyapi/fonts/ ---
        custom_fonts_dir = Path(__file__).resolve().parent.parent / "fonts"
        
        comic_fonts = {
            "Bangers": "Bangers-Regular.ttf",
            "PermanentMarker": "PermanentMarker-Regular.ttf",
            "Oswald": "Oswald-Bold.ttf",
            "SpaceMono": "SpaceMono-Regular.ttf"
        }
        
        for family_name, file_name in comic_fonts.items():
            font_path = custom_fonts_dir / file_name
            if font_path.exists():
                pdfmetrics.registerFont(TTFont(family_name, str(font_path)))

        logger.info("Cinzel + Lora + Comic fonts registered with ReportLab")
        return True

    except Exception as exc:
        logger.warning("Font registration failed (%s) — falling back to Helvetica", exc)
        return False


# ---------------------------------------------------------------------------
# Job store — file-backed dictionary to survive Uvicorn reloads
# ---------------------------------------------------------------------------
_jobs: Dict[str, dict] = {}
_JOBS_DIR = Path(tempfile.gettempdir()) / "sage_magazine_pdf_jobs"
_JOBS_DIR.mkdir(parents=True, exist_ok=True)

def _job_dir(job_id: str) -> Path:
    d = _JOBS_DIR / job_id
    d.mkdir(parents=True, exist_ok=True)
    return d

def _save_job(job_id: str, data: dict) -> None:
    _jobs[job_id] = data
    state_file = _job_dir(job_id) / "state.json"
    try:
        state_file.write_text(json.dumps(data))
    except Exception as e:
        logger.warning(f"Could not save job state to disk for {job_id}: {e}")

def _get_job(job_id: str) -> Optional[dict]:
    if job_id in _jobs:
        return _jobs[job_id]
    state_file = _job_dir(job_id) / "state.json"
    if state_file.exists():
        try:
            data = json.loads(state_file.read_text())
            _jobs[job_id] = data
            return data
        except Exception:
            pass
    return None

# ---------------------------------------------------------------------------
# Pydantic models (used for documentation only — actual input via Form/File)
# ---------------------------------------------------------------------------
class FrameEntry(BaseModel):
    frame_id: int
    sketch_id: int
    overlay_texts: Dict[str, List[str]]   # lang_code → [text lines]
    fuki_texts: Dict[str, List[Dict[str, Any]]] = {} # lang_code → [{x, y, text_content, ...}]


class MagazineJobMeta(BaseModel):
    series_title: str
    sequence_name: str
    sequence_id: int
    series_id: int
    languages: List[str]
    asset_url_prefix: Optional[str] = ""
    frames: List[FrameEntry]
    cover_frame_id: Optional[int] = None
    description: Optional[str] = ""
    localized_sequence_names: Dict[str, str] = {}
    localized_descriptions: Dict[str, str] = {}
    pdf_full_upright: bool = False
    pdf_disable_texts: bool = False
    pdf_disable_fuki: bool = False


# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------

def _image_path(job_id: str, frame_id: int) -> Optional[Path]:
    """Return the saved image path for a frame, or None if missing."""
    job_dir = _job_dir(job_id)
    for ext in ("jpg", "jpeg", "png", "webp"):
        p = job_dir / f"img_{frame_id}.{ext}"
        if p.exists():
            return p
    return None


# ---------------------------------------------------------------------------
# PDF builder & Custom Flowables
# ---------------------------------------------------------------------------

from reportlab.platypus import Flowable
from reportlab.platypus import Image as RLImage
from reportlab.platypus import Paragraph
from reportlab.lib.styles import ParagraphStyle
from reportlab.lib.enums import TA_CENTER, TA_LEFT, TA_RIGHT
from reportlab.lib.colors import HexColor
from reportlab.lib.utils import ImageReader

class FrameImageWithFuki(Flowable):
    """
    A custom ReportLab Flowable that draws a base image and then layers 
    absolute positioned Fuki texts on top of it.
    """
    def __init__(self, img_path: str, fuki_list: List[Dict[str, Any]], body_w: float, default_max_h: float, full_page_max_h: float = None, pdf_full_upright: bool = False):
        Flowable.__init__(self)
        self.hAlign = 'CENTER' # Forces ReportLab layout engine to horizontally center this flowable
        
        self.img = RLImage(img_path)
        self.fuki_list = fuki_list
        
        is_upright = self.img.imageHeight > self.img.imageWidth
        if pdf_full_upright and is_upright and full_page_max_h is not None:
            max_h = full_page_max_h
        else:
            max_h = default_max_h
        
        scale = body_w / self.img.imageWidth
        self.img.drawWidth = body_w
        self.img.drawHeight = self.img.imageHeight * scale
        
        if self.img.drawHeight > max_h:
            scale2 = max_h / self.img.drawHeight
            self.img.drawWidth *= scale2
            self.img.drawHeight = max_h
            self.scale = scale * scale2
        else:
            self.scale = scale
            
        self.width = self.img.drawWidth
        self.height = self.img.drawHeight

    def wrap(self, availWidth, availHeight):
        return self.width, self.height

    def draw(self):
        c = self.canv
        
        # 1. Draw the image (RLImage uses (0,0) as its bottom-left relative to the flowable's position)
        self.img.drawOn(c, 0, 0)
        
        # 2. Draw fuki texts if any
        if not self.fuki_list:
            return
            
        c.saveState()
        
        # Shift coordinate system so (0,0) is top-left of the image, matching Konva
        c.translate(0, self.height)
        
        for ft in self.fuki_list:
            text = ft.get("text_content", "")
            if not text: continue
            
            x = ft.get("x", 0) * self.scale
            y = ft.get("y", 0) * self.scale
            w = ft.get("width", 200) * self.scale
            rot = ft.get("rotation", 0)
            font_size = max(4, ft.get("font_size", 24) * self.scale)
            
            family = ft.get("font_family", "Bangers")
            is_bold = ft.get("is_bold", 0) == 1
            is_italic = ft.get("is_italic", 0) == 1
            
            # Font Mapping Fallback Logic
            rl_font = "Helvetica"
            if family == "Cinzel":
                rl_font = "Cinzel-Bold" if is_bold else "Cinzel"
            elif family == "Lora":
                if is_bold and is_italic: rl_font = "Lora-BoldItalic"
                elif is_bold: rl_font = "Lora-Bold"
                elif is_italic: rl_font = "Lora-Italic"
                else: rl_font = "Lora"
            elif family == "Bangers":
                rl_font = "Bangers"
            elif family == "Permanent Marker":
                rl_font = "PermanentMarker"
            elif family == "Oswald":
                rl_font = "Oswald"
            elif family == "Space Mono":
                rl_font = "SpaceMono"
            else:
                if is_bold and is_italic: rl_font = "Helvetica-BoldOblique"
                elif is_bold: rl_font = "Helvetica-Bold"
                elif is_italic: rl_font = "Helvetica-Oblique"
                else: rl_font = "Helvetica"
                
            color_hex = ft.get("fill_color", "#111111")
            try:
                c.setFillColor(HexColor(color_hex))
            except:
                c.setFillColor(HexColor("#111111"))
                
            c.saveState()
            
            # Position the context for this specific text block
            c.translate(x, -y)
            c.rotate(-rot) # Reportlab rotates counter-clockwise
            
            align_str = ft.get("text_align", "center")
            if align_str == "right": align = TA_RIGHT
            elif align_str == "left": align = TA_LEFT
            else: align = TA_CENTER
            
            under = ft.get("is_underline", 0) == 1
            
            style = ParagraphStyle(
                name="FukiStyle",
                fontName=rl_font,
                fontSize=font_size,
                leading=font_size * 1.2,
                textColor=HexColor(color_hex),
                alignment=align
            )
            
            text_html = text.replace('\n', '<br/>')
            if under:
                text_html = f"<u>{text_html}</u>"
                
            p = Paragraph(text_html, style)
            pw, ph = p.wrap(w, self.height)
            
            # Paragraphs draw starting from bottom-left corner
            p.drawOn(c, 0, -ph)
            
            c.restoreState()
            
        c.restoreState()


def _build_pdf_for_language(
    meta: MagazineJobMeta,
    lang: str,
    job_id: str,
    out_path: Path,
) -> None:

    # Resolve the correct localized strings for this specific language iteration
    actual_seq_name = meta.localized_sequence_names.get(lang) or meta.sequence_name
    actual_desc     = meta.localized_descriptions.get(lang)   or meta.description

    from reportlab.lib.pagesizes import A4
    from reportlab.lib import colors
    from reportlab.lib.units import mm
    from reportlab.platypus import (
        BaseDocTemplate, Frame, PageTemplate, Paragraph, Spacer,
        Image as RLImage, PageBreak
    )
    from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
    from reportlab.lib.enums import TA_CENTER, TA_LEFT

    PAGE_W, PAGE_H = A4
    MARGIN = 14 * mm
    BODY_W = PAGE_W - 2 * MARGIN
    FRAME_H = PAGE_H - 2 * MARGIN - 6 * mm

    # ── Font selection ────────────────────────────────────────────────────────
    use_custom = _register_fonts()

    f_title    = "Cinzel"          if use_custom else "Helvetica-Bold"
    f_body     = "Lora"            if use_custom else "Times-Roman"
    f_italic   = "Lora-Italic"     if use_custom else "Times-Italic"
    f_bold     = "Lora-Bold"       if use_custom else "Times-Bold"

    # ── Styles ────────────────────────────────────────────────────────────────
    style_title = ParagraphStyle(
        "MagTitle",
        fontName=f_title, fontSize=29, leading=35,
        alignment=TA_CENTER,
        textColor=colors.HexColor("#f59e0b"),
        spaceAfter=6,
        tracking=120,
    )
    style_subtitle = ParagraphStyle(
        "MagSubtitle",
        fontName=f_title, fontSize=16, leading=21,
        alignment=TA_CENTER,
        textColor=colors.HexColor("#b0b0be"),
        spaceAfter=4,
        tracking=60,
    )
    style_lang_badge = ParagraphStyle(
        "LangBadge",
        fontName=f_title, fontSize=11, leading=15,
        alignment=TA_CENTER,
        textColor=colors.HexColor("#f59e0b"),
        spaceAfter=16,
        tracking=180,
    )
    style_desc = ParagraphStyle(
        "MagDesc",
        fontName=f_italic, fontSize=14, leading=20,
        alignment=TA_LEFT,
        textColor=colors.HexColor("#c8b88a"),
        spaceAfter=8,
    )
    style_overlay = ParagraphStyle(
        "OverlayText",
        fontName=f_body, fontSize=13, leading=18,
        alignment=TA_LEFT,
        textColor=colors.HexColor("#a8a8ac"),
        spaceBefore=4, spaceAfter=2,
        leftIndent=20, rightIndent=20,
    )

    # ── Document ──────────────────────────────────────────────────────────────
    def dark_page(canvas_obj, doc):
        canvas_obj.saveState()
        canvas_obj.setFillColor(colors.HexColor("#0d0d11"))
        canvas_obj.rect(0, 0, PAGE_W, PAGE_H, fill=1, stroke=0)
        canvas_obj.setStrokeColor(colors.HexColor("#f59e0b"))
        canvas_obj.setLineWidth(0.5)
        canvas_obj.line(MARGIN, 8 * mm, PAGE_W - MARGIN, 8 * mm)
        canvas_obj.setFont(f_title, 10)
        canvas_obj.setFillColor(colors.HexColor("#6b6b80"))
        canvas_obj.drawCentredString(PAGE_W / 2, 5 * mm, str(doc.page))
        canvas_obj.restoreState()

    frame_main = Frame(
        MARGIN, MARGIN + 6 * mm,
        BODY_W, FRAME_H,
        id="main",
        leftPadding=0, bottomPadding=0, rightPadding=0, topPadding=0
    )
    page_tpl = PageTemplate(id="main", frames=[frame_main], onPage=dark_page)

    doc = BaseDocTemplate(
        str(out_path), pagesize=A4, pageTemplates=[page_tpl],
        title=f"{meta.series_title} — {actual_seq_name}",
        author="SAGE AI", subject=lang.upper(),
        leftMargin=MARGIN, rightMargin=MARGIN,
        topMargin=MARGIN, bottomMargin=MARGIN + 6 * mm,
    )

    story = []

    # ── Cover / Intro page ────────────────────────────────────────────────────
    cover_img_path = None
    if meta.cover_frame_id is not None:
        cover_img_path = _image_path(job_id, meta.cover_frame_id)

    is_upright_cover = False

    if cover_img_path:
        try:
            img = RLImage(str(cover_img_path))
            is_upright_cover = img.imageHeight > img.imageWidth

            cover_pad  = 6
            cover_max_w = BODY_W - (2 * cover_pad)
            cover_max_h = FRAME_H - (2 * cover_pad)

            if is_upright_cover:
                max_h = cover_max_h
            else:
                max_h = PAGE_H * 0.42

            scale = min(cover_max_w / img.imageWidth, max_h / img.imageHeight)
            img.drawWidth  = img.imageWidth  * scale
            img.drawHeight = img.imageHeight * scale
            
            img.hAlign = 'CENTER'
            story.append(img)

            if not is_upright_cover:
                story.append(Spacer(1, 8 * mm))

        except Exception as e:
            logger.warning("Cover image load failed for job %s: %s", job_id, e)

    if not is_upright_cover:
        story.append(Paragraph(meta.series_title, style_title))
        story.append(Paragraph(actual_seq_name,   style_subtitle))
        story.append(Paragraph(lang.upper(),       style_lang_badge))

        if actual_desc:
            story.append(Spacer(1, 4 * mm))
            for line in actual_desc.strip().split("\n"):
                if line.strip():
                    story.append(Paragraph(line.strip(), style_desc))

    story.append(PageBreak())

    # ── Frame pages ───────────────────────────────────────────────────────────
    for idx, frame in enumerate(meta.frames):
        img_path = _image_path(job_id, frame.frame_id)
        
        is_upright = False
        if img_path:
            try:
                ir = ImageReader(str(img_path))
                img_w, img_h = ir.getSize()
                is_upright = img_h > img_w
            except Exception:
                pass

        if meta.pdf_disable_texts:
            texts = []
        else:
            texts = frame.overlay_texts.get(lang) or frame.overlay_texts.get("en") or []
            texts = [line for line in texts if line.strip()]

        if img_path:
            try:
                if meta.pdf_disable_fuki:
                    fuki_list = []
                else:
                    fuki_list = frame.fuki_texts.get(lang) or frame.fuki_texts.get("en") or []
                
                if is_upright and meta.pdf_full_upright:
                    fp_max_h = FRAME_H - (1 * mm) # Fill the frame exactly
                else:
                    fp_max_h = PAGE_H * 0.55
                
                img_flowable = FrameImageWithFuki(
                    img_path=str(img_path), 
                    fuki_list=fuki_list, 
                    body_w=BODY_W - 0.1, 
                    default_max_h=PAGE_H * 0.55,
                    full_page_max_h=fp_max_h,
                    pdf_full_upright=meta.pdf_full_upright
                )
                story.append(img_flowable)
            except Exception as e:
                logger.warning("Frame image load failed frame_id=%d: %s", frame.frame_id, e)
        
        if texts:
            if is_upright and meta.pdf_full_upright:
                # Push text to the next page to leave the image alone on its page
                story.append(PageBreak())
            else:
                # Keep text on the same page, under the square/landscape image
                story.append(Spacer(1, 10))
            
            for line in texts:
                story.append(Paragraph(line.strip(), style_overlay))

        # Always ensure the NEXT frame (image + text) starts fresh at the top of a new page.
        if idx < len(meta.frames) - 1:
            story.append(PageBreak())

    doc.build(story)


def _build_job(job_id: str, meta: MagazineJobMeta) -> None:
    """
    Background thread: builds one PDF per language, zips them, cleans up images.
    Updates _jobs[job_id] throughout.
    """
    job_data = _get_job(job_id)
    if not job_data:
        return

    job_data["status"] = "processing"
    _save_job(job_id, job_data)

    job_dir  = _job_dir(job_id)
    zip_path = job_dir / f"magazine_{meta.series_id}_seq{meta.sequence_id}.zip"

    try:
        pdf_paths = []
        for lang in meta.languages:
            pdf_name = f"magazine_seq{meta.sequence_id}_{lang}.pdf"
            pdf_path = job_dir / pdf_name
            logger.info("Building PDF job=%s lang=%s → %s", job_id, lang, pdf_path)
            _build_pdf_for_language(meta, lang, job_id, pdf_path)
            pdf_paths.append((pdf_name, pdf_path))

        with zipfile.ZipFile(zip_path, "w", zipfile.ZIP_DEFLATED) as zf:
            for arc_name, pdf_path in pdf_paths:
                zf.write(str(pdf_path), arc_name)

        for _, p in pdf_paths:
            try:
                p.unlink()
            except Exception:
                pass

        job_data["status"]     = "done"
        job_data["result_zip"] = str(zip_path)
        _save_job(job_id, job_data)
        logger.info("PDF job %s done → %s", job_id, zip_path)

    except Exception as exc:
        logger.exception("PDF job %s failed: %s", job_id, exc)
        job_data["status"]        = "error"
        job_data["error_message"] = str(exc)
        _save_job(job_id, job_data)


# ---------------------------------------------------------------------------
# Endpoints
# ---------------------------------------------------------------------------

@router.post("/submit")
async def submit_pdf_job(request: Request):
    form = await request.form()
    job_meta_str = form.get("job_meta")
    images = form.getlist("images")

    if not job_meta_str:
        raise HTTPException(status_code=400, detail="Missing job_meta")

    try:
        if hasattr(job_meta_str, "file"):
            meta_raw = await job_meta_str.read()
            meta_dict = json.loads(meta_raw)
        else:
            meta_dict = json.loads(job_meta_str)

        meta = MagazineJobMeta(**meta_dict)
    except Exception as e:
        raise HTTPException(status_code=400, detail=f"Invalid job_meta: {e}")

    if not meta.frames:
        raise HTTPException(status_code=400, detail="job_meta.frames must not be empty")
    if not meta.languages:
        raise HTTPException(status_code=400, detail="job_meta.languages must not be empty")

    job_id  = str(uuid.uuid4())
    job_dir = _job_dir(job_id)

    saved = 0
    for upload in images:
        if not hasattr(upload, "filename"):
            continue
        fname  = upload.filename or ""
        stem   = Path(fname).stem
        suffix = Path(fname).suffix or ".jpg"
        parts  = stem.split("_")
        if len(parts) >= 2 and parts[-1].isdigit():
            frame_id = int(parts[-1])
        else:
            try:
                frame_id = int("".join(filter(str.isdigit, stem)))
            except ValueError:
                logger.warning("Cannot parse frame_id from filename '%s', skipping", fname)
                continue

        dest    = job_dir / f"img_{frame_id}{suffix}"
        content = await upload.read()
        dest.write_bytes(content)
        saved += 1

    logger.info("Job %s created, %d images saved, languages=%s", job_id, saved, meta.languages)

    job_data = {
        "status":        "pending",
        "series_id":     meta.series_id,
        "sequence_id":   meta.sequence_id,
        "languages":     meta.languages,
        "result_zip":    None,
        "error_message": None,
    }
    _save_job(job_id, job_data)

    t = threading.Thread(target=_build_job, args=(job_id, meta), daemon=True)
    t.start()

    return JSONResponse({"job_id": job_id, "status": "pending", "images_received": saved})


@router.get("/status/{job_id}")
async def poll_pdf_job(job_id: str):
    job = _get_job(job_id)
    if not job:
        raise HTTPException(status_code=404, detail="Job not found")

    return JSONResponse({
        "job_id":        job_id,
        "status":        job["status"],
        "series_id":     job.get("series_id"),
        "sequence_id":   job.get("sequence_id"),
        "languages":     job.get("languages"),
        "error_message": job.get("error_message"),
    })


@router.get("/download/{job_id}")
async def download_pdf_job(job_id: str):
    job = _get_job(job_id)
    if not job:
        raise HTTPException(status_code=404, detail="Job not found")
    if job["status"] != "done":
        raise HTTPException(status_code=409, detail=f"Job is not done yet (status: {job['status']})")

    zip_path = job.get("result_zip")
    if not zip_path or not Path(zip_path).exists():
        raise HTTPException(status_code=410, detail="Result file no longer available")

    seq_id = job.get("sequence_id", "unknown")
    return FileResponse(
        path=zip_path,
        media_type="application/zip",
        filename=f"magazine_seq{seq_id}_pdfs.zip",
    )


@router.delete("/cleanup/{job_id}")
async def cleanup_pdf_job(job_id: str):
    _jobs.pop(job_id, None)
    try:
        shutil.rmtree(str(_JOBS_DIR / job_id), ignore_errors=True)
    except Exception:
        pass
    return JSONResponse({"deleted": job_id})


@router.get("/_health")
async def health():
    fonts_ready = "Cinzel" in __import__("reportlab.pdfbase.pdfmetrics", fromlist=["pdfmetrics"]).getRegisteredFontNames()
    return {
        "status":      "ok",
        "service":     "magazine_pdf_service",
        "active_jobs": len(_jobs),
        "custom_fonts": fonts_ready,
    }