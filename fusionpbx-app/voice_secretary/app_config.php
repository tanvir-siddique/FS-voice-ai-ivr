<?php
/**
 * Voice Secretary App Configuration
 * FusionPBX Application
 * 
 * ⚠️ MULTI-TENANT: TODAS as tabelas têm domain_uuid como foreign key obrigatória
 */

// Application details
$apps[$x]['name'] = "Voice Secretary";
$apps[$x]['uuid'] = "a1b2c3d4-e5f6-7890-abcd-ef1234567890";
$apps[$x]['category'] = "AI";
$apps[$x]['subcategory'] = "";
$apps[$x]['version'] = "1.0.0";
$apps[$x]['license'] = "Proprietary";
$apps[$x]['url'] = "https://omniplay.com.br";
$apps[$x]['description']['en-us'] = "AI-powered virtual secretary for phone calls";
$apps[$x]['description']['pt-br'] = "Secretária virtual com IA para atendimento telefônico";

// Menu
$apps[$x]['menu'][0]['title']['en-us'] = "Voice Secretary";
$apps[$x]['menu'][0]['title']['pt-br'] = "Secretária Virtual";
$apps[$x]['menu'][0]['uuid'] = "b2c3d4e5-f6a7-8901-bcde-f12345678901";
$apps[$x]['menu'][0]['parent_uuid'] = "0438b504-8613-7887-c420-c837ffb20cb1"; // Apps menu
$apps[$x]['menu'][0]['category'] = "internal";
$apps[$x]['menu'][0]['path'] = "/app/voice_secretary/secretary.php";
$apps[$x]['menu'][0]['groups'][] = "superadmin";
$apps[$x]['menu'][0]['groups'][] = "admin";

// Sub-menus
$apps[$x]['menu'][1]['title']['en-us'] = "Secretaries";
$apps[$x]['menu'][1]['title']['pt-br'] = "Secretárias";
$apps[$x]['menu'][1]['uuid'] = "c3d4e5f6-a7b8-9012-cdef-123456789012";
$apps[$x]['menu'][1]['parent_uuid'] = "b2c3d4e5-f6a7-8901-bcde-f12345678901";
$apps[$x]['menu'][1]['category'] = "internal";
$apps[$x]['menu'][1]['path'] = "/app/voice_secretary/secretary.php";
$apps[$x]['menu'][1]['groups'][] = "superadmin";
$apps[$x]['menu'][1]['groups'][] = "admin";

$apps[$x]['menu'][2]['title']['en-us'] = "AI Providers";
$apps[$x]['menu'][2]['title']['pt-br'] = "Provedores de IA";
$apps[$x]['menu'][2]['uuid'] = "d4e5f6a7-b8c9-0123-def0-234567890123";
$apps[$x]['menu'][2]['parent_uuid'] = "b2c3d4e5-f6a7-8901-bcde-f12345678901";
$apps[$x]['menu'][2]['category'] = "internal";
$apps[$x]['menu'][2]['path'] = "/app/voice_secretary/providers.php";
$apps[$x]['menu'][2]['groups'][] = "superadmin";
$apps[$x]['menu'][2]['groups'][] = "admin";

$apps[$x]['menu'][3]['title']['en-us'] = "Documents";
$apps[$x]['menu'][3]['title']['pt-br'] = "Documentos";
$apps[$x]['menu'][3]['uuid'] = "e5f6a7b8-c9d0-1234-ef01-345678901234";
$apps[$x]['menu'][3]['parent_uuid'] = "b2c3d4e5-f6a7-8901-bcde-f12345678901";
$apps[$x]['menu'][3]['category'] = "internal";
$apps[$x]['menu'][3]['path'] = "/app/voice_secretary/documents.php";
$apps[$x]['menu'][3]['groups'][] = "superadmin";
$apps[$x]['menu'][3]['groups'][] = "admin";

$apps[$x]['menu'][4]['title']['en-us'] = "Conversations";
$apps[$x]['menu'][4]['title']['pt-br'] = "Conversas";
$apps[$x]['menu'][4]['uuid'] = "f6a7b8c9-d0e1-2345-f012-456789012345";
$apps[$x]['menu'][4]['parent_uuid'] = "b2c3d4e5-f6a7-8901-bcde-f12345678901";
$apps[$x]['menu'][4]['category'] = "internal";
$apps[$x]['menu'][4]['path'] = "/app/voice_secretary/conversations.php";
$apps[$x]['menu'][4]['groups'][] = "superadmin";
$apps[$x]['menu'][4]['groups'][] = "admin";

