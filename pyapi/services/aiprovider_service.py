"""
AIProvider Service - Python wrapper for AIProvider.php
Exposes AI provider functionality through FastAPI endpoints
"""
from fastapi import APIRouter, HTTPException
from pydantic import BaseModel, Field
from typing import Optional, Dict, Any, List
import subprocess
import logging
from pathlib import Path
import json
import shlex

logger = logging.getLogger(__name__)

# Create router
router = APIRouter(tags=["aiprovider"])

# Configuration: paths relative to pyapi/
PROJECT_ROOT = Path(__file__).resolve().parents[1]  # pyapi/
PHP_RUNNER = PROJECT_ROOT.parent / "bash" / "ai_query_runner.php"

# Ensure the PHP runner exists
if not PHP_RUNNER.exists():
    logger.error(f"PHP runner not found at {PHP_RUNNER}")

# =============================================================================
# PYDANTIC REQUEST MODELS
# =============================================================================

class AIMessageModel(BaseModel):
    """Single message in a conversation"""
    role: str = Field(..., description="Message role: 'system', 'user', or 'assistant'")
    content: str = Field(..., description="Message content")


class AIPromptRequest(BaseModel):
    """Simple prompt request"""
    model: str = Field(default="gemini-2.5-flash-lite", description="AI model to use")
    prompt: str = Field(..., description="User prompt text")
    system_prompt: Optional[str] = Field(None, description="Optional system prompt")
    temperature: Optional[float] = Field(None, ge=0.0, le=2.0, description="Temperature (0.0-2.0)")
    max_tokens: Optional[int] = Field(None, ge=1, description="Maximum tokens to generate")


class AIMessageRequest(BaseModel):
    """Multi-message conversation request"""
    model: str = Field(default="gemini-2.5-flash-lite", description="AI model to use")
    messages: List[AIMessageModel] = Field(..., description="Array of conversation messages")
    temperature: Optional[float] = Field(None, ge=0.0, le=2.0, description="Temperature (0.0-2.0)")
    max_tokens: Optional[int] = Field(None, ge=1, description="Maximum tokens to generate")


class AIStreamPromptRequest(BaseModel):
    """Streaming prompt request (for future SSE implementation)"""
    model: str = Field(default="gemini-2.5-flash-lite", description="AI model to use")
    prompt: str = Field(..., description="User prompt text")
    system_prompt: Optional[str] = Field(None, description="Optional system prompt")
    temperature: Optional[float] = Field(None, ge=0.0, le=2.0, description="Temperature (0.0-2.0)")
    max_tokens: Optional[int] = Field(None, ge=1, description="Maximum tokens to generate")


# =============================================================================
# HELPER FUNCTIONS
# =============================================================================

def _format_cmd_for_log(args: List[str]) -> str:
    """Return a safely-quoted string for logging."""
    return " ".join(shlex.quote(str(a)) for a in args)


def run_php_aiprovider(args: List[str], stdin_data: Optional[str] = None, timeout: int = 300) -> Dict[str, Any]:
    """
    Execute the PHP AIProvider runner with given arguments.
    
    Args:
        args: Command line arguments to pass to the PHP script
        stdin_data: Optional data to pipe to STDIN
        timeout: Timeout in seconds
        
    Returns:
        Dict with stdout, stderr, returncode, and command
    """
    try:
        # Build command: php <runner_script> <args>
        cmd = ["php", str(PHP_RUNNER)] + args
        
        display_cmd = _format_cmd_for_log(cmd)
        logger.info("Executing: %s", display_cmd)
        
        if stdin_data:
            logger.debug("STDIN data length: %d", len(stdin_data))
        
        result = subprocess.run(
            cmd,
            input=stdin_data,
            capture_output=True,
            text=True,
            timeout=timeout,
            check=False
        )
        
        stdout = (result.stdout or "").rstrip("\n")
        stderr = (result.stderr or "").rstrip("\n")
        
        if stdout:
            logger.debug("PHP stdout length: %d", len(stdout))
        if stderr:
            logger.info("PHP stderr: %s", stderr[:500])
        
        return {
            "stdout": stdout,
            "stderr": stderr,
            "returncode": result.returncode,
            "command": display_cmd
        }
        
    except subprocess.TimeoutExpired:
        logger.exception("Command timed out after %s seconds", timeout)
        return {
            "stdout": "",
            "stderr": f"Command timed out after {timeout} seconds",
            "returncode": -1,
            "command": _format_cmd_for_log(cmd)
        }
    except Exception as e:
        logger.exception("Error executing PHP AIProvider: %s", e)
        return {
            "stdout": "",
            "stderr": str(e),
            "returncode": -1,
            "command": _format_cmd_for_log(["php", str(PHP_RUNNER)] + args)
        }


