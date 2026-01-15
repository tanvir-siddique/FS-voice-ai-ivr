-- Migration: Add Handoff OmniPlay fields to v_voice_secretaries
-- Ref: openspec/changes/add-realtime-handoff-omni/proposal.md

-- OmniPlay Company ID (mapping FusionPBX domain_uuid → OmniPlay companyId)
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'v_voice_secretaries' 
        AND column_name = 'omniplay_company_id'
    ) THEN
        ALTER TABLE v_voice_secretaries ADD COLUMN omniplay_company_id INTEGER;
        COMMENT ON COLUMN v_voice_secretaries.omniplay_company_id IS 'OmniPlay companyId for API integration';
    END IF;
END $$;

-- Handoff enabled flag
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'v_voice_secretaries' 
        AND column_name = 'handoff_enabled'
    ) THEN
        ALTER TABLE v_voice_secretaries ADD COLUMN handoff_enabled BOOLEAN DEFAULT true;
        COMMENT ON COLUMN v_voice_secretaries.handoff_enabled IS 'Enable handoff to human agents or ticket creation';
    END IF;
END $$;

-- Handoff keywords (comma-separated)
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'v_voice_secretaries' 
        AND column_name = 'handoff_keywords'
    ) THEN
        ALTER TABLE v_voice_secretaries ADD COLUMN handoff_keywords VARCHAR(500) DEFAULT 'atendente,humano,pessoa,operador';
        COMMENT ON COLUMN v_voice_secretaries.handoff_keywords IS 'Comma-separated keywords that trigger handoff';
    END IF;
END $$;

-- Fallback ticket enabled
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'v_voice_secretaries' 
        AND column_name = 'fallback_ticket_enabled'
    ) THEN
        ALTER TABLE v_voice_secretaries ADD COLUMN fallback_ticket_enabled BOOLEAN DEFAULT true;
        COMMENT ON COLUMN v_voice_secretaries.fallback_ticket_enabled IS 'Create pending ticket when no agents available';
    END IF;
END $$;

-- Handoff queue ID (OmniPlay queue for ticket assignment)
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'v_voice_secretaries' 
        AND column_name = 'handoff_queue_id'
    ) THEN
        ALTER TABLE v_voice_secretaries ADD COLUMN handoff_queue_id INTEGER;
        COMMENT ON COLUMN v_voice_secretaries.handoff_queue_id IS 'OmniPlay queue ID for ticket assignment';
    END IF;
END $$;

-- Handoff timeout (already exists, but ensure it's there)
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'v_voice_secretaries' 
        AND column_name = 'handoff_timeout'
    ) THEN
        ALTER TABLE v_voice_secretaries ADD COLUMN handoff_timeout INTEGER DEFAULT 30;
        COMMENT ON COLUMN v_voice_secretaries.handoff_timeout IS 'Timeout in seconds before fallback';
    END IF;
END $$;

-- Presence check enabled (already exists, but ensure it's there)
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'v_voice_secretaries' 
        AND column_name = 'presence_check_enabled'
    ) THEN
        ALTER TABLE v_voice_secretaries ADD COLUMN presence_check_enabled BOOLEAN DEFAULT true;
        COMMENT ON COLUMN v_voice_secretaries.presence_check_enabled IS 'Check extension presence before transfer';
    END IF;
END $$;

-- Time condition UUID (business hours restriction)
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'v_voice_secretaries' 
        AND column_name = 'time_condition_uuid'
    ) THEN
        ALTER TABLE v_voice_secretaries ADD COLUMN time_condition_uuid UUID;
        COMMENT ON COLUMN v_voice_secretaries.time_condition_uuid IS 'Time condition UUID for business hours';
    END IF;
END $$;

-- Create index for faster lookups
CREATE INDEX IF NOT EXISTS idx_voice_secretaries_handoff 
ON v_voice_secretaries (domain_uuid, handoff_enabled) 
WHERE handoff_enabled = true;

SELECT 'Migration 009_add_handoff_fields completed successfully' AS status;
