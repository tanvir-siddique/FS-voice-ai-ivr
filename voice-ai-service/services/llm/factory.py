"""
Factory for creating LLM provider instances.

Supports multi-tenant: Each domain can have different providers configured.
"""

from typing import Dict, Type

from .base import BaseLLM


# Registry of available providers
_providers: Dict[str, Type[BaseLLM]] = {}


def register_provider(name: str, provider_class: Type[BaseLLM]):
    """Register a provider class."""
    _providers[name] = provider_class


def create_llm_provider(provider_name: str, config: dict) -> BaseLLM:
    """
    Create an LLM provider instance.
    
    Args:
        provider_name: Name of the provider (openai, anthropic, etc.)
        config: Provider configuration from database
        
    Returns:
        Configured LLM provider instance
        
    Raises:
        ValueError: If provider not found
    """
    if provider_name not in _providers:
        raise ValueError(
            f"Unknown LLM provider: {provider_name}. "
            f"Available: {list(_providers.keys())}"
        )
    
    return _providers[provider_name](config)


def get_available_providers() -> list:
    """Get list of available provider names."""
    return list(_providers.keys())


# Auto-register providers when imported
def _register_all():
    """Register all available LLM providers."""
    provider_classes = [
        ("openai", "openai", "OpenAILLM"),
        ("azure_openai", "azure_openai", "AzureOpenAILLM"),
        ("anthropic", "anthropic", "AnthropicLLM"),
        ("google_gemini", "google_gemini", "GoogleGeminiLLM"),
        ("aws_bedrock", "aws_bedrock", "AWSBedrockLLM"),
        ("groq", "groq", "GroqLLM"),
        ("ollama_local", "ollama_local", "OllamaLLM"),
        ("lmstudio_local", "lmstudio_local", "LMStudioLLM"),
    ]
    
    import importlib
    
    for name, module_name, class_name in provider_classes:
        try:
            module = importlib.import_module(f"services.llm.{module_name}")
            provider_class = getattr(module, class_name)
            register_provider(name, provider_class)
        except (ImportError, AttributeError):
            pass


_register_all()
