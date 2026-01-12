"""
Google Cloud Text-to-Speech Provider.

Uses Google Cloud TTS for natural-sounding speech synthesis.
"""

import os
import uuid
from pathlib import Path
from typing import Optional, List

from .base import BaseTTS, SynthesisResult, VoiceInfo
from config.settings import settings


class GoogleCloudTTS(BaseTTS):
    """
    Google Cloud Text-to-Speech provider.
    
    Config:
        credentials_path: Path to service account JSON
        language_code: Language code (default: pt-BR)
        voice_name: Voice name (default: pt-BR-Wavenet-A)
    """
    
    provider_name = "google_tts"
    
    # Popular Portuguese voices
    PT_BR_VOICES = [
        ("pt-BR-Wavenet-A", "Wavenet A", "female"),
        ("pt-BR-Wavenet-B", "Wavenet B", "male"),
        ("pt-BR-Wavenet-C", "Wavenet C", "female"),
        ("pt-BR-Neural2-A", "Neural2 A", "female"),
        ("pt-BR-Neural2-B", "Neural2 B", "male"),
        ("pt-BR-Neural2-C", "Neural2 C", "female"),
        ("pt-BR-Standard-A", "Standard A", "female"),
        ("pt-BR-Standard-B", "Standard B", "male"),
    ]
    
    def __init__(self, config: dict):
        super().__init__(config)
        self.credentials_path = config.get("credentials_path")
        self.language_code = config.get("language_code", "pt-BR")
        self.voice_name = config.get("voice_name", "pt-BR-Wavenet-A")
        
        if self.credentials_path:
            os.environ["GOOGLE_APPLICATION_CREDENTIALS"] = self.credentials_path
    
    async def synthesize(
        self,
        text: str,
        voice_id: Optional[str] = None,
        speed: float = 1.0,
        output_path: Optional[str] = None,
    ) -> SynthesisResult:
        """
        Synthesize text using Google Cloud TTS.
        
        Args:
            text: Text to synthesize
            voice_id: Google voice name
            speed: Speaking rate (0.25 to 4.0)
            output_path: Path to save audio (optional)
            
        Returns:
            SynthesisResult with path to audio file
        """
        try:
            from google.cloud import texttospeech
        except ImportError:
            raise ImportError(
                "google-cloud-texttospeech not installed. "
                "Install with: pip install google-cloud-texttospeech"
            )
        
        voice = voice_id or self.voice_name
        
        # Generate output path if not provided
        if not output_path:
            output_path = str(settings.TEMP_DIR / f"tts_{uuid.uuid4().hex}.wav")
        
        # Ensure output directory exists
        Path(output_path).parent.mkdir(parents=True, exist_ok=True)
        
        # Create client
        client = texttospeech.TextToSpeechClient()
        
        # Set the text input
        synthesis_input = texttospeech.SynthesisInput(text=text)
        
        # Build the voice request
        voice_params = texttospeech.VoiceSelectionParams(
            language_code=self.language_code,
            name=voice,
        )
        
        # Select audio config
        audio_config = texttospeech.AudioConfig(
            audio_encoding=texttospeech.AudioEncoding.LINEAR16,
            speaking_rate=speed,
            sample_rate_hertz=16000,
        )
        
        # Perform synthesis (synchronous)
        import asyncio
        loop = asyncio.get_event_loop()
        response = await loop.run_in_executor(
            None,
            lambda: client.synthesize_speech(
                input=synthesis_input,
                voice=voice_params,
                audio_config=audio_config,
            ),
        )
        
        # Save audio
        with open(output_path, "wb") as f:
            f.write(response.audio_content)
        
        # Estimate duration from file size (16kHz, 16-bit = 32KB/s)
        file_size = os.path.getsize(output_path)
        duration_ms = int((file_size / 32000) * 1000)
        
        return SynthesisResult(
            audio_file=output_path,
            duration_ms=duration_ms,
            format="wav",
        )
    
    async def list_voices(self, language: str = "pt-BR") -> List[VoiceInfo]:
        """List available Portuguese voices."""
        voices = []
        
        for voice_name, name, gender in self.PT_BR_VOICES:
            voices.append(VoiceInfo(
                voice_id=voice_name,
                name=name,
                language="pt-BR",
                gender=gender,
            ))
        
        return voices
    
    async def is_available(self) -> bool:
        """Check if Google Cloud TTS is available."""
        try:
            from google.cloud import texttospeech
            if self.credentials_path and os.path.exists(self.credentials_path):
                return True
            if os.environ.get("GOOGLE_APPLICATION_CREDENTIALS"):
                return True
            return False
        except ImportError:
            return False
