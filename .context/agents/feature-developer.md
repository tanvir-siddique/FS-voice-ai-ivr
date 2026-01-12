# Feature Developer - Voice AI IVR

## Contexto

Você desenvolve novas funcionalidades para o Voice AI IVR, um módulo de Secretária Virtual com IA. O sistema tem 4 camadas principais:

1. **FreeSWITCH (Lua)** - Orquestração de chamadas
2. **Voice AI Service (Python)** - Processamento de IA
3. **FusionPBX (PHP)** - Interface web de configuração
4. **PostgreSQL** - Persistência de dados

## Workflow para Nova Feature

### 1. Planejamento
- Ler `openspec/changes/add-voice-ai-ivr/proposal.md`
- Verificar `tasks.md` para status atual
- Identificar componentes afetados

### 2. Ordem de Implementação

```
1. Database (migrations)
     ↓
2. Voice AI Service (Python)
     ↓
3. FreeSWITCH (Lua) - se necessário
     ↓
4. FusionPBX (PHP) - interface
     ↓
5. Testes
```

### 3. Implementação

#### Database
```sql
-- database/migrations/00X_feature_name.sql
-- SEMPRE idempotente!
CREATE TABLE IF NOT EXISTS v_voice_feature (...);
```

#### Python (Voice AI Service)
```python
# Criar endpoint em api/
# Criar service em services/
# Atualizar models/request.py e response.py
```

#### Lua (FreeSWITCH)
```lua
-- Apenas se a feature envolver fluxo de chamada
-- Editar freeswitch/scripts/secretary_ai.lua
```

#### PHP (FusionPBX)
```php
// Criar páginas em fusionpbx-app/voice_secretary/
// Criar classes em resources/classes/
```

## Regras Críticas

### Multi-Tenant
```python
# TODA feature DEVE suportar multi-tenant
class FeatureRequest(BaseRequest):  # Herda domain_uuid
    feature_param: str
```

### Não Quebrar Existente
```python
# ✅ Adicionar, não modificar
class ExistingRequest(BaseModel):
    existing_field: str
    new_field: Optional[str] = None  # Opcional para compatibilidade

# ❌ NUNCA remover ou renomear campos existentes
```

### Compatibilidade de Linguagem
```python
# Python: async/await, Pydantic, FastAPI
# Lua: FreeSWITCH mod_lua (sem dependências externas)
# PHP: FusionPBX padrões (require_once, PDO)
```

## Exemplos

### Adicionar Novo Provider STT

```python
# 1. services/stt/novo_provider.py
from .base import BaseSTT

class NovoProviderSTT(BaseSTT):
    @property
    def name(self) -> str:
        return "novo_provider"
    
    async def transcribe(self, audio_path: str, language: str = "pt-BR") -> str:
        # Implementação com SDK do provider
        pass
    
    async def health_check(self) -> bool:
        return True

# 2. Registrar em services/stt/factory.py
# (automático se seguir padrão)

# 3. Adicionar dependência em requirements.txt
# novo-provider-sdk>=1.0.0
```

### Adicionar Campo na Secretária

```sql
-- 1. database/migrations/00X_add_field.sql
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'v_voice_secretaries' 
        AND column_name = 'new_field'
    ) THEN
        ALTER TABLE v_voice_secretaries 
        ADD COLUMN new_field VARCHAR(255) DEFAULT 'valor';
    END IF;
END $$;
```

```php
<!-- 2. fusionpbx-app/voice_secretary/secretary_edit.php -->
<tr>
    <td class="vncell">Novo Campo</td>
    <td class="vtable">
        <input type="text" name="new_field" value="<?php echo $new_field; ?>">
    </td>
</tr>
```

### Adicionar Ação no LLM

```python
# 1. Atualizar system_prompt padrão
SYSTEM_PROMPT = """
...
Se for necessário criar ticket, responda com:
{"action": "create_ticket", "summary": "resumo", "priority": "alta"}
"""

# 2. Processar ação no chat endpoint
if result.action == "create_ticket":
    await create_ticket_webhook(domain_uuid, result.action_data)
```

## Checklist de Feature

- [ ] Migration SQL idempotente
- [ ] Python: endpoint com validação domain_uuid
- [ ] Python: testes unitários
- [ ] Lua: alterações (se necessário)
- [ ] PHP: páginas de UI (se necessário)
- [ ] Documentação atualizada
- [ ] tasks.md atualizado
