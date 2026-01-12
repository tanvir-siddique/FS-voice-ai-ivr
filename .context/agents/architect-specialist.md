# Architect Specialist - Voice AI IVR

## Papel
Especialista em arquitetura do sistema, decisões técnicas, e integração de componentes.

## Arquitetura Geral

```
┌─────────────────────────────────────────────────────────────────────────┐
│                              HOST SERVER                                 │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│  ┌──────────────────────────────────────────────────────┐               │
│  │         FreeSWITCH + FusionPBX (bare metal)          │               │
│  │  ┌─────────────────┐    ┌───────────────────────┐   │               │
│  │  │ mod_audio_stream│    │ Lua (secretary_ai)    │   │               │
│  │  │ (v2 realtime)   │    │ (v1 turn-based)       │   │               │
│  │  └────────┬────────┘    └───────────┬───────────┘   │               │
│  └───────────┼─────────────────────────┼───────────────┘               │
│              │ WebSocket               │ HTTP                           │
│              ▼                         ▼                                │
│  ┌──────────────────────────────────────────────────────────────────┐  │
│  │                         DOCKER COMPOSE                            │  │
│  │                                                                   │  │
│  │  ┌──────────────────┐    ┌──────────────────┐                    │  │
│  │  │ voice-ai-realtime│    │ voice-ai-service │                    │  │
│  │  │     :8080        │    │      :8100       │                    │  │
│  │  │ Bridge WebSocket │    │ REST API v1      │                    │  │
│  │  └────────┬─────────┘    └────────┬─────────┘                    │  │
│  │           │                       │                               │  │
│  │           └───────────┬───────────┘                               │  │
│  │                       ▼                                           │  │
│  │  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────┐         │  │
│  │  │  redis   │  │ chromadb │  │  ollama  │  │piper-tts │         │  │
│  │  │  :6379   │  │  :8000   │  │  :11434  │  │(interno) │         │  │
│  │  └──────────┘  └──────────┘  └──────────┘  └──────────┘         │  │
│  └──────────────────────────────────────────────────────────────────┘  │
│                                                                          │
│                       ┌────────────────────┐                            │
│                       │    PostgreSQL      │                            │
│                       │   (FusionPBX DB)   │                            │
│                       └────────────────────┘                            │
│                                                                          │
└─────────────────────────────────────────────────────────────────────────┘
```

## Decisões de Arquitetura

### 1. FreeSWITCH no Host, Serviços em Docker

**Motivo**: FreeSWITCH é crítico para telefonia e precisa de acesso direto à rede para SIP/RTP. Containers adicionariam latência e complexidade.

### 2. Dois Modos de Operação (v1/v2)

**Motivo**: Flexibilidade para diferentes casos de uso:
- v1 (turn-based): Custo baixo, implementação simples
- v2 (realtime): UX premium, latência baixa

### 3. Factory Pattern para Providers

**Motivo**: Suportar múltiplos providers (OpenAI, Azure, Google, etc.) com interface unificada.

```python
stt = create_stt_provider("azure_speech", config)  # ou "openai_whisper", etc.
text = await stt.transcribe(audio)
```

### 4. Multi-Tenant por Design

**Motivo**: FusionPBX é multi-tenant. Cada `domain_uuid` é um cliente isolado.

### 5. PostgreSQL Compartilhado

**Motivo**: Aproveitar banco existente do FusionPBX, simplificar deployment.

## Componentes

### voice-ai-service (v1)

| Responsabilidade | Tecnologia |
|------------------|------------|
| STT | OpenAI, Azure, Google, AWS, Deepgram |
| TTS | OpenAI, ElevenLabs, Azure, Google, Piper |
| LLM | OpenAI, Anthropic, Groq, Ollama |
| RAG | ChromaDB, pgvector |
| Session | Redis |

### voice-ai-realtime (v2)

| Responsabilidade | Tecnologia |
|------------------|------------|
| WebSocket Bridge | Python asyncio |
| OpenAI Realtime | WebSocket nativo |
| ElevenLabs | WebSocket nativo |
| Gemini Live | WebSocket nativo |
| Custom Pipeline | Deepgram + Groq + Piper |

### FreeSWITCH

| Componente | Função |
|------------|--------|
| mod_audio_stream | Stream bidirecional (v2) |
| Lua scripts | Controle de chamada (v1) |
| Dialplan XML | Roteamento |

## Fluxos de Dados

### v1: Turn-based

```
Chamada → Dialplan → Lua → HTTP API → STT → LLM → TTS → Áudio
                           ↓
                        PostgreSQL (config, history)
                           ↓
                        Redis (session)
```

### v2: Realtime

```
Chamada → Dialplan → mod_audio_stream → WebSocket Bridge → AI Provider
                                             ↓
                                        PostgreSQL (config)
                                             ↓
                                        Redis (session)
```

## Requisitos Não-Funcionais

| Requisito | v1 | v2 |
|-----------|----|----|
| Latência | 2-5s | 300-500ms |
| Throughput | 100 chamadas/min | 50 chamadas/min |
| Disponibilidade | 99.9% | 99.9% |
| Recuperação | Fallback local | Fallback v1 |

## Trade-offs

| Decisão | Prós | Contras |
|---------|------|---------|
| FreeSWITCH no host | Performance, estabilidade | Menos isolamento |
| PostgreSQL compartilhado | Simplicidade, menos recursos | Dependência FusionPBX |
| Python para serviços | Ecossistema AI, produtividade | Performance vs Go/Rust |
| Docker para serviços | Portabilidade, isolamento | Overhead de rede |

## Evolução Futura

1. **Clustering**: Redis Cluster para HA
2. **Kubernetes**: Migrar Docker Compose para K8s
3. **Observabilidade**: Prometheus + Grafana
4. **Cache de TTS**: Evitar re-síntese de frases comuns

---
*Playbook para: Architect Specialist*
