# Tooling & Productivity - Voice AI IVR

## Scripts Disponíveis

### Docker

| Script | Descrição |
|--------|-----------|
| `scripts/docker-build.sh` | Build da imagem Docker |
| `scripts/docker-up.sh` | Iniciar serviços |
| `scripts/docker-up.sh --dev` | Modo desenvolvimento (hot reload) |
| `scripts/docker-up.sh --ollama` | Com Ollama (LLM local) |
| `scripts/docker-install-ollama-models.sh` | Instalar modelos Ollama |
| `scripts/docker-install-piper-voices.sh` | Instalar vozes Piper |

### FreeSWITCH

| Script | Descrição |
|--------|-----------|
| `scripts/setup-freeswitch-integration.sh` | Copiar scripts/dialplan |

## Ferramentas de Desenvolvimento

### Python

```bash
# Virtual environment
python -m venv venv
source venv/bin/activate

# Dependências
pip install -r requirements.txt

# Linting
ruff check .
ruff check . --fix

# Formatting
black .

# Type checking
mypy .
```

### Docker

```bash
# Build
docker compose build

# Logs
docker compose logs -f voice-ai-service

# Shell no container
docker exec -it voice-ai-service bash

# Rebuild sem cache
docker compose build --no-cache
```

### FreeSWITCH

```bash
# Console
fs_cli

# Reload dialplan
fs_cli -x "reloadxml"

# Debug chamada
fs_cli -x "sofia status"

# Logs
tail -f /var/log/freeswitch/freeswitch.log
```

## IDE Configuration

### VS Code

```json
// .vscode/settings.json
{
    "python.linting.enabled": true,
    "python.linting.ruffEnabled": true,
    "python.formatting.provider": "black",
    "python.testing.pytestEnabled": true,
    "python.testing.pytestArgs": ["voice-ai-service/tests"],
    "editor.formatOnSave": true,
    "[python]": {
        "editor.codeActionsOnSave": {
            "source.organizeImports": true
        }
    }
}
```

### Extensions Recomendadas

```json
// .vscode/extensions.json
{
    "recommendations": [
        "ms-python.python",
        "ms-python.vscode-pylance",
        "charliermarsh.ruff",
        "ms-azuretools.vscode-docker",
        "redhat.vscode-yaml",
        "dbaeumer.vscode-eslint"
    ]
}
```

## Debugging

### Python (FastAPI)

```python
# Adicionar breakpoint
import pdb; pdb.set_trace()

# Ou com debugpy (remote debug)
# 1. Adicionar ao Dockerfile:
#    RUN pip install debugpy
# 2. Iniciar com:
#    python -m debugpy --listen 0.0.0.0:5678 -m uvicorn main:app
```

### Lua (FreeSWITCH)

```lua
-- Adicionar logs
freeswitch.consoleLog("INFO", "Debug: " .. variable .. "\n")

-- Ver no console
-- fs_cli
-- sofia loglevel all 9
```

## Monitoramento

### Health Checks

```bash
# API
curl http://localhost:8100/health

# Redis
redis-cli ping

# PostgreSQL
psql -c "SELECT 1"
```

### Logs

```bash
# Docker logs
docker compose logs -f

# Específico
docker logs voice-ai-service -f --tail 100

# Filtrar erros
docker logs voice-ai-service 2>&1 | grep -i error
```

### Métricas

```python
# Prometheus metrics endpoint (futuro)
# GET /metrics

# Exemplo de métricas
# voice_ai_requests_total{domain="...",endpoint="chat"}
# voice_ai_latency_seconds{domain="...",endpoint="chat"}
# voice_ai_errors_total{domain="...",type="timeout"}
```

## Automação

### Pre-commit Hooks

```yaml
# .pre-commit-config.yaml
repos:
  - repo: https://github.com/charliermarsh/ruff-pre-commit
    rev: v0.1.0
    hooks:
      - id: ruff
        args: [--fix]
  
  - repo: https://github.com/psf/black
    rev: 23.9.1
    hooks:
      - id: black
  
  - repo: https://github.com/pre-commit/pre-commit-hooks
    rev: v4.4.0
    hooks:
      - id: trailing-whitespace
      - id: end-of-file-fixer
      - id: check-yaml
```

### Makefile

```makefile
# Makefile
.PHONY: build up down logs test lint

build:
	./scripts/docker-build.sh

up:
	./scripts/docker-up.sh

down:
	docker compose down

logs:
	docker compose logs -f

test:
	docker exec voice-ai-service pytest

lint:
	docker exec voice-ai-service ruff check .

shell:
	docker exec -it voice-ai-service bash
```

## Troubleshooting Tools

### Network

```bash
# Verificar portas
netstat -tlnp | grep 8100

# Testar conectividade
curl -v http://localhost:8100/health

# DNS dentro do Docker
docker exec voice-ai-service nslookup host.docker.internal
```

### Memória/CPU

```bash
# Stats dos containers
docker stats

# Processos no container
docker exec voice-ai-service top
```

### Database

```bash
# Conectar ao PostgreSQL
docker exec -it voice-ai-service python
>>> from services.database import get_pool
>>> pool = await get_pool()
>>> await pool.fetchval("SELECT 1")
```

---
*Gerado em: 2026-01-12*