// Permissions
$apps[$x]['permissions'][0]['name'] = "voice_secretary_view";
$apps[$x]['permissions'][0]['groups'][] = "superadmin";
$apps[$x]['permissions'][0]['groups'][] = "admin";

$apps[$x]['permissions'][1]['name'] = "voice_secretary_add";
$apps[$x]['permissions'][1]['groups'][] = "superadmin";
$apps[$x]['permissions'][1]['groups'][] = "admin";

$apps[$x]['permissions'][2]['name'] = "voice_secretary_edit";
$apps[$x]['permissions'][2]['groups'][] = "superadmin";
$apps[$x]['permissions'][2]['groups'][] = "admin";

$apps[$x]['permissions'][3]['name'] = "voice_secretary_delete";
$apps[$x]['permissions'][3]['groups'][] = "superadmin";
$apps[$x]['permissions'][3]['groups'][] = "admin";

$apps[$x]['permissions'][4]['name'] = "voice_secretary_providers";
$apps[$x]['permissions'][4]['groups'][] = "superadmin";
$apps[$x]['permissions'][4]['groups'][] = "admin";

$apps[$x]['permissions'][5]['name'] = "voice_secretary_documents";
$apps[$x]['permissions'][5]['groups'][] = "superadmin";
$apps[$x]['permissions'][5]['groups'][] = "admin";

// Database schema
// ⚠️ MULTI-TENANT: TODAS as tabelas têm domain_uuid NOT NULL

// Providers table
$apps[$x]['db'][0]['table']['name'] = "v_voice_ai_providers";
$apps[$x]['db'][0]['table']['parent'] = "";
$y = 0;
$apps[$x]['db'][0]['fields'][$y]['name'] = "voice_ai_provider_uuid";
$apps[$x]['db'][0]['fields'][$y]['type']['pgsql'] = "uuid";
$apps[$x]['db'][0]['fields'][$y]['type']['sqlite'] = "text";
$apps[$x]['db'][0]['fields'][$y]['type']['mysql'] = "char(36)";
$apps[$x]['db'][0]['fields'][$y]['key']['type'] = "primary";
$y++;
$apps[$x]['db'][0]['fields'][$y]['name'] = "domain_uuid";
$apps[$x]['db'][0]['fields'][$y]['type']['pgsql'] = "uuid";
$apps[$x]['db'][0]['fields'][$y]['type']['sqlite'] = "text";
$apps[$x]['db'][0]['fields'][$y]['type']['mysql'] = "char(36)";
$apps[$x]['db'][0]['fields'][$y]['key']['type'] = "foreign";
$apps[$x]['db'][0]['fields'][$y]['key']['reference']['table'] = "v_domains";
$apps[$x]['db'][0]['fields'][$y]['key']['reference']['field'] = "domain_uuid";
$y++;
$apps[$x]['db'][0]['fields'][$y]['name'] = "provider_type";
$apps[$x]['db'][0]['fields'][$y]['type'] = "text";
$apps[$x]['db'][0]['fields'][$y]['description']['en-us'] = "stt, tts, llm, embeddings";
$y++;
$apps[$x]['db'][0]['fields'][$y]['name'] = "provider_name";
$apps[$x]['db'][0]['fields'][$y]['type'] = "text";
$y++;
$apps[$x]['db'][0]['fields'][$y]['name'] = "config";
$apps[$x]['db'][0]['fields'][$y]['type']['pgsql'] = "jsonb";
$apps[$x]['db'][0]['fields'][$y]['type']['sqlite'] = "text";
$apps[$x]['db'][0]['fields'][$y]['type']['mysql'] = "text";
$y++;
$apps[$x]['db'][0]['fields'][$y]['name'] = "is_default";
$apps[$x]['db'][0]['fields'][$y]['type'] = "boolean";
$apps[$x]['db'][0]['fields'][$y]['default'] = "false";
$y++;
$apps[$x]['db'][0]['fields'][$y]['name'] = "is_enabled";
$apps[$x]['db'][0]['fields'][$y]['type'] = "boolean";
$apps[$x]['db'][0]['fields'][$y]['default'] = "true";
$y++;
$apps[$x]['db'][0]['fields'][$y]['name'] = "priority";
$apps[$x]['db'][0]['fields'][$y]['type'] = "numeric";
$apps[$x]['db'][0]['fields'][$y]['default'] = "0";
$y++;
$apps[$x]['db'][0]['fields'][$y]['name'] = "insert_date";
$apps[$x]['db'][0]['fields'][$y]['type']['pgsql'] = "timestamptz";
$apps[$x]['db'][0]['fields'][$y]['type']['sqlite'] = "date";
$apps[$x]['db'][0]['fields'][$y]['type']['mysql'] = "timestamp";
$apps[$x]['db'][0]['fields'][$y]['default'] = "now()";
$y++;
$apps[$x]['db'][0]['fields'][$y]['name'] = "update_date";
$apps[$x]['db'][0]['fields'][$y]['type']['pgsql'] = "timestamptz";
$apps[$x]['db'][0]['fields'][$y]['type']['sqlite'] = "date";
$apps[$x]['db'][0]['fields'][$y]['type']['mysql'] = "timestamp";

