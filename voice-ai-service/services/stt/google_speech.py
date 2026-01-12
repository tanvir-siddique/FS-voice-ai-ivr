"""
Google Cloud Speech-to-Text Provider.

Uses Google Cloud Speech API for high-accuracy transcription.
"""

import os
from typing import Optional

from .base import BaseSTT, TranscriptionResult


class GoogleSpeechSTT(BaseSTT):
    """
    Google Cloud Speech-to-Text provider.
    
    Config:
        credentials_path: Path to service account JSON (or use GOOGLE_APPLICATION_CREDENTIALS env)
        language: Language code (default: pt-BR)
        model: Recognition model (default: latest_long)
    """
    
    provider_name = "google_speech"
    
    def __init__(self, config: dict):
        super().__init__(config)
        self.credentials_path = config.get("credentials_path")
        self.language = config.get("language", "pt-BR")
        self.model = config.get("model", "latest_long")
        
        # Set credentials if provided
        if self.credentials_path:
            os.environ["GOOGLE_APPLICATION_CREDENTIALS"] = self.credentials_path
    
    async def transcribe(
        self,
        audio_file: str,
        language: str = "pt",
    ) -> TranscriptionResult:
        """
        Transcribe audio using Google Cloud Speech.
        
        Args:
            audio_file: Path to audio file
            language: Language code (pt, en, es, etc.)
            
        Returns:
            TranscriptionResult with transcribed text
        """
        if not os.path.exists(audio_file):
            raise FileNotFoundError(f"Audio file not found: {audio_file}")
        
        try:
            from google.cloud import speech
        except ImportError:
            raise ImportError(
                "google-cloud-speech not installed. "
                "Install with: pip install google-cloud-speech"
            )
        
        # Map language code to Google format
        lang_map = {
            "pt": "pt-BR",
            "en": "en-US",
            "es": "es-ES",
            "fr": "fr-FR",
            "de": "de-DE",
        }
        google_lang = lang_map.get(language, language)
        
        # Read audio file
        with open(audio_file, "rb") as f:
            audio_content = f.read()
        
        # Create client
        client = speech.SpeechClient()
        
        # Configure audio
        audio = speech.RecognitionAudio(content=audio_content)
        
        # Detect audio encoding from file extension
        ext = os.path.splitext(audio_file)[1].lower()
        encoding_map = {
            ".wav": speech.RecognitionConfig.AudioEncoding.LINEAR16,
            ".mp3": speech.RecognitionConfig.AudioEncoding.MP3,
            ".flac": speech.RecognitionConfig.AudioEncoding.FLAC,
            ".ogg": speech.RecognitionConfig.AudioEncoding.OGG_OPUS,
        }
        encoding = encoding_map.get(ext, speech.RecognitionConfig.AudioEncoding.ENCODING_UNSPECIFIED)
        
        # Configure recognition
        config = speech.RecognitionConfig(
            encoding=encoding,
            sample_rate_hertz=16000,  # Common for telephony
            language_code=google_lang,
            model=self.model,
            enable_automatic_punctuation=True,
        )
        
        # Perform recognition (synchronous API)
        import asyncio
        loop = asyncio.get_event_loop()
        response = await loop.run_in_executor(
            None,
            lambda: client.recognize(config=config, audio=audio),
        )
        
        # Extract results
        if response.results:
            result = response.results[0]
            alternative = result.alternatives[0]
            
            return TranscriptionResult(
                text=alternative.transcript.strip(),
                language=google_lang,
                confidence=alternative.confidence if hasattr(alternative, 'confidence') else None,
                duration_ms=0,  # Google doesn't return duration in sync API
            )
        else:
            return TranscriptionResult(
                text="",
                language=google_lang,
                confidence=0.0,
                duration_ms=0,
            )
    
    async def is_available(self) -> bool:
        """Check if Google Cloud Speech is available."""
        try:
            from google.cloud import speech
            # Check if credentials are configured
            if self.credentials_path and os.path.exists(self.credentials_path):
                return True
            if os.environ.get("GOOGLE_APPLICATION_CREDENTIALS"):
                return True
            return False
        except ImportError:
            return False
