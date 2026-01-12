# Data Flow - Voice AI IVR

## Fluxo Principal - Turn-based (v1)

```
┌─────────────────────────────────────────────────────────────────────────┐
│                         CHAMADA TELEFÔNICA                               │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│  1. ENTRADA DA CHAMADA                                                   │
│  ┌──────────────┐                                                        │
│  │  PSTN/SIP    │ ──→ FreeSWITCH ──→ Dialplan ──→ secretary_ai.lua      │
│  │  Gateway     │                                                        │
│  └──────────────┘                                                        │
│                                                                          │
│  2. SAUDAÇÃO                                                             │
│  ┌──────────────────────────────────────────────────────────────────┐   │
│  │  Lua: Carrega config do PostgreSQL                                │   │
│  │  Lua: Sintetiza saudação via HTTP → voice-ai-service:8100/synth   │   │
│  │  Lua: session:streamFile(greeting.wav)                            │   │
│  └──────────────────────────────────────────────────────────────────┘   │
│                                                                          │
│  3. LOOP DE CONVERSAÇÃO                                                  │
│  ┌──────────────────────────────────────────────────────────────────┐   │
│  │                                                                   │   │
│  │  ┌─────────┐   ┌──────────────┐   ┌─────────────────────────┐   │   │
│  │  │ Cliente │ → │ record_file  │ → │ POST /transcribe        │   │   │
│  │  │  fala   │   │ input.wav    │   │ (STT Provider)          │   │   │
│  │  └─────────┘   └──────────────┘   └────────────┬────────────┘   │   │
│  │                                                 │                │   │
│  │                                                 ▼                │   │
│  │  ┌─────────────────────────────────────────────────────────┐    │   │
│  │  │ POST /chat                                               │    │   │
│  │  │ • Busca contexto RAG (documentos relevantes)            │    │   │
│  │  │ • Adiciona histórico da sessão                          │    │   │
│  │  │ • LLM gera resposta                                     │    │   │
│  │  │ • Detecta ação: TRANSFER, HANGUP, CONTINUE              │    │   │
│  │  └────────────────────────────────────────────┬────────────┘    │   │
│  │                                                │                │   │
│  │                                                ▼                │   │
│  │  ┌─────────────────────────────────────────────────────────┐    │   │
│  │  │ POST /synthesize                                         │    │   │
│  │  │ → TTS Provider → response.wav                           │    │   │
│  │  └────────────────────────────────────────────┬────────────┘    │   │
│  │                                                │                │   │
│  │                                                ▼                │   │
│  │  ┌─────────────────────────────────────────────────────────┐    │   │
│  │  │ session:streamFile(response.wav)                         │    │   │
│  │  │ → Cliente ouve a resposta                               │    │   │
│  │  └─────────────────────────────────────────────────────────┘    │   │
│  │                                                                  │   │
│  │  ← Repete até TRANSFER ou HANGUP                                │   │
│  └──────────────────────────────────────────────────────────────────┘   │
│                                                                          │
│  4. AÇÕES FINAIS                                                         │
│  ┌──────────────────────────────────────────────────────────────────┐   │
│  │  TRANSFER: session:transfer(ramal) → PBX roteia                  │   │
│  │  HANGUP: session:hangup() → Webhook OmniPlay (opcional)          │   │
│  └──────────────────────────────────────────────────────────────────┘   │
│                                                                          │
└─────────────────────────────────────────────────────────────────────────┘
```

## Fluxo Principal - Realtime (v2)

```
┌─────────────────────────────────────────────────────────────────────────┐
│                     CHAMADA REALTIME (FULL-DUPLEX)                       │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│  1. ENTRADA                                                              │
│  ┌──────────────┐                                                        │
│  │  PSTN/SIP    │ ──→ FreeSWITCH ──→ Dialplan ──→ mod_audio_stream      │
│  │  Gateway     │                                                        │
│  └──────────────┘                                                        │
│                                                                          │
│  2. CONEXÃO WEBSOCKET                                                    │
│  ┌──────────────────────────────────────────────────────────────────┐   │
│  │  mod_audio_stream conecta: ws://localhost:8080/stream/{uuid}      │   │
│  │  Bridge carrega config do PostgreSQL                             │   │
│  │  Bridge conecta ao provider (OpenAI Realtime, ElevenLabs, etc)   │   │
│  └──────────────────────────────────────────────────────────────────┘   │
│                                                                          │
│  3. STREAMING BIDIRECIONAL (SIMULTÂNEO)                                  │
│  ┌──────────────────────────────────────────────────────────────────┐   │
│  │                                                                   │   │
│  │     CLIENTE ────────────────────────────► AI PROVIDER            │   │
│  │       │                                        │                  │   │
│  │       │ Audio PCM 16kHz                       │ Audio PCM        │   │
│  │       │ (contínuo)                            │ (contínuo)       │   │
│  │       │                                        │                  │   │
│  │       ◄────────────────────────────────────────                  │   │
│  │                                                                   │   │
│  │  • Full-duplex: ambos falam ao mesmo tempo                       │   │
│  │  • VAD: detecta quando cliente parou de falar                    │   │
│  │  • Barge-in: cliente pode interromper IA                         │   │
│  │  • Latência: ~300ms                                              │   │
│  │                                                                   │   │
│  └──────────────────────────────────────────────────────────────────┘   │
│                                                                          │
│  4. FUNCTION CALLING (AÇÕES)                                             │
│  ┌──────────────────────────────────────────────────────────────────┐   │
│  │  AI retorna function_call via WebSocket:                          │   │
│  │  • transfer_call(ramal) → ESL: uuid_transfer                     │   │
│  │  • create_ticket(data) → Webhook OmniPlay                        │   │
│  │  • end_call() → ESL: uuid_kill                                   │   │
│  └──────────────────────────────────────────────────────────────────┘   │
│                                                                          │
└─────────────────────────────────────────────────────────────────────────┘
```

