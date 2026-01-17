-- Migration: Add Fallback Options to v_voice_secretaries
-- Ref: voice-ai-ivr/docs/TRANSFER_SETTINGS_VS_RULES.md
-- 
-- Adiciona opções de fallback quando a transferência falha:
-- - fallback_action: O que fazer (ticket, callback, voicemail, none)
-- - fallback_user_id: Usuário padrão para atribuir tickets (opcional)
-- - Mantém handoff_queue_id existente para a fila

-- Fallback action type
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'v_voice_secretaries' 
        AND column_name = 'fallback_action'
    ) THEN
        ALTER TABLE v_voice_secretaries ADD COLUMN fallback_action VARCHAR(20) DEFAULT 'ticket';
        COMMENT ON COLUMN v_voice_secretaries.fallback_action IS 'Fallback action: ticket, callback, voicemail, none';
    END IF;
END $$;

-- Fallback user ID (OmniPlay user to assign tickets)
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'v_voice_secretaries' 
        AND column_name = 'fallback_user_id'
    ) THEN
        ALTER TABLE v_voice_secretaries ADD COLUMN fallback_user_id INTEGER;
        COMMENT ON COLUMN v_voice_secretaries.fallback_user_id IS 'OmniPlay user ID to assign tickets (optional, for specific routing)';
    END IF;
END $$;

-- Fallback priority (ticket priority: low, medium, high, urgent)
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'v_voice_secretaries' 
        AND column_name = 'fallback_priority'
    ) THEN
        ALTER TABLE v_voice_secretaries ADD COLUMN fallback_priority VARCHAR(10) DEFAULT 'medium';
        COMMENT ON COLUMN v_voice_secretaries.fallback_priority IS 'Ticket priority: low, medium, high, urgent';
    END IF;
END $$;

-- Fallback notification enabled (notify via WhatsApp/Email when ticket created)
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'v_voice_secretaries' 
        AND column_name = 'fallback_notify_enabled'
    ) THEN
        ALTER TABLE v_voice_secretaries ADD COLUMN fallback_notify_enabled BOOLEAN DEFAULT true;
        COMMENT ON COLUMN v_voice_secretaries.fallback_notify_enabled IS 'Send notification when fallback ticket is created';
    END IF;
END $$;

SELECT 'Migration 016_add_fallback_options completed successfully' AS status;
