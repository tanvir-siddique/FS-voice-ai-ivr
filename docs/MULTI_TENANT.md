# Padrão Multi-Tenant - Voice AI IVR

## Visão Geral

O Voice AI IVR segue o padrão multi-tenant do FusionPBX, onde cada **domínio** (tenant) possui dados completamente isolados. O `domain_uuid` é a chave de isolamento.

## Regras Obrigatórias

### 1. Banco de Dados (PostgreSQL)

✅ **TODAS as tabelas DEVEM ter:**
```sql
domain_uuid UUID NOT NULL REFERENCES v_domains(domain_uuid) ON DELETE CASCADE
```

✅ **TODAS as tabelas DEVEM ter índice em domain_uuid:**
```sql
CREATE INDEX IF NOT EXISTS idx_<tabela>_domain ON <tabela>(domain_uuid);
```

✅ **TODAS as queries DEVEM filtrar por domain_uuid:**
```sql
-- ✅ CORRETO
SELECT * FROM v_voice_secretaries WHERE domain_uuid = $1;

-- ❌ ERRADO - NUNCA fazer isso!
SELECT * FROM v_voice_secretaries;
```

### 2. Serviço Python (FastAPI)

✅ **TODOS os endpoints DEVEM exigir domain_uuid:**
```python
from uuid import UUID
from pydantic import BaseModel, Field

class BaseRequest(BaseModel):
    domain_uuid: UUID = Field(..., description="OBRIGATÓRIO para multi-tenant")
```

✅ **TODOS os endpoints DEVEM rejeitar requisições sem domain_uuid:**
```python
if not request.domain_uuid:
    raise HTTPException(
        status_code=400,
        detail="domain_uuid is required for multi-tenant isolation"
    )
```

✅ **TODOS os dados DEVEM ser filtrados por domain_uuid:**
```python
# No DatabaseService
async def get_provider_config(self, domain_uuid: UUID, ...):
    if not domain_uuid:
        raise ValueError("domain_uuid is required")
    
    query = "SELECT * FROM ... WHERE domain_uuid = $1"
```

### 3. Scripts Lua (FreeSWITCH)

✅ **SEMPRE obter domain_uuid da sessão:**
```lua
local domain_uuid = session:getVariable("domain_uuid")
if not domain_uuid or domain_uuid == "" then
    log("ERROR", "domain_uuid not found!")
    session:hangup("NORMAL_TEMPORARY_FAILURE")
    return
end
```

✅ **SEMPRE passar domain_uuid em chamadas HTTP:**
```lua
local payload = json.encode({
    domain_uuid = domain_uuid,  -- OBRIGATÓRIO
    audio_file = audio_file,
    language = "pt",
})
```

✅ **SEMPRE filtrar queries SQL por domain_uuid:**
```lua
local sql = string.format([[
    SELECT * FROM v_voice_secretaries 
    WHERE domain_uuid = '%s'
]], domain_uuid)
```

### 4. App PHP (FusionPBX)

✅ **SEMPRE usar $_SESSION['domain_uuid']:**
```php
$domain_uuid = $_SESSION['domain_uuid'];

$sql = "SELECT * FROM v_voice_secretaries WHERE domain_uuid = :domain_uuid";
$parameters['domain_uuid'] = $domain_uuid;
```

✅ **NUNCA confiar em domain_uuid vindo do request:**
```php
// ❌ ERRADO - vulnerabilidade!
$domain_uuid = $_POST['domain_uuid'];

// ✅ CORRETO
$domain_uuid = $_SESSION['domain_uuid'];
```

## Diagrama de Fluxo

```
┌─────────────────────────────────────────────────────────────────┐
│                    CHAMADA TELEFÔNICA                           │
│                    (FreeSWITCH/FusionPBX)                       │
└─────────────────────┬───────────────────────────────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────────────────────────────┐
│  Lua Script (secretary_ai.lua)                                  │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │ domain_uuid = session:getVariable("domain_uuid")          │  │
│  │ IF NOT domain_uuid THEN hangup() END                      │  │
│  └───────────────────────────────────────────────────────────┘  │
└─────────────────────┬───────────────────────────────────────────┘
                      │ HTTP + domain_uuid
                      ▼
┌─────────────────────────────────────────────────────────────────┐
│  Python Service (FastAPI)                                       │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │ if not request.domain_uuid:                               │  │
│  │     raise HTTPException(400, "domain_uuid required")      │  │
│  └───────────────────────────────────────────────────────────┘  │
└─────────────────────┬───────────────────────────────────────────┘
                      │ SQL + domain_uuid
                      ▼
┌─────────────────────────────────────────────────────────────────┐
│  PostgreSQL                                                     │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │ SELECT * FROM v_voice_* WHERE domain_uuid = $1            │  │
│  └───────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────┘
```

## Consequências de Violação

⚠️ **Se domain_uuid não for usado corretamente:**
- Dados de um tenant podem vazar para outro
- Um tenant pode modificar dados de outro
- Violação de privacidade e segurança
- Possível violação de LGPD/GDPR

## Responsabilidades

| Componente | Responsável | Ação |
|------------|-------------|------|
| SQL/Migrations | DBA/Backend | Garantir domain_uuid em todas as tabelas |
| Python API | Backend | Validar domain_uuid em todos os endpoints |
| Lua Scripts | Telecom | Obter e passar domain_uuid |
| PHP App | Frontend | Usar $_SESSION['domain_uuid'] |
| Code Review | Todos | Verificar domain_uuid em PRs |

---

**Última atualização:** Janeiro 2026
