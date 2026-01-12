# Voice AI Service - API Documentation

## Overview

Voice AI Service é uma API REST para processamento de voz com IA, integrada ao FreeSWITCH/FusionPBX.

**Base URL:** `http://localhost:8100/api/v1`

**OpenAPI/Swagger:** `http://localhost:8100/docs`

**ReDoc:** `http://localhost:8100/redoc`

---

## Authentication

⚠️ **MULTI-TENANT OBRIGATÓRIO**

Todos os endpoints requerem `domain_uuid` para isolamento de dados entre tenants.

```json
{
  "domain_uuid": "seu-domain-uuid-aqui",
  // outros campos...
}
```

---

## Rate Limiting

Limites por domínio (retornados em headers):

| Endpoint | Limite/min | Limite/hora | Limite/dia |
|----------|------------|-------------|------------|
| `/transcribe` | 30 | 500 | 5.000 |
| `/synthesize` | 60 | 1.000 | 10.000 |
| `/chat` | 60 | 1.000 | 10.000 |
| `/documents` | 10 | 100 | 500 |

**Headers de resposta:**
```
X-RateLimit-Remaining-Minute: 59
X-RateLimit-Remaining-Hour: 999
```

**Erro 429 (Too Many Requests):**
```json
{
  "error": "Rate limit exceeded",
  "detail": "Too many requests for transcribe",
  "retry_after": 45,
  "limit_type": "minute"
}
```

---

## Endpoints

### Health Check

```http
GET /health
```

**Response:**
```json
{
  "status": "healthy",
  "service": "voice-ai-service",
  "version": "1.0.0"
}
```

---

### STT (Speech-to-Text)

#### Transcrever Áudio

```http
POST /api/v1/transcribe
Content-Type: application/json
```

**Request:**
```json
{
  "domain_uuid": "uuid",
  "audio_path": "/path/to/audio.wav",
  "language": "pt-BR",
  "provider": "whisper_local"  // opcional
}
```

**Response:**
```json
{
  "text": "Olá, qual o horário de funcionamento?",
  "language": "pt",
  "duration": 2.5,
  "confidence": 0.95,
  "provider": "whisper_local"
}
```

**Providers suportados:**
- `whisper_local` - Whisper.cpp/faster-whisper
- `whisper_api` - OpenAI Whisper API
- `azure_speech` - Azure Speech-to-Text
- `google_speech` - Google Cloud STT
- `aws_transcribe` - AWS Transcribe
- `deepgram` - Deepgram Nova

---

### TTS (Text-to-Speech)

#### Sintetizar Voz

```http
POST /api/v1/synthesize
Content-Type: application/json
```

**Request:**
```json
{
  "domain_uuid": "uuid",
  "text": "Bom dia! Como posso ajudar?",
  "voice": "pt_BR-faber-medium",
  "output_format": "wav",
  "provider": "piper_local"  // opcional
}
```

**Response:**
```json
{
  "audio_path": "/tmp/voice-ai/audio_abc123.wav",
  "duration": 1.8,
  "format": "wav",
  "provider": "piper_local"
}
```

**Providers suportados:**
- `piper_local` - Piper TTS
- `coqui_local` - Coqui TTS
- `openai_tts` - OpenAI TTS
- `elevenlabs` - ElevenLabs
- `azure_neural` - Azure Neural TTS
- `google_tts` - Google Cloud TTS
- `aws_polly` - AWS Polly
- `playht` - Play.ht

---

### LLM (Chat)

#### Conversar com IA

```http
POST /api/v1/chat
Content-Type: application/json
```

**Request:**
```json
{
  "domain_uuid": "uuid",
  "secretary_uuid": "uuid",  // opcional
  "message": "Qual o horário de funcionamento?",
  "conversation_id": "conv-123",  // opcional
  "include_rag": true,  // buscar no knowledge base
  "provider": "openai"  // opcional
}
```

**Response:**
```json
{
  "response": "Nosso horário de funcionamento é das 9h às 18h, de segunda a sexta.",
  "action": null,
  "action_data": null,
  "provider": "openai",
  "tokens_used": 150,
  "rag_sources": [
    {
      "content": "Horário: 9h-18h, seg-sex",
      "similarity": 0.92
    }
  ]
}
```

**Ações possíveis:**
- `null` - Continuar conversa
- `"transfer"` - Transferir chamada (action_data: `{"target": "200"}`)
- `"hangup"` - Encerrar chamada

