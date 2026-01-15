"""
MinIO Uploader para Voice AI.

Faz upload de gravações de chamadas para o MinIO compartilhado do OmniPlay.

Configuração via variáveis de ambiente:
- MINIO_ENDPOINT: URL do MinIO (ex: minio.netplay.net.br)
- MINIO_ACCESS_KEY: Chave de acesso
- MINIO_SECRET_KEY: Chave secreta
- MINIO_BUCKET: Nome do bucket (default: voice-recordings)
- MINIO_USE_SSL: Usar HTTPS (default: true)
- MINIO_REGION: Região (default: us-east-1)

Ref: https://min.io/docs/minio/linux/developers/python/minio-py.html
"""

import os
import io
import logging
from datetime import datetime
from typing import Optional, Dict, Any
from dataclasses import dataclass

logger = logging.getLogger(__name__)

# Configurações via ambiente
# Usa o mesmo MinIO do OmniPlay: storage.netplay.net.br
MINIO_ENDPOINT = os.getenv("MINIO_ENDPOINT", "storage.netplay.net.br")
MINIO_ACCESS_KEY = os.getenv("MINIO_ACCESS_KEY", "")
MINIO_SECRET_KEY = os.getenv("MINIO_SECRET_KEY", "")
MINIO_BUCKET = os.getenv("MINIO_BUCKET", "voice-recordings")
MINIO_USE_SSL = os.getenv("MINIO_USE_SSL", "true").lower() == "true"
MINIO_REGION = os.getenv("MINIO_REGION", "us-east-1")
MINIO_PUBLIC_URL = os.getenv("MINIO_PUBLIC_URL", f"https://{MINIO_ENDPOINT}")


@dataclass
class UploadResult:
    """Resultado do upload para MinIO."""
    success: bool
    url: Optional[str] = None
    object_name: Optional[str] = None
    size: int = 0
    error: Optional[str] = None


