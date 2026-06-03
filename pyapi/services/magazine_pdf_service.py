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
"""

import io
import json
import logging
import os
import shutil
import tempfile
import threading
import uuid
import zipfile
from pathlib import Path
from typing import Dict, List, Optional

from fastapi import APIRouter, File, Form, HTTPException, UploadFile, Request
from fastapi.responses import FileResponse, JSONResponse
from pydantic import BaseModel

logger = logging.getLogger(__name__)

router = APIRouter()

# ---------------------------------------------------------------------------
# Job store — file-backed dictionary to survive Uvicorn reloads in Codespaces
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
# PDF builder
# ---------------------------------------------------------------------------

def _build_pdf_for_language(
    meta: MagazineJobMeta,
    lang: str,
    job_id: str,
    out_path: Path,
) -> None:
    
    # Resolve the correct localized strings for this specific language iteration
    actual_seq_name = meta.localized_sequence_names.get(lang) or meta.sequence_name
    actual_desc = meta.localized_descriptions.get(lang) or meta.description

    from reportlab.lib.pagesizes import A4
    from reportlab.lib import colors
    from reportlab.lib.units import mm
    from reportlab.platypus import (
        BaseDocTemplate, Frame, PageTemplate, Paragraph, Spacer, 
        Image as RLImage, PageBreak, KeepTogether
    )
    from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
    from reportlab.lib.enums import TA_CENTER, TA_LEFT

    PAGE_W, PAGE_H = A4
    MARGIN = 14 * mm
    BODY_W = PAGE_W - 2 * MARGIN

    # ── Styles ──────────────────────────────────────────────────────────────
    styles = getSampleStyleSheet()

    style_title = ParagraphStyle("MagTitle", fontName="Helvetica-Bold", fontSize=26, leading=32, alignment=TA_CENTER, textColor=colors.HexColor("#f59e0b"), spaceAfter=6)
    style_subtitle = ParagraphStyle("MagSubtitle", fontName="Helvetica", fontSize=14, leading=18, alignment=TA_CENTER, textColor=colors.HexColor("#b0b0be"), spaceAfter=4)
    style_lang_badge = ParagraphStyle("LangBadge", fontName="Helvetica-Bold", fontSize=9, leading=12, alignment=TA_CENTER, textColor=colors.HexColor("#f59e0b"), spaceAfter=16)
    style_desc = ParagraphStyle("MagDesc", fontName="Helvetica", fontSize=11, leading=16, alignment=TA_LEFT, textColor=colors.HexColor("#e0e0e8"), spaceAfter=8)
    style_overlay = ParagraphStyle("OverlayText", fontName="Helvetica", fontSize=10, leading=14, alignment=TA_LEFT, textColor=colors.HexColor("#f0f0f4"), spaceBefore=4, spaceAfter=2, leftIndent=24, rightIndent=24)
    style_frame_num = ParagraphStyle("FrameNum", fontName="Helvetica", fontSize=8, leading=10, alignment=TA_LEFT, textColor=colors.HexColor("#6b6b80"), spaceAfter=2)

    # ── Document ─────────────────────────────────────────────────────────────
    def dark_page(canvas_obj, doc):
        canvas_obj.saveState()
        canvas_obj.setFillColor(colors.HexColor("#0d0d11"))
        canvas_obj.rect(0, 0, PAGE_W, PAGE_H, fill=1, stroke=0)
        canvas_obj.setStrokeColor(colors.HexColor("#f59e0b"))
        canvas_obj.setLineWidth(0.5)
        canvas_obj.line(MARGIN, 8 * mm, PAGE_W - MARGIN, 8 * mm)
        canvas_obj.setFont("Helvetica", 7)
        canvas_obj.setFillColor(colors.HexColor("#6b6b80"))
        canvas_obj.drawCentredString(PAGE_W / 2, 5 * mm, str(doc.page))
        canvas_obj.restoreState()

    frame_main = Frame(MARGIN, MARGIN + 6 * mm, BODY_W, PAGE_H - 2 * MARGIN - 6 * mm, id="main")
    page_tpl = PageTemplate(id="main", frames=[frame_main], onPage=dark_page)

    doc = BaseDocTemplate(
        str(out_path), pagesize=A4, pageTemplates=[page_tpl],
        title=f"{meta.series_title} — {actual_seq_name}",
        author="SAGE AI", subject=lang.upper(),
        leftMargin=MARGIN, rightMargin=MARGIN, topMargin=MARGIN, bottomMargin=MARGIN + 6 * mm,
    )

    story = []

    # ── Cover / Intro page ───────────────────────────────────────────────────
    cover_img_path = None
    if meta.cover_frame_id is not None:
        cover_img_path = _image_path(job_id, meta.cover_frame_id)

    is_upright_cover = False

    if cover_img_path:
        try:
            img = RLImage(str(cover_img_path))

            # Detect portrait cover
            is_upright_cover = img.imageHeight > img.imageWidth

            # IMPORTANT:
            # ReportLab Frame has internal padding, so do NOT size the image
            # to the raw frame box. Subtract the default 6 pt padding on each side.
            cover_pad = 6  # points (ReportLab default frame padding)
            cover_max_w = BODY_W - (2 * cover_pad)
            cover_max_h = (PAGE_H - 2 * MARGIN - 6 * mm) - (2 * cover_pad)

            if is_upright_cover:
                # Portrait cover: fill as much of the usable page as possible,
                # but still stay within the true drawable area.
                max_h = cover_max_h
            else:
                # Landscape/square cover: keep room for title/intro text
                max_h = PAGE_H * 0.42

            scale = min(cover_max_w / img.imageWidth, max_h / img.imageHeight)
            img.drawWidth = img.imageWidth * scale
            img.drawHeight = img.imageHeight * scale

            story.append(img)

            # No extra spacer for upright covers
            if not is_upright_cover:
                story.append(Spacer(1, 8 * mm))

        except Exception as e:
            logger.warning("Cover image load failed for job %s: %s", job_id, e)
            
    # Only append the metadata overlay texts if the cover is not upright
    if not is_upright_cover:
        story.append(Paragraph(meta.series_title, style_title))
        story.append(Paragraph(actual_seq_name, style_subtitle))
        story.append(Paragraph(lang.upper(), style_lang_badge))

        if actual_desc:
            story.append(Spacer(1, 4 * mm))
            for line in actual_desc.strip().split("\n"):
                if line.strip():
                    story.append(Paragraph(line.strip(), style_desc))

    story.append(PageBreak())

    # ── Frame pages ──────────────────────────────────────────────────────────
    for idx, frame in enumerate(meta.frames):
        frame_elements = []

        # Frame number label
        frame_elements.append(
            Paragraph(f"#{idx + 1}  ·  frame {frame.frame_id}", style_frame_num)
        )

        # Image
        img_path = _image_path(job_id, frame.frame_id)
        if img_path:
            try:
                img = RLImage(str(img_path))
                scale = BODY_W / img.imageWidth
                img.drawWidth = BODY_W
                img.drawHeight = img.imageHeight * scale
                # Cap to 55% page height so text fits on the same page
                max_h = PAGE_H * 0.55
                if img.drawHeight > max_h:
                    scale2 = max_h / img.drawHeight
                    img.drawWidth *= scale2
                    img.drawHeight = max_h
                frame_elements.append(img)
            except Exception as e:
                logger.warning("Frame image load failed frame_id=%d: %s", frame.frame_id, e)
                frame_elements.append(
                    Paragraph(f"[Image unavailable: frame {frame.frame_id}]", style_frame_num)
                )
        else:
            frame_elements.append(
                Paragraph(f"[Image not provided: frame {frame.frame_id}]", style_frame_num)
            )

        # Overlay texts for this language (fall back to 'en')
        texts = frame.overlay_texts.get(lang) or frame.overlay_texts.get("en") or []
        for line in texts:
            if line.strip():
                frame_elements.append(Paragraph(line.strip(), style_overlay))

        # Amber separator line (via a tiny coloured spacer trick)
        frame_elements.append(Spacer(1, 2 * mm))

        story.append(KeepTogether(frame_elements))
        story.append(Spacer(1, 4 * mm))

    doc.build(story)


def _build_job(job_id: str, meta: MagazineJobMeta) -> None:
    """
    Background thread: builds one PDF per language, zips them, cleans up images.
    Updates _jobs[job_id] throughout.
    """
    job_data = _get_job(job_id)
    if not job_data: return

    job_data["status"] = "processing"
    _save_job(job_id, job_data)
    
    job_dir = _job_dir(job_id)
    zip_path = job_dir / f"magazine_{meta.series_id}_seq{meta.sequence_id}.zip"

    try:
        pdf_paths = []
        for lang in meta.languages:
            pdf_name = f"magazine_seq{meta.sequence_id}_{lang}.pdf"
            pdf_path = job_dir / pdf_name
            logger.info("Building PDF job=%s lang=%s → %s", job_id, lang, pdf_path)
            _build_pdf_for_language(meta, lang, job_id, pdf_path)
            pdf_paths.append((pdf_name, pdf_path))

        # Pack into ZIP
        with zipfile.ZipFile(zip_path, "w", zipfile.ZIP_DEFLATED) as zf:
            for arc_name, pdf_path in pdf_paths:
                zf.write(str(pdf_path), arc_name)

        # Remove individual PDFs (keep only ZIP)
        for _, p in pdf_paths:
            try:
                p.unlink()
            except Exception:
                pass

        job_data["status"] = "done"
        job_data["result_zip"] = str(zip_path)
        _save_job(job_id, job_data)
        logger.info("PDF job %s done → %s", job_id, zip_path)

    except Exception as exc:
        logger.exception("PDF job %s failed: %s", job_id, exc)
        job_data["status"] = "error"
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

    # Parse meta
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

    job_id = str(uuid.uuid4())
    job_dir = _job_dir(job_id)

    # Save uploaded images by frame_id
    saved = 0
    for upload in images:
        if not hasattr(upload, "filename"):
            continue
        fname = upload.filename or ""
        # Expect filename like "image_12345" — extract frame_id
        stem = Path(fname).stem  # e.g. "image_12345"
        suffix = Path(fname).suffix or ".jpg"
        parts = stem.split("_")
        if len(parts) >= 2 and parts[-1].isdigit():
            frame_id = int(parts[-1])
        else:
            # Try parsing digits directly
            try:
                frame_id = int("".join(filter(str.isdigit, stem)))
            except ValueError:
                logger.warning("Cannot parse frame_id from filename '%s', skipping", fname)
                continue

        dest = job_dir / f"img_{frame_id}{suffix}"
        content = await upload.read()
        dest.write_bytes(content)
        saved += 1

    logger.info("Job %s created, %d images saved, languages=%s", job_id, saved, meta.languages)

    # Register job safely to disk
    job_data = {
        "status": "pending",
        "series_id": meta.series_id,
        "sequence_id": meta.sequence_id,
        "languages": meta.languages,
        "result_zip": None,
        "error_message": None,
    }
    _save_job(job_id, job_data)

    # Launch background thread
    t = threading.Thread(target=_build_job, args=(job_id, meta), daemon=True)
    t.start()

    return JSONResponse({"job_id": job_id, "status": "pending", "images_received": saved})


@router.get("/status/{job_id}")
async def poll_pdf_job(job_id: str):
    job = _get_job(job_id)
    if not job:
        raise HTTPException(status_code=404, detail="Job not found")

    return JSONResponse({
        "job_id": job_id,
        "status": job["status"],
        "series_id": job.get("series_id"),
        "sequence_id": job.get("sequence_id"),
        "languages": job.get("languages"),
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
    job = _jobs.pop(job_id, None)
    try:
        shutil.rmtree(str(_JOBS_DIR / job_id), ignore_errors=True)
    except Exception:
        pass
    return JSONResponse({"deleted": job_id})


@router.get("/_health")
async def health():
    return {"status": "ok", "service": "magazine_pdf_service", "active_jobs": len(_jobs)}