<?php
/**
 * Voice Secretary App Defaults
 * Default values for new installations
 * 
 * IMPORTANT: These defaults are synchronized with:
 * - voice-ai-service/config/settings.py
 * - fusionpbx-app/voice_secretary/settings.php
 */

// Check if domain_uuid is set (MULTI-TENANT requirement)
if (!isset($_SESSION["domain_uuid"])) {
    return;
}

$domain_uuid = $_SESSION["domain_uuid"];

//====================================================================
// DEFAULT SETTINGS - Create initial configuration for new domains
//====================================================================
$default_settings = [
    // Service Configuration
    'service_url' => 'http://127.0.0.1:8100/api/v1',
    'max_concurrent_calls' => '10',
    'default_max_turns' => '20',
    'rate_limit_rpm' => '60',
    
    // ESL Configuration
    'esl_host' => '127.0.0.1',
    'esl_port' => '8021',
    'esl_password' => 'ClueCon',
    'esl_connect_timeout' => '5.0',
    'esl_read_timeout' => '30.0',
    
    // Transfer Settings
    'transfer_default_timeout' => '30',
    'transfer_announce_enabled' => 'true',
    'transfer_music_on_hold' => 'local_stream://moh',
    'transfer_cache_ttl_seconds' => '300',
    
    // Callback Settings
    'callback_enabled' => 'true',
    'callback_expiration_hours' => '24',
    'callback_max_notifications' => '5',
    'callback_min_interval_minutes' => '10',
    
    // OmniPlay Integration
    'omniplay_api_url' => 'http://127.0.0.1:8080',
    'omniplay_api_timeout_ms' => '10000',
    
    // Data Management
    'data_retention_days' => '90',
    'recording_enabled' => 'true',
    
    // Audio Settings
    'audio_sample_rate' => '16000',
    'silence_threshold_ms' => '3000',
    'max_recording_seconds' => '30',
];

// Check if settings table exists and create default settings
$sql = "SELECT COUNT(*) as count FROM v_voice_secretary_settings WHERE domain_uuid = :domain_uuid";
$parameters['domain_uuid'] = $domain_uuid;
$result = $database->select($sql, $parameters, 'row');

if ($result['count'] == 0) {
    // Insert default settings for this domain
    foreach ($default_settings as $name => $value) {
        $sql_insert = "INSERT INTO v_voice_secretary_settings (domain_uuid, setting_name, setting_value) 
                       VALUES (:domain_uuid, :name, :value) 
                       ON CONFLICT (domain_uuid, setting_name) DO NOTHING";
        $database->execute($sql_insert, [
            'domain_uuid' => $domain_uuid,
            'name' => $name,
            'value' => $value
        ]);
    }
}
unset($parameters);

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
