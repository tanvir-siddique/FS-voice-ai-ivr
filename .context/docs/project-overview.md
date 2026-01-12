# Project Overview - Voice AI IVR

## Visão Geral

**Voice AI IVR** é um módulo de Secretária Virtual com Inteligência Artificial para FreeSWITCH/FusionPBX. Transforma chamadas telefônicas em conversas naturais usando tecnologias de STT (Speech-to-Text), TTS (Text-to-Speech), LLM (Large Language Models) e RAG (Retrieval Augmented Generation).

## Problema Resolvido

Tradicionalmente, URAs (IVR) são sistemas robóticos com menus rígidos ("Pressione 1 para..."). Este módulo cria uma **secretária virtual que conversa naturalmente**, entende o contexto da empresa através de documentos carregados (RAG), e pode:
- Responder perguntas sobre a empresa
- Transferir chamadas para departamentos/ramais corretos
- Criar tickets no OmniPlay (sistema omnichannel)
- Funcionar 24/7 sem intervenção humana

## Arquitetura

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   FreeSWITCH    │    │ Voice AI Service│    │   PostgreSQL    │
│   (mod_lua)     │◄──►│   (FastAPI)     │◄──►│   (FusionPBX)   │
└─────────────────┘    └─────────────────┘    └─────────────────┘
         │                      │
         │                      ▼
         │              ┌───────────────┐
         │              │ AI Providers  │
         │              │ STT/TTS/LLM   │
         │              └───────────────┘
         ▼
┌─────────────────┐
│   FusionPBX     │
│ (Interface Web) │
└─────────────────┘
```

## Componentes Principais

### 1. Voice AI Service (Python/FastAPI)
- **Localização**: `voice-ai-service/`
- **Responsabilidade**: Processamento de áudio (STT), síntese de voz (TTS), chat com IA (LLM), busca em documentos (RAG)
- **Endpoints**: `/transcribe`, `/synthesize`, `/chat`, `/documents`, `/conversations`, `/webhooks`

### 2. Scripts FreeSWITCH (Lua)
- **Localização**: `freeswitch/scripts/`
- **Responsabilidade**: Orquestrar o fluxo de chamada (gravar, transcrever, processar, falar)
- **Script Principal**: `secretary_ai.lua`

### 3. App FusionPBX (PHP)
- **Localização**: `fusionpbx-app/voice_secretary/`
- **Responsabilidade**: Interface web para configurar secretárias, providers, documentos, regras
- **Páginas**: Secretárias, Documentos, Regras de Transferência, Histórico, Providers, Configurações

### 4. Database (PostgreSQL)
- **Localização**: `database/migrations/`
- **Tabelas**: `v_voice_ai_providers`, `v_voice_secretaries`, `v_voice_documents`, `v_voice_document_chunks`, `v_voice_transfer_rules`, `v_voice_conversations`, `v_voice_messages`

## Multi-Tenant

⚠️ **CRÍTICO**: O sistema é 100% multi-tenant. Cada domínio FusionPBX tem:
- Suas próprias secretárias configuradas
- Seus próprios documentos na base de conhecimento
- Seus próprios providers de IA
- Isolamento total de dados via `domain_uuid`

## Providers Suportados

| Tipo | Providers |
|------|-----------|
| **STT** | Whisper Local, Whisper API, Azure Speech, Google Cloud, AWS Transcribe, Deepgram |
| **TTS** | Piper Local, Coqui Local, OpenAI, ElevenLabs, Azure Neural, Google Cloud, AWS Polly, Play.ht |
| **LLM** | OpenAI, Azure OpenAI, Anthropic Claude, Google Gemini, AWS Bedrock, Groq, Ollama, LM Studio |
| **Embeddings** | OpenAI, Azure OpenAI, Cohere, Voyage AI, Local (sentence-transformers) |

## Tecnologias

- **Backend Service**: Python 3.10+, FastAPI, asyncpg, httpx
- **FreeSWITCH**: Lua scripts (mod_lua)
- **FusionPBX**: PHP 7.4+
- **Database**: PostgreSQL 13+ com pgvector
- **Deploy**: systemd, Docker (opcional)

## Status do Projeto

**~95% completo** - Faltam apenas testes de integração e deploy em produção.