// Secretaries table
$apps[$x]['db'][1]['table']['name'] = "v_voice_secretaries";
$apps[$x]['db'][1]['table']['parent'] = "";
$y = 0;
$apps[$x]['db'][1]['fields'][$y]['name'] = "voice_secretary_uuid";
$apps[$x]['db'][1]['fields'][$y]['type']['pgsql'] = "uuid";
$apps[$x]['db'][1]['fields'][$y]['type']['sqlite'] = "text";
$apps[$x]['db'][1]['fields'][$y]['type']['mysql'] = "char(36)";
$apps[$x]['db'][1]['fields'][$y]['key']['type'] = "primary";
$y++;
$apps[$x]['db'][1]['fields'][$y]['name'] = "domain_uuid";
$apps[$x]['db'][1]['fields'][$y]['type']['pgsql'] = "uuid";
$apps[$x]['db'][1]['fields'][$y]['type']['sqlite'] = "text";
$apps[$x]['db'][1]['fields'][$y]['type']['mysql'] = "char(36)";
$apps[$x]['db'][1]['fields'][$y]['key']['type'] = "foreign";
$apps[$x]['db'][1]['fields'][$y]['key']['reference']['table'] = "v_domains";
$apps[$x]['db'][1]['fields'][$y]['key']['reference']['field'] = "domain_uuid";
$y++;
$apps[$x]['db'][1]['fields'][$y]['name'] = "secretary_name";
$apps[$x]['db'][1]['fields'][$y]['type'] = "text";
$y++;
$apps[$x]['db'][1]['fields'][$y]['name'] = "company_name";
$apps[$x]['db'][1]['fields'][$y]['type'] = "text";
$y++;
$apps[$x]['db'][1]['fields'][$y]['name'] = "personality_prompt";
$apps[$x]['db'][1]['fields'][$y]['type'] = "text";
$y++;
$apps[$x]['db'][1]['fields'][$y]['name'] = "greeting_message";
$apps[$x]['db'][1]['fields'][$y]['type'] = "text";
$y++;
$apps[$x]['db'][1]['fields'][$y]['name'] = "farewell_message";
$apps[$x]['db'][1]['fields'][$y]['type'] = "text";
$y++;
$apps[$x]['db'][1]['fields'][$y]['name'] = "stt_provider_uuid";
$apps[$x]['db'][1]['fields'][$y]['type']['pgsql'] = "uuid";
$apps[$x]['db'][1]['fields'][$y]['type']['sqlite'] = "text";
$apps[$x]['db'][1]['fields'][$y]['type']['mysql'] = "char(36)";
$y++;
$apps[$x]['db'][1]['fields'][$y]['name'] = "tts_provider_uuid";
$apps[$x]['db'][1]['fields'][$y]['type']['pgsql'] = "uuid";
$apps[$x]['db'][1]['fields'][$y]['type']['sqlite'] = "text";
$apps[$x]['db'][1]['fields'][$y]['type']['mysql'] = "char(36)";
$y++;
$apps[$x]['db'][1]['fields'][$y]['name'] = "llm_provider_uuid";
$apps[$x]['db'][1]['fields'][$y]['type']['pgsql'] = "uuid";
$apps[$x]['db'][1]['fields'][$y]['type']['sqlite'] = "text";
$apps[$x]['db'][1]['fields'][$y]['type']['mysql'] = "char(36)";
$y++;
$apps[$x]['db'][1]['fields'][$y]['name'] = "max_turns";
$apps[$x]['db'][1]['fields'][$y]['type'] = "numeric";
$apps[$x]['db'][1]['fields'][$y]['default'] = "20";
$y++;
$apps[$x]['db'][1]['fields'][$y]['name'] = "transfer_extension";
$apps[$x]['db'][1]['fields'][$y]['type'] = "text";
$y++;
$apps[$x]['db'][1]['fields'][$y]['name'] = "enabled";
$apps[$x]['db'][1]['fields'][$y]['type'] = "boolean";
$apps[$x]['db'][1]['fields'][$y]['default'] = "true";
$y++;
$apps[$x]['db'][1]['fields'][$y]['name'] = "insert_date";
$apps[$x]['db'][1]['fields'][$y]['type']['pgsql'] = "timestamptz";
$apps[$x]['db'][1]['fields'][$y]['type']['sqlite'] = "date";
$apps[$x]['db'][1]['fields'][$y]['type']['mysql'] = "timestamp";
$apps[$x]['db'][1]['fields'][$y]['default'] = "now()";