## Fluxo de Upload de Documento (RAG)

```
┌─────────────────────────────────────────────────────────────────────────┐
│                         UPLOAD DE DOCUMENTO                              │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│  1. UPLOAD                                                               │
│  ┌──────────────────────────────────────────────────────────────────┐   │
│  │  Admin (FusionPBX) → POST /documents/upload                       │   │
│  │  • PDF, DOCX, TXT                                                 │   │
│  │  • Validação de tamanho e tipo                                   │   │
│  │  • Salva metadata no PostgreSQL                                  │   │
│  └──────────────────────────────────────────────────────────────────┘   │
│                                                                          │
│  2. PROCESSAMENTO (Assíncrono)                                           │
│  ┌──────────────────────────────────────────────────────────────────┐   │
│  │  • Extração de texto (pypdf, python-docx)                         │   │
│  │  • Chunking (1000 tokens, overlap 200)                           │   │
│  │  • Geração de embeddings (OpenAI/Local)                          │   │
│  │  • Armazenamento no Vector Store                                 │   │
│  │  • Atualiza status: processing → ready                           │   │
│  └──────────────────────────────────────────────────────────────────┘   │
│                                                                          │
│  3. BUSCA (Durante chat)                                                 │
│  ┌──────────────────────────────────────────────────────────────────┐   │
│  │  Query do usuário → Embedding → Similarity Search → Top K chunks │   │
│  │  Chunks adicionados ao context do LLM                            │   │
│  └──────────────────────────────────────────────────────────────────┘   │
│                                                                          │
└─────────────────────────────────────────────────────────────────────────┘
```

## Fluxo de Autenticação Multi-Tenant

```
┌─────────────────────────────────────────────────────────────────────────┐
│                       VALIDAÇÃO MULTI-TENANT                             │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│  ┌─────────────┐    ┌─────────────────────────────────────────────┐     │
│  │ FreeSWITCH  │───►│ Lua: session:getVariable("domain_uuid")     │     │
│  │ (call data) │    └─────────────────────────────────────────────┘     │
│  └─────────────┘                      │                                  │
│                                       ▼                                  │
│  ┌─────────────────────────────────────────────────────────────────┐    │
│  │ HTTP Request para voice-ai-service                               │    │
│  │ Header: X-Domain-UUID: {domain_uuid}                            │    │
│  │ Body: { "domain_uuid": "{domain_uuid}", ... }                   │    │
│  └─────────────────────────────────────────────────────────────────┘    │
│                                       │                                  │
│                                       ▼                                  │
│  ┌─────────────────────────────────────────────────────────────────┐    │
│  │ Middleware de Validação                                          │    │
│  │ • Valida UUID format                                            │    │
│  │ • Verifica domain existe no PostgreSQL                          │    │
│  │ • Aplica rate limiting por domain                               │    │
│  │ • Carrega providers específicos do domain                       │    │
│  └─────────────────────────────────────────────────────────────────┘    │
│                                                                          │
└─────────────────────────────────────────────────────────────────────────┘
```

## Integração OmniPlay (Webhook)

```
┌─────────────────────────────────────────────────────────────────────────┐
│                      WEBHOOK OMNIPLAY (OPCIONAL)                         │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│  Ao final da chamada:                                                    │
│                                                                          │
│  ┌──────────────────────────────────────────────────────────────────┐   │
│  │  POST https://omniplay.example.com/api/voice-webhook              │   │
│  │                                                                   │   │
│  │  {                                                                │   │
│  │    "event": "call_completed",                                     │   │
│  │    "call_uuid": "abc-123",                                        │   │
│  │    "domain_uuid": "def-456",                                      │   │
│  │    "caller_id": "+5511999999999",                                 │   │
│  │    "secretary_name": "Recepção",                                  │   │
│  │    "duration_seconds": 120,                                       │   │
│  │    "transcript": [...],                                           │   │
│  │    "resolution": "transferred",                                   │   │
│  │    "transferred_to": "1001"                                       │   │
│  │  }                                                                │   │
│  └──────────────────────────────────────────────────────────────────┘   │
│                                                                          │
│  OmniPlay pode:                                                          │
│  • Criar ticket automaticamente                                          │
│  • Associar ao contato pelo caller_id                                   │
│  • Anexar transcrição                                                   │
│                                                                          │
└─────────────────────────────────────────────────────────────────────────┘
```

## Comunicação entre Componentes

| De | Para | Protocolo | Dados |
|----|------|-----------|-------|
| FreeSWITCH Lua | voice-ai-service | HTTP REST | JSON |
| mod_audio_stream | voice-ai-realtime | WebSocket | PCM 16kHz |
| voice-ai-service | PostgreSQL | asyncpg | SQL |
| voice-ai-service | Redis | Redis protocol | Session cache |
| voice-ai-service | ChromaDB | HTTP | Vectors |
| voice-ai-service | AI Providers | HTTP/WS | API calls |
| voice-ai-realtime | OpenAI Realtime | WebSocket | Audio + JSON |
| Lua script | OmniPlay | HTTP REST | Webhook JSON |

---
*Gerado em: 2026-01-12*
