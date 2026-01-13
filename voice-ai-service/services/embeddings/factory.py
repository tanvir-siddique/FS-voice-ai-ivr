"""
Factory for creating Embeddings provider instances.

Supports multi-tenant: Each domain can have different providers configured.
"""

from typing import Dict, Type

from .base import BaseEmbeddings


# Registry of available providers
_providers: Dict[str, Type[BaseEmbeddings]] = {}


def register_provider(name: str, provider_class: Type[BaseEmbeddings]):
    """Register a provider class."""
    _providers[name] = provider_class


def create_embeddings_provider(provider_name: str, config: dict) -> BaseEmbeddings:
    """
    Create an Embeddings provider instance.
    
    Args:
        provider_name: Name of the provider (openai, azure_openai, etc.)
        config: Provider configuration from database
        
    Returns:
        Configured Embeddings provider instance
        
    Raises:
        ValueError: If provider not found
    """
    if provider_name not in _providers:
        raise ValueError(
            f"Unknown Embeddings provider: {provider_name}. "
            f"Available: {list(_providers.keys())}"
        )
    
    return _providers[provider_name](config)


def get_available_providers() -> list:
    """Get list of available provider names."""
    return list(_providers.keys())


# Auto-register providers when imported
def _register_all():
    """Register all available Embeddings providers."""
    import importlib
    
    provider_classes = [
        ("openai_embeddings", "openai", "OpenAIEmbeddings"),
        ("openai", "openai", "OpenAIEmbeddings"),  # Alias
        ("azure_openai_embeddings", "azure_openai", "AzureOpenAIEmbeddings"),
        ("azure_embeddings", "azure_openai", "AzureOpenAIEmbeddings"),  # Alias
        ("cohere", "cohere", "CohereEmbeddings"),
        ("voyage", "voyage", "VoyageAIEmbeddings"),
        ("local_embeddings", "local", "LocalEmbeddings"),
        ("local", "local", "LocalEmbeddings"),  # Alias
    ]
    
    for name, module_name, class_name in provider_classes:
        try:
            module = importlib.import_module(f"services.embeddings.{module_name}")
            provider_class = getattr(module, class_name)
            register_provider(name, provider_class)
        except (ImportError, AttributeError):
            pass


_register_all()
