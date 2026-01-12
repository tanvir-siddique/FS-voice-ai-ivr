# Database Specialist - Voice AI IVR

## Contexto

Você trabalha com PostgreSQL 13+ no contexto do FusionPBX. O Voice AI IVR adiciona 6 novas tabelas ao schema existente.

## Schema

```sql
-- Tabelas do Voice AI (prefixo v_voice_*)

v_voice_ai_providers     -- Configurações de providers (STT/TTS/LLM/Embeddings)
v_voice_secretaries      -- Secretárias configuradas
v_voice_documents        -- Documentos para RAG
v_voice_document_chunks  -- Chunks vetorizados
v_voice_transfer_rules   -- Regras de transferência
v_voice_conversations    -- Histórico de conversas
v_voice_messages         -- Mensagens das conversas
```

## Regras Críticas

### 1. Multi-Tenant OBRIGATÓRIO
```sql
-- ✅ TODAS as tabelas DEVEM ter domain_uuid
CREATE TABLE v_voice_example (
    example_uuid UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    domain_uuid UUID NOT NULL REFERENCES v_domains(domain_uuid) ON DELETE CASCADE,
    -- outros campos...
);

-- ✅ TODAS as queries DEVEM filtrar por domain_uuid
SELECT * FROM v_voice_secretaries WHERE domain_uuid = $1;
```

### 2. Migrations IDEMPOTENTES
```sql
-- ✅ CORRETO - Verifica antes de criar
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'v_voice_secretaries' 
        AND column_name = 'new_column'
    ) THEN
        ALTER TABLE v_voice_secretaries ADD COLUMN new_column VARCHAR(255);
    END IF;
END $$;

-- ✅ CORRETO - CREATE IF NOT EXISTS
CREATE TABLE IF NOT EXISTS v_voice_example (...);
CREATE INDEX IF NOT EXISTS idx_example ON v_voice_example(field);

-- ❌ ERRADO - Falha se executar 2 vezes
ALTER TABLE v_voice_secretaries ADD COLUMN new_column VARCHAR(255);
```

### 3. Índices para Performance
```sql
-- Índice para queries por tenant
CREATE INDEX idx_voice_secretaries_domain 
ON v_voice_secretaries(domain_uuid);

-- Índice composto para lookups frequentes
CREATE INDEX idx_voice_conversations_domain_caller
ON v_voice_conversations(domain_uuid, caller_id);

-- Índice para busca vetorial (pgvector)
CREATE INDEX idx_chunks_embedding 
ON v_voice_document_chunks 
USING ivfflat (embedding vector_cosine_ops);
```

## Tabelas Principais

### v_voice_ai_providers
```sql
CREATE TABLE v_voice_ai_providers (
    provider_uuid UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    domain_uuid UUID NOT NULL REFERENCES v_domains(domain_uuid) ON DELETE CASCADE,
    provider_type VARCHAR(50) NOT NULL CHECK (provider_type IN ('stt', 'tts', 'llm', 'embeddings')),
    provider_name VARCHAR(100) NOT NULL,
    display_name VARCHAR(255),
    config JSONB NOT NULL DEFAULT '{}',  -- API keys, endpoints, etc
    is_default BOOLEAN DEFAULT false,
    enabled BOOLEAN DEFAULT true,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(domain_uuid, provider_type, provider_name)
);
```

### v_voice_secretaries
```sql
CREATE TABLE v_voice_secretaries (
    voice_secretary_uuid UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    domain_uuid UUID NOT NULL REFERENCES v_domains(domain_uuid) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    enabled BOOLEAN DEFAULT true,
    
    -- Providers (FK)
    stt_provider_uuid UUID REFERENCES v_voice_ai_providers(provider_uuid),
    tts_provider_uuid UUID REFERENCES v_voice_ai_providers(provider_uuid),
    llm_provider_uuid UUID REFERENCES v_voice_ai_providers(provider_uuid),
    embeddings_provider_uuid UUID REFERENCES v_voice_ai_providers(provider_uuid),
    
    -- Configurações
    system_prompt TEXT,
    greeting_message TEXT,
    goodbye_message TEXT,
    voice_name VARCHAR(100),
    language VARCHAR(10) DEFAULT 'pt-BR',
    rag_enabled BOOLEAN DEFAULT true,
    rag_similarity_threshold DECIMAL(3,2) DEFAULT 0.5,
    max_conversation_turns INTEGER DEFAULT 10,
    
    -- Webhook (OmniPlay)
    webhook_url VARCHAR(500),
    webhook_enabled BOOLEAN DEFAULT false,
    
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);
```

### v_voice_document_chunks (pgvector)
```sql
-- Requer extensão pgvector
CREATE EXTENSION IF NOT EXISTS vector;

CREATE TABLE v_voice_document_chunks (
    chunk_uuid UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    domain_uuid UUID NOT NULL REFERENCES v_domains(domain_uuid) ON DELETE CASCADE,
    document_uuid UUID NOT NULL REFERENCES v_voice_documents(document_uuid) ON DELETE CASCADE,
    chunk_index INTEGER NOT NULL,
    content TEXT NOT NULL,
    embedding vector(1536),  -- OpenAI ada-002 dimension
    token_count INTEGER,
    metadata JSONB DEFAULT '{}',
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

-- Índice para busca vetorial eficiente
CREATE INDEX idx_chunks_embedding_ivfflat
ON v_voice_document_chunks 
USING ivfflat (embedding vector_cosine_ops)
WITH (lists = 100);
```

## Queries Comuns

### Busca Vetorial (RAG)
```sql
-- Encontrar chunks mais similares
SELECT 
    chunk_uuid,
    content,
    1 - (embedding <=> $2::vector) as similarity
FROM v_voice_document_chunks
WHERE domain_uuid = $1
ORDER BY embedding <=> $2::vector
LIMIT 5;
```

### Secretária com Providers
```sql
SELECT 
    s.*,
    stt.config as stt_config,
    stt.provider_name as stt_provider,
    tts.config as tts_config,
    tts.provider_name as tts_provider,
    llm.config as llm_config,
    llm.provider_name as llm_provider
FROM v_voice_secretaries s
LEFT JOIN v_voice_ai_providers stt ON s.stt_provider_uuid = stt.provider_uuid
LEFT JOIN v_voice_ai_providers tts ON s.tts_provider_uuid = tts.provider_uuid
LEFT JOIN v_voice_ai_providers llm ON s.llm_provider_uuid = llm.provider_uuid
WHERE s.domain_uuid = $1 AND s.voice_secretary_uuid = $2;
```

## Backup e Manutenção

```bash
# Backup específico das tabelas voice_ai
pg_dump -h localhost -U fusionpbx fusionpbx \
    -t 'v_voice_*' \
    -f voice_ai_backup.sql

# Vacuum para performance
VACUUM ANALYZE v_voice_document_chunks;
```

## Troubleshooting

### Erro: "vector extension not found"
```sql
CREATE EXTENSION IF NOT EXISTS vector;
```

### Query lenta em chunks
```sql
-- Verificar índice
EXPLAIN ANALYZE SELECT ... ORDER BY embedding <=> $1 LIMIT 5;

-- Rebuild índice se necessário
REINDEX INDEX idx_chunks_embedding_ivfflat;
```
