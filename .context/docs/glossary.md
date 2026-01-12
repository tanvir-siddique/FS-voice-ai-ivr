# Glossary - Voice AI IVR

## Termos de Domínio

### Domain / Tenant
Representa uma empresa/cliente no FusionPBX. Cada domain tem `domain_uuid` único.
- **Multi-tenant**: Sistema compartilhado com isolamento por domain

### Secretary / Secretária Virtual
Entidade que representa uma IA atendendo chamadas. Configurada por ramal.
- Pode ter prompt personalizado
- Escolhe modo: turn-based ou realtime

### Extension / Ramal
Número interno que aciona a secretária (ex: 8000)

## Termos Técnicos

### STT (Speech-to-Text)
Conversão de áudio para texto. Providers: Whisper, Azure Speech, Google STT

### TTS (Text-to-Speech)
Síntese de voz. Providers: OpenAI TTS, ElevenLabs, Piper

### LLM (Large Language Model)
IA que processa texto. Providers: GPT-4, Claude, Gemini

### RAG (Retrieval Augmented Generation)
Técnica para alimentar LLM com documentos externos (knowledge base)

### Embeddings
Representação vetorial de texto para busca semântica

### Vector Store
Banco de dados para embeddings (ChromaDB, pgvector)

### VAD (Voice Activity Detection)
Detecção automática de quando usuário está falando

### Barge-in
Capacidade de interromper a IA enquanto ela fala

### Full-duplex
Comunicação bidirecional simultânea (ambos falam ao mesmo tempo)

### Turn-based
Comunicação em turnos (um fala, depois o outro)

### ESL (Event Socket Library)
API do FreeSWITCH para controle externo

### Media Bug
Mecanismo do FreeSWITCH para interceptar áudio RTP

## Componentes do Sistema

| Termo | Descrição |
|-------|-----------|
| voice-ai-service | API REST (v1 turn-based) |
| voice-ai-realtime | Bridge WebSocket (v2 realtime) |
| mod_audio_stream | Módulo FreeSWITCH para streaming |
| secretary_ai.lua | Script Lua principal |
| ProviderManager | Gerenciador multi-tenant de providers |
| SessionManager | Contexto de conversação |

## Acrônimos

| Acrônimo | Significado |
|----------|-------------|
| IVR | Interactive Voice Response |
| URA | Unidade de Resposta Audível (IVR em português) |
| PBX | Private Branch Exchange |
| PSTN | Public Switched Telephone Network |
| SIP | Session Initiation Protocol |
| RTP | Real-time Transport Protocol |
| WS | WebSocket |
| PCM | Pulse Code Modulation (formato de áudio) |
| UUID | Universally Unique Identifier |

---
*Gerado em: 2026-01-12*
