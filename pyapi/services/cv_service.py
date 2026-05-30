"""
Computer Vision Service - Pollinations.ai Integration
"""
import os
import requests
import logging
from pathlib import Path
from typing import Optional
from fastapi import APIRouter, UploadFile, File, Form, HTTPException

# Initialize Router
router = APIRouter(tags=["computer-vision"])

# Logger
logger = logging.getLogger(__name__)

# Paths
# Assumes structure: PROJECT_ROOT / pyapi / services / cv_service.py
# Token dir is PROJECT_ROOT / token
SERVICE_DIR = Path(__file__).resolve().parent
PROJECT_ROOT = SERVICE_DIR.parent.parent # Goes up to root containing 'token' folder
TOKEN_DIR = PROJECT_ROOT / "token"

# --- Helper Functions ---

def get_token(filename: str) -> Optional[str]:
    """Reads a token file from the token directory."""
    token_path = TOKEN_DIR / filename
    if token_path.exists():
        try:
            return token_path.read_text().strip()
        except Exception as e:
            logger.error(f"Error reading token {filename}: {e}")
    return None

def upload_image_to_host(file_bytes: bytes, filename: str) -> str:
    """
    Uploads image to FreeImage.host and returns the direct URL.
    Logic mimics bash/genframe_db.sh
    """
    api_key = get_token(".freeimage_key")
    if not api_key:
        raise HTTPException(status_code=500, detail="FreeImage API key not found (.freeimage_key)")

    upload_url = "https://freeimage.host/api/1/upload"
    
    try:
        # Prepare multipart upload
        files = {
            'source': (filename, file_bytes, 'application/octet-stream')
        }
        data = {
            'key': api_key,
            'action': 'upload',
            'format': 'json'
        }
        
        # Upload
        response = requests.post(upload_url, data=data, files=files, timeout=30)
        response.raise_for_status()
        
        result = response.json()
        
        # Check success according to API
        if result.get('status_code') != 200:
            error_msg = result.get('error', {}).get('message', 'Unknown error')
            raise ValueError(f"FreeImage API Error: {error_msg}")
            
        image_url = result.get('image', {}).get('url')
        if not image_url:
            raise ValueError("No URL returned from FreeImage")
            
        return image_url

    except requests.RequestException as e:
        logger.error(f"FreeImage Upload Failed: {e}")
        raise HTTPException(status_code=502, detail=f"Failed to upload image to host: {str(e)}")
    except ValueError as e:
        logger.error(f"FreeImage Response Error: {e}")
        raise HTTPException(status_code=502, detail=str(e))

def analyze_with_pollinations(image_url: str, prompt: str, model: str = "claude-large") -> str:
    """
    Sends Image URL and Prompt to Pollinations AI.
    """
    # Pollinations Endpoint
    api_url = "https://gen.pollinations.ai/v1/chat/completions"
    
    # Get Token (optional for some models, but good practice if available)
    pollinations_token = get_token(".pollinationsaitoken")
    
    headers = {
        "Content-Type": "application/json"
    }
    if pollinations_token:
        headers["Authorization"] = f"Bearer {pollinations_token}"

    # OpenAI Compatible Vision Payload
    payload = {
        "model": model,
        "messages": [
            {
                "role": "user",
                "content": [
                    {
                        "type": "text", 
                        "text": prompt
                    },
                    {
                        "type": "image_url", 
                        "image_url": {
                            "url": image_url
                        }
                    }
                ]
            }
        ],
        "max_tokens": 1000  # Reasonable default for descriptions
    }

    try:
        response = requests.post(api_url, json=payload, headers=headers, timeout=60)
        
        # Pollinations might return text directly or JSON depending on endpoint nuances,
        # but the /chat/completions endpoint standard is JSON.
        response.raise_for_status()
        
        data = response.json()
        
        # Extract content
        try:
            content = data['choices'][0]['message']['content']
            return content
        except (KeyError, IndexError):
            logger.error(f"Unexpected Pollinations Response structure: {data}")
            raise ValueError("Invalid response structure from AI Provider")

    except requests.RequestException as e:
        logger.error(f"Pollinations API Failed: {e}")
        # Capture response text if available for debugging
        detail = str(e)
        if e.response is not None:
             detail += f" | Body: {e.response.text}"
        raise HTTPException(status_code=502, detail=f"AI Provider Error: {detail}")

# --- Endpoints ---

@router.post("/analyze")
async def analyze_image(
    file: UploadFile = File(...),
    prompt: str = Form("Describe this image in detail."),
    model: str = Form("claude-large")
):
    """
    Uploads an image, sends it to computer vision, and returns the description.
    
    1. Receives image bytes.
    2. Uploads to FreeImage.host to get a public URL.
    3. Sends URL + Prompt to Pollinations (claude-large).
    4. Returns text response.
    """
    if not file.filename:
        raise HTTPException(status_code=400, detail="Filename is missing")

    # 1. Read File Bytes
    try:
        file_bytes = await file.read()
    except Exception as e:
        raise HTTPException(status_code=400, detail=f"Failed to read file: {e}")

    # 2. Upload to Host
    try:
        logger.info(f"Uploading {file.filename} to FreeImage.host...")
        public_url = upload_image_to_host(file_bytes, file.filename)
        logger.info(f"Image uploaded successfully: {public_url}")
    except HTTPException as e:
        raise e
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Upload processing failed: {e}")

    # 3. Analyze
    try:
        logger.info(f"Analyzing with model {model}...")
        description = analyze_with_pollinations(public_url, prompt, model)
        
        return {
            "status": "success",
            "model": model,
            "image_url": public_url,
            "description": description
        }
    except HTTPException as e:
        raise e
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Analysis failed: {str(e)}")

@router.get("/health")
def health_check():
    return {
        "status": "active", 
        "service": "computer_vision", 
        "host_provider": "freeimage.host",
        "ai_provider": "pollinations.ai"
    }
