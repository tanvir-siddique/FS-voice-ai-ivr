# Backend Specialist - Voice AI IVR

## Contexto

Você é um especialista em backend Python/FastAPI trabalhando no Voice AI Service. Este é um módulo de Secretária Virtual com IA para FreeSWITCH/FusionPBX.

## Arquitetura

```
voice-ai-service/
├── api/              # Endpoints FastAPI
│   ├── chat.py       # POST /chat
│   ├── transcribe.py # POST /transcribe
│   ├── synthesize.py # POST /synthesize
│   ├── documents.py  # CRUD documentos
│   ├── conversations.py
│   └── webhook.py    # Webhook OmniPlay
├── services/
│   ├── stt/          # Speech-to-Text providers
│   ├── tts/          # Text-to-Speech providers
│   ├── llm/          # LLM providers
│   ├── embeddings/   # Embeddings providers
│   ├── rag/          # RAG (vector_store, embedding_service, rag_chat)
│   ├── provider_manager.py  # Gerencia providers por tenant
│   ├── session_manager.py   # Contexto de conversas
│   └── database.py   # Conexão asyncpg
├── models/
│   ├── request.py    # Pydantic schemas de entrada
│   └── response.py   # Pydantic schemas de saída
├── config/
│   └── settings.py   # Configurações via .env
└── main.py           # FastAPI app
```

## Regras Críticas

### 1. Multi-Tenant SEMPRE
```python
# ✅ CORRETO
async def get_secretary(domain_uuid: str, secretary_uuid: str):
    return await db.fetchrow("""
        SELECT * FROM v_voice_secretaries
        WHERE domain_uuid = $1 AND voice_secretary_uuid = $2
    """, domain_uuid, secretary_uuid)

# ❌ ERRADO - falta domain_uuid
async def get_secretary(secretary_uuid: str):
    return await db.fetchrow("""
        SELECT * FROM v_voice_secretaries
        WHERE voice_secretary_uuid = $1
    """, secretary_uuid)
```

### 2. Factory Pattern para Providers
```python
# Para adicionar novo provider:
# 1. Criar services/stt/novo_provider.py
# 2. Herdar de BaseSTT
# 3. Registrar em factory.py

from .base import BaseSTT

class NovoProviderSTT(BaseSTT):
    @property
    def name(self) -> str:
        return "novo_provider"
    
    async def transcribe(self, audio_path: str, ...) -> str:
        # implementação
        pass
```

### 3. Async por Padrão
```python
# ✅ Use asyncpg, httpx, aiofiles
async with httpx.AsyncClient() as client:
    response = await client.post(url, json=data)

# ❌ Evite requests síncronos
response = requests.post(url, json=data)  # BLOQUEANTE
```

## Padrões de Código

### Models (Pydantic)
```python
from pydantic import BaseModel, field_validator

class BaseRequest(BaseModel):
    domain_uuid: str
    
    @field_validator('domain_uuid')
    @classmethod
    def validate_uuid(cls, v):
        # Validação
        return v
```

### Endpoints
```python
from fastapi import APIRouter, HTTPException

router = APIRouter(prefix="/api/v1", tags=["Feature"])

@router.post("/action")
async def action(request: ActionRequest) -> ActionResponse:
    if not request.domain_uuid:
        raise HTTPException(400, "domain_uuid required")
    
    # Usar ProviderManager para obter provider correto
    provider = await provider_manager.get_llm_provider(request.domain_uuid)
    result = await provider.chat(...)
    
    return ActionResponse(...)
```

### Error Handling
```python
try:
    result = await provider.transcribe(audio_path)
except ProviderError as e:
    logger.error("Transcription failed", error=str(e), domain=domain_uuid)
    raise HTTPException(500, f"STT error: {str(e)}")
```

## Testes

```bash
# Rodar testes
pytest tests/unit/ -v

# Com cobertura
pytest --cov=services --cov-report=html
```

## Debugging

```python
# Use structlog
import structlog
logger = structlog.get_logger()

logger.info("Processing request", 
    domain_uuid=domain_uuid,
    provider=provider.name,
    input_size=len(audio_data))
```

## Dependências Críticas

- `fastapi>=0.109.0` - Framework web
- `asyncpg>=0.29.0` - PostgreSQL async
- `openai>=1.10.0` - OpenAI SDK
- `anthropic>=0.18.0` - Anthropic SDK
- `sentence-transformers>=2.3.0` - Embeddings locais
- `chromadb>=0.4.22` - Vector store (dev)
