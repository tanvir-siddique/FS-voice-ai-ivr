# Test Writer - Voice AI IVR

## Contexto

Você escreve testes para o Voice AI IVR. O projeto usa pytest para Python e estrutura de testes em `voice-ai-service/tests/`.

## Estrutura de Testes

```
voice-ai-service/tests/
├── conftest.py           # Fixtures compartilhadas
├── unit/
│   ├── test_stt_providers.py
│   ├── test_tts_providers.py
│   ├── test_llm_providers.py
│   ├── test_embeddings_providers.py
│   ├── test_rag_service.py
│   └── test_session_manager.py
├── integration/
│   ├── test_api_transcribe.py
│   ├── test_api_chat.py
│   └── test_database.py
└── e2e/
    └── test_full_conversation.py
```

## Fixtures Principais

```python
# conftest.py
import pytest
from unittest.mock import AsyncMock, MagicMock

@pytest.fixture
def domain_uuid():
    """UUID de teste para multi-tenant."""
    return "test-domain-uuid-1234"

@pytest.fixture
def mock_db():
    """Mock do banco de dados."""
    db = AsyncMock()
    db.fetchrow = AsyncMock(return_value={
        "provider_name": "openai",
        "config": {"api_key": "test-key"}
    })
    return db

@pytest.fixture
def mock_openai_client():
    """Mock do cliente OpenAI."""
    client = MagicMock()
    client.chat.completions.create = AsyncMock(return_value=MagicMock(
        choices=[MagicMock(message=MagicMock(content="Resposta teste"))]
    ))
    return client
```

## Padrões de Teste

### Teste de Provider (Unitário)

```python
# test_llm_providers.py
import pytest
from unittest.mock import AsyncMock, patch
from services.llm.openai import OpenAILLM

class TestOpenAILLM:
    @pytest.fixture
    def provider(self):
        return OpenAILLM({"api_key": "test-key", "model": "gpt-4"})
    
    @pytest.mark.asyncio
    async def test_chat_returns_response(self, provider):
        with patch.object(provider, 'client') as mock_client:
            mock_client.chat.completions.create = AsyncMock(
                return_value=MagicMock(
                    choices=[MagicMock(message=MagicMock(content="Olá!"))]
                )
            )
            
            result = await provider.chat(
                system_prompt="Você é uma secretária.",
                messages=[{"role": "user", "content": "Olá"}]
            )
            
            assert result.response == "Olá!"
            assert result.action is None
    
    @pytest.mark.asyncio
    async def test_chat_parses_transfer_action(self, provider):
        with patch.object(provider, 'client') as mock_client:
            mock_client.chat.completions.create = AsyncMock(
                return_value=MagicMock(
                    choices=[MagicMock(message=MagicMock(
                        content='Transferindo... {"action": "transfer", "target": "200"}'
                    ))]
                )
            )
            
            result = await provider.chat(
                system_prompt="...",
                messages=[{"role": "user", "content": "Falar com vendas"}]
            )
            
            assert result.action == "transfer"
            assert result.action_data["target"] == "200"
```

### Teste de API (Integração)

```python
# test_api_chat.py
import pytest
from fastapi.testclient import TestClient
from unittest.mock import AsyncMock, patch
from main import app

@pytest.fixture
def client():
    return TestClient(app)

class TestChatEndpoint:
    def test_chat_requires_domain_uuid(self, client):
        response = client.post("/api/v1/chat", json={
            "message": "Olá"
            # Falta domain_uuid
        })
        assert response.status_code == 422
    
    def test_chat_success(self, client, domain_uuid):
        with patch('api.chat.provider_manager') as mock_pm:
            mock_provider = AsyncMock()
            mock_provider.chat.return_value = MagicMock(
                response="Olá! Como posso ajudar?",
                action=None
            )
            mock_pm.get_llm_provider.return_value = mock_provider
            
            response = client.post("/api/v1/chat", json={
                "domain_uuid": domain_uuid,
                "message": "Olá"
            })
            
            assert response.status_code == 200
            assert "Olá" in response.json()["response"]
```

### Teste Multi-Tenant

```python
class TestMultiTenant:
    @pytest.mark.asyncio
    async def test_data_isolation(self, mock_db):
        """Verifica que tenant A não vê dados do tenant B."""
        domain_a = "domain-a-uuid"
        domain_b = "domain-b-uuid"
        
        # Configurar mock para retornar dados diferentes por domain
        async def fetch_by_domain(query, domain_uuid):
            if domain_uuid == domain_a:
                return {"name": "Secretária A"}
            elif domain_uuid == domain_b:
                return {"name": "Secretária B"}
            return None
        
        mock_db.fetchrow.side_effect = fetch_by_domain
        
        result_a = await mock_db.fetchrow("...", domain_a)
        result_b = await mock_db.fetchrow("...", domain_b)
        
        assert result_a["name"] == "Secretária A"
        assert result_b["name"] == "Secretária B"
```

## Comandos

```bash
# Rodar todos os testes
pytest

# Com verbose
pytest -v

# Apenas unitários
pytest tests/unit/

# Arquivo específico
pytest tests/unit/test_llm_providers.py

# Teste específico
pytest tests/unit/test_llm_providers.py::TestOpenAILLM::test_chat_returns_response

# Com cobertura
pytest --cov=services --cov-report=html

# Ver cobertura no terminal
pytest --cov=services --cov-report=term-missing
```

## Checklist de Testes

### Para cada Provider novo:
- [ ] Teste de inicialização
- [ ] Teste de operação principal (transcribe/synthesize/chat/embed)
- [ ] Teste de health_check
- [ ] Teste de erro (API key inválida, timeout, etc)

### Para cada Endpoint novo:
- [ ] Teste de validação (domain_uuid obrigatório)
- [ ] Teste de sucesso
- [ ] Teste de erro
- [ ] Teste de autenticação (se aplicável)

### Para cada Feature:
- [ ] Testes unitários dos componentes
- [ ] Teste de integração do fluxo completo
- [ ] Teste multi-tenant (isolamento)
