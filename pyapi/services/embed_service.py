# services/embed_service.py
"""
SAGE Embed Service (Low-RAM / Termux Edition)
---------------------------------------------
1. MiniLM (384 dim) -> Standard text tasks.
2. CLIP Vision (512 dim) -> Indexing images.
3. CLIP Text (512 dim) -> Searching images with text.

MEMORY STRATEGY:
- MiniLM: Always loaded (Small).
- CLIP (Text/Vision): Lazy load + Aggressive Unload.
"""

import logging
import io
import gc
from typing import List, Optional

from fastapi import APIRouter, HTTPException, UploadFile, File
from pydantic import BaseModel
from PIL import Image

logger = logging.getLogger(__name__)

router = APIRouter(tags=["embed"])

# ----------------------------
# Global Model Holders
# ----------------------------
_text_model = None       # MiniLM (384)
_image_model = None      # CLIP Vision (512)
_clip_text_model = None  # CLIP Text (512)

# ----------------------------
# Model Loading Logic
# ----------------------------
def get_text_model():
    """Standard Text (384 dim)"""
    global _text_model
    if _text_model is None:
        logger.info("Loading MiniLM (384)...")
        from fastembed import TextEmbedding
        _text_model = TextEmbedding(model_name="sentence-transformers/all-MiniLM-L6-v2")
    return _text_model

def get_image_model():
    """CLIP Vision (512 dim) - Heavy"""
    global _image_model
    if _image_model is None:
        logger.info("Loading CLIP Vision (512)...")
        from fastembed import ImageEmbedding
        _image_model = ImageEmbedding(model_name="Qdrant/clip-ViT-B-32-vision")
    return _image_model

def get_clip_text_model():
    """CLIP Text (512 dim) - Needed to search images"""
    global _clip_text_model
    if _clip_text_model is None:
        logger.info("Loading CLIP Text (512)...")
        from fastembed import TextEmbedding
        _clip_text_model = TextEmbedding(model_name="Qdrant/clip-ViT-B-32-text")
    return _clip_text_model

def unload_heavy_models():
    global _image_model, _clip_text_model
    freed = []
    if _image_model is not None:
        del _image_model
        _image_model = None
        freed.append("image")
    if _clip_text_model is not None:
        del _clip_text_model
        _clip_text_model = None
        freed.append("clip_text")
    
    if freed:
        gc.collect()
        logger.info(f"Unloaded models: {freed}")
    return freed

# ----------------------------
# Request Models
# ----------------------------
class EmbedRequest(BaseModel):
    text: str

# ----------------------------
# Endpoints
# ----------------------------

@router.post("/embed")
def embed_text(req: EmbedRequest):
    """Standard 384-dim text embedding (MiniLM)."""
    if not req.text.strip():
        raise HTTPException(status_code=400, detail="Empty text")
    try:
        model = get_text_model()
        vectors = list(model.embed([req.text.strip()]))
        return {
            "embedding": vectors[0].tolist(),
            "dimensions": len(vectors[0]),
            "model": "MiniLM-L6-v2"
        }
    except Exception as e:
        logger.error(f"Text embed failed: {e}")
        raise HTTPException(status_code=500, detail=str(e))

@router.post("/embed_image")
def embed_image(file: UploadFile = File(...)):
    """CLIP Vision 512-dim embedding."""
    try:
        contents = file.file.read()
        image = Image.open(io.BytesIO(contents))
        if image.mode != "RGB":
            image = image.convert("RGB")

        model = get_image_model()
        vectors = list(model.embed([image]))
        
        return {
            "embedding": vectors[0].tolist(),
            "dimensions": len(vectors[0]),
            "model": "CLIP-ViT-B-32-vision"
        }
    except Exception as e:
        logger.error(f"Image embed failed: {e}")
        unload_heavy_models()
        raise HTTPException(status_code=500, detail=f"Image failed: {e}")
    finally:
        file.file.close()

@router.post("/embed_search_query")
def embed_search_query(req: EmbedRequest):
    """
    CLIP Text 512-dim embedding.
    Use this when searching for IMAGES using TEXT.
    """
    if not req.text.strip():
        raise HTTPException(status_code=400, detail="Empty text")
    try:
        model = get_clip_text_model()
        vectors = list(model.embed([req.text.strip()]))
        return {
            "embedding": vectors[0].tolist(),
            "dimensions": len(vectors[0]),
            "model": "CLIP-ViT-B-32-text"
        }
    except Exception as e:
        logger.error(f"Search query embed failed: {e}")
        unload_heavy_models()
        raise HTTPException(status_code=500, detail=str(e))

@router.post("/embed/unload")
def unload_models():
    freed = unload_heavy_models()
    return {"ok": True, "unloaded": freed}
