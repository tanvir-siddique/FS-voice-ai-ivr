"""
Coqui TTS Local Provider.

Uses Coqui TTS for local, offline voice synthesis.
Supports XTTS, VITS, and other models.
"""

import os
import uuid
from pathlib import Path
from typing import Optional, List

from .base import BaseTTS, SynthesisResult, VoiceInfo
from config.settings import settings


class CoquiLocalTTS(BaseTTS):
    """
    Coqui TTS local provider.
    
    Config:
        model_name: Coqui model name (default: tts_models/multilingual/multi-dataset/xtts_v2)
        device: Device to use (cpu or cuda)
        speaker_wav: Path to speaker WAV for voice cloning (optional)
        language: Language code (default: pt)
    """
    
    provider_name = "coqui_local"
    
    def __init__(self, config: dict):
        super().__init__(config)
        self.model_name = config.get(
            "model_name",
            "tts_models/multilingual/multi-dataset/xtts_v2"
        )
        self.device = config.get("device", "cpu")
        self.speaker_wav = config.get("speaker_wav")
        self.language = config.get("language", "pt")
        self._tts = None
    
    def _load_model(self):
        """Load Coqui TTS model (lazy loading)."""
        if self._tts is None:
            try:
                from TTS.api import TTS
            except ImportError:
                raise ImportError(
                    "TTS not installed. Install with: pip install TTS"
                )
            
            self._tts = TTS(model_name=self.model_name).to(self.device)
        
        return self._tts
    
    async def synthesize(
        self,
        text: str,
        voice_id: Optional[str] = None,
        speed: float = 1.0,
        output_path: Optional[str] = None,
    ) -> SynthesisResult:
        """
        Synthesize text using Coqui TTS.
        
        Args:
            text: Text to synthesize
            voice_id: Path to speaker WAV for cloning (overrides config)
            speed: Not directly supported
            output_path: Path to save audio (optional)
            
        Returns:
            SynthesisResult with path to audio file
        """
        # Generate output path if not provided
        if not output_path:
            output_path = str(settings.TEMP_DIR / f"tts_{uuid.uuid4().hex}.wav")
        
        # Ensure output directory exists
        Path(output_path).parent.mkdir(parents=True, exist_ok=True)
        
        # Load model
        import asyncio
        loop = asyncio.get_event_loop()
        tts = await loop.run_in_executor(None, self._load_model)
        
        # Get speaker WAV
        speaker_wav = voice_id or self.speaker_wav
        
        # Synthesize
        if speaker_wav and os.path.exists(speaker_wav):
            # Voice cloning with XTTS
            await loop.run_in_executor(
                None,
                lambda: tts.tts_to_file(
                    text=text,
                    file_path=output_path,
                    speaker_wav=speaker_wav,
                    language=self.language,
                ),
            )
        else:
            # Default synthesis
            await loop.run_in_executor(
                None,
                lambda: tts.tts_to_file(
                    text=text,
                    file_path=output_path,
                    language=self.language,
                ),
            )
        
        # Get duration from WAV
        duration_ms = self._get_wav_duration(output_path)
        
        return SynthesisResult(
            audio_file=output_path,
            duration_ms=duration_ms,
            format="wav",
        )
    
    def _get_wav_duration(self, wav_path: str) -> int:
        """Get WAV file duration in milliseconds."""
        try:
            import wave
            with wave.open(wav_path, 'rb') as wav:
                frames = wav.getnframes()
                rate = wav.getframerate()
                duration = frames / float(rate)
                return int(duration * 1000)
        except Exception:
            file_size = os.path.getsize(wav_path)
            return int((file_size / 32000) * 1000)
    
    async def list_voices(self, language: str = "pt-BR") -> List[VoiceInfo]:
        """
        List available voices.
        
        Coqui XTTS uses speaker WAV files for voice cloning.
        """
        return [
            VoiceInfo(
                voice_id="default",
                name="Default (No cloning)",
                language="multilingual",
                gender=None,
            ),
            VoiceInfo(
                voice_id="clone",
                name="Voice Clone (provide speaker_wav)",
                language="multilingual",
                gender=None,
            ),
        ]
    
    async def is_available(self) -> bool:
        """Check if Coqui TTS is available."""
        try:
            from TTS.api import TTS
            return True
        except ImportError:
            return False
