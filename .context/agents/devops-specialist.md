# DevOps Specialist - Voice AI IVR

## Papel
Especialista em Docker, deployment, monitoramento e integração FreeSWITCH.

## Arquitetura Docker

```
┌─────────────────────────────────────────────┐
│ HOST (FreeSWITCH + FusionPBX bare metal)    │
│                                              │
│  ┌────────────────────────────────────────┐ │
│  │            DOCKER COMPOSE              │ │
│  │  ┌──────────────┐ ┌──────────────┐    │ │
│  │  │voice-ai-srv  │ │voice-ai-rt   │    │ │
│  │  │  :8100       │ │  :8080       │    │ │
│  │  └──────────────┘ └──────────────┘    │ │
│  │  ┌──────────────┐ ┌──────────────┐    │ │
│  │  │redis :6379   │ │chromadb     │    │ │
│  │  └──────────────┘ └──────────────┘    │ │
│  └────────────────────────────────────────┘ │
└─────────────────────────────────────────────┘
```

## Arquivos Principais

| Arquivo | Função |
|---------|--------|
| `docker-compose.yml` | Orquestração principal |
| `docker-compose.override.example.yml` | Exemplo de customização |
| `voice-ai-service/Dockerfile` | Build da API |
| `voice-ai-service/Dockerfile.realtime` | Build do bridge (futuro) |
| `env.docker.example` | Variáveis de ambiente |

## Scripts

```bash
# Build
./scripts/docker-build.sh

# Start (produção)
./scripts/docker-up.sh

# Start (dev com hot reload)
./scripts/docker-up.sh --dev

# Start (com Ollama)
./scripts/docker-up.sh --ollama

# Instalar modelos Ollama
./scripts/docker-install-ollama-models.sh

# Instalar vozes Piper
./scripts/docker-install-piper-voices.sh

# Integrar com FreeSWITCH
./scripts/setup-freeswitch-integration.sh
```

## Variáveis de Ambiente

```bash
# .env (NUNCA commitar)
DATABASE_URL=postgresql://fusionpbx:pass@host.docker.internal/fusionpbx
REDIS_URL=redis://redis:6379
OPENAI_API_KEY=sk-...
ELEVENLABS_API_KEY=...
ANTHROPIC_API_KEY=...
```

## Tarefas Comuns

### Deploy Nova Versão

```bash
# No servidor
cd /opt/voice-ai-ivr
git pull origin main
docker compose build --no-cache voice-ai-service
docker compose up -d
docker logs voice-ai-service -f
```

### Troubleshooting

```bash
# Ver logs
docker compose logs -f

# Shell no container
docker exec -it voice-ai-service bash

# Verificar rede
docker exec voice-ai-service ping host.docker.internal

# Stats
docker stats
```

### Backup

```bash
# Volumes
docker run --rm -v voice-ai-data:/data -v $(pwd):/backup alpine \
  tar czf /backup/voice-ai-data.tar.gz /data
```

## Health Checks

```bash
# API
curl http://localhost:8100/health

# Redis
docker exec voice-ai-redis redis-cli ping

# ChromaDB
curl http://localhost:8000/api/v1/heartbeat
```

## Monitoramento

### Logs

```bash
# Logrotate configurado em:
/etc/logrotate.d/voice-ai-service

# Logs vão para:
/var/log/voice-ai/
```

### Alertas (configurar)

- Container restart
- Alto uso de CPU/memória
- Erro rate > 5%
- Latência > 5s

## Integração FreeSWITCH

```bash
# Copiar scripts
cp freeswitch/scripts/*.lua /usr/share/freeswitch/scripts/
cp freeswitch/dialplan/*.xml /etc/freeswitch/dialplan/default/

# Reload
fs_cli -x "reloadxml"
```

## Cuidados

- ✅ Sempre usar `host.docker.internal` para PostgreSQL
- ✅ Manter .env fora do git
- ✅ Configurar limites de recursos
- ✅ Health checks em todos os serviços
- ❌ Nunca expor Redis para internet
- ❌ Nunca rodar como root

---
*Playbook para: DevOps Specialist*