def parse_php_response(result: Dict[str, Any]) -> str:
    """
    Parse PHP script response and extract AI content.
    
    Args:
        result: Result dict from run_php_aiprovider
        
    Returns:
        AI response content as string
        
    Raises:
        HTTPException: If the response indicates an error
    """
    if result["returncode"] != 0:
        error_msg = result["stderr"] or "Unknown error from PHP script"
        logger.error("PHP script error: %s", error_msg)
        raise HTTPException(
            status_code=500,
            detail={
                "error": error_msg,
                "command": result["command"],
                "stdout": result["stdout"][:1000] if result["stdout"] else None
            }
        )
    
    stdout = result["stdout"]
    if not stdout:
        raise HTTPException(
            status_code=500,
            detail={
                "error": "No response from AI provider",
                "command": result["command"],
                "stderr": result["stderr"]
            }
        )
    
    return stdout


# =============================================================================
# ENDPOINTS
# =============================================================================

@router.get("/models")
def list_models():
    """
    Get list of available AI models organized by provider.
    Returns the model catalog from AIProvider.php
    """
    try:
        result = run_php_aiprovider(["--list-models"])
        
        if result["returncode"] != 0:
            raise HTTPException(
                status_code=500,
                detail={
                    "error": result["stderr"],
                    "command": result["command"]
                }
            )
        
        # Parse JSON response from PHP
        try:
            models_data = json.loads(result["stdout"])
            return {
                "status": "success",
                "models": models_data,
                "command": result["command"]
            }
        except json.JSONDecodeError as e:
            logger.error("Failed to parse models JSON: %s", e)
            return {
                "status": "success",
                "models": result["stdout"],
                "command": result["command"],
                "note": "Raw response (JSON parse failed)"
            }
            
    except Exception as e:
        logger.exception("Error listing models: %s", e)
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/prompt")
def send_prompt(req: AIPromptRequest):
    """
    Send a simple prompt to an AI model.
    
    This is the basic endpoint for single-turn interactions.
    """
    try:
        args = ["--model", req.model]
        
        if req.system_prompt:
            args.extend(["--system", req.system_prompt])
        
        if req.temperature is not None:
            args.extend(["--temperature", str(req.temperature)])
        
        if req.max_tokens is not None:
            args.extend(["--max-tokens", str(req.max_tokens)])
        
        args.extend(["--user", req.prompt])
        
        result = run_php_aiprovider(args)
        response_text = parse_php_response(result)
        
        return {
            "status": "success",
            "model": req.model,
            "response": response_text,
            "command": result["command"]
        }
        
    except HTTPException:
        raise
    except Exception as e:
        logger.exception("Error sending prompt: %s", e)
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/messages")
def send_messages(req: AIMessageRequest):
    """
    Send a multi-turn conversation to an AI model.
    
    Supports full conversation history with system, user, and assistant messages.
    """
    try:
        args = ["--model", req.model]
        
        if req.temperature is not None:
            args.extend(["--temperature", str(req.temperature)])
        
        if req.max_tokens is not None:
            args.extend(["--max-tokens", str(req.max_tokens)])
        
        # Add messages flag to indicate we're passing JSON messages
        args.append("--messages")
        
        # Convert messages to JSON for STDIN
        messages_json = json.dumps([
            {"role": msg.role, "content": msg.content}
            for msg in req.messages
        ])
        
        result = run_php_aiprovider(args, stdin_data=messages_json)
        response_text = parse_php_response(result)
        
        return {
            "status": "success",
            "model": req.model,
            "response": response_text,
            "message_count": len(req.messages),
            "command": result["command"]
        }
        
    except HTTPException:
        raise
    except Exception as e:
        logger.exception("Error sending messages: %s", e)
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/pipe")
def pipe_with_context(req: AIPromptRequest):
    """
    Send a prompt with context piped from STDIN.
    
    This mimics the bash script behavior where you can pipe context:
    echo "context" | curl -X POST .../aiprovider/pipe -d '{"prompt":"analyze this"}'
    
    Note: In HTTP context, the "piped" content should be in the prompt itself.
    For true piping behavior, use the bash script wrapper.
    """
    try:
        args = ["--model", req.model]
        
        if req.system_prompt:
            args.extend(["--system", req.system_prompt])
        
        if req.temperature is not None:
            args.extend(["--temperature", str(req.temperature)])
        
        if req.max_tokens is not None:
            args.extend(["--max-tokens", str(req.max_tokens)])
        
        # The prompt is the instruction, stdin would be the context
        # In HTTP context, we combine them
        args.extend(["--user", req.prompt])
        
        result = run_php_aiprovider(args)
        response_text = parse_php_response(result)
        
        return {
            "status": "success",
            "model": req.model,
            "response": response_text,
            "command": result["command"],
            "note": "For true pipe behavior with context files, use the bash wrapper script"
        }
        
    except HTTPException:
        raise
    except Exception as e:
        logger.exception("Error in pipe endpoint: %s", e)
        raise HTTPException(status_code=500, detail=str(e))


