"""
Base interface for Embeddings providers.

All Embeddings providers MUST implement this interface.
"""

from abc import ABC, abstractmethod
from dataclasses import dataclass
from typing import List


@dataclass
class EmbeddingResult:
    """Result from embedding generation."""
    
    embedding: List[float]
    dimensions: int
    model: str
    tokens_used: int = 0


class BaseEmbeddings(ABC):
    """
    Abstract base class for Embeddings providers.
    
    All implementations MUST:
    - Be stateless (no domain-specific data stored)
    - Accept config dict in __init__
    - Implement embed() and embed_batch() methods
    """
    
    provider_name: str = "base"
    dimensions: int = 1536
    
    def __init__(self, config: dict):
        """
        Initialize the provider with configuration.
        
        Args:
            config: Provider-specific configuration (API keys, models, etc.)
        """
        self.config = config
    
    @abstractmethod
    async def embed(self, text: str) -> EmbeddingResult:
        """
        Generate embedding for a single text.
        
        Args:
            text: Text to embed
            
        Returns:
            EmbeddingResult with embedding vector
        """
        pass
    
    @abstractmethod
    async def embed_batch(self, texts: List[str]) -> List[EmbeddingResult]:
        """
        Generate embeddings for multiple texts.
        
        Args:
            texts: List of texts to embed
            
        Returns:
            List of EmbeddingResult
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
    
    def get_dimensions(self) -> int:
        """Get embedding dimensions."""
        return self.dimensions
    
    def get_name(self) -> str:
        """Get provider name."""
        return self.provider_name
