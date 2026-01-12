"""
Pytest configuration and fixtures.
"""

import pytest
import asyncio
from uuid import uuid4


@pytest.fixture(scope="session")
def event_loop():
    """Create event loop for async tests."""
    loop = asyncio.get_event_loop_policy().new_event_loop()
    yield loop
    loop.close()


@pytest.fixture
def sample_domain_uuid():
    """Generate a sample domain UUID for testing."""
    return uuid4()


@pytest.fixture
def openai_config():
    """OpenAI provider configuration for testing."""
    return {
        "api_key": "test-api-key",
        "model": "gpt-4o-mini",
    }


@pytest.fixture
def anthropic_config():
    """Anthropic provider configuration for testing."""
    return {
        "api_key": "test-api-key",
        "model": "claude-sonnet-4-20250514",
    }


@pytest.fixture
def groq_config():
    """Groq provider configuration for testing."""
    return {
        "api_key": "test-api-key",
        "model": "llama-3.1-70b-versatile",
    }


@pytest.fixture
def ollama_config():
    """Ollama local provider configuration for testing."""
    return {
        "base_url": "http://localhost:11434",
        "model": "llama3",
    }


@pytest.fixture
def whisper_api_config():
    """OpenAI Whisper API configuration for testing."""
    return {
        "api_key": "test-api-key",
        "model": "whisper-1",
        "language": "pt",
    }


@pytest.fixture
def openai_tts_config():
    """OpenAI TTS configuration for testing."""
    return {
        "api_key": "test-api-key",
        "model": "tts-1",
        "voice": "nova",
    }


@pytest.fixture
def elevenlabs_config():
    """ElevenLabs configuration for testing."""
    return {
        "api_key": "test-api-key",
        "voice_id": "21m00Tcm4TlvDq8ikWAM",
        "model_id": "eleven_multilingual_v2",
    }


@pytest.fixture
def piper_config():
    """Piper local TTS configuration for testing."""
    return {
        "model_path": "/tmp/pt_BR-faber-medium.onnx",
        "speaker": 0,
    }


@pytest.fixture
def openai_embeddings_config():
    """OpenAI Embeddings configuration for testing."""
    return {
        "api_key": "test-api-key",
        "model": "text-embedding-3-small",
    }


@pytest.fixture
def local_embeddings_config():
    """Local embeddings configuration for testing."""
    return {
        "model": "all-MiniLM-L6-v2",
        "device": "cpu",
    }
