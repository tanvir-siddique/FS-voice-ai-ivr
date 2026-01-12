-- ============================================
-- Migration 002: Create v_voice_secretaries table
-- Voice AI IVR - Secretárias Virtuais
-- 
-- ⚠️ MULTI-TENANT: domain_uuid é OBRIGATÓRIO
-- ============================================

CREATE TABLE IF NOT EXISTS v_voice_secretaries (
    voice_secretary_uuid UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    domain_uuid UUID NOT NULL REFERENCES v_domains(domain_uuid) ON DELETE CASCADE,
    
    -- Identidade
    secretary_name VARCHAR(100) NOT NULL,
    company_name VARCHAR(200),
    
    -- Personalidade e Prompts
    personality_prompt TEXT NOT NULL,
    greeting_message TEXT,
    farewell_message TEXT,
    fallback_message TEXT DEFAULT 'Desculpe, não entendi. Pode repetir?',
    transfer_message TEXT DEFAULT 'Vou transferir você para um atendente.',
    
    -- Referências aos Providers (se NULL, usa o default do domínio)
    stt_provider_uuid UUID REFERENCES v_voice_ai_providers(voice_ai_provider_uuid),
    tts_provider_uuid UUID REFERENCES v_voice_ai_providers(voice_ai_provider_uuid),
    llm_provider_uuid UUID REFERENCES v_voice_ai_providers(voice_ai_provider_uuid),
    embeddings_provider_uuid UUID REFERENCES v_voice_ai_providers(voice_ai_provider_uuid),
    
    -- Configurações específicas de voz
    tts_voice_id VARCHAR(100),
    tts_speed DECIMAL(3,2) DEFAULT 1.0,
    
    -- Configurações específicas de LLM
    llm_model_override VARCHAR(100),
    llm_temperature DECIMAL(3,2) DEFAULT 0.7,
    llm_max_tokens INTEGER DEFAULT 500,
    
    -- Comportamento
    max_turns INTEGER DEFAULT 20,
    silence_timeout_ms INTEGER DEFAULT 3000,
    max_recording_seconds INTEGER DEFAULT 30,
    
    -- Transferência
    transfer_extension VARCHAR(20),
    transfer_on_failure BOOLEAN DEFAULT TRUE,
    
    -- Integração OmniPlay
    create_ticket_on_transfer BOOLEAN DEFAULT FALSE,
    omniplay_webhook_url VARCHAR(500),
    omniplay_queue_id VARCHAR(50),
    
    -- Controle
    enabled BOOLEAN DEFAULT TRUE,
    insert_date TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    update_date TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Índices para performance multi-tenant
CREATE INDEX IF NOT EXISTS idx_voice_secretaries_domain 
    ON v_voice_secretaries(domain_uuid);
CREATE INDEX IF NOT EXISTS idx_voice_secretaries_enabled 
    ON v_voice_secretaries(domain_uuid, enabled);

-- Comentário
COMMENT ON TABLE v_voice_secretaries IS 'Configuração das secretárias virtuais com IA';
COMMENT ON COLUMN v_voice_secretaries.domain_uuid IS 'OBRIGATÓRIO: UUID do domínio para isolamento multi-tenant';
COMMENT ON COLUMN v_voice_secretaries.personality_prompt IS 'Prompt que define a personalidade da secretária';
