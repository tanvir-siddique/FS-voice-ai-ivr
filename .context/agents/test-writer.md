# Test Writer - Voice AI IVR

## Papel
Escrever e manter testes unitários e de integração.

## Framework
- **pytest** + pytest-asyncio
- **unittest.mock** para mocks
- Coverage target: **80%**

## Estrutura de Testes

```
voice-ai-service/tests/
├── conftest.py                    # Fixtures compartilhadas
└── unit/
    ├── test_llm_providers.py
    ├── test_stt_providers.py
    ├── test_tts_providers.py
    ├── test_embeddings_providers.py
    ├── test_rag_service.py
    ├── test_session_manager.py
    └── test_rate_limiter.py
```

## Fixtures Padrão

```python
# conftest.py
import pytest
from unittest.mock import AsyncMock, patch

@pytest.fixture
def mock_db():
    """Mock database pool"""
    with patch('services.database.db_pool') as mock:
        yield mock

@pytest.fixture
def openai_config():
    return {"api_key": "test-key", "model": "gpt-4o-mini"}

@pytest.fixture
def sample_domain_uuid():
    return "12345678-1234-1234-1234-123456789012"
```

## Padrões de Teste

### Teste de Provider

```python
class TestOpenAILLM:
    @pytest.mark.asyncio
    async def test_chat_completion_success(self, openai_config):
        # Arrange
        with patch('openai.AsyncOpenAI') as mock_client:
            mock_client.return_value.chat.completions.create = AsyncMock(
                return_value=MagicMock(
                    choices=[MagicMock(message=MagicMock(content="OK"))]
                )
            )
            llm = OpenAILLM(openai_config)
            
            # Act
            result = await llm.chat("Hello", [])
            
            # Assert
            assert result.response == "OK"

    @pytest.mark.asyncio
    async def test_chat_completion_timeout(self, openai_config):
        with patch('openai.AsyncOpenAI') as mock_client:
            mock_client.return_value.chat.completions.create = AsyncMock(
                side_effect=asyncio.TimeoutError()
            )
            llm = OpenAILLM(openai_config)
            
            with pytest.raises(asyncio.TimeoutError):
                await llm.chat("Hello", [])
```

### Teste de API Endpoint

```python
from fastapi.testclient import TestClient

def test_transcribe_endpoint():
    client = TestClient(app)
    
    response = client.post("/transcribe", json={
        "domain_uuid": "test-uuid",
        "audio_base64": "...",
        "format": "wav"
    })
    
    assert response.status_code == 200
    assert "text" in response.json()
```

### Teste de Rate Limiter

```python
@pytest.mark.asyncio
async def test_rate_limit_exceeded():
    limiter = RateLimiter()
    domain = "test-domain"
    
    # Exhaust limit
    for _ in range(100):
        await limiter.increment(domain, "chat")
    
    # Should be limited
    allowed = await limiter.check(domain, "chat")
    assert allowed is False
```

## Mocking External Services

```python
# Mock OpenAI
@pytest.fixture
def mock_openai():
    with patch('services.llm.openai.AsyncOpenAI') as mock:
        mock.return_value.chat.completions.create = AsyncMock(...)
        yield mock

# Mock Redis
@pytest.fixture
def mock_redis():
    with patch('aioredis.from_url') as mock:
        mock.return_value.get = AsyncMock(return_value=None)
        mock.return_value.set = AsyncMock()
        yield mock
```

## Comandos

```bash
# Rodar todos
pytest

# Com coverage
pytest --cov=services --cov-report=html

# Específico
pytest tests/unit/test_llm_providers.py -v

# Com output
pytest -v -s

# Por marker
pytest -m "not slow"
```

## Convenções

- Nome de arquivo: `test_*.py`
- Nome de classe: `Test{Componente}`
- Nome de método: `test_{comportamento}_{contexto}`
- Arrange → Act → Assert
- Um assert por teste (idealmente)

---
*Playbook para: Test Writer*
