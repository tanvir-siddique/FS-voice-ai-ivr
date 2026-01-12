"""
Unit tests for LLM providers.
"""

import pytest
from unittest.mock import AsyncMock, MagicMock, patch

from services.llm.base import Message, ChatResult
from services.llm.openai import OpenAILLM
from services.llm.anthropic import AnthropicLLM
from services.llm.groq import GroqLLM
from services.llm.ollama_local import OllamaLLM
from services.llm.factory import create_llm_provider, get_available_providers


class TestOpenAILLM:
    """Tests for OpenAI LLM provider."""
    
    def test_init(self, openai_config):
        """Test provider initialization."""
        provider = OpenAILLM(openai_config)
        assert provider.provider_name == "openai"
        assert provider.config == openai_config
    
    @pytest.mark.asyncio
    async def test_chat_success(self, openai_config):
        """Test successful chat completion."""
        provider = OpenAILLM(openai_config)
        
        # Mock OpenAI response
        mock_response = MagicMock()
        mock_response.choices = [
            MagicMock(
                message=MagicMock(content="Olá! Como posso ajudar?"),
                finish_reason="stop",
            )
        ]
        mock_response.usage = MagicMock(total_tokens=50)
        
        with patch.object(provider, '_get_client') as mock_client:
            mock_client.return_value.chat.completions.create = AsyncMock(
                return_value=mock_response
            )
            
            messages = [Message(role="user", content="Olá")]
            result = await provider.chat(messages)
            
            assert isinstance(result, ChatResult)
            assert result.text == "Olá! Como posso ajudar?"
            assert result.tokens_used == 50
    
    def test_parse_action_transfer(self, openai_config):
        """Test action parsing for transfer."""
        provider = OpenAILLM(openai_config)
        
        action, ext, dept = provider.parse_action(
            "Vou transferir para o suporte. [TRANSFERIR: 200, Suporte]"
        )
        
        assert action == "transfer"
        assert ext == "200"
        assert dept == "Suporte"
    
    def test_parse_action_hangup(self, openai_config):
        """Test action parsing for hangup."""
        provider = OpenAILLM(openai_config)
        
        action, ext, dept = provider.parse_action(
            "Obrigado por ligar! [ENCERRAR]"
        )
        
        assert action == "hangup"
        assert ext is None
    
    def test_parse_action_continue(self, openai_config):
        """Test action parsing for continue."""
        provider = OpenAILLM(openai_config)
        
        action, ext, dept = provider.parse_action(
            "Posso ajudar com mais alguma coisa?"
        )
        
        assert action == "continue"


class TestAnthropicLLM:
    """Tests for Anthropic Claude LLM provider."""
    
    def test_init(self, anthropic_config):
        """Test provider initialization."""
        provider = AnthropicLLM(anthropic_config)
        assert provider.provider_name == "anthropic"
    
    @pytest.mark.asyncio
    async def test_chat_success(self, anthropic_config):
        """Test successful chat completion."""
        provider = AnthropicLLM(anthropic_config)
        
        # Mock Anthropic response
        mock_response = MagicMock()
        mock_response.content = [MagicMock(text="Olá! Em que posso ajudar?")]
        mock_response.usage = MagicMock(input_tokens=10, output_tokens=15)
        mock_response.stop_reason = "end_turn"
        
        with patch.object(provider, '_get_client') as mock_client:
            mock_client.return_value.messages.create = AsyncMock(
                return_value=mock_response
            )
            
            messages = [Message(role="user", content="Olá")]
            result = await provider.chat(messages)
            
            assert isinstance(result, ChatResult)
            assert result.text == "Olá! Em que posso ajudar?"
            assert result.tokens_used == 25


class TestGroqLLM:
    """Tests for Groq LLM provider."""
    
    def test_init(self, groq_config):
        """Test provider initialization."""
        provider = GroqLLM(groq_config)
        assert provider.provider_name == "groq"
    
    @pytest.mark.asyncio
    async def test_chat_success(self, groq_config):
        """Test successful chat completion."""
        provider = GroqLLM(groq_config)
        
        # Mock Groq response (OpenAI-compatible format)
        mock_response = MagicMock()
        mock_response.choices = [
            MagicMock(
                message=MagicMock(content="Olá! Posso ajudar?"),
                finish_reason="stop",
            )
        ]
        mock_response.usage = MagicMock(total_tokens=30)
        
        with patch.object(provider, '_get_client') as mock_client:
            mock_client.return_value.chat.completions.create = AsyncMock(
                return_value=mock_response
            )
            
            messages = [Message(role="user", content="Olá")]
            result = await provider.chat(messages)
            
            assert isinstance(result, ChatResult)
            assert result.text == "Olá! Posso ajudar?"


class TestOllamaLLM:
    """Tests for Ollama local LLM provider."""
    
    def test_init(self, ollama_config):
        """Test provider initialization."""
        provider = OllamaLLM(ollama_config)
        assert provider.provider_name == "ollama_local"
        assert provider.base_url == "http://localhost:11434"
    
    @pytest.mark.asyncio
    async def test_chat_success(self, ollama_config):
        """Test successful chat completion."""
        provider = OllamaLLM(ollama_config)
        
        mock_json = {
            "message": {"content": "Olá! Como posso ajudar?"},
            "done": True,
            "eval_count": 20,
            "prompt_eval_count": 10,
        }
        
        with patch('httpx.AsyncClient') as mock_client:
            mock_response = AsyncMock()
            mock_response.json.return_value = mock_json
            mock_response.raise_for_status = MagicMock()
            
            mock_client.return_value.__aenter__.return_value.post = AsyncMock(
                return_value=mock_response
            )
            
            messages = [Message(role="user", content="Olá")]
            result = await provider.chat(messages)
            
            assert isinstance(result, ChatResult)
            assert result.text == "Olá! Como posso ajudar?"


class TestLLMFactory:
    """Tests for LLM factory."""
    
    def test_get_available_providers(self):
        """Test listing available providers."""
        providers = get_available_providers()
        assert isinstance(providers, list)
    
    def test_create_openai_provider(self, openai_config):
        """Test creating OpenAI provider via factory."""
        provider = create_llm_provider("openai", openai_config)
        assert isinstance(provider, OpenAILLM)
    
    def test_create_unknown_provider(self, openai_config):
        """Test error when creating unknown provider."""
        with pytest.raises(ValueError):
            create_llm_provider("unknown_provider", openai_config)
