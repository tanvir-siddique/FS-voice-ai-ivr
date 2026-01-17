"""
Announcement TTS Service - Gera áudio de anúncio usando ElevenLabs.

Usado para anunciar clientes ao atendente humano durante transferência.

Ref: voice-ai-ivr/openspec/changes/announced-transfer/
"""

import asyncio
import hashlib
import logging
import os
import subprocess
import tempfile
import time
from pathlib import Path
from typing import Optional

import httpx

logger = logging.getLogger(__name__)

# Diretório para cache de áudio
# Usar /tmp que é acessível pelo FreeSWITCH
AUDIO_CACHE_DIR = Path(os.getenv("ANNOUNCEMENT_CACHE_DIR", "/tmp/voice-ai-announcements"))

# TTL do cache em segundos (1 hora)
CACHE_TTL = int(os.getenv("ANNOUNCEMENT_CACHE_TTL", "3600"))


class AnnouncementTTS:
    """
    Serviço para gerar áudio de anúncio usando ElevenLabs.
    
    Fluxo:
    1. Verificar cache (mesmo texto = mesmo áudio)
    2. Se não tem cache, gerar via ElevenLabs
    3. Converter MP3 para WAV (PCM 16kHz mono)
    4. Salvar em diretório acessível pelo FreeSWITCH
    5. Retornar caminho do arquivo
    """
    
    def __init__(
        self,
        api_key: Optional[str] = None,
        voice_id: Optional[str] = None,
        model_id: str = "eleven_multilingual_v2"
    ):
        """
        Args:
            api_key: ElevenLabs API key (ou usar env ELEVENLABS_API_KEY)
            voice_id: ID da voz (ou usar env ELEVENLABS_VOICE_ID)
            model_id: Modelo do ElevenLabs
        """
        self.api_key = api_key or os.getenv("ELEVENLABS_API_KEY", "")
        self.voice_id = voice_id or os.getenv("ELEVENLABS_VOICE_ID", "21m00Tcm4TlvDq8ikWAM")
        self.model_id = model_id
        self.base_url = "https://api.elevenlabs.io/v1"
        
        # Criar diretório de cache
        AUDIO_CACHE_DIR.mkdir(parents=True, exist_ok=True)
    
    def _get_cache_key(self, text: str, voice_id: str) -> str:
        """Gera chave de cache baseada no texto e voz."""
        content = f"{voice_id}:{text}"
        return hashlib.md5(content.encode()).hexdigest()
    
    def _get_cache_path(self, cache_key: str) -> Path:
        """Retorna caminho do arquivo de cache."""
        return AUDIO_CACHE_DIR / f"{cache_key}.wav"
    
    def _is_cache_valid(self, cache_path: Path) -> bool:
        """Verifica se cache existe e não expirou."""
        if not cache_path.exists():
            return False
        
        age = time.time() - cache_path.stat().st_mtime
        return age < CACHE_TTL
    
    async def generate_announcement(
        self,
        text: str,
        voice_id: Optional[str] = None
    ) -> Optional[str]:
        """
        Gera áudio de anúncio.
        
        Args:
            text: Texto do anúncio
            voice_id: ID da voz (opcional, usa padrão)
        
        Returns:
            Caminho do arquivo WAV ou None se falhar
        """
        start_time = time.time()
        voice = voice_id or self.voice_id
        
        # Validar voice_id (ElevenLabs usa IDs de ~20 caracteres)
        if not voice:
            logger.warning("No voice_id provided, using default")
            voice = self.voice_id
        elif len(voice) < 10:
            logger.warning(f"voice_id '{voice}' seems invalid, using default")
            voice = self.voice_id
        
        if not self.api_key:
            logger.error("ElevenLabs API key not configured")
            return None
        
        # Verificar cache
        cache_key = self._get_cache_key(text, voice)
        cache_path = self._get_cache_path(cache_key)
        
        if self._is_cache_valid(cache_path):
            duration = time.time() - start_time
            logger.info(
                f"Announcement cache hit: {cache_key}",
                extra={
                    "tts_provider": "elevenlabs",
                    "text_length": len(text),
                    "cache_hit": True,
                    "duration_seconds": duration
                }
            )
            return str(cache_path)
        
        logger.info(f"Generating announcement via ElevenLabs: {text[:50]}...")
        
        try:
            # 1. Gerar MP3 via ElevenLabs
            mp3_path = await self._generate_mp3(text, voice)
            if not mp3_path:
                return None
            
            # 2. Converter MP3 para WAV (PCM 16kHz mono)
            wav_path = await self._convert_to_wav(mp3_path, cache_path)
            
            # 3. Limpar MP3 temporário
            try:
                os.unlink(mp3_path)
            except Exception:
                pass
            
            if wav_path:
                duration = time.time() - start_time
                logger.info(
                    f"Announcement generated: {wav_path}",
                    extra={
                        "tts_provider": "elevenlabs",
                        "text_length": len(text),
                        "cache_hit": False,
                        "duration_seconds": duration,
                        "voice_id": voice
                    }
                )
                return wav_path
            
            return None
            
        except Exception as e:
            logger.exception(f"Error generating announcement: {e}")
            return None
    
    async def _generate_mp3(self, text: str, voice_id: str) -> Optional[str]:
        """Gera MP3 via ElevenLabs API."""
        try:
            headers = {
                "Accept": "audio/mpeg",
                "Content-Type": "application/json",
                "xi-api-key": self.api_key,
            }
            
            payload = {
                "text": text,
                "model_id": self.model_id,
                "voice_settings": {
                    "stability": 0.5,
                    "similarity_boost": 0.75,
                    "style": 0.0,
                    "use_speaker_boost": True,
                },
            }
            
            async with httpx.AsyncClient(timeout=30) as client:
                response = await client.post(
                    f"{self.base_url}/text-to-speech/{voice_id}",
                    headers=headers,
                    json=payload,
                )
                response.raise_for_status()
                
                # Salvar MP3 temporário
                fd, mp3_path = tempfile.mkstemp(suffix=".mp3")
                with os.fdopen(fd, "wb") as f:
                    f.write(response.content)
                
                return mp3_path
                
        except httpx.HTTPStatusError as e:
            logger.error(f"ElevenLabs API error: {e.response.status_code} - {e.response.text}")
            return None
        except Exception as e:
            logger.error(f"Error calling ElevenLabs: {e}")
            return None
    
    async def _convert_to_wav(self, mp3_path: str, wav_path: Path) -> Optional[str]:
        """
        Converte MP3 para WAV PCM 16kHz mono.
        
        Usa ffmpeg para conversão.
        
        Args:
            mp3_path: Caminho do MP3 de entrada
            wav_path: Caminho do WAV de saída
        
        Returns:
            Caminho do WAV ou None se falhar
        """
        try:
            # Usar ffmpeg para converter
            # -ar 16000: sample rate 16kHz
            # -ac 1: mono
            # -acodec pcm_s16le: PCM 16-bit little-endian
            cmd = [
                "ffmpeg", "-y",
                "-i", mp3_path,
                "-ar", "16000",
                "-ac", "1",
                "-acodec", "pcm_s16le",
                str(wav_path)
            ]
            
            # Executar em thread separada para não bloquear
            loop = asyncio.get_event_loop()
            result = await loop.run_in_executor(
                None,
                lambda: subprocess.run(
                    cmd,
                    capture_output=True,
                    timeout=30
                )
            )
            
            if result.returncode != 0:
                logger.error(f"ffmpeg error: {result.stderr.decode()}")
                return None
            
            return str(wav_path)
            
        except subprocess.TimeoutExpired:
            logger.error("ffmpeg timeout")
            return None
        except FileNotFoundError:
            logger.error("ffmpeg not found - install with: apt-get install ffmpeg")
            return None
        except Exception as e:
            logger.error(f"Conversion error: {e}")
            return None
    
    def cleanup_old_cache(self) -> int:
        """
        Remove arquivos de cache expirados.
        
        Returns:
            Número de arquivos removidos
        """
        removed = 0
        now = time.time()
        
        try:
            for path in AUDIO_CACHE_DIR.glob("*.wav"):
                age = now - path.stat().st_mtime
                if age > CACHE_TTL:
                    path.unlink()
                    removed += 1
        except Exception as e:
            logger.warning(f"Cache cleanup error: {e}")
        
        return removed
    
    async def is_available(self) -> bool:
        """
        Verifica se ElevenLabs está disponível.
        
        Útil para health checks e fallback decisions.
        
        Returns:
            True se a API está acessível e autenticada
        """
        if not self.api_key:
            return False
        
        try:
            async with httpx.AsyncClient(timeout=5) as client:
                response = await client.get(
                    f"{self.base_url}/user",
                    headers={"xi-api-key": self.api_key},
                )
                return response.status_code == 200
        except Exception as e:
            logger.warning(f"ElevenLabs health check failed: {e}")
            return False


# Singleton para reuso
_announcement_tts: Optional[AnnouncementTTS] = None


def get_announcement_tts() -> AnnouncementTTS:
    """Retorna instância singleton do AnnouncementTTS."""
    global _announcement_tts
    if _announcement_tts is None:
        _announcement_tts = AnnouncementTTS()
    return _announcement_tts
