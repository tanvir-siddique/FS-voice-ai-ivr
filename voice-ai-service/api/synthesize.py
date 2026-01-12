"""
Synthesize API endpoint (Text-to-Speech).

⚠️ MULTI-TENANT: domain_uuid é OBRIGATÓRIO em todas as requisições.
"""

import logging

from fastapi import APIRouter, HTTPException, status

from models.request import SynthesizeRequest
from models.response import SynthesizeResponse
from services.provider_manager import provider_manager

router = APIRouter()
logger = logging.getLogger(__name__)


@router.post("/synthesize", response_model=SynthesizeResponse)
async def synthesize_text(request: SynthesizeRequest) -> SynthesizeResponse:
    """
    Synthesize text to speech.
    
    Args:
        request: SynthesizeRequest with domain_uuid, text, voice_id
        
    Returns:
        SynthesizeResponse with path to audio file
        
    Raises:
        HTTPException: If synthesis fails
    """
    # MULTI-TENANT: Validar domain_uuid
    if not request.domain_uuid:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="domain_uuid is required for multi-tenant isolation",
        )
    
    try:
        # Get provider from ProviderManager (loads from DB with fallback)
        provider = await provider_manager.get_tts_provider(
            domain_uuid=request.domain_uuid,
            provider_name=request.provider,
        )
        
        # Synthesize
        result = await provider.synthesize(
            text=request.text,
            voice_id=request.voice_id,
            speed=request.speed,
        )
        
        logger.info(
            f"Synthesized {len(request.text)} chars for domain {request.domain_uuid} "
            f"using {provider.provider_name}"
        )
        
        return SynthesizeResponse(
            audio_file=result.audio_file,
            duration_ms=result.duration_ms,
            format=result.format,
            provider=provider.provider_name,
        )
        
    except ValueError as e:
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail=str(e))
    except Exception as e:
        logger.exception(f"Synthesis failed: {e}")
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Synthesis failed: {str(e)}",
        )
