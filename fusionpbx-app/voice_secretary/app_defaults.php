<?php
/**
 * Voice Secretary App Defaults
 * Default values for new installations
 */

// Check if domain_uuid is set (MULTI-TENANT requirement)
if (!isset($_SESSION["domain_uuid"])) {
    return;
}

$domain_uuid = $_SESSION["domain_uuid"];

// Create default STT provider (Whisper Local)
$sql = "SELECT COUNT(*) as count FROM v_voice_ai_providers 
        WHERE domain_uuid = :domain_uuid AND provider_type = 'stt' AND is_default = true";
$parameters['domain_uuid'] = $domain_uuid;
$result = $database->select($sql, $parameters, 'row');

if ($result['count'] == 0) {
    // Insert default Whisper Local provider
    $provider_uuid = uuid();
    // FusionPBX padrão: nome lógico do array (voice_ai_providers) -> tabela v_voice_ai_providers
    $array['voice_ai_providers'][0]['voice_ai_provider_uuid'] = $provider_uuid;
    $array['voice_ai_providers'][0]['domain_uuid'] = $domain_uuid;
    $array['voice_ai_providers'][0]['provider_type'] = 'stt';
    $array['voice_ai_providers'][0]['provider_name'] = 'whisper_local';
    $array['voice_ai_providers'][0]['config'] = json_encode(['model' => 'base', 'device' => 'cpu']);
    $array['voice_ai_providers'][0]['is_default'] = true;
    $array['voice_ai_providers'][0]['is_enabled'] = true;
    $array['voice_ai_providers'][0]['priority'] = 0;
    
    $database->app_name = 'voice_secretary';
    $database->app_uuid = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';
    $database->save($array);
    unset($array);
}

// Create default TTS provider (Piper Local)
$sql = "SELECT COUNT(*) as count FROM v_voice_ai_providers 
        WHERE domain_uuid = :domain_uuid AND provider_type = 'tts' AND is_default = true";
$result = $database->select($sql, $parameters, 'row');

if ($result['count'] == 0) {
    $provider_uuid = uuid();
    $array['voice_ai_providers'][0]['voice_ai_provider_uuid'] = $provider_uuid;
    $array['voice_ai_providers'][0]['domain_uuid'] = $domain_uuid;
    $array['voice_ai_providers'][0]['provider_type'] = 'tts';
    $array['voice_ai_providers'][0]['provider_name'] = 'piper_local';
    $array['voice_ai_providers'][0]['config'] = json_encode(['model' => 'pt_BR-faber-medium']);
    $array['voice_ai_providers'][0]['is_default'] = true;
    $array['voice_ai_providers'][0]['is_enabled'] = true;
    $array['voice_ai_providers'][0]['priority'] = 0;
    
    $database->app_name = 'voice_secretary';
    $database->app_uuid = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';
    $database->save($array);
    unset($array);
}

?>
