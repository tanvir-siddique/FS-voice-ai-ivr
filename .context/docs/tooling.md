# Tooling - Voice AI IVR

## Linting e Formatação

### Python (Voice AI Service)

| Ferramenta | Uso | Comando |
|------------|-----|---------|
| ruff | Linting rápido | `ruff check .` |
| black | Formatação | `black .` |
| mypy | Type checking | `mypy services/` |

```bash
cd voice-ai-service

# Verificar estilo
ruff check .

# Corrigir automaticamente
ruff check . --fix

# Formatar código
black .

# Type checking
mypy services/ api/ models/
```

### Configuração (pyproject.toml)

```toml
[tool.ruff]
line-length = 100
select = ["E", "F", "W", "I", "N", "D", "UP"]
ignore = ["D100", "D104"]

[tool.black]
line-length = 100
target-version = ["py310"]

[tool.mypy]
python_version = "3.10"
warn_return_any = true
warn_unused_ignores = true
```

### PHP (FusionPBX App)

Seguir padrões do FusionPBX existente:
- Indentação: tabs
- Aspas simples para strings
- Nomes de variáveis: snake_case

### Lua (FreeSWITCH)

- Indentação: 4 espaços
- Nomes locais: snake_case
- Funções: camelCase (padrão FreeSWITCH)

## IDE Configuration

### VSCode

```json
// .vscode/settings.json
{
    "python.linting.enabled": true,
    "python.linting.ruffEnabled": true,
    "python.formatting.provider": "black",
    "editor.formatOnSave": true,
    "[python]": {
        "editor.defaultFormatter": "ms-python.black-formatter"
    },
    "python.analysis.typeCheckingMode": "basic"
}
```

### Extensões Recomendadas

```json
// .vscode/extensions.json
{
    "recommendations": [
        "ms-python.python",
        "ms-python.vscode-pylance",
        "charliermarsh.ruff",
        "ms-python.black-formatter",
        "sumneko.lua",
        "bmewburn.vscode-intelephense-client"
    ]
}
```

## Scripts de Automação

### Verificação Completa

```bash
#!/bin/bash
# scripts/check-all.sh

echo "=== Linting ==="
cd voice-ai-service
ruff check .

echo "=== Type Checking ==="
mypy services/ api/

echo "=== Tests ==="
pytest tests/unit/ -v

echo "=== Coverage ==="
pytest --cov=. --cov-report=term-missing
```

### Pre-commit Hook

```bash
#!/bin/bash
# .git/hooks/pre-commit

cd voice-ai-service

# Lint
ruff check . --fix
if [ $? -ne 0 ]; then
    echo "Linting failed"
    exit 1
fi

# Format
black --check .
if [ $? -ne 0 ]; then
    echo "Formatting required. Run: black ."
    exit 1
fi

# Types
mypy services/ api/ --ignore-missing-imports
if [ $? -ne 0 ]; then
    echo "Type errors found"
    exit 1
fi

# Quick tests
pytest tests/unit/ -q
if [ $? -ne 0 ]; then
    echo "Tests failed"
    exit 1
fi
```

## Debugging

### Python

```python
# Usar breakpoint() para debug interativo
def process_request(data):
    breakpoint()  # Pausa aqui
    result = transform(data)
    return result
```

```bash
# Rodar com debug
python -m pdb main.py

# Ou com ipdb (mais amigável)
pip install ipdb
PYTHONBREAKPOINT=ipdb.set_trace python main.py
```

### Lua (FreeSWITCH)

```lua
-- Logging detalhado
local function log(level, message)
    freeswitch.consoleLog(level, "[SECRETARY_AI] " .. message .. "\n")
end

log("INFO", "Starting request")
log("DEBUG", "Data: " .. tostring(data))
log("ERR", "Error occurred: " .. error_msg)
```

```bash
# Acompanhar logs em tempo real
tail -f /var/log/freeswitch/freeswitch.log | grep SECRETARY_AI
```

### PHP

```php
// Debug logging
error_log("Debug: " . print_r($variable, true));

// Ou usar FusionPBX debug
if ($debug) {
    echo "<pre>" . print_r($data, true) . "</pre>";
}
```

## Profiling

### Python Performance

```python
# Profiling simples
import time

start = time.perf_counter()
result = await expensive_operation()
elapsed = time.perf_counter() - start
logger.info(f"Operation took {elapsed:.3f}s")
```

```bash
# Profiling detalhado
python -m cProfile -o profile.prof main.py
python -m pstats profile.prof
```

### Memory

```bash
# Monitorar memória
pip install memory-profiler
python -m memory_profiler script.py
```

## Database Tools

```bash
# Conectar ao banco
psql -h localhost -U fusionpbx fusionpbx

# Verificar tabelas voice_ai
\dt v_voice_*

# Explain query
EXPLAIN ANALYZE SELECT * FROM v_voice_document_chunks 
WHERE domain_uuid = '...' 
ORDER BY embedding <=> '...'::vector 
LIMIT 5;

# Ver índices
\di v_voice_*
```

## Comandos Úteis

```bash
# Voice AI Service
cd voice-ai-service
uvicorn main:app --reload --port 8100  # Dev
uvicorn main:app --host 0.0.0.0 --port 8100  # Prod

# FreeSWITCH
fs_cli  # Console interativo
fs_cli -x "reloadxml"  # Recarregar dialplan
fs_cli -x "show channels"  # Ver chamadas ativas

# FusionPBX
sudo systemctl restart nginx php-fpm  # Restart web
sudo rm -rf /var/www/fusionpbx/temp/*  # Limpar cache
```
