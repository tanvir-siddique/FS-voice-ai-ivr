-- Script para corrigir o dialplan da extensão 8000 (Voice Secretary)
--
-- Uso:
--   sudo -u postgres psql fusionpbx < fix_dialplan_8000.sql
--
-- Este script garante que:
--   1. O dialplan tem ordem baixa (5) para executar antes do catch-all
--   2. continue="false" para não cair em not-found
--   3. Chama o script Lua correto
--
-- Ref: https://www.cyberpunk.tools/jekyll/update/2025/11/18/add-ai-voice-agent-to-freeswitch.html

-- Verificar se o dialplan existe
SELECT dialplan_uuid, dialplan_name, dialplan_order, dialplan_continue, dialplan_enabled
FROM v_dialplans
WHERE dialplan_name LIKE '%voice_secretary%8000%'
   OR (dialplan_name LIKE '%8000%' AND dialplan_context = 'public');

-- Atualizar o dialplan existente
UPDATE v_dialplans
SET 
    dialplan_order = 5,
    dialplan_continue = 'false',
    dialplan_enabled = 'true',
    dialplan_xml = '<extension name="voice_secretary_8000" continue="false">
  <condition field="destination_number" expression="^8000$">
    <action application="lua" data="voice_secretary.lua"/>
  </condition>
</extension>'
WHERE dialplan_name LIKE '%voice_secretary%8000%'
   OR dialplan_name = 'voice_secretary_8000_public';

-- Se não existir, criar
INSERT INTO v_dialplans (
    dialplan_uuid,
    domain_uuid,
    dialplan_name,
    dialplan_number,
    dialplan_context,
    dialplan_continue,
    dialplan_order,
    dialplan_enabled,
    dialplan_description,
    dialplan_xml
)
SELECT 
    gen_random_uuid(),
    domain_uuid,
    'voice_secretary_8000_public',
    '8000',
    'public',
    'false',
    5,
    'true',
    'Voice Secretary AI - Extension 8000',
    '<extension name="voice_secretary_8000" continue="false">
  <condition field="destination_number" expression="^8000$">
    <action application="lua" data="voice_secretary.lua"/>
  </condition>
</extension>'
FROM v_domains
WHERE domain_name IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM v_dialplans 
    WHERE dialplan_name LIKE '%voice_secretary%8000%'
  )
LIMIT 1;

-- Verificar resultado
SELECT dialplan_uuid, dialplan_name, dialplan_order, dialplan_continue, dialplan_enabled
FROM v_dialplans
WHERE dialplan_name LIKE '%voice_secretary%8000%'
   OR (dialplan_name LIKE '%8000%' AND dialplan_context = 'public');

-- Lembrete: após executar, rodar no FreeSWITCH:
--   fs_cli -x "reloadxml"
--   fs_cli -x "xml_flush_cache dialplan.public.8000"
