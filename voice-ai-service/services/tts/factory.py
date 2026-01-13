"""
Factory for creating TTS provider instances.

Supports multi-tenant: Each domain can have different providers configured.
"""

from typing import Dict, Type

from .base import BaseTTS


# Registry of available providers
_providers: Dict[str, Type[BaseTTS]] = {}


def register_provider(name: str, provider_class: Type[BaseTTS]):
    """Register a provider class."""
    _providers[name] = provider_class


def create_tts_provider(provider_name: str, config: dict) -> BaseTTS:
    """
    Create a TTS provider instance.
    
    Args:
        provider_name: Name of the provider (piper_local, elevenlabs, etc.)
        config: Provider configuration from database
        
    Returns:
        Configured TTS provider instance
        
    Raises:
        ValueError: If provider not found
    """
    if provider_name not in _providers:
        raise ValueError(
            f"Unknown TTS provider: {provider_name}. "
            f"Available: {list(_providers.keys())}"
        )
    
    return _providers[provider_name](config)


def get_available_providers() -> list:
    """Get list of available provider names."""
    return list(_providers.keys())


# Auto-register providers when imported
def _register_all():
    """Register all available TTS providers."""
    import importlib
    
    provider_classes = [
        ("piper_local", "piper_local", "PiperLocalTTS"),
        ("openai_tts", "openai_tts", "OpenAITTS"),
        ("elevenlabs", "elevenlabs", "ElevenLabsTTS"),
        ("azure_neural", "azure_neural", "AzureNeuralTTS"),
        ("google_tts", "google_tts", "GoogleCloudTTS"),
        ("aws_polly", "aws_polly", "AWSPollyTTS"),
        ("coqui_local", "coqui_local", "CoquiLocalTTS"),
        ("playht", "playht", "PlayHTTTS"),
    ]
    
    for name, module_name, class_name in provider_classes:
        try:
            module = importlib.import_module(f"services.tts.{module_name}")
            provider_class = getattr(module, class_name)
            register_provider(name, provider_class)
        except (ImportError, AttributeError) as e:
            # Log but don't fail - provider may have missing dependencies
            pass


_register_all()