// Documents table
$apps[$x]['db'][2]['table']['name'] = "v_voice_documents";
$apps[$x]['db'][2]['table']['parent'] = "";
$y = 0;
$apps[$x]['db'][2]['fields'][$y]['name'] = "voice_document_uuid";
$apps[$x]['db'][2]['fields'][$y]['type']['pgsql'] = "uuid";
$apps[$x]['db'][2]['fields'][$y]['type']['sqlite'] = "text";
$apps[$x]['db'][2]['fields'][$y]['type']['mysql'] = "char(36)";
$apps[$x]['db'][2]['fields'][$y]['key']['type'] = "primary";
$y++;
$apps[$x]['db'][2]['fields'][$y]['name'] = "domain_uuid";
$apps[$x]['db'][2]['fields'][$y]['type']['pgsql'] = "uuid";
$apps[$x]['db'][2]['fields'][$y]['type']['sqlite'] = "text";
$apps[$x]['db'][2]['fields'][$y]['type']['mysql'] = "char(36)";
$apps[$x]['db'][2]['fields'][$y]['key']['type'] = "foreign";
$apps[$x]['db'][2]['fields'][$y]['key']['reference']['table'] = "v_domains";
$apps[$x]['db'][2]['fields'][$y]['key']['reference']['field'] = "domain_uuid";
$y++;
$apps[$x]['db'][2]['fields'][$y]['name'] = "voice_secretary_uuid";
$apps[$x]['db'][2]['fields'][$y]['type']['pgsql'] = "uuid";
$apps[$x]['db'][2]['fields'][$y]['type']['sqlite'] = "text";
$apps[$x]['db'][2]['fields'][$y]['type']['mysql'] = "char(36)";
$y++;
$apps[$x]['db'][2]['fields'][$y]['name'] = "document_name";
$apps[$x]['db'][2]['fields'][$y]['type'] = "text";
$y++;
$apps[$x]['db'][2]['fields'][$y]['name'] = "document_type";
$apps[$x]['db'][2]['fields'][$y]['type'] = "text";
$y++;
$apps[$x]['db'][2]['fields'][$y]['name'] = "content";
$apps[$x]['db'][2]['fields'][$y]['type'] = "text";
$y++;
$apps[$x]['db'][2]['fields'][$y]['name'] = "file_path";
$apps[$x]['db'][2]['fields'][$y]['type'] = "text";
$y++;
$apps[$x]['db'][2]['fields'][$y]['name'] = "chunk_count";
$apps[$x]['db'][2]['fields'][$y]['type'] = "numeric";
$y++;
$apps[$x]['db'][2]['fields'][$y]['name'] = "enabled";
$apps[$x]['db'][2]['fields'][$y]['type'] = "boolean";
$apps[$x]['db'][2]['fields'][$y]['default'] = "true";
$y++;
$apps[$x]['db'][2]['fields'][$y]['name'] = "insert_date";
$apps[$x]['db'][2]['fields'][$y]['type']['pgsql'] = "timestamptz";
$apps[$x]['db'][2]['fields'][$y]['type']['sqlite'] = "date";
$apps[$x]['db'][2]['fields'][$y]['type']['mysql'] = "timestamp";
$apps[$x]['db'][2]['fields'][$y]['default'] = "now()";

