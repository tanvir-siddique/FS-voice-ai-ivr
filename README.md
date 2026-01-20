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
├── README.md
├── voice-ai-service/     # Python (STT / TTS / LLM / RAG)
├── freeswitch/           # Lua scripts & dialplan
├── fusionpbx-app/        # FusionPBX PHP app
├── database/             # PostgreSQL migrations
├── deploy/               # systemd, nginx, install scripts
└── docs/                 # Documentation
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
