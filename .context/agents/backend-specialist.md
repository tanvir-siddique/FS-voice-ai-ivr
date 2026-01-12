# Backend Specialist - Voice AI IVR

## Papel
Especialista em desenvolvimento do backend Python/FastAPI, incluindo providers de IA, RAG, e APIs.

## Stack
- **Framework**: FastAPI + Pydantic v2
- **Async**: asyncio, aiohttp
- **Database**: asyncpg (PostgreSQL)
- **Cache**: Redis (aioredis)
- **AI SDKs**: openai, anthropic, google-generativeai, boto3

## Estrutura do Código

```
voice-ai-service/
├── main.py              # App FastAPI
├── api/                 # Routers
│   ├── transcribe.py    # POST /transcribe
│   ├── synthesize.py    # POST /synthesize
│   ├── chat.py          # POST /chat
│   └── documents.py     # CRUD documentos
├── services/
│   ├── provider_manager.py  # Multi-tenant providers
│   ├── session_manager.py   # Contexto de conversa
│   ├── rate_limiter.py      # Rate limiting Redis
│   ├── stt/                 # Speech-to-Text
│   ├── tts/                 # Text-to-Speech
│   ├── llm/                 # LLMs
│   ├── embeddings/          # Embeddings
│   └── rag/                 # RAG pipeline
├── models/              # Pydantic models
└── config/              # Settings
```

## Padrões Importantes

### Factory Pattern (Providers)

```python
# services/llm/factory.py
def create_llm_provider(provider_name: str, config: dict) -> BaseLLM:
    providers = {
        "openai": OpenAILLM,
        "anthropic": AnthropicLLM,
        "groq": GroqLLM,
        # ...
    }
    return providers[provider_name](config)
```

### Multi-Tenant (ProviderManager)

```python
# Sempre buscar provider pelo domain_uuid
provider = await provider_manager.get_llm(domain_uuid)
result = await provider.chat(message, history)
```

### Pydantic v2

```python
from pydantic import BaseModel, field_validator, model_config

class ChatRequest(BaseModel):
    model_config = {"extra": "forbid"}
    
    domain_uuid: UUID
    message: str
    
    @field_validator('domain_uuid')
    @classmethod
    def validate_uuid(cls, v):
        return UUID(str(v))
```

## Tarefas Comuns

### Adicionar Novo Provider

1. Criar classe em `services/{tipo}/{provider}.py`
2. Herdar de `Base{Tipo}` (ex: `BaseLLM`)
3. Implementar métodos abstratos
4. Adicionar ao factory
5. Adicionar testes

### Novo Endpoint

1. Criar router em `api/`
2. Definir request/response em `models/`
3. Incluir router em `main.py`
4. Adicionar rate limiting se necessário

## Debugging

```python
# Logs estruturados
import logging
logger = logging.getLogger(__name__)

logger.info("Processing request", extra={
    "domain_uuid": str(domain_uuid),
    "endpoint": "chat",
})
```

## Testes

```bash
# Rodar testes
pytest tests/unit/test_llm_providers.py -v

# Com coverage
pytest --cov=services --cov-report=html
```

## Cuidados

- ✅ Sempre validar `domain_uuid`
- ✅ Usar `async/await` para I/O
- ✅ Tratar timeouts (AI providers são lentos)
- ✅ Log sem dados sensíveis
- ❌ Nunca hardcodar API keys
- ❌ Nunca confiar em input do cliente

---
*Playbook para: Backend Specialist*
