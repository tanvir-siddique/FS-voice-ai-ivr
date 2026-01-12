# Development Workflow - Voice AI IVR

## Setup Inicial

### Pré-requisitos

- Python 3.11+
- Docker & Docker Compose
- FreeSWITCH 1.10+ (no host)
- FusionPBX 5.x (no host)
- PostgreSQL (shared com FusionPBX)

### Clone e Setup

```bash
# Clonar repositório
git clone https://github.com/julianotarga/voice-ai-ivr.git
cd voice-ai-ivr

# Copiar arquivo de ambiente
cp env.docker.example .env

# Editar variáveis
nano .env
```

### Variáveis de Ambiente Essenciais

```bash
# Database (FusionPBX)
DATABASE_URL=postgresql://fusionpbx:password@host.docker.internal/fusionpbx

# AI Providers (pelo menos um de cada tipo)
OPENAI_API_KEY=sk-...
ELEVENLABS_API_KEY=...
GOOGLE_API_KEY=...
ANTHROPIC_API_KEY=...

# Redis
REDIS_URL=redis://redis:6379

# OmniPlay (opcional)
OMNIPLAY_WEBHOOK_URL=https://...
OMNIPLAY_WEBHOOK_SECRET=...
```

## Desenvolvimento Local

### Iniciar Serviços Docker

```bash
# Build e start
./scripts/docker-up.sh

# Ou em modo dev (hot reload)
./scripts/docker-up.sh --dev

# Com Ollama (LLM local)
./scripts/docker-up.sh --ollama
```

### Verificar Status

```bash
# Logs
docker logs voice-ai-service -f

# Health check
curl http://localhost:8100/health
curl http://localhost:8080/health

# API Docs
open http://localhost:8100/docs
```

### Instalar Modelos Locais (Opcional)

```bash
# Ollama models
./scripts/docker-install-ollama-models.sh

# Piper TTS voices
./scripts/docker-install-piper-voices.sh
```

## Integração com FreeSWITCH

```bash
# Copiar scripts Lua e dialplan
./scripts/setup-freeswitch-integration.sh

# Verificar instalação
ls /usr/share/freeswitch/scripts/secretary_ai.lua
ls /etc/freeswitch/dialplan/default/00_voice_ai.xml

# Recarregar FreeSWITCH
fs_cli -x "reloadxml"
```

## Estrutura de Código

```
voice-ai-service/
├── main.py              # Entry point FastAPI
├── api/                 # Endpoints
│   ├── transcribe.py    # POST /transcribe
│   ├── synthesize.py    # POST /synthesize
│   ├── chat.py          # POST /chat
│   ├── documents.py     # POST /documents
│   └── conversations.py # GET /conversations
├── services/
│   ├── provider_manager.py  # Multi-tenant/provider
│   ├── session_manager.py   # Contexto de sessão
│   ├── rate_limiter.py      # Rate limiting
│   ├── stt/                 # Speech-to-Text providers
│   ├── tts/                 # Text-to-Speech providers
│   ├── llm/                 # LLM providers
│   ├── embeddings/          # Embedding providers
│   └── rag/                 # RAG components
├── models/
│   ├── request.py       # Pydantic request models
│   └── response.py      # Pydantic response models
├── config/
│   └── settings.py      # Environment config
└── tests/
    ├── conftest.py      # Fixtures
    └── unit/            # Unit tests
```

## Testes

### Rodar Testes

```bash
# Entrar no container
docker exec -it voice-ai-service bash

# Todos os testes
pytest

# Com coverage
pytest --cov=services --cov-report=html

# Testes específicos
pytest tests/unit/test_llm_providers.py -v
```

### Testar API Manualmente

```bash
# Transcrição
curl -X POST http://localhost:8100/transcribe \
  -H "Content-Type: application/json" \
  -d '{"domain_uuid":"abc-123","audio_base64":"...","format":"wav"}'

# Chat
curl -X POST http://localhost:8100/chat \
  -H "Content-Type: application/json" \
  -d '{
    "domain_uuid": "abc-123",
    "secretary_uuid": "def-456",
    "message": "Qual o horário de funcionamento?",
    "history": []
  }'
```

## Branching Strategy

```
main              # Produção
  └── develop     # Desenvolvimento
        ├── feature/xxx   # Novas features
        ├── fix/xxx       # Bug fixes
        └── hotfix/xxx    # Hotfixes urgentes
```

## Convenções de Commit

```bash
# Formato
tipo: descrição breve

# Tipos
feat:     Nova feature
fix:      Bug fix
refactor: Refatoração
docs:     Documentação
test:     Testes
chore:    Manutenção

# Exemplos
git commit -m "feat: add ElevenLabs TTS provider"
git commit -m "fix: handle timeout in OpenAI API"
git commit -m "docs: update API documentation"
```

## Migrations

```bash
# Criar nova migration
cd database/migrations
touch 00X_description.sql

# Aplicar migrations
docker exec -it voice-ai-service python -m scripts.apply_migrations
```

## Deploy

### Produção

```bash
# Build da imagem
./scripts/docker-build.sh --prod

# Push para registry
docker tag voice-ai-ivr-voice-ai-service:latest registry.example.com/voice-ai:latest
docker push registry.example.com/voice-ai:latest

# No servidor de produção
docker compose -f docker-compose.yml pull
docker compose -f docker-compose.yml up -d
```

### Checklist de Deploy

- [ ] Variáveis de ambiente configuradas
- [ ] PostgreSQL acessível
- [ ] Migrations aplicadas
- [ ] API Keys válidas
- [ ] FreeSWITCH integrado
- [ ] Health checks passando
- [ ] Logs configurados
- [ ] Backup de dados

## Troubleshooting

### Container não inicia

```bash
# Ver logs
docker logs voice-ai-service --tail 100

# Verificar variáveis
docker exec voice-ai-service env | grep -E "DATABASE|REDIS"
```

### Erro de conexão com PostgreSQL

```bash
# Verificar se host.docker.internal resolve
docker exec voice-ai-service ping host.docker.internal

# Ou usar IP direto
DATABASE_URL=postgresql://user:pass@192.168.1.100/fusionpbx
```

### STT/TTS não funciona

```bash
# Verificar API keys
docker exec voice-ai-service python -c "from config.settings import settings; print(settings.OPENAI_API_KEY[:10])"

# Testar provider diretamente
docker exec -it voice-ai-service python
>>> from services.stt.factory import create_stt_provider
>>> stt = create_stt_provider("openai_whisper", {"api_key": "..."})
```

---
*Gerado em: 2026-01-12*
