# Development Workflow - Voice AI IVR

## Pré-requisitos

- Python 3.10+
- PostgreSQL 13+ com extensão pgvector
- FreeSWITCH 1.10+ com mod_lua
- FusionPBX 5.x+
- Node.js 18+ (para ferramentas de build)

## Setup do Ambiente

### 1. Voice AI Service (Python)

```bash
cd voice-ai-service

# Criar ambiente virtual
python3 -m venv venv
source venv/bin/activate

# Instalar dependências
pip install -r requirements.txt

# Copiar e configurar .env
cp .env.example .env
# Editar .env com suas configurações

# Rodar migrações
psql -h localhost -U fusionpbx -d fusionpbx -f ../database/migrations/001_create_providers.sql
psql -h localhost -U fusionpbx -d fusionpbx -f ../database/migrations/002_create_secretaries.sql
# ... demais migrações

# Iniciar servidor
uvicorn main:app --host 0.0.0.0 --port 8100 --reload
```

### 2. Scripts FreeSWITCH (Lua)

```bash
# Copiar scripts
sudo cp freeswitch/scripts/*.lua /usr/share/freeswitch/scripts/
sudo cp -r freeswitch/scripts/lib /usr/share/freeswitch/scripts/

# Copiar dialplan
sudo cp freeswitch/dialplan/*.xml /etc/freeswitch/dialplan/default/

# Recarregar dialplan
fs_cli -x "reloadxml"
```

### 3. App FusionPBX (PHP)

```bash
# Copiar app
sudo cp -r fusionpbx-app/voice_secretary /var/www/fusionpbx/app/

# Definir permissões
sudo chown -R www-data:www-data /var/www/fusionpbx/app/voice_secretary

# Limpar cache do FusionPBX (se necessário)
sudo rm -rf /var/www/fusionpbx/temp/*
```

## Estrutura de Branches

```
main
├── develop          # Branch de desenvolvimento ativo
├── feature/*        # Novas funcionalidades
├── fix/*            # Correções de bugs
└── release/*        # Preparação de release
```

**Convenções:**
- `feature/add-google-stt` - Nova feature
- `fix/rag-empty-context` - Correção de bug
- `release/v1.0.0` - Preparação de versão

## Comandos de Desenvolvimento

### Testes

```bash
cd voice-ai-service

# Rodar todos os testes
pytest

# Com cobertura
pytest --cov=. --cov-report=html

# Apenas testes unitários
pytest tests/unit/

# Testes específicos
pytest tests/unit/test_llm_providers.py -v
```

### Linting

```bash
# Verificar estilo
ruff check .

# Corrigir automaticamente
ruff check . --fix

# Formatação
black .
```

### Type Checking

```bash
mypy services/ api/ models/
```

## Workflow de Desenvolvimento

### 1. Adicionar Novo Provider

**Exemplo: Adicionar novo STT provider**

```python
# 1. Criar arquivo services/stt/novo_provider.py
from .base import BaseSTT

class NovoProviderSTT(BaseSTT):
    @property
    def name(self) -> str:
        return "novo_provider"
    
    async def transcribe(self, audio_path: str, ...) -> str:
        # Implementação
        pass

# 2. Registrar na factory (services/stt/factory.py)
# O registro é automático ao importar - basta adicionar na lista _register_all()

# 3. Adicionar dependências em requirements.txt
# novo-provider-sdk>=1.0.0
```

### 2. Modificar Schema do Banco

```sql
-- database/migrations/007_add_new_field.sql

-- Garantir idempotência
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'v_voice_secretaries' 
        AND column_name = 'new_field'
    ) THEN
        ALTER TABLE v_voice_secretaries 
        ADD COLUMN new_field VARCHAR(255);
    END IF;
END $$;

-- Índice se necessário
CREATE INDEX IF NOT EXISTS idx_voice_secretaries_new_field 
ON v_voice_secretaries(new_field) 
WHERE new_field IS NOT NULL;
```

### 3. Adicionar Endpoint na API

```python
# api/new_endpoint.py
from fastapi import APIRouter, Depends
from models.request import BaseRequest

router = APIRouter(prefix="/api/v1/new", tags=["New Feature"])

@router.post("/action")
async def new_action(request: NewRequest):
    # Sempre validar domain_uuid
    if not request.domain_uuid:
        raise HTTPException(400, "domain_uuid required")
    
    # Implementação
    pass
```

## Checklist de Code Review

- [ ] Multi-tenant: domain_uuid usado em todas as queries
- [ ] Não quebra funcionalidades existentes
- [ ] Testes unitários adicionados
- [ ] Linting passou (ruff check)
- [ ] Typing correto (mypy)
- [ ] Documentação atualizada se necessário
- [ ] Migrations são idempotentes
- [ ] Secrets não expostos em código

## Deploy

### Desenvolvimento Local

```bash
# Terminal 1: Backend Python
cd voice-ai-service && uvicorn main:app --reload --port 8100

# Terminal 2: Logs FreeSWITCH
tail -f /var/log/freeswitch/freeswitch.log | grep SECRETARY_AI
```

### Produção

```bash
# Systemd service para voice-ai-service
sudo systemctl enable voice-ai
sudo systemctl start voice-ai

# Verificar status
sudo systemctl status voice-ai
```

## Troubleshooting

### Erro: "Provider not found"
- Verificar se o provider está registrado na factory
- Verificar se a dependência está instalada

### Erro: "domain_uuid required"
- Garantir que FreeSWITCH está passando domain_uuid
- Verificar variável de canal: `session:getVariable("domain_uuid")`

### Erro: "RAG context empty"
- Verificar se documentos foram processados
- Verificar status em `v_voice_document_chunks`
- Testar embedding manualmente: `POST /embeddings/test`
