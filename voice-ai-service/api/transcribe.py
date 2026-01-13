"""
Transcribe API endpoint (Speech-to-Text).

⚠️ MULTI-TENANT: domain_uuid é OBRIGATÓRIO em todas as requisições.
"""

import base64
import logging
import os
import tempfile
import uuid

from fastapi import APIRouter, HTTPException, status

from models.request import TranscribeRequest
from models.response import TranscribeResponse
from services.provider_manager import provider_manager

router = APIRouter()
logger = logging.getLogger(__name__)


@router.post("/transcribe", response_model=TranscribeResponse)
async def transcribe_audio(request: TranscribeRequest) -> TranscribeResponse:
    """
    Transcribe audio to text.
    
    Aceita:
    - audio_file: caminho para arquivo de áudio
    - audio_base64: conteúdo de áudio em base64 (alternativa)
    
    Args:
        request: TranscribeRequest with domain_uuid, audio_file/audio_base64, language
        
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
    
    # Validar que uma fonte de áudio foi fornecida
    if not request.audio_file and not request.audio_base64:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Either audio_file or audio_base64 must be provided",
        )
    
    audio_path = request.audio_file
    temp_file = None
    
    try:
        # Se recebemos base64, salvar em arquivo temporário
        if request.audio_base64:
            try:
                audio_bytes = base64.b64decode(request.audio_base64)
            except Exception as e:
                raise HTTPException(
                    status_code=status.HTTP_400_BAD_REQUEST,
                    detail=f"Invalid base64 audio data: {e}",
                )
            
            # Criar arquivo temporário
            ext = request.format or "wav"
            temp_file = tempfile.NamedTemporaryFile(
                suffix=f".{ext}",
                prefix="voice_ai_stt_",
                delete=False,
            )
            temp_file.write(audio_bytes)
            temp_file.close()
            audio_path = temp_file.name
            
            logger.debug(f"Saved base64 audio to temp file: {audio_path}")
        
        # Verificar se arquivo existe
        if not os.path.exists(audio_path):
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail=f"Audio file not found: {audio_path}",
            )
        
        # Get provider from ProviderManager (loads from DB with fallback)
        provider = await provider_manager.get_stt_provider(
            domain_uuid=request.domain_uuid,
            provider_name=request.provider,
        )
        
        # Transcribe
        result = await provider.transcribe(
            audio_file=audio_path,
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
        
    except HTTPException:
        raise
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
    finally:
        # Limpar arquivo temporário
        if temp_file and os.path.exists(temp_file.name):
            try:
                os.unlink(temp_file.name)
            except Exception:
                pass