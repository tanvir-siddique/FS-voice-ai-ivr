"""
Unit tests for STT providers.
"""

import pytest
from unittest.mock import AsyncMock, MagicMock, patch
import tempfile
import os

from services.stt.base import TranscriptionResult
from services.stt.whisper_api import OpenAIWhisperSTT
from services.stt.factory import create_stt_provider, get_available_providers


class TestOpenAIWhisperSTT:
    """Tests for OpenAI Whisper API STT provider."""
    
    def test_init(self, whisper_api_config):
        """Test provider initialization."""
        provider = OpenAIWhisperSTT(whisper_api_config)
        assert provider.provider_name == "whisper_api"
    
    @pytest.mark.asyncio
    async def test_transcribe_success(self, whisper_api_config):
        """Test successful transcription."""
        provider = OpenAIWhisperSTT(whisper_api_config)
        
        # Create temp audio file
        with tempfile.NamedTemporaryFile(suffix=".mp3", delete=False) as f:
            f.write(b"fake audio data")
            audio_path = f.name
        
        try:
            # Mock OpenAI response
            mock_response = MagicMock()
            mock_response.text = "Olá, como você está?"
            mock_response.language = "pt"
            mock_response.duration = 2.5
            
            with patch.object(provider, '_get_client') as mock_client:
                mock_client.return_value.audio.transcriptions.create = AsyncMock(
                    return_value=mock_response
                )
                
                result = await provider.transcribe(audio_path)
                
                assert isinstance(result, TranscriptionResult)
                assert result.text == "Olá, como você está?"
                assert result.language == "pt"
                
        finally:
            os.unlink(audio_path)
    
    @pytest.mark.asyncio
    async def test_transcribe_file_not_found(self, whisper_api_config):
        """Test error when audio file not found."""
        provider = OpenAIWhisperSTT(whisper_api_config)
        
        with pytest.raises(FileNotFoundError):
            await provider.transcribe("/nonexistent/audio.mp3")


class TestSTTFactory:
    """Tests for STT factory."""
    
    def test_get_available_providers(self):
        """Test listing available providers."""
        providers = get_available_providers()
        assert isinstance(providers, list)
    
    def test_create_whisper_api_provider(self, whisper_api_config):
        """Test creating Whisper API provider via factory."""
        provider = create_stt_provider("whisper_api", whisper_api_config)
        assert isinstance(provider, OpenAIWhisperSTT)
    
    def test_create_unknown_provider(self, whisper_api_config):
        """Test error when creating unknown provider."""
        with pytest.raises(ValueError):
            create_stt_provider("unknown_provider", whisper_api_config)
