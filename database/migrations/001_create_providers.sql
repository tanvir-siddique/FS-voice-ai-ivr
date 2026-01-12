-- ============================================
-- Migration 001: Create v_voice_ai_providers table
-- Voice AI IVR - Provedores de IA Multi-Provider
-- 
-- ⚠️ MULTI-TENANT: domain_uuid é OBRIGATÓRIO
-- ============================================

-- Criar tabela apenas se não existir
CREATE TABLE IF NOT EXISTS v_voice_ai_providers (
    voice_ai_provider_uuid UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    domain_uuid UUID NOT NULL REFERENCES v_domains(domain_uuid) ON DELETE CASCADE,
    
    -- Tipo e Provider
    provider_type VARCHAR(20) NOT NULL,
    provider_name VARCHAR(50) NOT NULL,
    
    -- Configuração (JSON flexível)
    config JSONB NOT NULL DEFAULT '{}',
    
    -- Controle
    is_default BOOLEAN DEFAULT FALSE,
    is_enabled BOOLEAN DEFAULT TRUE,
    priority INTEGER DEFAULT 0,
    
    -- Limites e custos
    rate_limit_rpm INTEGER,
    cost_per_unit DECIMAL(10,6),
    cost_unit VARCHAR(20),
    
    -- Timestamps
    insert_date TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    update_date TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    
    -- Constraints
    UNIQUE(domain_uuid, provider_type, provider_name)
);

-- Check constraints
DO $$ 
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'chk_provider_type'
    ) THEN
        ALTER TABLE v_voice_ai_providers ADD CONSTRAINT chk_provider_type 
            CHECK (provider_type IN ('stt', 'tts', 'llm', 'embeddings'));
    END IF;
END $$;

DO $$ 
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'chk_provider_name'
    ) THEN
        ALTER TABLE v_voice_ai_providers ADD CONSTRAINT chk_provider_name
            CHECK (provider_name IN (
                -- STT Providers
                'whisper_local', 'whisper_api', 'azure_speech', 'google_speech', 
                'aws_transcribe', 'deepgram',
                -- TTS Providers
                'piper_local', 'coqui_local', 'openai_tts', 'elevenlabs', 
                'azure_neural', 'google_tts', 'aws_polly', 'playht',
                -- LLM Providers
                'openai', 'azure_openai', 'anthropic', 'google_gemini', 
                'aws_bedrock', 'groq', 'ollama_local', 'lmstudio_local',
                -- Embeddings Providers
                'openai_embeddings', 'azure_embeddings', 'cohere', 'voyage', 
                'local_embeddings'
            ));
    END IF;
END $$;

-- Índices para performance multi-tenant
CREATE INDEX IF NOT EXISTS idx_voice_ai_providers_domain 
    ON v_voice_ai_providers(domain_uuid);
CREATE INDEX IF NOT EXISTS idx_voice_ai_providers_type_enabled 
    ON v_voice_ai_providers(provider_type, is_enabled, priority);
CREATE INDEX IF NOT EXISTS idx_voice_ai_providers_default 
    ON v_voice_ai_providers(domain_uuid, provider_type) 
    WHERE is_default = TRUE;

-- Comentário
COMMENT ON TABLE v_voice_ai_providers IS 'Configuração de provedores de IA por tenant (STT, TTS, LLM, Embeddings)';
COMMENT ON COLUMN v_voice_ai_providers.domain_uuid IS 'OBRIGATÓRIO: UUID do domínio para isolamento multi-tenant';
COMMENT ON COLUMN v_voice_ai_providers.config IS 'Configuração JSON do provider (API keys, modelos, etc)';
