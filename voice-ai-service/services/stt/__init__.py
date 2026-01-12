"""Speech-to-Text providers."""
from .base import BaseSTT, TranscriptionResult
from .factory import create_stt_provider, get_available_providers

__all__ = [
    "BaseSTT",
    "TranscriptionResult",
    "create_stt_provider",
    "get_available_providers",
]
