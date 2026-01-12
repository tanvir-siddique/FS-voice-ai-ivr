-- Migration: Insert default AI providers
-- Version: 007
-- Description: Insere providers padrão para cada tipo (STT, TTS, LLM, Embeddings)
-- 
-- NOTA: Esta migration usa INSERT ON CONFLICT para ser idempotente.
-- Os providers são inseridos apenas se não existirem para o domain_uuid.
-- 
-- Para usar em produção, execute para cada domain:
-- UPDATE esta migration SET domain_uuid = '<seu-domain-uuid>' e execute.

-- =====================================================================
-- FUNÇÃO: Criar providers padrão para um domínio
-- =====================================================================

CREATE OR REPLACE FUNCTION create_default_voice_ai_providers(target_domain_uuid UUID)
RETURNS void AS $$
BEGIN
    -- =====================================================================
    -- STT PROVIDERS
    -- =====================================================================
    
    -- Whisper Local (padrão)
    INSERT INTO v_voice_ai_providers (
        domain_uuid, provider_type, provider_name, display_name, 
        config, is_default, enabled
    )
    VALUES (
        target_domain_uuid, 'stt', 'whisper_local', 'Whisper Local',
        '{"model": "base", "language": "pt"}'::jsonb,
        true, true
    )
    ON CONFLICT (domain_uuid, provider_type, provider_name) DO NOTHING;
    
    -- OpenAI Whisper API
    INSERT INTO v_voice_ai_providers (
        domain_uuid, provider_type, provider_name, display_name,
        config, is_default, enabled
    )
    VALUES (
        target_domain_uuid, 'stt', 'whisper_api', 'OpenAI Whisper API',
        '{"model": "whisper-1", "language": "pt"}'::jsonb,
        false, false
    )
    ON CONFLICT (domain_uuid, provider_type, provider_name) DO NOTHING;
    
    -- =====================================================================
    -- TTS PROVIDERS
    -- =====================================================================
    
    -- Piper Local (padrão)
    INSERT INTO v_voice_ai_providers (
        domain_uuid, provider_type, provider_name, display_name,
        config, is_default, enabled
    )
    VALUES (
        target_domain_uuid, 'tts', 'piper_local', 'Piper TTS Local',
        '{"voice": "pt_BR-faber-medium", "output_format": "wav"}'::jsonb,
        true, true
    )
    ON CONFLICT (domain_uuid, provider_type, provider_name) DO NOTHING;
    
    -- OpenAI TTS
    INSERT INTO v_voice_ai_providers (
        domain_uuid, provider_type, provider_name, display_name,
        config, is_default, enabled
    )
    VALUES (
        target_domain_uuid, 'tts', 'openai_tts', 'OpenAI TTS',
        '{"model": "tts-1", "voice": "nova"}'::jsonb,
        false, false
    )
    ON CONFLICT (domain_uuid, provider_type, provider_name) DO NOTHING;
    
    -- ElevenLabs
    INSERT INTO v_voice_ai_providers (
        domain_uuid, provider_type, provider_name, display_name,
        config, is_default, enabled
    )
    VALUES (
        target_domain_uuid, 'tts', 'elevenlabs', 'ElevenLabs',
        '{"voice_id": "", "model_id": "eleven_multilingual_v2"}'::jsonb,
        false, false
    )
    ON CONFLICT (domain_uuid, provider_type, provider_name) DO NOTHING;
    
    -- =====================================================================
    -- LLM PROVIDERS
    -- =====================================================================
    
    -- OpenAI GPT-4o-mini (padrão)
    INSERT INTO v_voice_ai_providers (
        domain_uuid, provider_type, provider_name, display_name,
        config, is_default, enabled
    )
    VALUES (
        target_domain_uuid, 'llm', 'openai', 'OpenAI GPT-4o-mini',
        '{"model": "gpt-4o-mini", "temperature": 0.7, "max_tokens": 500}'::jsonb,
        true, false
    )
    ON CONFLICT (domain_uuid, provider_type, provider_name) DO NOTHING;
    
    -- Anthropic Claude
    INSERT INTO v_voice_ai_providers (
        domain_uuid, provider_type, provider_name, display_name,
        config, is_default, enabled
    )
    VALUES (
        target_domain_uuid, 'llm', 'anthropic', 'Anthropic Claude',
        '{"model": "claude-3-haiku-20240307", "temperature": 0.7, "max_tokens": 500}'::jsonb,
        false, false
    )
    ON CONFLICT (domain_uuid, provider_type, provider_name) DO NOTHING;
    
    -- Groq (Llama rápido)
    INSERT INTO v_voice_ai_providers (
        domain_uuid, provider_type, provider_name, display_name,
        config, is_default, enabled
    )
    VALUES (
        target_domain_uuid, 'llm', 'groq', 'Groq Llama',
        '{"model": "llama-3.1-8b-instant", "temperature": 0.7, "max_tokens": 500}'::jsonb,
        false, false
    )
    ON CONFLICT (domain_uuid, provider_type, provider_name) DO NOTHING;
    
    -- Ollama Local
    INSERT INTO v_voice_ai_providers (
        domain_uuid, provider_type, provider_name, display_name,
        config, is_default, enabled
    )
    VALUES (
        target_domain_uuid, 'llm', 'ollama_local', 'Ollama Local',
        '{"model": "llama3.2", "base_url": "http://localhost:11434"}'::jsonb,
        false, true
    )
    ON CONFLICT (domain_uuid, provider_type, provider_name) DO NOTHING;
    
    -- =====================================================================
    -- EMBEDDINGS PROVIDERS
    -- =====================================================================
    
    -- Local sentence-transformers (padrão)
    INSERT INTO v_voice_ai_providers (
        domain_uuid, provider_type, provider_name, display_name,
        config, is_default, enabled
    )
    VALUES (
        target_domain_uuid, 'embeddings', 'local', 'Local Embeddings',
        '{"model": "all-MiniLM-L6-v2", "dimension": 384}'::jsonb,
        true, true
    )
    ON CONFLICT (domain_uuid, provider_type, provider_name) DO NOTHING;
    
    -- OpenAI Embeddings
    INSERT INTO v_voice_ai_providers (
        domain_uuid, provider_type, provider_name, display_name,
        config, is_default, enabled
    )
    VALUES (
        target_domain_uuid, 'embeddings', 'openai', 'OpenAI Embeddings',
        '{"model": "text-embedding-3-small", "dimension": 1536}'::jsonb,
        false, false
    )
    ON CONFLICT (domain_uuid, provider_type, provider_name) DO NOTHING;

END;
$$ LANGUAGE plpgsql;

-- =====================================================================
-- COMENTÁRIO: Para criar providers para um domínio específico, execute:
-- SELECT create_default_voice_ai_providers('seu-domain-uuid-aqui');
-- =====================================================================

COMMENT ON FUNCTION create_default_voice_ai_providers(UUID) IS 
'Cria providers de IA padrão para um domínio. Idempotente - não duplica se já existir.';
