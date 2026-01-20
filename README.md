# 🤖 AI Virtual Secretary for FreeSWITCH & FusionPBX

![License](https://img.shields.io/badge/license-Proprietary-red)
![Python](https://img.shields.io/badge/python-3.10%2B-blue)
![Lua](https://img.shields.io/badge/lua-5.2%2B-blue)
![PHP](https://img.shields.io/badge/php-7.4%2B-blue)
![PostgreSQL](https://img.shields.io/badge/postgresql-supported-blue)

An **AI-powered phone answering system** that behaves like a real human secretary, fully integrated with **FreeSWITCH** and **FusionPBX**.  
Supports **STT, TTS, LLMs, and RAG**, with strict **multi-tenant isolation**.

---

## ✨ Features

- 📞 Human-like AI phone secretary  
- 🧠 LLM-based conversations (GPT, Claude, Gemini, Ollama, etc.)  
- 🎙️ Speech-to-Text (Whisper, Azure, Google, AWS…)  
- 🔊 Text-to-Speech (Piper, ElevenLabs, Azure Neural…)  
- 📚 Knowledge Base with RAG (documents, embeddings)  
- 🔁 Call transfer rules (business hours, intent-based)  
- 🏢 **Multi-tenant safe** (FusionPBX domain-aware)  
- ⚡ Ultra-low latency options (Groq, local models)

---

## ⚠️ Mandatory Requirements

### Multi-Tenant Rules (STRICT)
- ALL tables **MUST** include `domain_uuid NOT NULL`
- ALL queries **MUST** filter by `domain_uuid`
- 🚫 **NO cross-domain data leaks – EVER**

### Technology Stack

| Component | Requirement |
|---------|------------|
| FreeSWITCH Scripts | Lua **5.2+** |
| FusionPBX App | PHP **7.4+ / 8.x** |
| Database | PostgreSQL |
| AI Service | Python **3.10+** |
| API Framework | FastAPI |

---

## 📂 Project Structure

```
voice-ai-ivr/
├── README.md # This file
│
├── voice-ai-service/                 # Python service (STT/TTS/LLM/RAG)
│ ├── main.py                         # FastAPI application
│ ├── requirements.txt                # Python dependencies
│ ├── config/                         # Settings
│ │ └── settings.py
│ ├── api/                            # REST endpoints
│ │ ├── __init__.py
│ │ ├── transcribe.py                 # POST /transcribe
│ │ ├── synthesize.py                 # POST /synthesize
│ │ ├── chat.py                       # POST /chat
│ │ └── documents.py                  # POST /documents
│ ├── services/                       # Business logic
│ │ ├── stt/                          # Speech-to-Text providers
│ │ │ ├── __init__.py
│ │ │ ├── base.py                     # Base interface
│ │ │ ├── whisper_local.py            # Whisper.cpp/faster-whisper
│ │ │ ├── whisper_api.py              # OpenAI Whisper API
│ │ │ ├── azure_speech.py             ​​# Azure Speech-to-Text
│ │ │ ├── google_speech.py ​​           # Google Cloud STT
│ │ │ ├── aws_transcribe.py           # AWS Transcribe
│ │ │ └── deepgram.py                 # Deepgram
│ │ ├── tts/                          # Text-to-Speech providers
│ │ │ ├── __init__.py
│ │ │ ├── base.py
│ │ │ ├── piper_local.py              # Piper TTS site
│ │ │ ├── openai_tts.py               # OpenAI TTS
│ │ │ ├── elevenlabs.py               # ElevenLabs
│ │ │ ├── azure_neural.py             # Azure Neural TTS
│ │ │ └── ...
│ │ ├── llm/                          # LLM providers
│ │ │ ├── __init__.py
│ │ │ ├── base.py
│ │ │ ├── openai.py                  # OpenAI GPT-4
│ │ │ ├── azure_openai.py            # Azure OpenAI
│ │ │ ├── anthropic.py               # Claude
│ │ │ ├── groq.py                    # Groq (ultra-fast)
│ │ │ ├── ollama_local.py            # Ollama local
│ │ │ └── ...
│ │ ├── embeddings/                  # Embeddings providers
│ │ │ ├── __init__.py
│ │ │ ├── base.py
│ │ │ ├── openai.py
│ │ │ └── local.py
│ │ └── rag/                         # Retrieval Augmented Generation
│ │ ├── __init__.py
│ │ ├── document_processor.py
│ │ ├── vector_store.py
│ │ └── retriever.py
│ ├── models/                        # Pydantic models
│ │ ├── __init__.py
│ │ ├── request.py
│ │ └── response.py
│ ├── data/                          # Local data
│ │ ├── whisper/                     # Whisper Models
│ │ ├── piper/                       # Piper Voices
│ │ └── embeddings/                  # Embeddings cache
│ └── tests/                         # Tests
│ ├── unit/
│ └── integration/
│
├── freeswitch/                     # Scripts FreeSWITCH (Lua)
│ ├── scripts/
│ │ ├── secretary_ai.lua            # Main script
│ │ ├── lib/
│ │ │ ├── http.lua                  # HTTP Client
│ │ │ ├── json.lua                  # JSON Parser
│ │ │ ├── config.lua                # Loads database configuration
│ │ │ └── utils.lua                 # Utilities
│ │ └── handlers/
│ │ ├── stt.lua                     # STT Handler
│ │ ├── tts.lua                     # TTS Handler
│ │ └── chat.lua                    # Chat Handler
│ ├── dialplan/                     # Call Routing
│ │ └── secretary.xml               
│ └── sounds/                       # Generated Audio Files
│ └── .gitkeep                     
│
├── fusionpbx-app/ # FusionPBX App (PHP)
│ └── voice_secretary/
│ ├── app_config.php # Schema and Permissions
│ ├── app_defaults.php # Default Values
│ ├── app_languages.php # Translations
│ ├── app_menu.php # Menu
│ ├── secretary.php # List of secretaries
│ ├── secretary_edit.php # Edit secretary
│ ├── providers.php # List of providers
│ ├── providers_edit.php # Configure provider
│ ├── documents.php # List of documents
│ ├── documents_edit.php # Upload document
│ ├── transfer_rules.php # Transfer rules
│ ├── transfer_rules_edit.php
│ ├── conversations.php # History
│ ├── conversation_detail.php
│ ├── settings.php # Settings
│ ├── resources/
│ │ ├── classes/
│ │ │ ├── voice_secretary.php
│ │ │ └── voice_ai_provider.php
│ │ ├── dashboard/
│ │ │ └── voice_secretary.php
│ │ └── functions/
│ └── languages/
│ └── pt-br/
│ └── app_languages.php
│
├── database/ # Migrations
│ ├── migrations/
│ │ ├── 001_create_providers.sql
│ │ ├── 002_create_secretaries.sql
│ │ ├── 003_create_documents.sql
│ │ ├── 004_create_conversations.sql
│ │ └── 005_create_transfer_rules.sql
│ └── seeds/
│ └── default_providers.sql
│
├── deploy/ # Deploy scripts
│ ├── install.sh # Installation complete
│ ├── systemd/
│ │ └── voice-ai-service.service
│ └── nginx/
│ └── voice-ai.conf
│
└── docs/
    ├── installation.md
    ├── configuration.md
    ├── providers.md
    └── api.md
```

---

## 🚀 Quick Start

### 1️⃣ Install Python AI Service

```bash
cd voice-ai-service
python -m venv venv
source venv/bin/activate
pip install -r requirements.txt
```

### 2️⃣ Configure Providers

Edit:

```text
voice-ai-service/config/settings.py
```

Add your API keys (OpenAI, Azure, Google, AWS, etc.).

### 3️⃣ Start Service

```bash
# Development
uvicorn main:app --host 127.0.0.1 --port 8100 --reload

# Production
systemctl start voice-ai-service
```

### 4️⃣ Install FusionPBX App

```bash
cp -r fusionpbx-app/voice_secretary /var/www/fusionpbx/app/
chown -R www-data:www-data /var/www/fusionpbx/app/voice_secretary
```

### 5️⃣ Install FreeSWITCH Lua Scripts

```bash
cp -r freeswitch/scripts/* /usr/share/freeswitch/scripts/
```

### 6️⃣ Run Database Migrations

```bash
psql -U fusionpbx -d fusionpbx -f database/migrations/001_create_providers.sql
```

---

## 🔌 Supported Providers

### 🎙️ Speech-to-Text (STT)
- Whisper (Local / OpenAI)
- Azure Speech
- Google Speech
- AWS Transcribe
- Deepgram

### 🔊 Text-to-Speech (TTS)
- Piper (Local)
- OpenAI TTS
- ElevenLabs
- Azure Neural TTS
- Google Cloud TTS
- AWS Polly

### 🧠 LLMs
- OpenAI (GPT‑4o, GPT‑4o‑mini)
- Azure OpenAI
- Anthropic Claude
- Google Gemini
- Groq (Ultra-fast)
- Ollama (Local)
- LM Studio (Local)

### 📚 Embeddings (RAG)
- OpenAI
- Azure OpenAI
- Cohere
- sentence-transformers (Local)

---

## 🔐 Security Notes

- Domain-level isolation enforced everywhere
- API keys stored securely (env / config)
- Designed for **public internet exposure**
- Compatible with reverse proxies (NGINX)

---

## 📖 Documentation

- Installation: `docs/installation.md`
- Configuration: `docs/configuration.md`
- Providers: `docs/providers.md`
- API Reference: `docs/api.md`

---

## 🧾 License

**Proprietary**  
© OmniPlay. All rights reserved.
