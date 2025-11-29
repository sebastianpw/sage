"""
OpenAI Compatible Service
Provides an OpenAI-compatible '/v1/chat/completions' and '/v1/models' endpoints
by wrapping the existing aiprovider_service.
"""
import logging
import time
import uuid
from typing import List, Optional, Dict, Literal, Any

import httpx
from fastapi import APIRouter, HTTPException, Request
from pydantic import BaseModel, Field

# Local models from aiprovider_service (for making the internal request)
from .aiprovider_service import AIMessageRequest, AIMessageModel

logger = logging.getLogger(__name__)

# Create router
router = APIRouter(tags=["openai_compatible"])

# =============================================================================
# PYDANTIC MODELS for OpenAI Compatibility
# =============================================================================

# --- Chat Completion Request Models ---

class OpenAIChatMessage(BaseModel):
    """A message in the chat completion request."""
    role: Literal["system", "user", "assistant"]
    content: str
    name: Optional[str] = None


class ChatCompletionRequest(BaseModel):
    """OpenAI Chat Completion Request Body"""
    model: str
    messages: List[OpenAIChatMessage]
    temperature: Optional[float] = Field(default=None, ge=0.0, le=2.0)
    max_tokens: Optional[int] = Field(default=None, ge=1)
    stream: bool = Field(default=False, description="Streaming is not supported and must be false.")
    n: Optional[int] = 1
    top_p: Optional[float] = None
    stop: Optional[List[str]] = None


# --- Chat Completion Response Models ---

class ResponseMessage(BaseModel):
    """The message object in the response."""
    role: Literal["assistant"] = "assistant"
    content: str


class Choice(BaseModel):
    """A single choice in the chat completion response."""
    index: int = 0
    message: ResponseMessage
    finish_reason: Literal["stop", "length"] = "stop"


class Usage(BaseModel):
    """Token usage statistics."""
    prompt_tokens: int
    completion_tokens: int
    total_tokens: int


class ChatCompletionResponse(BaseModel):
    """OpenAI Chat Completion Response Body"""
    id: str = Field(default_factory=lambda: f"chatcmpl-{uuid.uuid4()}")
    object: Literal["chat.completion"] = "chat.completion"
    created: int = Field(default_factory=lambda: int(time.time()))
    model: str
    choices: List[Choice]
    usage: Usage


# --- Models List Response Models ---

class ModelCard(BaseModel):
    """A single model card in the list models response."""
    id: str
    object: Literal["model"] = "model"
    created: int = Field(default_factory=lambda: int(time.time()))
    owned_by: str


class ModelList(BaseModel):
    """OpenAI List Models Response Body"""
    object: Literal["list"] = "list"
    data: List[ModelCard]


# =============================================================================
# HELPER FUNCTIONS
# =============================================================================

async def call_internal_aiprovider_post(
    base_url: str,
    payload: AIMessageRequest
) -> Dict:
    """Makes an async POST request to the internal /aiprovider/messages endpoint."""
    url = f"{base_url}/aiprovider/messages"
    try:
        async with httpx.AsyncClient(timeout=300.0) as client:
            logger.info(f"Calling internal endpoint: POST {url}")
            response = await client.post(url, json=payload.model_dump())
            response.raise_for_status()
            return response.json()
    except httpx.RequestError as e:
        logger.error(f"Internal request to {url} failed: {e}")
        raise HTTPException(status_code=503, detail=f"Service unavailable: Could not connect to internal AIProvider service. Reason: {e}")
    except httpx.HTTPStatusError as e:
        logger.error(f"Internal AIProvider service returned an error: {e.response.status_code} - {e.response.text}")
        error_detail = e.response.json().get("detail", e.response.text)
        raise HTTPException(status_code=502, detail=f"Internal AIProvider service failed: {error_detail}")


