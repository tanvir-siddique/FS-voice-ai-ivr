# Project Overview - Voice AI IVR

## Summary

**Voice AI IVR** é uma solução de Secretária Virtual Inteligente para FreeSWITCH/FusionPBX. O sistema oferece dois modos de operação:

- **v1 (Turn-based)**: Latência 2-5s, custo baixo, ideal para IVRs simples
- **v2 (Realtime)**: Latência 300-500ms, full-duplex, barge-in, conversa natural

## Objetivos do Projeto

1. **Conversação Natural** - Parecer um atendente humano, não um robô
2. **Multi-Tenant** - Isolamento total por domain_uuid (FusionPBX)
3. **Multi-Provider** - Suporte a OpenAI, Azure, Google, AWS, ElevenLabs, etc.
4. **RAG** - Base de conhecimento própria por tenant
5. **Integração OmniPlay** - Criar tickets automaticamente (opcional)

## Stack Tecnológica

| Componente | Tecnologia |
|------------|------------|
| **Backend API** | Python 3.11 + FastAPI |
| **Realtime Bridge** | Python asyncio + websockets |
| **Scripts FreeSWITCH** | Lua 5.1 |
| **UI Admin** | PHP 7.4+ (FusionPBX) |
| **Banco de Dados** | PostgreSQL (shared com FusionPBX) |
| **Cache/Session** | Redis 7 |
| **Vector Store** | ChromaDB / pgvector |
| **Containerização** | Docker Compose |

## Modos de Operação

### v1 - Turn-based

```
Cliente fala → Grava WAV → STT → LLM → TTS → Reproduz
                    └── Latência: 2-5 segundos por turno
```

**Ideal para:**
- IVRs simples com menu
- FAQ automatizado
- Alto volume, baixo orçamento

### v2 - Realtime

```
Cliente fala ─────────────┐
                          │ WebSocket
                          ▼ Bidirecional
                    ┌──────────┐
                    │ AI Bridge│ ←→ OpenAI Realtime / ElevenLabs / Gemini
                    └──────────┘
                          │
IA responde ←─────────────┘
        └── Latência: 300-500ms, Full-duplex
```

**Ideal para:**
- Atendimento premium
- Conversas complexas
- Suporte técnico

## Estrutura do Projeto

```
voice-ai-ivr/
├── voice-ai-service/        # Backend Python
│   ├── api/                  # Endpoints FastAPI
│   ├── services/             # Providers (STT/TTS/LLM/Embeddings)
│   │   ├── stt/              # Speech-to-Text
│   │   ├── tts/              # Text-to-Speech
│   │   ├── llm/              # Large Language Models
│   │   ├── embeddings/       # Vector embeddings
│   │   └── rag/              # RAG components
│   ├── models/               # Pydantic models
│   └── tests/                # Testes unitários
├── freeswitch/               # Scripts Lua + Dialplan
│   ├── scripts/              # secretary_ai.lua, libs
│   └── dialplan/             # XML extensions
├── fusionpbx-app/            # Páginas PHP
│   └── voice_secretary/      # UI admin
├── database/                 # SQL migrations
├── scripts/                  # Docker helpers
├── openspec/                 # Documentação OpenSpec
└── docker-compose.yml        # Orquestração
```

## Providers Suportados

### STT (Speech-to-Text)
- OpenAI Whisper API
- faster-whisper (local)
- Azure Speech
- Google Cloud STT
- AWS Transcribe
- Deepgram Nova

### TTS (Text-to-Speech)
- OpenAI TTS
- ElevenLabs
- Azure Neural TTS
- Google Cloud TTS
- AWS Polly
- Piper TTS (local)
- Coqui TTS (local)

### LLM
- OpenAI GPT-4o
- Anthropic Claude
- Google Gemini
- Groq (Llama)
- Azure OpenAI
- AWS Bedrock
- Ollama (local)
- LM Studio (local)

### Embeddings
- OpenAI text-embedding-3
- Azure OpenAI
- Cohere Embed
- Voyage AI
- sentence-transformers (local)

## Integrações Externas

| Sistema | Tipo | Propósito |
|---------|------|-----------|
| FusionPBX | Database | Configurações, domains |
| FreeSWITCH | ESL/Lua | Controle de chamadas |
| OmniPlay | Webhook | Criação de tickets |
| AI Providers | API | STT/TTS/LLM |

## Links Importantes

- **OpenSpec**: `/openspec/changes/` - Proposals e specs
- **API Docs**: http://localhost:8100/docs (Swagger)
- **FusionPBX**: `/fusionpbx-app/voice_secretary/`

---
*Gerado em: 2026-01-12*