class MinioUploader:
    """
    Cliente para upload de arquivos para MinIO.
    
    Usado pelo HandoffHandler para fazer upload de gravações
    antes de enviar o handoff para o OmniPlay.
    """
    
    def __init__(self):
        self._client = None
        self._initialized = False
        self._init_error: Optional[str] = None
        
        # Tentar inicializar
        self._initialize()
    
    def _initialize(self) -> None:
        """Inicializa o cliente MinIO."""
        if not MINIO_ACCESS_KEY or not MINIO_SECRET_KEY:
            self._init_error = "MINIO_ACCESS_KEY or MINIO_SECRET_KEY not configured"
            logger.warning(f"MinioUploader: {self._init_error}")
            return
        
        try:
            from minio import Minio
            
            self._client = Minio(
                MINIO_ENDPOINT,
                access_key=MINIO_ACCESS_KEY,
                secret_key=MINIO_SECRET_KEY,
                secure=MINIO_USE_SSL,
                region=MINIO_REGION,
            )
            
            # Verificar se bucket existe, criar se não
            if not self._client.bucket_exists(MINIO_BUCKET):
                logger.info(f"MinioUploader: Creating bucket '{MINIO_BUCKET}'")
                self._client.make_bucket(MINIO_BUCKET, location=MINIO_REGION)
            
            self._initialized = True
            logger.info(f"MinioUploader: Initialized successfully", extra={
                "endpoint": MINIO_ENDPOINT,
                "bucket": MINIO_BUCKET,
                "ssl": MINIO_USE_SSL,
            })
            
        except ImportError:
            self._init_error = "minio package not installed. Run: pip install minio"
            logger.error(f"MinioUploader: {self._init_error}")
        except Exception as e:
            self._init_error = f"Failed to initialize MinIO client: {str(e)}"
            logger.error(f"MinioUploader: {self._init_error}")
    
    @property
    def is_available(self) -> bool:
        """Verifica se o uploader está disponível."""
        return self._initialized and self._client is not None
    
    def upload_audio(
        self,
        audio_data: bytes,
        call_uuid: str,
        company_id: int,
        content_type: str = "audio/mpeg",
        metadata: Optional[Dict[str, str]] = None
    ) -> UploadResult:
        """
        Faz upload de áudio para o MinIO.
        
        Args:
            audio_data: Bytes do áudio
            call_uuid: UUID da chamada (usado no nome do arquivo)
            company_id: ID da empresa (multi-tenant)
            content_type: MIME type do áudio
            metadata: Metadados opcionais
            
        Returns:
            UploadResult com URL pública ou erro
        """
        if not self.is_available:
            return UploadResult(
                success=False,
                error=self._init_error or "MinIO not available"
            )
        
        if not audio_data:
            return UploadResult(success=False, error="No audio data provided")
        
        try:
            # Gerar nome do objeto
            # Estrutura: company_{id}/voice/{YYYY}/{MM}/{DD}/{call_uuid}.mp3
            now = datetime.utcnow()
            date_path = now.strftime("%Y/%m/%d")
            
            # Extensão baseada no content_type
            ext = "mp3"
            if "wav" in content_type:
                ext = "wav"
            elif "ogg" in content_type:
                ext = "ogg"
            elif "webm" in content_type:
                ext = "webm"
            
            object_name = f"company_{company_id}/voice/{date_path}/{call_uuid}.{ext}"
            
            # Preparar metadados
            upload_metadata = {
                "x-amz-meta-call-uuid": call_uuid,
                "x-amz-meta-company-id": str(company_id),
                "x-amz-meta-uploaded-at": now.isoformat(),
            }
            if metadata:
                for key, value in metadata.items():
                    upload_metadata[f"x-amz-meta-{key}"] = str(value)
            
            # Fazer upload
            data_stream = io.BytesIO(audio_data)
            
            self._client.put_object(
                MINIO_BUCKET,
                object_name,
                data_stream,
                length=len(audio_data),
                content_type=content_type,
                metadata=upload_metadata,
            )
            
            # Construir URL pública
            public_url = f"{MINIO_PUBLIC_URL}/{MINIO_BUCKET}/{object_name}"
            
            logger.info(f"MinioUploader: Upload successful", extra={
                "call_uuid": call_uuid,
                "company_id": company_id,
                "object_name": object_name,
                "size": len(audio_data),
                "url": public_url,
            })
            
            return UploadResult(
                success=True,
                url=public_url,
                object_name=object_name,
                size=len(audio_data),
            )
            
        except Exception as e:
            error_msg = f"Upload failed: {str(e)}"
            logger.error(f"MinioUploader: {error_msg}", extra={
                "call_uuid": call_uuid,
                "company_id": company_id,
            })
            return UploadResult(success=False, error=error_msg)
    
    def upload_transcript(
        self,
        transcript: str,
        call_uuid: str,
        company_id: int,
        metadata: Optional[Dict[str, str]] = None
    ) -> UploadResult:
        """
        Faz upload de transcrição (texto) para o MinIO.
        
        Args:
            transcript: Texto da transcrição
            call_uuid: UUID da chamada
            company_id: ID da empresa
            metadata: Metadados opcionais
            
        Returns:
            UploadResult com URL ou erro
        """
        if not self.is_available:
            return UploadResult(
                success=False,
                error=self._init_error or "MinIO not available"
            )
        
        if not transcript:
            return UploadResult(success=False, error="No transcript provided")
        
        try:
            # Gerar nome do objeto
            now = datetime.utcnow()
            date_path = now.strftime("%Y/%m/%d")
            object_name = f"company_{company_id}/transcripts/{date_path}/{call_uuid}.txt"
            
            # Converter para bytes
            transcript_bytes = transcript.encode("utf-8")
            data_stream = io.BytesIO(transcript_bytes)
            
            # Metadados
            upload_metadata = {
                "x-amz-meta-call-uuid": call_uuid,
                "x-amz-meta-company-id": str(company_id),
                "x-amz-meta-uploaded-at": now.isoformat(),
            }
            if metadata:
                for key, value in metadata.items():
                    upload_metadata[f"x-amz-meta-{key}"] = str(value)
            
            # Upload
            self._client.put_object(
                MINIO_BUCKET,
                object_name,
                data_stream,
                length=len(transcript_bytes),
                content_type="text/plain; charset=utf-8",
                metadata=upload_metadata,
            )
            
            public_url = f"{MINIO_PUBLIC_URL}/{MINIO_BUCKET}/{object_name}"
            
            logger.info(f"MinioUploader: Transcript upload successful", extra={
                "call_uuid": call_uuid,
                "company_id": company_id,
                "object_name": object_name,
            })
            
            return UploadResult(
                success=True,
                url=public_url,
                object_name=object_name,
                size=len(transcript_bytes),
            )
            
        except Exception as e:
            error_msg = f"Transcript upload failed: {str(e)}"
            logger.error(f"MinioUploader: {error_msg}")
            return UploadResult(success=False, error=error_msg)


# Singleton global
_uploader: Optional[MinioUploader] = None


def get_minio_uploader() -> MinioUploader:
    """Retorna instância singleton do MinioUploader."""
    global _uploader
    if _uploader is None:
        _uploader = MinioUploader()
    return _uploader
