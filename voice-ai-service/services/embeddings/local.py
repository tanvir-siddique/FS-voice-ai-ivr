"""
Local Embeddings Provider using sentence-transformers.

Zero cost, runs entirely offline.
Supports multilingual models for Portuguese.
"""

from typing import List, Optional
import asyncio
from concurrent.futures import ThreadPoolExecutor

from .base import BaseEmbeddings, EmbeddingResult


class LocalEmbeddings(BaseEmbeddings):
    """
    Local Embeddings provider using sentence-transformers.
    
    Config:
        model: Model name (default: all-MiniLM-L6-v2)
        device: Device to use (cpu, cuda)
        normalize_embeddings: Whether to normalize (default: True)
    """
    
    provider_name = "local_embeddings"
    
    # Common multilingual models
    RECOMMENDED_MODELS = {
        "all-MiniLM-L6-v2": 384,
        "paraphrase-multilingual-MiniLM-L12-v2": 384,
        "paraphrase-multilingual-mpnet-base-v2": 768,
        "distiluse-base-multilingual-cased-v2": 512,
    }
    
    def __init__(self, config: dict):
        super().__init__(config)
        self._model = None
        self._executor = ThreadPoolExecutor(max_workers=2)
        
        self.model_name = config.get("model", "all-MiniLM-L6-v2")
        self.device = config.get("device", "cpu")
        self.normalize = config.get("normalize_embeddings", True)
        self.dimensions = self.RECOMMENDED_MODELS.get(self.model_name, 384)
    
    def _load_model(self):
        """Load sentence-transformers model (lazy loading)."""
        if self._model is None:
            try:
                from sentence_transformers import SentenceTransformer
                self._model = SentenceTransformer(
                    self.model_name,
                    device=self.device,
                )
            except ImportError:
                raise ImportError(
                    "sentence-transformers not installed. "
                    "Install with: pip install sentence-transformers"
                )
        return self._model
    
    def _embed_sync(self, texts: List[str]) -> List[List[float]]:
        """Synchronous embedding generation."""
        model = self._load_model()
        
        embeddings = model.encode(
            texts,
            normalize_embeddings=self.normalize,
            show_progress_bar=False,
        )
        
        # Convert numpy arrays to lists
        return [emb.tolist() for emb in embeddings]
    
    async def embed(self, text: str) -> EmbeddingResult:
        """
        Generate embedding for a single text.
        
        Args:
            text: Text to embed
            
        Returns:
            EmbeddingResult with embedding vector
        """
        results = await self.embed_batch([text])
        return results[0]
    
    async def embed_batch(self, texts: List[str]) -> List[EmbeddingResult]:
        """
        Generate embeddings for multiple texts.
        
        Args:
            texts: List of texts to embed
            
        Returns:
            List of EmbeddingResult
        """
        if not texts:
            return []
        
        # Run in thread pool to avoid blocking
        loop = asyncio.get_event_loop()
        embeddings = await loop.run_in_executor(
            self._executor,
            self._embed_sync,
            texts,
        )
        
        # Build results
        results = []
        for embedding in embeddings:
            results.append(EmbeddingResult(
                embedding=embedding,
                dimensions=len(embedding),
                model=self.model_name,
                tokens_used=0,  # Local model, no token count
            ))
        
        return results
    
    async def is_available(self) -> bool:
        """Check if local embeddings is available."""
        try:
            from sentence_transformers import SentenceTransformer
            return True
        except ImportError:
            return False
    
    def get_dimensions(self) -> int:
        """Get embedding dimensions."""
        return self.dimensions
