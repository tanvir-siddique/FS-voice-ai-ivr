# Architecture - Voice AI IVR

## Overview

O sistema segue uma arquitetura híbrida onde:
- **FreeSWITCH/FusionPBX** roda no host (bare metal)
- **Serviços de IA** rodam em Docker

```
┌───────────────────────────────────────────────────────────────────┐
│                          HOST SERVER                               │
├───────────────────────────────────────────────────────────────────┤
│                                                                    │
│  ┌──────────────────────────────────────────┐                     │
│  │     FreeSWITCH + FusionPBX (bare metal)  │                     │
│  │  ┌─────────────────┐  ┌───────────────┐  │                     │
│  │  │ mod_audio_stream│  │ Lua Scripts   │  │                     │
│  │  │ (realtime)      │  │ (turn-based)  │  │                     │
│  │  └────────┬────────┘  └───────┬───────┘  │                     │
│  └───────────┼───────────────────┼──────────┘                     │
│              │                   │                                 │
│              │ ws://8080         │ http://8100                     │
│              ▼                   ▼                                 │
│  ┌──────────────────────────────────────────────────────────┐     │
│  │                    DOCKER COMPOSE                         │     │
│  │  ┌──────────────────┐    ┌──────────────────┐            │     │
│  │  │ voice-ai-realtime│    │ voice-ai-service │            │     │
│  │  │    :8080 (WS)    │    │   :8100 (HTTP)   │            │     │
│  │  │  • Bridge WS     │    │  • REST API      │            │     │
│  │  │  • OpenAI RT     │    │  • STT/TTS/LLM   │            │     │
│  │  │  • ElevenLabs    │    │  • RAG/Docs      │            │     │
│  │  └────────┬─────────┘    └────────┬─────────┘            │     │
│  │           │                       │                       │     │
│  │           └───────────┬───────────┘                       │     │
│  │                       ▼                                   │     │
│  │  ┌────────────┐  ┌────────────┐  ┌────────────┐          │     │
│  │  │   redis    │  │  chromadb  │  │   ollama   │          │     │
│  │  │   :6379    │  │   :8000    │  │   :11434   │          │     │
│  │  └────────────┘  └────────────┘  └────────────┘          │     │
│  └──────────────────────────────────────────────────────────┘     │
│                                                                    │
│                    ┌────────────────────────────┐                 │
│                    │      PostgreSQL            │                 │
│                    │  (shared with FusionPBX)   │                 │
│                    └────────────────────────────┘                 │
│                                                                    │
└───────────────────────────────────────────────────────────────────┘
```

## Key Patterns

### 1. Factory Pattern (Multi-Provider)

Cada tipo de serviço (STT, TTS, LLM, Embeddings) usa factory pattern:

```python
# voice-ai-service/services/stt/factory.py
def create_stt_provider(provider_name: str, config: dict) -> BaseSTT:
    providers = {
        "openai_whisper": OpenAIWhisperSTT,
        "azure_speech": AzureSpeechSTT,
        "google_speech": GoogleSpeechSTT,
        # ...
    }
    return providers[provider_name](config)
```

### 2. Provider Manager (Multi-Tenant)

O `ProviderManager` gerencia instâncias de providers por domain:

```python
class ProviderManager:
    async def get_stt(self, domain_uuid: str) -> BaseSTT:
        # 1. Busca config do domain no PostgreSQL
        # 2. Cria ou retorna instância cacheada
        # 3. Aplica fallback se necessário
```

### 3. Session Manager (Context)

Mantém histórico de conversas para contexto do LLM:

```python
class SessionManager:
    def get_context(self, call_uuid: str) -> List[ChatMessage]:
        # Retorna últimas N mensagens da sessão
    
    def add_message(self, call_uuid: str, message: ChatMessage):
        # Adiciona mensagem ao histórico
```

### 4. RAG Pipeline

