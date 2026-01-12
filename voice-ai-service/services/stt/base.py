"""
Base interface for Speech-to-Text providers.

All STT providers MUST implement this interface.
"""

from abc import ABC, abstractmethod
from dataclasses import dataclass
from typing import Optional


@dataclass
class TranscriptionResult:
    """Result from transcription."""
    
    text: str
    language: str
    confidence: Optional[float] = None
    duration_ms: int = 0


class BaseSTT(ABC):
    """
    Abstract base class for Speech-to-Text providers.
    
    All implementations MUST:
    - Be stateless (no domain-specific data stored)
    - Accept config dict in __init__
    - Implement transcribe() method
    """
    
    provider_name: str = "base"
    
    def __init__(self, config: dict):
        """
        Initialize the provider with configuration.
        
        Args:
            config: Provider-specific configuration (API keys, models, etc.)
        """
        self.config = config
    
    @abstractmethod
    async def transcribe(
        self,
        audio_file: str,
        language: str = "pt",
    ) -> TranscriptionResult:
        """
        Transcribe audio file to text.
        
        Args:
            audio_file: Path to audio file (WAV format preferred)
            language: Language code (pt, en, es, etc.)
            
        Returns:
            TranscriptionResult with transcribed text
            
        Raises:
            Exception: If transcription fails
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
