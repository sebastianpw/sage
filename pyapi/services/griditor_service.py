# pyapi/services/griditor_service.py
import json
import uuid
from pathlib import Path
from fastapi import APIRouter, UploadFile, File, Form, HTTPException
from pydantic import BaseModel
from typing import List, Optional
from PIL import Image, ImageOps, UnidentifiedImageError

router = APIRouter(tags=["griditor"])

BASE_DIR = Path(__file__).resolve().parents[2]
# Save to a dedicated temp directory that PHP can access
TEMP_DIR = BASE_DIR / "public" / "temp" / "griditor"
TEMP_DIR.mkdir(parents=True, exist_ok=True)

def open_image_from_upload(upload: UploadFile) -> Image.Image:
    try:
        upload.file.seek(0)
        img = Image.open(upload.file)
        img = ImageOps.exif_transpose(img)
        img.convert("RGBA") # Ensure consistent color space
        return img
    except UnidentifiedImageError as e:
        raise HTTPException(status_code=400, detail=f"Invalid image: {e}")
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Could not open image: {e}")

@router.post("/griditor/cut")
async def cut_image(
    file: UploadFile = File(...),
    h_guides: str = Form('[]'),
    v_guides: str = Form('[]')
):
    """
    Slices an image into a grid based on horizontal and vertical guide coordinates.
    """
    try:
        img = open_image_from_upload(file)
        width, height = img.size

        # Parse guides (expecting integer pixel coordinates)
        try:
            hg = json.loads(h_guides)
            vg = json.loads(v_guides)
        except Exception:
            raise HTTPException(status_code=400, detail="Invalid JSON for guides")

        # Filter out bounds and clamp to image size
        hg = [int(y) for y in hg if 0 < int(y) < height]
        vg = [int(x) for x in vg if 0 < int(x) < width]

        # Add image boundaries to create complete bounding boxes
        hg.extend([0, height])
        vg.extend([0, width])

        # Sort and deduplicate
        hg = sorted(list(set(hg)))
        vg = sorted(list(set(vg)))

        crops = []
        
        # Iterate over bounding box coordinates
        # Top-to-bottom (Y), then Left-to-right (X)
        for i in range(len(hg) - 1):
            y1, y2 = hg[i], hg[i + 1]
            for j in range(len(vg) - 1):
                x1, x2 = vg[j], vg[j + 1]
                
                # Safe crop
                crop_img = img.crop((x1, y1, x2, y2))
                
                # Generate unique temp filename
                filename = f"gridcut_{uuid.uuid4().hex[:12]}.png"
                out_path = TEMP_DIR / filename
                
                # Save as PNG to preserve quality and alpha
                crop_img.save(out_path, "PNG")
                
                # Return the relative path for PHP to locate
                crops.append(f"temp/griditor/{filename}")

        return {
            "success": True,
            "original_size": {"width": width, "height": height},
            "slices": len(crops),
            "crops": crops
        }

    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))