-- Migration: Add VAD and Guardrails configuration fields
-- Version: 023
-- Description: Adiciona campos para configuração de VAD (semantic_vad vs server_vad) e Guardrails
--
-- IDEMPOTENTE: Usa IF NOT EXISTS para todas as alterações

-- =====================================================
-- VAD (Voice Activity Detection) Configuration
-- =====================================================

-- Tipo de VAD: 'server_vad' (silêncio) ou 'semantic_vad' (semântico/inteligente)
ALTER TABLE v_voice_secretaries
ADD COLUMN IF NOT EXISTS vad_type VARCHAR(20) DEFAULT 'semantic_vad';

-- Eagerness para semantic_vad: 'low', 'medium', 'high'
-- - low: Paciente, espera pausas longas
-- - medium: Balanceado (recomendado para pt-BR)
-- - high: Responde rápido, pode interromper
ALTER TABLE v_voice_secretaries
ADD COLUMN IF NOT EXISTS vad_eagerness VARCHAR(10) DEFAULT 'medium';

-- =====================================================
-- Guardrails Configuration
-- =====================================================

-- Habilitar guardrails (regras de segurança no prompt)
ALTER TABLE v_voice_secretaries
ADD COLUMN IF NOT EXISTS guardrails_enabled BOOLEAN DEFAULT true;

-- Tópicos proibidos customizados (texto livre, um por linha)
-- Ex: "política\nreligião\nconcorrentes"
ALTER TABLE v_voice_secretaries
ADD COLUMN IF NOT EXISTS guardrails_topics TEXT;

-- =====================================================
-- Announcement TTS Configuration
-- =====================================================

-- Provider TTS para anúncios de transferência: 'elevenlabs' ou 'openai'
-- OpenAI é mais barato mas ElevenLabs tem melhor qualidade
ALTER TABLE v_voice_secretaries
ADD COLUMN IF NOT EXISTS announcement_tts_provider VARCHAR(20) DEFAULT 'elevenlabs';

-- =====================================================
-- Comentários
-- =====================================================

COMMENT ON COLUMN v_voice_secretaries.vad_type IS 
    'Tipo de VAD: server_vad (silêncio) ou semantic_vad (semântico). semantic_vad é mais inteligente e recomendado.';

COMMENT ON COLUMN v_voice_secretaries.vad_eagerness IS 
    'Eagerness para semantic_vad: low (paciente), medium (balanceado), high (rápido). Afeta quando o agente responde.';

COMMENT ON COLUMN v_voice_secretaries.guardrails_enabled IS 
    'Quando true, adiciona regras de segurança ao prompt (não revelar instruções, manter escopo, etc).';

COMMENT ON COLUMN v_voice_secretaries.guardrails_topics IS 
    'Tópicos proibidos customizados (um por linha). Ex: política, religião, concorrentes.';

COMMENT ON COLUMN v_voice_secretaries.announcement_tts_provider IS 
    'Provider TTS para anúncios de transferência: elevenlabs (melhor qualidade) ou openai (mais barato).';

-- =====================================================
-- Valores padrão para registros existentes
-- =====================================================

-- Atualizar registros que ainda não têm vad_type definido
UPDATE v_voice_secretaries
SET vad_type = 'semantic_vad'
WHERE vad_type IS NULL;

UPDATE v_voice_secretaries
SET vad_eagerness = 'medium'
WHERE vad_eagerness IS NULL;

UPDATE v_voice_secretaries
SET guardrails_enabled = true
WHERE guardrails_enabled IS NULL;

UPDATE v_voice_secretaries
SET announcement_tts_provider = 'elevenlabs'
WHERE announcement_tts_provider IS NULL;