async def call_internal_aiprovider_get(base_url: str, path: str) -> Dict:
    """Makes a generic async GET request to an internal aiprovider endpoint."""
    url = f"{base_url}{path}"
    try:
        async with httpx.AsyncClient(timeout=300.0) as client:
            logger.info(f"Calling internal endpoint: GET {url}")
            response = await client.get(url)
            response.raise_for_status()
            return response.json()
    except httpx.RequestError as e:
        logger.error(f"Internal request to {url} failed: {e}")
        raise HTTPException(status_code=503, detail=f"Service unavailable: Could not connect to internal AIProvider service. Reason: {e}")
    except httpx.HTTPStatusError as e:
        logger.error(f"Internal AIProvider service returned an error: {e.response.status_code} - {e.response.text}")
        error_detail = e.response.json().get("detail", e.response.text)
        raise HTTPException(status_code=502, detail=f"Internal AIProvider service failed: {error_detail}")


# =============================================================================
# ENDPOINTS
# =============================================================================

@router.get(
    "/v1/models",
    response_model=ModelList,
    summary="OpenAI Compatible List Models"
)
async def list_models(http_request: Request):
    """
    Lists the currently available models.
    
    This endpoint is a wrapper around the `/aiprovider/models` service,
    providing compatibility with the OpenAI List Models API.
    """
    base_url = f"{http_request.scope['scheme']}://{http_request.scope['server'][0]}:{http_request.scope['server'][1]}"
    internal_response = await call_internal_aiprovider_get(base_url, "/aiprovider/models")
    
    models_data = internal_response.get("models", {})
    if not isinstance(models_data, dict):
        logger.error(f"Expected 'models' key to contain a dict, but got {type(models_data)}")
        raise HTTPException(status_code=500, detail="Internal AIProvider service returned an invalid model format.")
        
    model_cards: List[ModelCard] = []
    for provider, model_list in models_data.items():
        if isinstance(model_list, list):
            # ===== THIS IS THE CORRECTED SECTION =====
            for model_obj in model_list:
                # Check if the item is a dictionary and has an 'id' key
                if isinstance(model_obj, dict) and 'id' in model_obj:
                    # Correctly access the 'id' key from the dictionary
                    model_cards.append(
                        ModelCard(id=model_obj['id'], owned_by=provider)
                    )
                else:
                    # Log a warning if the format is unexpected to avoid crashing
                    logger.warning(f"Skipping unexpected model format in provider '{provider}': {model_obj}")
            # =======================================

    return ModelList(data=model_cards)


@router.post(
    "/v1/chat/completions",
    response_model=ChatCompletionResponse,
    summary="OpenAI Compatible Chat Completions"
)
async def chat_completions(req: ChatCompletionRequest, http_request: Request):
    """
    Generates a model response for the given conversation.
    
    This endpoint is a wrapper around the `/aiprovider/messages` service,
    providing compatibility with the OpenAI Chat Completions API.
    
    **Note:** Streaming is not supported.
    """
    if req.stream:
        raise HTTPException(status_code=400, detail="Streaming responses are not supported by this endpoint.")

    aiprovider_messages = [AIMessageModel(role=msg.role, content=msg.content) for msg in req.messages]
    aiprovider_payload = AIMessageRequest(
        model=req.model, messages=aiprovider_messages, temperature=req.temperature, max_tokens=req.max_tokens
    )

    base_url = f"{http_request.scope['scheme']}://{http_request.scope['server'][0]}:{http_request.scope['server'][1]}"
    internal_response = await call_internal_aiprovider_post(base_url, aiprovider_payload)
    
    ai_content = internal_response.get("response")
    if not ai_content:
        raise HTTPException(status_code=500, detail="Internal AIProvider service returned an empty response.")

    response_message = ResponseMessage(content=ai_content)
    choice = Choice(message=response_message)
    usage_stats = Usage(prompt_tokens=0, completion_tokens=0, total_tokens=0)
    
    openai_response = ChatCompletionResponse(
        model=req.model, choices=[choice], usage=usage_stats
    )
    
    return openai_response

@router.get("/v1/health", summary="Service Health Check")
def health_check():
    """Simple health check for the OpenAI compatible service wrapper."""
    return {
        "status": "healthy",
        "service": "openai_compatible_wrapper",
        "timestamp": time.time()
    }

