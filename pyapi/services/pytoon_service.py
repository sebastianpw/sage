# pyapi/services/pytoon_service.py
"""
SAGE Pytoon Service
Handles webtoon packaging: PDF → per-page JPGs, cover canvas compositor, ZIP output.
"""

import os
import io
import json
import uuid
import shutil
import logging
from pathlib import Path
from typing import List, Optional

from fastapi import APIRouter, UploadFile, File, Form, HTTPException, BackgroundTasks
from fastapi.responses import StreamingResponse, JSONResponse
from PIL import Image, ImageOps, ImageSequence

router = APIRouter(prefix="/pytoon", tags=["pytoon"])
logger = logging.getLogger(__name__)

# ── Storage ───────────────────────────────────────────────────────────────────
BASE_DIR = Path(__file__).resolve().parents[2]
JOBS_DIR = BASE_DIR / "services_data" / "pytoon_jobs"
JOBS_DIR.mkdir(parents=True, exist_ok=True)

# In-memory job registry (cleared on restart — acceptable for ephemeral jobs)
_jobs: dict = {}

# ── Helpers ───────────────────────────────────────────────────────────────────

def _job_dir(job_id: str) -> Path:
    d = JOBS_DIR / job_id
    d.mkdir(parents=True, exist_ok=True)
    return d


def _open_image(upload: UploadFile) -> Image.Image:
    upload.file.seek(0)
    img = Image.open(upload.file)
    img = ImageOps.exif_transpose(img)
    img.load()
    return img


def _save_jpg(img: Image.Image, path: Path, quality: int = 85) -> None:
    if img.mode in ("RGBA", "LA", "P"):
        bg = Image.new("RGB", img.size, (255, 255, 255))
        bg.paste(img, mask=img.split()[-1] if img.mode in ("RGBA", "LA") else None)
        bg.save(str(path), "JPEG", quality=quality, optimize=True)
    else:
        img.convert("RGB").save(str(path), "JPEG", quality=quality, optimize=True)


# ── Cover Canvas ──────────────────────────────────────────────────────────────

@router.post("/cover/compose")
async def compose_cover(
    file: UploadFile = File(...),
    x: float = Form(0.0),
    y: float = Form(0.0),
    scale: float = Form(1.0),
    canvas_w: int = Form(1080),
    canvas_h: int = Form(1920),
    quality: int = Form(85),
):
    """
    Place an uploaded image onto a 1080×1920 (16:9 portrait) black canvas
    with pixel-perfect positioning from the JS canvas editor.

    x, y   — top-left offset in canvas pixels (may be negative for crop/bleed)
    scale  — uniform scale factor applied to the source image
    """
    try:
        src = _open_image(file)
        sw = int(src.width * scale)
        sh = int(src.height * scale)
        if sw > 0 and sh > 0:
            src = src.resize((sw, sh), Image.Resampling.LANCZOS)

        canvas = Image.new("RGB", (canvas_w, canvas_h), (0, 0, 0))

        px, py = int(round(x)), int(round(y))

        # Compute source/dest crop regions so we only paste what's visible
        src_x1 = max(0, -px)
        src_y1 = max(0, -py)
        src_x2 = min(sw, canvas_w - px)
        src_y2 = min(sh, canvas_h - py)
        dst_x1 = max(0, px)
        dst_y1 = max(0, py)

        if src_x2 > src_x1 and src_y2 > src_y1:
            region = src.crop((src_x1, src_y1, src_x2, src_y2)).convert("RGB")
            canvas.paste(region, (dst_x1, dst_y1))

        buf = io.BytesIO()
        canvas.save(buf, "JPEG", quality=quality, optimize=True)
        buf.seek(0)
        return StreamingResponse(
            buf,
            media_type="image/jpeg",
            headers={"Content-Disposition": 'attachment; filename="cover_1080x1920.jpg"'},
        )
    except Exception as e:
        logger.exception("compose_cover failed")
        raise HTTPException(status_code=500, detail=str(e))


# ── PDF → JPG pages ───────────────────────────────────────────────────────────

