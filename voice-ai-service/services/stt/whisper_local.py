"""
Whisper Local STT Provider.

Uses faster-whisper for local speech-to-text processing.
Zero cost, runs on CPU or GPU.
"""

import os
from pathlib import Path

from .base import BaseSTT, TranscriptionResult


class WhisperLocalSTT(BaseSTT):
    """
    Local Whisper STT using faster-whisper.
    
    Config:
        model: Model size (tiny, base, small, medium, large)
        device: Device to use (cpu, cuda)
        compute_type: Compute type (int8, float16, float32)
        model_path: Optional custom model path
    """
    
    provider_name = "whisper_local"
    
    def __init__(self, config: dict):
        super().__init__(config)
        self.model = None
        self._initialized = False
        
    async def _ensure_initialized(self):
        """Lazy initialization of Whisper model."""
        if self._initialized:
            return
            
        try:
            from faster_whisper import WhisperModel
            
            model_size = self.config.get("model", "base")
            device = self.config.get("device", "cpu")
            compute_type = self.config.get("compute_type", "int8")
            model_path = self.config.get("model_path")
            
            if model_path and Path(model_path).exists():
                self.model = WhisperModel(
                    model_path,
                    device=device,
                    compute_type=compute_type,
                )
            else:
                self.model = WhisperModel(
                    model_size,
                    device=device,
                    compute_type=compute_type,
                )
            
            self._initialized = True
            
        except ImportError:
            raise ImportError(
                "faster-whisper not installed. "
                "Install with: pip install faster-whisper"
            )
    
    async def transcribe(
        self,
        audio_file: str,
        language: str = "pt",
    ) -> TranscriptionResult:
        """
        Transcribe audio using local Whisper model.
        
        Args:
            audio_file: Path to audio file
            language: Language code
            
        Returns:
            TranscriptionResult with transcribed text
        """
        await self._ensure_initialized()
        
        if not os.path.exists(audio_file):
            raise FileNotFoundError(f"Audio file not found: {audio_file}")
        
        # Transcribe
        segments, info = self.model.transcribe(
            audio_file,
            language=language,
            beam_size=5,
            vad_filter=True,
        )
        
        # Collect all segments
        text_parts = []
        for segment in segments:
            text_parts.append(segment.text.strip())
        
        full_text = " ".join(text_parts)
        
        return TranscriptionResult(
            text=full_text,
            language=info.language,
            confidence=info.language_probability,
            duration_ms=int(info.duration * 1000),
        )
    
    async def is_available(self) -> bool:
        """Check if Whisper local is available."""
        try:
            from faster_whisper import WhisperModel
            return True
        except ImportError:
            return False
