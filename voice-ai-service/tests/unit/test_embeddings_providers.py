"""
Unit tests for Embeddings providers.
"""

import pytest
from unittest.mock import AsyncMock, MagicMock, patch

from services.embeddings.base import EmbeddingResult
from services.embeddings.openai import OpenAIEmbeddings
from services.embeddings.local import LocalEmbeddings
from services.embeddings.factory import create_embeddings_provider, get_available_providers


class TestOpenAIEmbeddings:
    """Tests for OpenAI Embeddings provider."""
    
    def test_init(self, openai_embeddings_config):
        """Test provider initialization."""
        provider = OpenAIEmbeddings(openai_embeddings_config)
        assert provider.provider_name == "openai"
        assert provider.model == "text-embedding-3-small"
        assert provider.dimensions == 1536
    
    def test_dimensions(self, openai_embeddings_config):
        """Test dimensions for different models."""
        # text-embedding-3-small
        provider = OpenAIEmbeddings(openai_embeddings_config)
        assert provider.get_dimensions() == 1536
        
        # text-embedding-3-large
        config_large = {**openai_embeddings_config, "model": "text-embedding-3-large"}
        provider_large = OpenAIEmbeddings(config_large)
        assert provider_large.get_dimensions() == 3072
    
    @pytest.mark.asyncio
    async def test_embed_success(self, openai_embeddings_config):
        """Test successful embedding generation."""
        provider = OpenAIEmbeddings(openai_embeddings_config)
        
        # Mock OpenAI response
        mock_response = MagicMock()
        mock_response.data = [
            MagicMock(embedding=[0.1, 0.2, 0.3] * 512)  # 1536 dimensions
        ]
        mock_response.usage = MagicMock(total_tokens=10)
        
        with patch.object(provider, '_get_client') as mock_client:
            mock_client.return_value.embeddings.create = AsyncMock(
                return_value=mock_response
            )
            
            result = await provider.embed("Texto de teste")
            
            assert isinstance(result, EmbeddingResult)
            assert len(result.embedding) == 1536
    
    @pytest.mark.asyncio
    async def test_embed_batch_success(self, openai_embeddings_config):
        """Test successful batch embedding generation."""
        provider = OpenAIEmbeddings(openai_embeddings_config)
        
        # Mock OpenAI response
        mock_response = MagicMock()
        mock_response.data = [
            MagicMock(embedding=[0.1] * 1536),
            MagicMock(embedding=[0.2] * 1536),
            MagicMock(embedding=[0.3] * 1536),
        ]
        mock_response.usage = MagicMock(total_tokens=30)
        
        with patch.object(provider, '_get_client') as mock_client:
            mock_client.return_value.embeddings.create = AsyncMock(
                return_value=mock_response
            )
            
            results = await provider.embed_batch([
                "Texto 1",
                "Texto 2",
                "Texto 3",
            ])
            
            assert len(results) == 3
            assert all(isinstance(r, EmbeddingResult) for r in results)
    
    @pytest.mark.asyncio
    async def test_embed_batch_empty(self, openai_embeddings_config):
        """Test embedding empty list."""
        provider = OpenAIEmbeddings(openai_embeddings_config)
        
        results = await provider.embed_batch([])
        
        assert results == []


class TestLocalEmbeddings:
    """Tests for local embeddings provider."""
    
    def test_init(self, local_embeddings_config):
        """Test provider initialization."""
        provider = LocalEmbeddings(local_embeddings_config)
        assert provider.provider_name == "local_embeddings"
        assert provider.model_name == "all-MiniLM-L6-v2"
        assert provider.dimensions == 384
    
    def test_recommended_models(self, local_embeddings_config):
        """Test recommended models dimensions."""
        provider = LocalEmbeddings(local_embeddings_config)
        
        expected_models = {
            "all-MiniLM-L6-v2": 384,
            "paraphrase-multilingual-MiniLM-L12-v2": 384,
            "paraphrase-multilingual-mpnet-base-v2": 768,
        }
        
        for model, dims in expected_models.items():
            assert provider.RECOMMENDED_MODELS[model] == dims
    
    @pytest.mark.asyncio
    async def test_is_available(self, local_embeddings_config):
        """Test availability check."""
        provider = LocalEmbeddings(local_embeddings_config)
        
        # This will be True if sentence-transformers is installed
        # Or False if not
        available = await provider.is_available()
        assert isinstance(available, bool)


class TestEmbeddingsFactory:
    """Tests for Embeddings factory."""
    
    def test_get_available_providers(self):
        """Test listing available providers."""
        providers = get_available_providers()
        assert isinstance(providers, list)
    
    def test_create_openai_provider(self, openai_embeddings_config):
        """Test creating OpenAI provider via factory."""
        provider = create_embeddings_provider("openai", openai_embeddings_config)
        assert isinstance(provider, OpenAIEmbeddings)
    
    def test_create_unknown_provider(self, openai_embeddings_config):
        """Test error when creating unknown provider."""
        with pytest.raises(ValueError):
            create_embeddings_provider("unknown_provider", openai_embeddings_config)
