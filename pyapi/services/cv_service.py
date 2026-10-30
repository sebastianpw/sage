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
    Uploads image to Pollinations Media, falling back to FreeImage.host and Litterbox (catbox.moe).
    """
    error_msgs = []
    
    # 1. Try Pollinations Media first
    pollinations_token = get_token(".pollinationsaitoken")
    if pollinations_token:
        try:
            logger.info("Trying Pollinations Media upload...")
            upload_url = "https://media.pollinations.ai/upload"
            headers = {"Authorization": f"Bearer {pollinations_token}"}
            files = {'file': (filename, file_bytes, 'application/octet-stream')}
            
            response = requests.post(upload_url, headers=headers, files=files, timeout=30)
            response.raise_for_status()
            
            text = response.text.strip()
            if text.startswith("http"):
                logger.info(f"Pollinations upload successful: {text}")
                return text
            else:
                try:
                    result = response.json()
                    image_url = result.get('url')
                    if image_url and str(image_url).startswith("http"):
                        logger.info(f"Pollinations upload successful: {image_url}")
                        return image_url
                except Exception:
                    pass
            error_msgs.append("Pollinations response invalid or missing URL.")
        except Exception as e:
            logger.warning(f"Pollinations upload failed: {e}")
            error_msgs.append(f"Pollinations Request Error: {str(e)}")
    else:
        error_msgs.append("Pollinations API key not found for image upload.")

    # 2. Try FreeImage.host fallback
    api_key = get_token(".freeimage_key")
    if api_key:
        upload_url = "https://freeimage.host/api/1/upload"
        try:
            logger.info("Falling back to FreeImage...")
            files = {
                'source': (filename, file_bytes, 'application/octet-stream')
            }
            data = {
                'key': api_key,
                'action': 'upload',
                'format': 'json'
            }
            response = requests.post(upload_url, data=data, files=files, timeout=30)
            response.raise_for_status()
            result = response.json()
            
            if result.get('status_code') == 200:
                image_url = result.get('image', {}).get('url')
                if image_url:
                    logger.info(f"FreeImage upload successful: {image_url}")
                    return image_url
            error_msgs.append(f"FreeImage API Error: {result.get('error', {}).get('message', 'Unknown')}")
        except Exception as e:
            logger.warning(f"FreeImage upload failed: {e}")
            error_msgs.append(f"FreeImage Request Error: {str(e)}")
    else:
        error_msgs.append("FreeImage API key not found.")

    # 3. Fallback to Litterbox
    try:
        logger.info("Falling back to Litterbox...")
        upload_url = "https://litterbox.catbox.moe/resources/internals/api.php"
        files = {
            'fileToUpload': (filename, file_bytes, 'application/octet-stream')
        }
        data = {
            'reqtype': 'fileupload',
            'time': '1h'
        }
        response = requests.post(upload_url, data=data, files=files, timeout=30)
        response.raise_for_status()
        text = response.text.strip()
        
        # Success is just the URL string starting with http
        if text.startswith("http"):
            logger.info(f"Litterbox upload successful: {text}")
            return text
        
        error_msgs.append(f"Litterbox response invalid: {text}")
    except Exception as e:
        logger.error(f"Litterbox upload failed: {e}")
        error_msgs.append(f"Litterbox Request Error: {str(e)}")

    # If all fail, surface all reasons
    raise HTTPException(status_code=502, detail=f"All image upload methods failed. Errors: {'; '.join(error_msgs)}")

def analyze_with_pollinations(image_url: str, prompt: str, model: str = "qwen-vision") -> str:
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
        "max_tokens": 48000  # Reasonable default for descriptions
    }

    try:
        response = requests.post(api_url, json=payload, headers=headers, timeout=60)
        response.raise_for_status()
        
        data = response.json()
        
        try:
            content = data['choices'][0]['message']['content']
            return content
        except (KeyError, IndexError):
            logger.error(f"Unexpected Pollinations Response structure: {data}")
            raise ValueError("Invalid response structure from AI Provider")

    except requests.RequestException as e:
        logger.error(f"Pollinations API Failed: {e}")
        detail = str(e)
        if e.response is not None:
             detail += f" | Body: {e.response.text}"
        raise HTTPException(status_code=502, detail=f"AI Provider Error: {detail}")

# --- Endpoints ---

@router.post("/analyze")
async def analyze_image(
    file: UploadFile = File(...),
    prompt: str = Form("Describe this image in detail."),
    model: str = Form("qwen-vision")
):
    """
    Uploads an image, sends it to computer vision, and returns the description.
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
        logger.info(f"Uploading {file.filename} to external host...")
        public_url = upload_image_to_host(file_bytes, file.filename)
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
        "host_provider": "media.pollinations.ai, freeimage.host, litterbox.catbox.moe",
        "ai_provider": "pollinations.ai"
    }