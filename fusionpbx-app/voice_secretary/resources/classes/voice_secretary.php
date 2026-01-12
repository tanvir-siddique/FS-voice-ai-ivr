<?php
/**
 * Voice Secretary Class
 * 
 * CRUD operations for voice AI secretaries.
 * ⚠️ MULTI-TENANT: ALL operations MUST use domain_uuid from session.
 *
 * @package voice_secretary
 */

require_once "domain_validator.php";

class voice_secretary {
    
    private $database;
    private $domain_uuid;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->database = database::new();
        $this->domain_uuid = domain_validator::require_domain_uuid();
    }
    
    /**
     * List all secretaries for current domain
     */
    public function list($order_by = 'secretary_name', $order = 'asc') {
        $sql = "SELECT * FROM v_voice_secretaries 
                WHERE domain_uuid = :domain_uuid 
                ORDER BY {$order_by} {$order}";
        
        $parameters = [];
        domain_validator::add_to_parameters($parameters);
        
        return $this->database->select($sql, $parameters);
    }
    
    /**
     * Get single secretary by UUID
     */
    public function get($secretary_uuid) {
        $sql = "SELECT * FROM v_voice_secretaries 
                WHERE voice_secretary_uuid = :secretary_uuid 
                AND domain_uuid = :domain_uuid";
        
        $parameters = [
            'secretary_uuid' => $secretary_uuid
        ];
        domain_validator::add_to_parameters($parameters);
        
        $rows = $this->database->select($sql, $parameters);
        return isset($rows[0]) ? $rows[0] : null;
    }
    
    /**
     * Create new secretary
     */
    public function create($data) {
        $secretary_uuid = uuid();
        
        $sql = "INSERT INTO v_voice_secretaries (
            voice_secretary_uuid,
            domain_uuid,
            secretary_name,
            company_name,
            system_prompt,
            greeting_message,
            farewell_message,
            stt_provider_uuid,
            tts_provider_uuid,
            llm_provider_uuid,
            embeddings_provider_uuid,
            tts_voice,
            language,
            max_turns,
            transfer_extension,
            is_active,
            webhook_url,
            created_at
        ) VALUES (
            :secretary_uuid,
            :domain_uuid,
            :secretary_name,
            :company_name,
            :system_prompt,
            :greeting_message,
            :farewell_message,
            :stt_provider_uuid,
            :tts_provider_uuid,
            :llm_provider_uuid,
            :embeddings_provider_uuid,
            :tts_voice,
            :language,
            :max_turns,
            :transfer_extension,
            :is_active,
            :webhook_url,
            NOW()
        )";
        
        $parameters = [
            'secretary_uuid' => $secretary_uuid,
            'secretary_name' => $data['secretary_name'],
            'company_name' => $data['company_name'] ?? null,
            'system_prompt' => $data['system_prompt'] ?? null,
            'greeting_message' => $data['greeting_message'] ?? 'Olá! Como posso ajudar?',
            'farewell_message' => $data['farewell_message'] ?? 'Foi um prazer ajudar! Até logo!',
            'stt_provider_uuid' => $data['stt_provider_uuid'] ?? null,
            'tts_provider_uuid' => $data['tts_provider_uuid'] ?? null,
            'llm_provider_uuid' => $data['llm_provider_uuid'] ?? null,
            'embeddings_provider_uuid' => $data['embeddings_provider_uuid'] ?? null,
            'tts_voice' => $data['tts_voice'] ?? null,
            'language' => $data['language'] ?? 'pt-BR',
            'max_turns' => $data['max_turns'] ?? 20,
            'transfer_extension' => $data['transfer_extension'] ?? '200',
            'is_active' => $data['is_active'] ?? true,
            'webhook_url' => $data['webhook_url'] ?? null,
        ];
        domain_validator::add_to_parameters($parameters);
        
        $this->database->execute($sql, $parameters);
        
        return $secretary_uuid;
    }
    
    /**
     * Update existing secretary
     */
    public function update($secretary_uuid, $data) {
        // Build SET clause dynamically
        $allowed_fields = [
            'secretary_name', 'company_name', 'system_prompt',
            'greeting_message', 'farewell_message',
            'stt_provider_uuid', 'tts_provider_uuid', 'llm_provider_uuid',
            'embeddings_provider_uuid', 'tts_voice', 'language',
            'max_turns', 'transfer_extension', 'is_active', 'webhook_url'
        ];
        
        $set_parts = [];
        $parameters = [
            'secretary_uuid' => $secretary_uuid
        ];
        domain_validator::add_to_parameters($parameters);
        
        foreach ($allowed_fields as $field) {
            if (array_key_exists($field, $data)) {
                $set_parts[] = "{$field} = :{$field}";
                $parameters[$field] = $data[$field];
            }
        }
        
        if (empty($set_parts)) {
            return false;
        }
        
        $set_parts[] = "updated_at = NOW()";
        
        $sql = "UPDATE v_voice_secretaries 
                SET " . implode(', ', $set_parts) . "
                WHERE voice_secretary_uuid = :secretary_uuid 
                AND domain_uuid = :domain_uuid";
        
        $this->database->execute($sql, $parameters);
        
        return true;
    }
    
    /**
     * Delete secretary
     */
    public function delete($secretary_uuid) {
        $sql = "DELETE FROM v_voice_secretaries 
                WHERE voice_secretary_uuid = :secretary_uuid 
                AND domain_uuid = :domain_uuid";
        
        $parameters = [
            'secretary_uuid' => $secretary_uuid
        ];
        domain_validator::add_to_parameters($parameters);
        
        $this->database->execute($sql, $parameters);
        
        return true;
    }
    
    /**
     * Get providers for dropdown (by type)
     */
    public function get_providers($type) {
        $sql = "SELECT provider_uuid, provider_name 
                FROM v_voice_ai_providers 
                WHERE domain_uuid = :domain_uuid 
                AND provider_type = :type 
                AND is_active = true 
                ORDER BY priority ASC, provider_name ASC";
        
        $parameters = [
            'type' => $type
        ];
        domain_validator::add_to_parameters($parameters);
        
        return $this->database->select($sql, $parameters);
    }
    
    /**
     * Test TTS voice
     */
    public function test_voice($text, $voice_id = null, $provider_uuid = null) {
        $service_url = $_ENV['VOICE_AI_SERVICE_URL'] ?? 'http://127.0.0.1:8089/api/v1';
        
        $payload = json_encode([
            'domain_uuid' => $this->domain_uuid,
            'text' => $text,
            'voice_id' => $voice_id,
        ]);
        
        $ch = curl_init($service_url . '/synthesize');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code == 200) {
            $data = json_decode($response, true);
            return $data['audio_file'] ?? null;
        }
        
        return null;
    }
}
?>
