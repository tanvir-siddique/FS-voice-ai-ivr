"""
Transcribe API endpoint (Speech-to-Text).

⚠️ MULTI-TENANT: domain_uuid é OBRIGATÓRIO em todas as requisições.
"""

import logging

from fastapi import APIRouter, HTTPException, status

from models.request import TranscribeRequest
from models.response import TranscribeResponse
from services.provider_manager import provider_manager

router = APIRouter()
logger = logging.getLogger(__name__)


@router.post("/transcribe", response_model=TranscribeResponse)
async def transcribe_audio(request: TranscribeRequest) -> TranscribeResponse:
    """
    Transcribe audio file to text.
    
    Args:
        request: TranscribeRequest with domain_uuid, audio_file, language
        
    Returns:
        TranscribeResponse with transcribed text
        
    Raises:
        HTTPException: If transcription fails
    """
    # MULTI-TENANT: Validar domain_uuid
    if not request.domain_uuid:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="domain_uuid is required for multi-tenant isolation",
        )
    
    try:
        # Get provider from ProviderManager (loads from DB with fallback)
        provider = await provider_manager.get_stt_provider(
            domain_uuid=request.domain_uuid,
            provider_name=request.provider,
        )
        
        # Transcribe
        result = await provider.transcribe(
            audio_file=request.audio_file,
            language=request.language,
        )
        
        logger.info(
            f"Transcribed {result.duration_ms}ms audio for domain {request.domain_uuid} "
            f"using {provider.provider_name}"
        )
        
        return TranscribeResponse(
            text=result.text,
            language=result.language,
            confidence=result.confidence,
            duration_ms=result.duration_ms,
            provider=provider.provider_name,
        )
        
    except FileNotFoundError as e:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail=str(e))
    except ValueError as e:
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail=str(e))
    except Exception as e:
        logger.exception(f"Transcription failed: {e}")
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Transcription failed: {str(e)}",
        )