```
┌──────────┐    ┌───────────┐    ┌──────────────┐
│ Document │ →  │ Chunking  │ →  │  Embeddings  │
│  Upload  │    │ (1000tok) │    │ (OpenAI/etc) │
└──────────┘    └───────────┘    └──────────────┘
                                        │
                                        ▼
                               ┌──────────────┐
                               │ Vector Store │
                               │ (ChromaDB)   │
                               └──────────────┘

┌─────────┐    ┌──────────────┐    ┌─────────┐
│  Query  │ →  │ Similarity   │ →  │ Context │ → LLM
│         │    │   Search     │    │ + Query │
└─────────┘    └──────────────┘    └─────────┘
```

## Component Details

### voice-ai-service (API v1)

| Path | Responsabilidade |
|------|------------------|
| `/api/transcribe.py` | STT endpoint |
| `/api/synthesize.py` | TTS endpoint |
| `/api/chat.py` | LLM endpoint com RAG |
| `/api/documents.py` | Upload/processamento docs |
| `/api/conversations.py` | Histórico |
| `/services/provider_manager.py` | Multi-tenant/provider |
| `/services/rag/` | Vector store, embeddings |

### voice-ai-realtime (Bridge v2)

| Componente | Responsabilidade |
|------------|------------------|
| `RealtimeBridge` | WebSocket server |
| `ProviderRouter` | Seleciona provider realtime |
| `OpenAIRealtimeProvider` | OpenAI Realtime API |
| `ElevenLabsProvider` | ElevenLabs Conv AI |
| `GeminiLiveProvider` | Google Gemini 2.0 |
| `CustomPipelineProvider` | Deepgram + Groq + Piper |

### freeswitch/scripts

| Script | Modo | Descrição |
|--------|------|-----------|
| `secretary_ai.lua` | Turn-based | Fluxo completo v1 |
| `get_secretary_mode.lua` | Router | Decide realtime/turn-based |
| `lib/http.lua` | Util | HTTP client |
| `lib/config.lua` | Util | Carrega config do PostgreSQL |

### fusionpbx-app

| Página | Função |
|--------|--------|
| `secretary.php` | Lista secretárias |
| `secretary_edit.php` | Criar/editar secretária |
| `providers.php` | Gerenciar providers |
| `documents.php` | Upload de documentos |
| `conversations.php` | Histórico |

## Database Schema

```sql
-- Providers de IA
CREATE TABLE v_voice_ai_providers (
    provider_uuid UUID PRIMARY KEY,
    domain_uuid UUID NOT NULL REFERENCES v_domains,
    provider_type VARCHAR(20),  -- stt, tts, llm, embeddings
    provider_name VARCHAR(50),  -- openai_whisper, elevenlabs, etc
    config JSONB,               -- api_key, model, etc (encrypted)
    is_default BOOLEAN
);

-- Secretárias virtuais
CREATE TABLE v_voice_secretaries (
    secretary_uuid UUID PRIMARY KEY,
    domain_uuid UUID NOT NULL REFERENCES v_domains,
    name VARCHAR(100),
    extension VARCHAR(10),
    processing_mode VARCHAR(20),  -- turn_based, realtime, auto
    system_prompt TEXT,
    greeting TEXT,
    farewell TEXT,
    stt_provider_uuid UUID REFERENCES v_voice_ai_providers,
    tts_provider_uuid UUID REFERENCES v_voice_ai_providers,
    llm_provider_uuid UUID REFERENCES v_voice_ai_providers,
    realtime_provider_uuid UUID REFERENCES v_voice_ai_providers
);
```

## Multi-Tenant Isolation

Cada domain_uuid tem:
- ✅ Próprias secretárias
- ✅ Próprios providers configurados
- ✅ Própria base de conhecimento (RAG)
- ✅ Próprio histórico de conversas
- ✅ Próprios limites de uso (rate limiting)

## Network Ports

| Service | Port | Protocol |
|---------|------|----------|
| voice-ai-service | 8100 | HTTP/REST |
| voice-ai-realtime | 8080 | WebSocket |
| Redis | 6379 | Redis protocol |
| ChromaDB | 8000 | HTTP |
| Ollama | 11434 | HTTP |
| PostgreSQL | 5432 | PostgreSQL |

---
*Gerado em: 2026-01-12*