**Providers suportados:**
- `openai` - OpenAI GPT-4o/GPT-4o-mini
- `azure_openai` - Azure OpenAI
- `anthropic` - Anthropic Claude
- `google_gemini` - Google Gemini
- `aws_bedrock` - AWS Bedrock
- `groq` - Groq (Llama ultra-rápido)
- `ollama_local` - Ollama local
- `lmstudio_local` - LM Studio local

---

### Documents (Knowledge Base)

#### Upload de Documento

```http
POST /api/v1/documents
Content-Type: application/json
```

**Request:**
```json
{
  "domain_uuid": "uuid",
  "document_name": "FAQ Empresa",
  "content": "Conteúdo do documento em texto...",
  "file_type": "txt"
}
```

**Response:**
```json
{
  "document_id": "doc-uuid",
  "document_name": "FAQ Empresa",
  "chunk_count": 15,
  "status": "processed"
}
```

#### Listar Documentos

```http
GET /api/v1/documents?domain_uuid=uuid
```

**Response:**
```json
{
  "documents": [
    {
      "document_id": "uuid",
      "document_name": "FAQ",
      "status": "processed",
      "chunk_count": 15
    }
  ],
  "total": 1
}
```

#### Ver Chunks de Documento

```http
GET /api/v1/documents/{document_id}/chunks?domain_uuid=uuid&limit=10&offset=0
```

**Response:**
```json
{
  "document_id": "uuid",
  "document_name": "FAQ",
  "chunks": [
    {
      "chunk_uuid": "uuid",
      "chunk_index": 0,
      "content": "Horário de funcionamento...",
      "token_count": 50
    }
  ],
  "total_chunks": 15
}
```

#### Deletar Documento

```http
DELETE /api/v1/documents/{document_id}?domain_uuid=uuid
```

---

### Conversations

#### Salvar Conversa

```http
POST /api/v1/conversations
Content-Type: application/json
```

**Request:**
```json
{
  "domain_uuid": "uuid",
  "secretary_uuid": "uuid",
  "caller_id": "+5511999999999",
  "messages": [
    {"role": "user", "content": "Olá"},
    {"role": "assistant", "content": "Olá! Como posso ajudar?"}
  ],
  "final_action": "transfer",
  "transfer_target": "200",
  "duration_seconds": 120
}
```

#### Listar Conversas

```http
GET /api/v1/conversations?domain_uuid=uuid&limit=20
```

#### Ver Detalhes da Conversa

```http
GET /api/v1/conversations/{conversation_id}?domain_uuid=uuid
```

---

### Webhooks

#### Enviar Webhook para OmniPlay

```http
POST /api/v1/webhooks/send
Content-Type: application/json
```

**Request:**
```json
{
  "domain_uuid": "uuid",
  "webhook_url": "https://omniplay.com/api/webhook",
  "payload": {
    "event": "voice_ai_conversation",
    "conversation_id": "uuid",
    "caller_id": "+5511999999999",
    "summary": "Cliente perguntou sobre horário",
    "action": "transfer"
  }
}
```

---

## Error Responses

**400 Bad Request:**
```json
{
  "detail": "domain_uuid is required for multi-tenant isolation"
}
```

**404 Not Found:**
```json
{
  "detail": "Document uuid not found in domain uuid"
}
```

**429 Too Many Requests:**
```json
{
  "error": "Rate limit exceeded",
  "retry_after": 60
}
```

**500 Internal Server Error:**
```json
{
  "detail": "Processing failed: error message"
}
```

---

## SDKs e Exemplos

### Python

```python
import httpx

async def transcribe_audio(domain_uuid: str, audio_path: str):
    async with httpx.AsyncClient() as client:
        response = await client.post(
            "http://localhost:8100/api/v1/transcribe",
            json={
                "domain_uuid": domain_uuid,
                "audio_path": audio_path,
                "language": "pt-BR",
            },
        )
        return response.json()
```

### Lua (FreeSWITCH)

```lua
local http = require("lib.http")
local json = require("lib.json")

local function transcribe(domain_uuid, audio_path)
    local body = json.encode({
        domain_uuid = domain_uuid,
        audio_path = audio_path,
        language = "pt-BR",
    })
    
    local response = http.post(
        "http://localhost:8100/api/v1/transcribe",
        body,
        {["Content-Type"] = "application/json"}
    )
    
    return json.decode(response.body)
end
```

### cURL

```bash
# Transcrever
curl -X POST http://localhost:8100/api/v1/transcribe \
  -H "Content-Type: application/json" \
  -d '{"domain_uuid": "uuid", "audio_path": "/path/audio.wav"}'

# Chat
curl -X POST http://localhost:8100/api/v1/chat \
  -H "Content-Type: application/json" \
  -d '{"domain_uuid": "uuid", "message": "Qual o horário?"}'
```