@router.get("/status")
def aiprovider_status():
    """
    Check AIProvider service status and available providers.
    """
    try:
        # Check if PHP runner exists
        runner_exists = PHP_RUNNER.exists()
        
        # Try to get models list as a health check
        models_result = None
        if runner_exists:
            try:
                result = run_php_aiprovider(["--list-models"], timeout=10)
                if result["returncode"] == 0:
                    models_result = "available"
                else:
                    models_result = f"error: {result['stderr'][:100]}"
            except Exception as e:
                models_result = f"exception: {str(e)[:100]}"
        
        return {
            "status": "operational" if runner_exists and models_result == "available" else "degraded",
            "message": "AIProvider service is configured and accessible" if runner_exists else "PHP runner not found",
            "php_runner": str(PHP_RUNNER),
            "runner_exists": runner_exists,
            "models_check": models_result,
            "endpoints": {
                "models": "GET /models - List available AI models",
                "prompt": "POST /prompt - Send simple prompt",
                "messages": "POST /messages - Send conversation messages",
                "pipe": "POST /pipe - Send prompt with piped context",
                "status": "GET /status - Service health check"
            },
            "supported_providers": [
                "Pollinations (free & seed models)",
                "Groq API",
                "Mistral API",
                "Google Gemini",
                "Cohere API",
                "Qwen/InternLM (local via zrok)"
            ]
        }
        
    except Exception as e:
        logger.exception("Error checking status: %s", e)
        return {
            "status": "error",
            "message": str(e),
            "php_runner": str(PHP_RUNNER)
        }


@router.get("/health")
def health_check():
    """Simple health check endpoint"""
    return {
        "status": "healthy",
        "service": "aiprovider",
        "php_runner": str(PHP_RUNNER),
        "runner_exists": PHP_RUNNER.exists()
    }
