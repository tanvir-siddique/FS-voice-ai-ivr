"""
Base interface for Text-to-Speech providers.

All TTS providers MUST implement this interface.
"""

from abc import ABC, abstractmethod
from dataclasses import dataclass
from typing import Optional, List


@dataclass
class SynthesisResult:
    """Result from synthesis."""
    
    audio_file: str
    duration_ms: int
    format: str = "wav"


@dataclass
class VoiceInfo:
    """Information about an available voice."""
    
    voice_id: str
    name: str
    language: str
    gender: Optional[str] = None


class BaseTTS(ABC):
    """
    Abstract base class for Text-to-Speech providers.
    
    All implementations MUST:
    - Be stateless (no domain-specific data stored)
    - Accept config dict in __init__
    - Implement synthesize() method
    - Return audio in WAV format compatible with FreeSWITCH
    """
    
    provider_name: str = "base"
    
    def __init__(self, config: dict):
        """
        Initialize the provider with configuration.
        
        Args:
            config: Provider-specific configuration (API keys, voices, etc.)
        """
        self.config = config
    
    @abstractmethod
    async def synthesize(
        self,
        text: str,
        voice_id: Optional[str] = None,
        speed: float = 1.0,
        output_path: Optional[str] = None,
    ) -> SynthesisResult:
        """
        Synthesize text to speech.
        
        Args:
            text: Text to synthesize
            voice_id: Voice to use (provider-specific)
            speed: Speech speed (0.5 to 2.0)
            output_path: Path to save audio (optional, generates temp file if not provided)
            
        Returns:
            SynthesisResult with path to audio file
            
        Raises:
            Exception: If synthesis fails
        """
        pass
    
    @abstractmethod
    async def list_voices(self, language: str = "pt-BR") -> List[VoiceInfo]:
        """
        List available voices for a language.
        
        Args:
            language: Language code (e.g., pt-BR, en-US)
            
        Returns:
            List of available voices
        """
        pass
    
    @abstractmethod
    async def is_available(self) -> bool:
        """
        Check if the provider is available and properly configured.
        
        Returns:
            True if provider is ready to use
        """
        pass
    
    def get_name(self) -> str:
        """Get provider name."""
        return self.provider_name
