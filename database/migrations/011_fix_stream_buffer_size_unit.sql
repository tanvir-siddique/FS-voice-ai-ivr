-- =============================================================================
-- CORREÇÃO: STREAM_BUFFER_SIZE É EM MILISSEGUNDOS, NÃO SAMPLES!
-- =============================================================================
-- 
-- Descoberta (16/Jan/2026):
-- O mod_audio_stream README documenta claramente:
--   "STREAM_BUFFER_SIZE | buffer duration in MILLISECONDS, divisible by 20 | 20"
--
-- Estava configurado como 320 (pensando ser 320 samples = 20ms @ 16kHz)
-- Mas na verdade era interpretado como 320ms de buffer!
-- Isso causava chunks de áudio chegando a cada 320ms ao invés de 20ms.
--
-- Correção: 320 → 20 (20ms = valor padrão recomendado)
-- =============================================================================

-- Atualizar default da coluna
ALTER TABLE v_voice_secretaries 
ALTER COLUMN stream_buffer_size SET DEFAULT 20;

-- Corrigir valores existentes que usam o valor errado (320)
UPDATE v_voice_secretaries 
SET stream_buffer_size = 20 
WHERE stream_buffer_size = 320;

-- Atualizar comentário
COMMENT ON COLUMN v_voice_secretaries.stream_buffer_size IS 
'mod_audio_stream buffer duration in MILLISECONDS (not samples!). Default: 20ms. Higher = more stable but higher latency.';

-- Verificar correção
SELECT 
    voice_secretary_uuid,
    secretary_name,
    stream_buffer_size,
    CASE 
        WHEN stream_buffer_size = 20 THEN 'OK (20ms)'
        WHEN stream_buffer_size < 20 THEN 'WARNING: too low'
        WHEN stream_buffer_size > 100 THEN 'WARNING: too high latency'
        ELSE 'OK'
    END as status
FROM v_voice_secretaries;