@router.post("/pdf/split")
async def split_pdf(
    background_tasks: BackgroundTasks,
    file: UploadFile = File(...),
    dpi: int = Form(150),
    quality: int = Form(85),
):
    """
    Accept a PDF upload, rasterise every page to JPEG at `dpi`, zip them,
    and return a job_id for async polling.

    Uses pdf2image (poppler).  Falls back gracefully with an error status if
    poppler is not available so the rest of the service keeps running.
    """
    job_id = str(uuid.uuid4())
    _jobs[job_id] = {"status": "processing", "page_count": 0, "error": None}
    raw = await file.read()

    background_tasks.add_task(_do_split_pdf, job_id, raw, dpi, quality)
    return {"job_id": job_id, "status": "processing"}


def _do_split_pdf(job_id: str, pdf_bytes: bytes, dpi: int, quality: int):
    jdir = _job_dir(job_id)
    pages = []
    
    try:
        try:
            from pdf2image import convert_from_bytes  # type: ignore
            pages = convert_from_bytes(pdf_bytes, dpi=dpi)
        except ImportError:
            # Fallback to pure Pillow for Termux or systems without poppler/pdf2image
            pdf_stream = io.BytesIO(pdf_bytes)
            img = Image.open(pdf_stream)
            for page in ImageSequence.Iterator(img):
                pages.append(page.convert("RGB"))
                
        if not pages:
            raise ValueError("No pages could be extracted from PDF.")

        for i, page in enumerate(pages):
            out_path = jdir / f"page_{i + 1:04d}.jpg"
            _save_jpg(page, out_path, quality=quality)

        # Build ZIP in memory
        import zipfile

        zip_path = JOBS_DIR / f"{job_id}.zip"
        with zipfile.ZipFile(str(zip_path), "w", zipfile.ZIP_DEFLATED) as zf:
            for jpg in sorted(jdir.glob("*.jpg")):
                zf.write(str(jpg), jpg.name)

        _jobs[job_id] = {
            "status": "done",
            "page_count": len(pages),
            "zip_path": str(zip_path),
            "error": None,
        }
    except Exception as e:
        logger.exception("_do_split_pdf failed for job %s", job_id)
        _jobs[job_id] = {"status": "error", "error": f"Extraction failed: {str(e)}", "page_count": 0}
    finally:
        # Clean per-page temp dir (keep zip)
        try:
            shutil.rmtree(str(jdir), ignore_errors=True)
        except Exception:
            pass


@router.get("/pdf/status/{job_id}")
async def pdf_status(job_id: str):
    info = _jobs.get(job_id)
    if not info:
        raise HTTPException(status_code=404, detail="Job not found")
    return {
        "job_id": job_id,
        "status": info["status"],
        "page_count": info.get("page_count", 0),
        "error": info.get("error"),
    }


@router.get("/pdf/download/{job_id}")
async def pdf_download(job_id: str):
    info = _jobs.get(job_id)
    if not info:
        raise HTTPException(status_code=404, detail="Job not found")
    if info["status"] != "done":
        raise HTTPException(status_code=409, detail=f"Job status: {info['status']}")
    zip_path = Path(info["zip_path"])
    if not zip_path.exists():
        raise HTTPException(status_code=410, detail="ZIP already cleaned up")

    def iterfile():
        with open(str(zip_path), "rb") as f:
            while chunk := f.read(65536):
                yield chunk

    return StreamingResponse(
        iterfile(),
        media_type="application/zip",
        headers={"Content-Disposition": f'attachment; filename="webtoon_pages_{job_id[:8]}.zip"'},
    )


@router.delete("/pdf/cleanup/{job_id}")
async def pdf_cleanup(job_id: str):
    info = _jobs.pop(job_id, None)
    if info and info.get("zip_path"):
        try:
            Path(info["zip_path"]).unlink(missing_ok=True)
        except Exception:
            pass
    return {"deleted": True}


# ── Cover image → single JPG download (immediate, no async) ──────────────────

@router.post("/cover/preview")
async def cover_preview(
    file: UploadFile = File(...),
    x: float = Form(0.0),
    y: float = Form(0.0),
    scale: float = Form(1.0),
    canvas_w: int = Form(1080),
    canvas_h: int = Form(1920),
):
    """Thin wrapper — identical to /cover/compose but always returns quality=85 for fast preview."""
    return await compose_cover(
        file=file, x=x, y=y, scale=scale,
        canvas_w=canvas_w, canvas_h=canvas_h, quality=85,
    )


@router.get("/_health")
async def health():
    return {"status": "ok", "service": "pytoon_service"}
