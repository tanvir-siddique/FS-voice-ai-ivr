"""
Factory for creating STT provider instances.

Supports multi-tenant: Each domain can have different providers configured.
"""

from typing import Dict, Type

from .base import BaseSTT


# Registry of available providers
_providers: Dict[str, Type[BaseSTT]] = {}


def register_provider(name: str, provider_class: Type[BaseSTT]):
    """Register a provider class."""
    _providers[name] = provider_class


def create_stt_provider(provider_name: str, config: dict) -> BaseSTT:
    """
    Create an STT provider instance.
    
    Args:
        provider_name: Name of the provider (whisper_local, whisper_api, etc.)
        config: Provider configuration from database
        
    Returns:
        Configured STT provider instance
        
    Raises:
        ValueError: If provider not found
    """
    if provider_name not in _providers:
        raise ValueError(
            f"Unknown STT provider: {provider_name}. "
            f"Available: {list(_providers.keys())}"
        )
    
    return _providers[provider_name](config)


def get_available_providers() -> list:
    """Get list of available provider names."""
    return list(_providers.keys())


# Auto-register providers when imported
def _register_all():
    """Register all available STT providers."""
    import importlib
    
    provider_classes = [
        ("whisper_local", "whisper_local", "WhisperLocalSTT"),
        ("whisper_api", "whisper_api", "OpenAIWhisperSTT"),
        ("azure_speech", "azure_speech", "AzureSpeechSTT"),
        ("google_speech", "google_speech", "GoogleSpeechSTT"),
        ("aws_transcribe", "aws_transcribe", "AWSTranscribeSTT"),
        ("deepgram", "deepgram", "DeepgramSTT"),
    ]
    
    for name, module_name, class_name in provider_classes:
        try:
            module = importlib.import_module(f"services.stt.{module_name}")
            provider_class = getattr(module, class_name)
            register_provider(name, provider_class)
        except (ImportError, AttributeError):
            pass


_register_all()
