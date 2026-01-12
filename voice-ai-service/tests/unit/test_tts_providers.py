"""
Unit tests for TTS providers.
"""

import pytest
from unittest.mock import AsyncMock, MagicMock, patch
import tempfile
import os

from services.tts.base import SynthesisResult, VoiceInfo
from services.tts.openai_tts import OpenAITTS
from services.tts.elevenlabs import ElevenLabsTTS
from services.tts.factory import create_tts_provider, get_available_providers


class TestOpenAITTS:
    """Tests for OpenAI TTS provider."""
    
    def test_init(self, openai_tts_config):
        """Test provider initialization."""
        provider = OpenAITTS(openai_tts_config)
        assert provider.provider_name == "openai_tts"
    
    def test_voices_list(self, openai_tts_config):
        """Test that all voices are defined."""
        provider = OpenAITTS(openai_tts_config)
        assert len(provider.VOICES) == 6
        assert "nova" in provider.VOICES
        assert "alloy" in provider.VOICES
    
    @pytest.mark.asyncio
    async def test_list_voices(self, openai_tts_config):
        """Test listing available voices."""
        provider = OpenAITTS(openai_tts_config)
        
        voices = await provider.list_voices()
        
        assert len(voices) == 6
        assert all(isinstance(v, VoiceInfo) for v in voices)
        
        voice_ids = [v.voice_id for v in voices]
        assert "nova" in voice_ids
        assert "alloy" in voice_ids
    
    @pytest.mark.asyncio
    async def test_synthesize_success(self, openai_tts_config):
        """Test successful synthesis."""
        provider = OpenAITTS(openai_tts_config)
        
        with tempfile.TemporaryDirectory() as tmpdir:
            output_path = os.path.join(tmpdir, "output.mp3")
            
            # Mock streaming response
            mock_stream = AsyncMock()
            
            async def mock_iter_bytes():
                yield b"fake audio data"
            
            mock_stream.iter_bytes = mock_iter_bytes
            mock_stream.__aenter__ = AsyncMock(return_value=mock_stream)
            mock_stream.__aexit__ = AsyncMock(return_value=None)
            
            with patch.object(provider, '_get_client') as mock_client:
                mock_client.return_value.audio.speech.with_streaming_response.create.return_value = mock_stream
                
                result = await provider.synthesize(
                    text="Olá, como você está?",
                    output_path=output_path,
                )
                
                assert isinstance(result, SynthesisResult)
                assert result.format == "mp3"


class TestElevenLabsTTS:
    """Tests for ElevenLabs TTS provider."""
    
    def test_init(self, elevenlabs_config):
        """Test provider initialization."""
        provider = ElevenLabsTTS(elevenlabs_config)
        assert provider.provider_name == "elevenlabs"
        assert provider.api_key == "test-api-key"
    
    def test_headers(self, elevenlabs_config):
        """Test API headers generation."""
        provider = ElevenLabsTTS(elevenlabs_config)
        
        headers = provider._get_headers()
        
        assert "xi-api-key" in headers
        assert headers["xi-api-key"] == "test-api-key"
        assert headers["Accept"] == "audio/mpeg"
    
    @pytest.mark.asyncio
    async def test_is_available_no_key(self):
        """Test availability check without API key."""
        provider = ElevenLabsTTS({})
        
        available = await provider.is_available()
        
        assert available is False


class TestTTSFactory:
    """Tests for TTS factory."""
    
    def test_get_available_providers(self):
        """Test listing available providers."""
        providers = get_available_providers()
        assert isinstance(providers, list)
    
    def test_create_openai_tts_provider(self, openai_tts_config):
        """Test creating OpenAI TTS provider via factory."""
        provider = create_tts_provider("openai_tts", openai_tts_config)
        assert isinstance(provider, OpenAITTS)
    
    def test_create_elevenlabs_provider(self, elevenlabs_config):
        """Test creating ElevenLabs provider via factory."""
        provider = create_tts_provider("elevenlabs", elevenlabs_config)
        assert isinstance(provider, ElevenLabsTTS)
    
    def test_create_unknown_provider(self, openai_tts_config):
        """Test error when creating unknown provider."""
        with pytest.raises(ValueError):
            create_tts_provider("unknown_provider", openai_tts_config)
