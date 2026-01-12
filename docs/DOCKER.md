# Voice AI IVR - Docker Deployment

## Arquitetura

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                              SERVIDOR HOST                                   │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│   ┌─────────────────────┐      ┌─────────────────────────────────────────┐  │
│   │   FreeSWITCH        │      │            Docker                        │  │
│   │   + FusionPBX       │      │  ┌─────────────────────────────────┐    │  │
│   │                     │ HTTP │  │     voice-ai-service            │    │  │
│   │   ┌─────────────┐   │◄────►│  │     (FastAPI/Python)            │    │  │
│   │   │ secretary_  │   │      │  │     Port: 8100                  │    │  │
│   │   │ ai.lua      │   │      │  └─────────────────────────────────┘    │  │
│   │   └─────────────┘   │      │                  │                      │  │
│   │                     │      │                  │                      │  │
│   │   ┌─────────────┐   │      │  ┌──────────────┴──────────────┐       │  │
│   │   │ PostgreSQL  │   │◄─────┤  │  Redis    │   Ollama        │       │  │
│   │   │ (FusionPBX) │   │      │  │  :6379    │   :11434        │       │  │
│   │   └─────────────┘   │      │  └───────────┴─────────────────┘       │  │
│   │                     │      │                                         │  │
│   └─────────────────────┘      └─────────────────────────────────────────┘  │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

## Pré-requisitos

- Docker 24.0+
- Docker Compose 2.20+
- FreeSWITCH/FusionPBX instalado no host
- PostgreSQL do FusionPBX acessível

## Quick Start

### 1. Clone e configure

```bash
cd /opt
git clone <repo> voice-ai-ivr
cd voice-ai-ivr

# Configurar variáveis de ambiente
cp env.docker.example .env
nano .env  # Editar com suas configurações
```

### 2. Configure o .env

```bash
# Mínimo necessário:
DB_HOST=host.docker.internal  # ou IP do host
DB_PASS=sua_senha_fusionpbx

# Pelo menos um provider de IA:
OPENAI_API_KEY=sk-xxx  # ou
OLLAMA_BASE_URL=http://ollama:11434  # para local
```

### 3. Inicie os serviços

```bash
# Produção (voice-ai + redis)
./scripts/docker-up.sh

# Com Ollama (LLM local)
./scripts/docker-up.sh ollama

# Desenvolvimento (hot reload + todos os serviços)
./scripts/docker-up.sh dev
```

### 4. Verifique

```bash
# Status
docker compose ps

# Logs
docker compose logs -f voice-ai-service

# Health check
curl http://localhost:8100/health
```

## Serviços

### Produção

| Serviço | Porta | Descrição |
|---------|-------|-----------|
| voice-ai-service | 8100 | API principal FastAPI |
| redis | 6379 | Cache e rate limiting |

### Opcional (profiles)

| Serviço | Porta | Profile | Descrição |
|---------|-------|---------|-----------|
| ollama | 11434 | `--profile ollama` | LLM local |
| chromadb | 8000 | `--profile dev` | Vector store dev |
| voice-ai-dev | 8100 | `--profile dev` | Hot reload |

## Comandos

### Gerenciamento

```bash
# Iniciar
docker compose up -d

# Parar
docker compose down

# Reiniciar
docker compose restart

# Logs
docker compose logs -f voice-ai-service

# Shell no container
docker exec -it voice-ai-service bash
```

### Build

```bash
# Build produção
./scripts/docker-build.sh

# Build desenvolvimento
./scripts/docker-build.sh dev

# Build e push para registry
DOCKER_REGISTRY=myregistry.com/ ./scripts/docker-build.sh --push
```

### Instalar modelos

```bash
# Ollama LLM
./scripts/docker-install-ollama-models.sh

# Piper TTS voices
./scripts/docker-install-piper-voices.sh
```

## Configuração

### Acesso ao PostgreSQL do Host

O Docker usa `host.docker.internal` para acessar o PostgreSQL do FusionPBX:

```yaml
environment:
  - DB_HOST=host.docker.internal
```

Se não funcionar, use o IP do host:
```bash
# Descobrir IP do host
ip route | grep default | awk '{print $3}'
```

### Volumes Compartilhados com FreeSWITCH

```yaml
volumes:
  # Sons do FreeSWITCH (read-only)
  - /var/lib/freeswitch/sounds:/freeswitch/sounds:ro
  
  # Gravações (read-write)
  - /var/lib/freeswitch/recordings:/freeswitch/recordings
```

### Configurar FreeSWITCH

No host, configure o Lua script para chamar o container:

```lua
-- /usr/share/freeswitch/scripts/lib/config.lua
-- Altere o endpoint para o container Docker
local VOICE_AI_URL = "http://localhost:8100"
-- ou se usando rede bridge:
-- local VOICE_AI_URL = "http://172.28.0.2:8100"
```

## Produção

### Systemd Service

Crie `/etc/systemd/system/voice-ai-docker.service`:

```ini
[Unit]
Description=Voice AI IVR Docker
Requires=docker.service
After=docker.service

[Service]
Type=oneshot
RemainAfterExit=yes
WorkingDirectory=/opt/voice-ai-ivr
ExecStart=/usr/bin/docker compose up -d
ExecStop=/usr/bin/docker compose down
TimeoutStartSec=0

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl enable voice-ai-docker
sudo systemctl start voice-ai-docker
```

### Logs

```bash
# Ver logs
docker compose logs -f

# Logs do último dia
docker compose logs --since 24h

# Logs de um serviço específico
docker compose logs -f voice-ai-service
```

### Backup

```bash
# Backup volumes
docker run --rm \
  -v voice-ai-data:/data:ro \
  -v $(pwd):/backup \
  alpine tar czf /backup/voice-ai-data.tar.gz /data
```

### Atualização

```bash
cd /opt/voice-ai-ivr

# Pull latest
git pull

# Rebuild e restart
docker compose down
./scripts/docker-build.sh
docker compose up -d
```

## Troubleshooting

### Container não inicia

```bash
# Ver logs detalhados
docker compose logs voice-ai-service

# Verificar recursos
docker stats

# Verificar rede
docker network ls
docker network inspect voice-ai-ivr_voice-ai-network
```

### Não conecta ao PostgreSQL

```bash
# Testar conexão de dentro do container
docker exec voice-ai-service \
  python -c "import asyncpg; print('OK')"

# Verificar host.docker.internal
docker exec voice-ai-service \
  ping -c 1 host.docker.internal

# Se não funcionar, use IP direto
export DB_HOST=$(ip route | grep default | awk '{print $3}')
```

### Ollama lento

```bash
# Verificar se tem GPU
docker exec voice-ai-ollama nvidia-smi

# Ver modelos instalados
docker exec voice-ai-ollama ollama list

# Instalar modelo menor
docker exec voice-ai-ollama ollama pull llama3.2:1b
```

### Rate limit

```bash
# Verificar Redis
docker exec voice-ai-redis redis-cli INFO

# Limpar rate limits
docker exec voice-ai-redis redis-cli FLUSHDB
```