// Conversations table
$apps[$x]['db'][3]['table']['name'] = "v_voice_conversations";
$apps[$x]['db'][3]['table']['parent'] = "";
$y = 0;
$apps[$x]['db'][3]['fields'][$y]['name'] = "voice_conversation_uuid";
$apps[$x]['db'][3]['fields'][$y]['type']['pgsql'] = "uuid";
$apps[$x]['db'][3]['fields'][$y]['type']['sqlite'] = "text";
$apps[$x]['db'][3]['fields'][$y]['type']['mysql'] = "char(36)";
$apps[$x]['db'][3]['fields'][$y]['key']['type'] = "primary";
$y++;
$apps[$x]['db'][3]['fields'][$y]['name'] = "domain_uuid";
$apps[$x]['db'][3]['fields'][$y]['type']['pgsql'] = "uuid";
$apps[$x]['db'][3]['fields'][$y]['type']['sqlite'] = "text";
$apps[$x]['db'][3]['fields'][$y]['type']['mysql'] = "char(36)";
$apps[$x]['db'][3]['fields'][$y]['key']['type'] = "foreign";
$apps[$x]['db'][3]['fields'][$y]['key']['reference']['table'] = "v_domains";
$apps[$x]['db'][3]['fields'][$y]['key']['reference']['field'] = "domain_uuid";
$y++;
$apps[$x]['db'][3]['fields'][$y]['name'] = "voice_secretary_uuid";
$apps[$x]['db'][3]['fields'][$y]['type']['pgsql'] = "uuid";
$apps[$x]['db'][3]['fields'][$y]['type']['sqlite'] = "text";
$apps[$x]['db'][3]['fields'][$y]['type']['mysql'] = "char(36)";
$y++;
$apps[$x]['db'][3]['fields'][$y]['name'] = "call_uuid";
$apps[$x]['db'][3]['fields'][$y]['type']['pgsql'] = "uuid";
$apps[$x]['db'][3]['fields'][$y]['type']['sqlite'] = "text";
$apps[$x]['db'][3]['fields'][$y]['type']['mysql'] = "char(36)";
$y++;
$apps[$x]['db'][3]['fields'][$y]['name'] = "caller_id_number";
$apps[$x]['db'][3]['fields'][$y]['type'] = "text";
$y++;
$apps[$x]['db'][3]['fields'][$y]['name'] = "caller_id_name";
$apps[$x]['db'][3]['fields'][$y]['type'] = "text";
$y++;
$apps[$x]['db'][3]['fields'][$y]['name'] = "start_time";
$apps[$x]['db'][3]['fields'][$y]['type']['pgsql'] = "timestamptz";
$apps[$x]['db'][3]['fields'][$y]['type']['sqlite'] = "date";
$apps[$x]['db'][3]['fields'][$y]['type']['mysql'] = "timestamp";
$y++;
$apps[$x]['db'][3]['fields'][$y]['name'] = "end_time";
$apps[$x]['db'][3]['fields'][$y]['type']['pgsql'] = "timestamptz";
$apps[$x]['db'][3]['fields'][$y]['type']['sqlite'] = "date";
$apps[$x]['db'][3]['fields'][$y]['type']['mysql'] = "timestamp";
$y++;
$apps[$x]['db'][3]['fields'][$y]['name'] = "total_turns";
$apps[$x]['db'][3]['fields'][$y]['type'] = "numeric";
$apps[$x]['db'][3]['fields'][$y]['default'] = "0";
$y++;
$apps[$x]['db'][3]['fields'][$y]['name'] = "final_action";
$apps[$x]['db'][3]['fields'][$y]['type'] = "text";
$y++;
$apps[$x]['db'][3]['fields'][$y]['name'] = "transfer_extension";
$apps[$x]['db'][3]['fields'][$y]['type'] = "text";
$y++;
$apps[$x]['db'][3]['fields'][$y]['name'] = "ticket_created";
$apps[$x]['db'][3]['fields'][$y]['type'] = "boolean";
$apps[$x]['db'][3]['fields'][$y]['default'] = "false";
$y++;
$apps[$x]['db'][3]['fields'][$y]['name'] = "ticket_id";
$apps[$x]['db'][3]['fields'][$y]['type'] = "text";
$y++;
$apps[$x]['db'][3]['fields'][$y]['name'] = "insert_date";
$apps[$x]['db'][3]['fields'][$y]['type']['pgsql'] = "timestamptz";
$apps[$x]['db'][3]['fields'][$y]['type']['sqlite'] = "date";
$apps[$x]['db'][3]['fields'][$y]['type']['mysql'] = "timestamp";
$apps[$x]['db'][3]['fields'][$y]['default'] = "now()";

?>
