# Testing Strategy - Voice AI IVR

## Pirâmide de Testes

```
        ┌───────────────┐
        │     E2E       │  ← Poucos, lentos, caros
        │   (manual)    │
        ├───────────────┤
        │  Integration  │  ← Alguns, com mocks
        │    Tests      │
        ├───────────────┤
        │    Unit       │  ← Muitos, rápidos
        │    Tests      │
        └───────────────┘
```

## Unit Tests

### Estrutura

```
voice-ai-service/tests/
├── conftest.py                    # Fixtures compartilhadas
└── unit/
    ├── test_llm_providers.py      # LLM providers
    ├── test_stt_providers.py      # STT providers
    ├── test_tts_providers.py      # TTS providers
    ├── test_embeddings_providers.py
    ├── test_rag_service.py        # RAG pipeline
    ├── test_session_manager.py    # Session handling
    └── test_rate_limiter.py       # Rate limiting
```

### Fixtures

```python
# conftest.py
@pytest.fixture
def mock_db():
    """Mock database connection"""
    with patch('services.database.get_pool') as mock:
        yield mock

@pytest.fixture
def openai_config():
    """Config para testes OpenAI"""
    return {"api_key": "test-key", "model": "gpt-4o-mini"}

@pytest.fixture
def sample_audio():
    """Audio de teste"""
    return base64.b64encode(b"RIFF...").decode()
```

### Exemplos de Testes

```python
# test_llm_providers.py
class TestOpenAILLM:
    @pytest.mark.asyncio
    async def test_chat_completion(self, openai_config):
        with patch.object(AsyncOpenAI, 'chat') as mock:
            mock.completions.create = AsyncMock(return_value=...)
            
            llm = OpenAILLM(openai_config)
            result = await llm.chat("Hello", [])
            
            assert result.response is not None
            assert result.action is not None

    @pytest.mark.asyncio
    async def test_parse_action_transfer(self, openai_config):
        llm = OpenAILLM(openai_config)
        response = "Vou transferir para o setor comercial [TRANSFER:1001]"
        
        action = llm.parse_action(response)
        
        assert action["type"] == "transfer"
        assert action["target"] == "1001"
```

### Rodar Testes

```bash
# Todos os testes
pytest

# Com coverage
pytest --cov=services --cov-report=html

# Testes específicos
pytest tests/unit/test_llm_providers.py -v

# Por marker
pytest -m "not slow"

# Verbose com output
pytest -v -s
```

## Integration Tests

### API Tests

```python
# test_api_integration.py
from fastapi.testclient import TestClient
from main import app

client = TestClient(app)

def test_transcribe_endpoint():
    response = client.post("/transcribe", json={
        "domain_uuid": "test-domain-uuid",
        "audio_base64": "...",
        "format": "wav"
    })
    assert response.status_code == 200
    assert "text" in response.json()

def test_chat_with_rag():
    # Setup: upload document first
    # ...
    
    response = client.post("/chat", json={
        "domain_uuid": "test-domain-uuid",
        "secretary_uuid": "test-secretary-uuid",
        "message": "Qual o horário?",
        "history": []
    })
    
    assert response.status_code == 200
    assert "response" in response.json()
```

### Database Tests

```python
# test_database_integration.py
@pytest.mark.asyncio
async def test_provider_config_retrieval():
    async with get_pool() as pool:
        async with pool.acquire() as conn:
            result = await conn.fetchrow(
                "SELECT * FROM v_voice_ai_providers WHERE domain_uuid = $1",
                "test-domain-uuid"
            )
            assert result is not None
```

## E2E Tests (Manual)

### Checklist de Teste de Chamada

```markdown
## Teste Turn-based

- [ ] Ligar para ramal 8000
- [ ] Ouvir saudação
- [ ] Falar pergunta
- [ ] Receber resposta coerente
- [ ] Pedir transferência
- [ ] Verificar transferência funciona
- [ ] Verificar log de conversa no FusionPBX

## Teste Realtime

- [ ] Ligar para ramal 8001 (realtime)
- [ ] Conversar naturalmente
- [ ] Testar barge-in (interromper IA)
- [ ] Verificar latência < 500ms
- [ ] Testar full-duplex
- [ ] Verificar function calling (transfer)
```

## Mocking

### Providers Externos

```python
# Mock OpenAI
@pytest.fixture
def mock_openai():
    with patch('openai.AsyncOpenAI') as mock:
        mock.return_value.chat.completions.create = AsyncMock(
            return_value=MagicMock(
                choices=[MagicMock(
                    message=MagicMock(content="Resposta mock")
                )]
            )
        )
        yield mock
```

### Database

```python
# Mock asyncpg
@pytest.fixture
def mock_db_pool():
    mock_pool = AsyncMock()
    mock_conn = AsyncMock()
    mock_pool.acquire.return_value.__aenter__.return_value = mock_conn
    mock_conn.fetchrow.return_value = {
        "provider_uuid": "...",
        "config": {"api_key": "..."}
    }
    
    with patch('services.database.db_pool', mock_pool):
        yield mock_pool
```

## CI/CD Integration

### GitHub Actions

```yaml
# .github/workflows/test.yml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    services:
      redis:
        image: redis:7-alpine
        ports:
          - 6379:6379
    
    steps:
      - uses: actions/checkout@v4
      
      - name: Setup Python
        uses: actions/setup-python@v4
        with:
          python-version: '3.11'
      
      - name: Install dependencies
        run: |
          cd voice-ai-service
          pip install -r requirements.txt
          pip install pytest pytest-cov pytest-asyncio
      
      - name: Run tests
        run: |
          cd voice-ai-service
          pytest --cov=services --cov-report=xml
      
      - name: Upload coverage
        uses: codecov/codecov-action@v3
```

## Test Data

### Fixtures de Áudio

```python
# tests/fixtures/
# - sample_speech.wav (5s de fala)
# - silence.wav (silêncio)
# - noise.wav (ruído)
# - long_speech.wav (60s)
```

### Fixtures de Documentos

```python
# tests/fixtures/
# - sample.pdf (2 páginas)
# - sample.docx (1 página)
# - sample.txt (1KB)
# - large.pdf (100 páginas)
```

## Métricas de Qualidade

| Métrica | Target | Atual |
|---------|--------|-------|
| Code Coverage | > 80% | - |
| Unit Tests | > 100 | - |
| Integration Tests | > 20 | - |
| Flaky Tests | 0 | - |
| Test Runtime | < 5min | - |

---
*Gerado em: 2026-01-12*
