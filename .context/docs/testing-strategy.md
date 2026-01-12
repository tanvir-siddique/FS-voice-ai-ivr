# Testing Strategy - Voice AI IVR

## Visão Geral

O Voice AI IVR usa uma estratégia de testes em 3 níveis:

1. **Unitários** - Componentes isolados (providers, services)
2. **Integração** - Endpoints API, database
3. **E2E** - Fluxo completo de conversa

## Ferramentas

| Ferramenta | Uso |
|------------|-----|
| pytest | Framework de testes |
| pytest-asyncio | Suporte a async/await |
| pytest-cov | Cobertura de código |
| unittest.mock | Mocking |
| httpx | Cliente HTTP para testes de API |

## Estrutura

```
voice-ai-service/tests/
├── conftest.py           # Fixtures globais
├── unit/
│   ├── test_stt_providers.py
│   ├── test_tts_providers.py
│   ├── test_llm_providers.py
│   ├── test_embeddings_providers.py
│   ├── test_rag_service.py
│   └── test_session_manager.py
├── integration/
│   ├── test_api_transcribe.py
│   ├── test_api_synthesize.py
│   ├── test_api_chat.py
│   └── test_database.py
└── e2e/
    └── test_full_conversation.py
```

## Testes Unitários

### Objetivo
Testar componentes isoladamente, mockando dependências externas.

### Cobertura Atual

| Componente | Arquivos | Cobertura |
|------------|----------|-----------|
| STT Providers | whisper_local, whisper_api | ~80% |
| TTS Providers | piper, openai, elevenlabs | ~80% |
| LLM Providers | openai, anthropic, groq, ollama | ~85% |
| Embeddings | openai, local | ~80% |
| RAG Service | vector_store, embedding_service | ~70% |
| Session Manager | session_manager | ~90% |

### Exemplo

```python
# tests/unit/test_llm_providers.py
import pytest
from unittest.mock import AsyncMock, patch

class TestOpenAILLM:
    @pytest.mark.asyncio
    async def test_chat_basic(self):
        """Testa chat básico sem ação."""
        provider = OpenAILLM({"api_key": "test", "model": "gpt-4"})
        
        with patch.object(provider, 'client') as mock:
            mock.chat.completions.create = AsyncMock(return_value=...)
            
            result = await provider.chat(
                system_prompt="...",
                messages=[{"role": "user", "content": "Olá"}]
            )
            
            assert result.response is not None
```

## Testes de Integração

### Objetivo
Testar endpoints API com banco de dados real (ou mock realista).

### Setup

```python
# conftest.py para integração
@pytest.fixture
async def test_db():
    """Banco de teste isolado."""
    # Criar schema temporário
    await db.execute("CREATE SCHEMA test_voice_ai")
    # Rodar migrations
    await run_migrations()
    yield db
    # Cleanup
    await db.execute("DROP SCHEMA test_voice_ai CASCADE")
```

### Exemplo

```python
# tests/integration/test_api_chat.py
from fastapi.testclient import TestClient

def test_chat_endpoint(client, domain_uuid):
    response = client.post("/api/v1/chat", json={
        "domain_uuid": domain_uuid,
        "message": "Qual o horário de funcionamento?",
        "conversation_id": "test-conv-123"
    })
    
    assert response.status_code == 200
    data = response.json()
    assert "response" in data
```

## Testes E2E

### Objetivo
Testar fluxo completo: FreeSWITCH → Voice AI → resposta.

### Approach
Simular chamadas usando FreeSWITCH test harness ou mocks.

```python
# tests/e2e/test_full_conversation.py
@pytest.mark.e2e
async def test_complete_call_flow():
    """Simula uma conversa completa."""
    # 1. Iniciar sessão
    session = await create_session(domain_uuid, caller_id)
    
    # 2. Saudação
    greeting = await synthesize(domain_uuid, "Olá, como posso ajudar?")
    
    # 3. Transcrever pergunta
    text = await transcribe(domain_uuid, audio_path)
    
    # 4. Chat com IA
    response = await chat(domain_uuid, text, session.id)
    
    # 5. Verificar ação
    if response.action == "transfer":
        # Verificar transferência
        pass
    
    # 6. Cleanup
    await session.close()
```

## Comandos

```bash
# Todos os testes
pytest

# Apenas unitários (rápido)
pytest tests/unit/ -v

# Integração (requer banco)
pytest tests/integration/ -v

# E2E (mais lento)
pytest tests/e2e/ -v -m e2e

# Com cobertura
pytest --cov=services --cov=api --cov-report=html

# Relatório no terminal
pytest --cov=. --cov-report=term-missing

# Testes específicos
pytest tests/unit/test_llm_providers.py::TestOpenAILLM -v
```

## CI/CD

### Pipeline Sugerido

```yaml
# .github/workflows/test.yml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    services:
      postgres:
        image: pgvector/pgvector:pg15
        env:
          POSTGRES_DB: test_voice_ai
          POSTGRES_USER: test
          POSTGRES_PASSWORD: test
    
    steps:
      - uses: actions/checkout@v4
      
      - name: Setup Python
        uses: actions/setup-python@v5
        with:
          python-version: '3.10'
      
      - name: Install dependencies
        run: |
          cd voice-ai-service
          pip install -r requirements.txt
      
      - name: Run tests
        run: |
          cd voice-ai-service
          pytest --cov=. --cov-report=xml
      
      - name: Upload coverage
        uses: codecov/codecov-action@v4
```

## Métricas de Qualidade

| Métrica | Meta | Atual |
|---------|------|-------|
| Cobertura de código | ≥80% | ~75% |
| Testes unitários | 100% providers | ✅ |
| Testes de integração | 100% endpoints | ~80% |
| Testes E2E | Fluxo principal | Em progresso |

## Manutenção

### Ao adicionar novo Provider
1. Criar `tests/unit/test_PROVIDER.py`
2. Testar: inicialização, operação principal, erros

### Ao adicionar novo Endpoint
1. Criar `tests/integration/test_api_ENDPOINT.py`
2. Testar: validação, sucesso, erros, multi-tenant

### Ao modificar código existente
1. Rodar testes existentes
2. Adicionar testes para novos cenários
3. Verificar cobertura não diminuiu
