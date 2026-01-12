"""
Embedding Service - Generates embeddings using configured provider.

MULTI-TENANT: Uses provider configured for each domain.
"""

from typing import List, Optional

from services.provider_manager import ProviderManager


class EmbeddingService:
    """
    Service for generating embeddings.
    
    Uses the embeddings provider configured for the domain.
    """
    
    def __init__(self, provider_manager: ProviderManager):
        """
        Initialize with provider manager.
        
        Args:
            provider_manager: Manager for loading providers from config
        """
        self.provider_manager = provider_manager
    
    async def embed_text(
        self,
        domain_uuid: str,
        text: str,
    ) -> List[float]:
        """
        Generate embedding for a single text.
        
        Args:
            domain_uuid: Tenant identifier (REQUIRED)
            text: Text to embed
            
        Returns:
            Embedding vector as list of floats
        """
        if not domain_uuid:
            raise ValueError("domain_uuid is required for multi-tenant isolation")
        
        # Get configured embeddings provider for this domain
        provider = await self.provider_manager.get_embeddings_provider(domain_uuid)
        
        # Generate embedding
        result = await provider.embed(text)
        
        return result.embedding
    
    async def embed_texts(
        self,
        domain_uuid: str,
        texts: List[str],
    ) -> List[List[float]]:
        """
        Generate embeddings for multiple texts.
        
        Args:
            domain_uuid: Tenant identifier (REQUIRED)
            texts: List of texts to embed
            
        Returns:
            List of embedding vectors
        """
        if not domain_uuid:
            raise ValueError("domain_uuid is required for multi-tenant isolation")
        
        if not texts:
            return []
        
        # Get configured embeddings provider for this domain
        provider = await self.provider_manager.get_embeddings_provider(domain_uuid)
        
        # Generate embeddings in batch
        results = await provider.embed_batch(texts)
        
        return [r.embedding for r in results]
    
    async def get_dimensions(self, domain_uuid: str) -> int:
        """
        Get embedding dimensions for the configured provider.
        
        Args:
            domain_uuid: Tenant identifier
            
        Returns:
            Number of dimensions
        """
        provider = await self.provider_manager.get_embeddings_provider(domain_uuid)
        return provider.get_dimensions()
