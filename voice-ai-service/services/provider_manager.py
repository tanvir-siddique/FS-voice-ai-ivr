"""
Provider Manager Service.

Manages provider selection and fallback for multi-tenant Voice AI.

⚠️ MULTI-TENANT: ALL operations require domain_uuid.
"""

from typing import Optional, Dict, Any
from uuid import UUID
import logging

from .database import db
from .stt import create_stt_provider, BaseSTT
from .tts import create_tts_provider, BaseTTS
from .llm import create_llm_provider, BaseLLM
from .embeddings import create_embeddings_provider, BaseEmbeddings

logger = logging.getLogger(__name__)


class ProviderManager:
    """
    Manages AI providers for Voice AI.
    
    Handles:
    - Loading provider configurations from database
    - Creating provider instances
    - Fallback between providers on failure
    
    ⚠️ MULTI-TENANT: All methods require domain_uuid.
    """
    
    # Default configurations for fallback
    DEFAULT_CONFIGS = {
        "stt": {
            "whisper_local": {"model": "base", "device": "cpu"},
        },
        "tts": {
            "piper_local": {"model": "pt_BR-faber-medium"},
        },
        "llm": {
            "ollama_local": {"base_url": "http://localhost:11434", "model": "llama3"},
        },
        "embeddings": {
            "local_embeddings": {"model": "all-MiniLM-L6-v2"},
        },
    }
    
    async def get_stt_provider(
        self,
        domain_uuid: UUID,
        provider_name: Optional[str] = None,
    ) -> BaseSTT:
        """
        Get STT provider for a domain.
        
        Args:
            domain_uuid: REQUIRED - Domain UUID
            provider_name: Optional specific provider
            
        Returns:
            Configured STT provider
        """
        return await self._get_provider(
            domain_uuid=domain_uuid,
            provider_type="stt",
            provider_name=provider_name,
            factory_func=create_stt_provider,
        )
    
    async def get_tts_provider(
        self,
        domain_uuid: UUID,
        provider_name: Optional[str] = None,
    ) -> BaseTTS:
        """
        Get TTS provider for a domain.
        
        Args:
            domain_uuid: REQUIRED - Domain UUID
            provider_name: Optional specific provider
            
        Returns:
            Configured TTS provider
        """
        return await self._get_provider(
            domain_uuid=domain_uuid,
            provider_type="tts",
            provider_name=provider_name,
            factory_func=create_tts_provider,
        )
    
    async def get_llm_provider(
        self,
        domain_uuid: UUID,
        provider_name: Optional[str] = None,
    ) -> BaseLLM:
        """
        Get LLM provider for a domain.
        
        Args:
            domain_uuid: REQUIRED - Domain UUID
            provider_name: Optional specific provider
            
        Returns:
            Configured LLM provider
        """
        return await self._get_provider(
            domain_uuid=domain_uuid,
            provider_type="llm",
            provider_name=provider_name,
            factory_func=create_llm_provider,
        )
    
    async def get_embeddings_provider(
        self,
        domain_uuid: UUID,
        provider_name: Optional[str] = None,
    ) -> BaseEmbeddings:
        """
        Get Embeddings provider for a domain.
        
        Args:
            domain_uuid: REQUIRED - Domain UUID
            provider_name: Optional specific provider
            
        Returns:
            Configured Embeddings provider
        """
        return await self._get_provider(
            domain_uuid=domain_uuid,
            provider_type="embeddings",
            provider_name=provider_name,
            factory_func=create_embeddings_provider,
        )
    
    async def _get_provider(
        self,
        domain_uuid: UUID,
        provider_type: str,
        provider_name: Optional[str],
        factory_func,
    ):
        """
        Generic provider getter with database lookup and fallback.
        
        Args:
            domain_uuid: Domain UUID (REQUIRED)
            provider_type: Type of provider (stt, tts, llm, embeddings)
            provider_name: Specific provider name (optional)
            factory_func: Factory function to create provider
            
        Returns:
            Configured provider instance
        """
        if not domain_uuid:
            raise ValueError("domain_uuid is required for multi-tenant isolation")
        
        # Try to load from database
        try:
            provider_config = await db.get_provider_config(
                domain_uuid=domain_uuid,
                provider_type=provider_type,
                provider_name=provider_name,
            )
            
            if provider_config:
                name = provider_config["provider_name"]
                config = provider_config["config"]
                
                logger.info(f"Using {provider_type} provider: {name} for domain {domain_uuid}")
                return factory_func(name, config)
                
        except Exception as e:
            logger.warning(f"Failed to load {provider_type} config from database: {e}")
        
        # Fallback to default local provider
        defaults = self.DEFAULT_CONFIGS.get(provider_type, {})
        if defaults:
            default_name = list(defaults.keys())[0]
            default_config = defaults[default_name]
            
            logger.warning(f"Using fallback {provider_type} provider: {default_name}")
            return factory_func(default_name, default_config)
        
        raise ValueError(f"No {provider_type} provider available for domain {domain_uuid}")


# Singleton instance
provider_manager = ProviderManager()
