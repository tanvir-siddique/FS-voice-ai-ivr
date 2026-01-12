# Database Specialist - Voice AI IVR

## Papel
Especialista em PostgreSQL, migrations, e integração com FusionPBX.

## Contexto

O sistema usa o **mesmo PostgreSQL do FusionPBX**, adicionando tabelas próprias com prefixo `v_voice_`.

## Schema Principal

```sql
-- Providers de IA por tenant
CREATE TABLE v_voice_ai_providers (
    provider_uuid UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    domain_uuid UUID NOT NULL REFERENCES v_domains(domain_uuid),
    provider_type VARCHAR(20) NOT NULL,  -- stt, tts, llm, embeddings
    provider_name VARCHAR(50) NOT NULL,
    config JSONB NOT NULL,  -- API keys criptografadas
    is_default BOOLEAN DEFAULT false,
    is_enabled BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT NOW()
);

-- Secretárias virtuais
CREATE TABLE v_voice_secretaries (
    secretary_uuid UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    domain_uuid UUID NOT NULL REFERENCES v_domains(domain_uuid),
    name VARCHAR(100) NOT NULL,
    extension VARCHAR(10),
    processing_mode VARCHAR(20) DEFAULT 'turn_based',
    system_prompt TEXT,
    greeting TEXT,
    farewell TEXT,
    stt_provider_uuid UUID REFERENCES v_voice_ai_providers,
    tts_provider_uuid UUID REFERENCES v_voice_ai_providers,
    llm_provider_uuid UUID REFERENCES v_voice_ai_providers,
    realtime_provider_uuid UUID REFERENCES v_voice_ai_providers,
    created_at TIMESTAMP DEFAULT NOW()
);

-- Conversas
CREATE TABLE v_voice_conversations (
    conversation_uuid UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    domain_uuid UUID NOT NULL,
    secretary_uuid UUID REFERENCES v_voice_secretaries,
    caller_id VARCHAR(50),
    call_uuid VARCHAR(100),
    started_at TIMESTAMP DEFAULT NOW(),
    ended_at TIMESTAMP,
    resolution VARCHAR(50),
    transferred_to VARCHAR(50)
);

-- Mensagens
CREATE TABLE v_voice_messages (
    message_uuid UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    conversation_uuid UUID REFERENCES v_voice_conversations,
    role VARCHAR(20),  -- user, assistant, system
    content TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);
```

## Migrations

### Estrutura

```
database/migrations/
├── 001_create_providers.sql
├── 002_create_secretaries.sql
├── 003_create_conversations.sql
├── 004_create_documents.sql
├── 005_create_transfer_rules.sql
├── 006_create_messages.sql
└── 007_insert_default_providers.sql
```

### Criar Nova Migration

```bash
# Convenção de nome
touch database/migrations/00X_descricao.sql
```

### Regras de Migration

```sql
-- SEMPRE idempotente
CREATE TABLE IF NOT EXISTS ...

-- Com IF NOT EXISTS
ALTER TABLE tabela ADD COLUMN IF NOT EXISTS coluna tipo;

-- Ou verificar antes
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'tabela' AND column_name = 'coluna'
    ) THEN
        ALTER TABLE tabela ADD COLUMN coluna tipo;
    END IF;
END $$;
```

## Queries Comuns

### Buscar Config de Provider

```sql
SELECT 
    p.provider_uuid,
    p.provider_name,
    pgp_sym_decrypt(p.config::bytea, current_setting('app.encryption_key'))::json as config
FROM v_voice_ai_providers p
WHERE p.domain_uuid = $1
  AND p.provider_type = $2
  AND p.is_enabled = true
ORDER BY p.is_default DESC
LIMIT 1;
```

### Buscar Secretária por Ramal

```sql
SELECT s.*, 
       p_stt.provider_name as stt_provider,
       p_tts.provider_name as tts_provider,
       p_llm.provider_name as llm_provider
FROM v_voice_secretaries s
LEFT JOIN v_voice_ai_providers p_stt ON s.stt_provider_uuid = p_stt.provider_uuid
LEFT JOIN v_voice_ai_providers p_tts ON s.tts_provider_uuid = p_tts.provider_uuid
LEFT JOIN v_voice_ai_providers p_llm ON s.llm_provider_uuid = p_llm.provider_uuid
WHERE s.domain_uuid = $1
  AND s.extension = $2;
```

### Histórico de Conversas

```sql
SELECT 
    c.conversation_uuid,
    c.caller_id,
    c.started_at,
    c.ended_at,
    c.resolution,
    s.name as secretary_name,
    COUNT(m.message_uuid) as message_count
FROM v_voice_conversations c
JOIN v_voice_secretaries s ON c.secretary_uuid = s.secretary_uuid
LEFT JOIN v_voice_messages m ON c.conversation_uuid = m.conversation_uuid
WHERE c.domain_uuid = $1
  AND c.started_at >= $2
GROUP BY c.conversation_uuid, s.name
ORDER BY c.started_at DESC;
```

## Índices

```sql
-- Multi-tenant: SEMPRE indexar domain_uuid
CREATE INDEX idx_providers_domain ON v_voice_ai_providers(domain_uuid);
CREATE INDEX idx_secretaries_domain ON v_voice_secretaries(domain_uuid);
CREATE INDEX idx_conversations_domain ON v_voice_conversations(domain_uuid);

-- Busca por ramal
CREATE INDEX idx_secretaries_extension ON v_voice_secretaries(domain_uuid, extension);

-- Busca por data
CREATE INDEX idx_conversations_date ON v_voice_conversations(domain_uuid, started_at);
```

## Conexão (Python)

```python
# services/database.py
import asyncpg

async def create_pool():
    return await asyncpg.create_pool(
        settings.DATABASE_URL,
        min_size=5,
        max_size=20,
        command_timeout=30,
        max_inactive_connection_lifetime=300
    )
```

## Cuidados

- ✅ Sempre filtrar por `domain_uuid` (multi-tenant)
- ✅ Usar queries parametrizadas (nunca concatenar)
- ✅ Criptografar API keys no banco
- ✅ Migrations idempotentes
- ❌ Nunca expor dados de outros domains
- ❌ Nunca logar queries com dados sensíveis

---
*Playbook para: Database Specialist*
