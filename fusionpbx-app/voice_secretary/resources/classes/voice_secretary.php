<?php
/**
 * Voice Secretary Class
 * 
 * CRUD operations for voice AI secretaries.
 * ⚠️ MULTI-TENANT: ALL operations MUST use domain_uuid parameter.
 *
 * @package voice_secretary
 */

class voice_secretary {
    
    private $database;
    
    /**
     * Constructor
     */
    public function __construct() {
        // Use FusionPBX database class
        $this->database = new database;
    }
    
    /**
     * Get list of secretaries for a domain
     */
    public function get_list($domain_uuid, $order_by = 'secretary_name', $order = 'asc') {
        // Sanitize order parameters
        $allowed_columns = ['secretary_name', 'company_name', 'extension', 'insert_date'];
        $allowed_order = ['asc', 'desc'];
        
        if (!in_array($order_by, $allowed_columns)) {
            $order_by = 'secretary_name';
        }
        if (!in_array(strtolower($order), $allowed_order)) {
            $order = 'asc';
        }
        
        $sql = "SELECT * FROM v_voice_secretaries 
                WHERE domain_uuid = :domain_uuid 
                ORDER BY {$order_by} {$order}";
        
        $parameters['domain_uuid'] = $domain_uuid;
        
        $this->database->sql = $sql;
        $this->database->parameters = $parameters;
        return $this->database->execute('all', PDO::FETCH_ASSOC);
    }
    
    /**
     * Get single secretary by UUID
     */
    public function get($secretary_uuid, $domain_uuid) {
        $sql = "SELECT * FROM v_voice_secretaries 
                WHERE voice_secretary_uuid = :secretary_uuid 
                AND domain_uuid = :domain_uuid";
        
        $parameters['secretary_uuid'] = $secretary_uuid;
        $parameters['domain_uuid'] = $domain_uuid;
        
        $this->database->sql = $sql;
        $this->database->parameters = $parameters;
        $result = $this->database->execute('all', PDO::FETCH_ASSOC);
        
        return isset($result[0]) ? $result[0] : null;
    }
    
    /**
     * Create new secretary
     */
    public function create($data, $domain_uuid) {
        $secretary_uuid = uuid();
        
        $sql = "INSERT INTO v_voice_secretaries (
            voice_secretary_uuid,
            domain_uuid,
            secretary_name,
            company_name,
            extension,
            processing_mode,
            personality_prompt,
            greeting_message,
            farewell_message,
            stt_provider_uuid,
            tts_provider_uuid,
            llm_provider_uuid,
            embeddings_provider_uuid,
            realtime_provider_uuid,
            tts_voice_id,
            language,
            max_turns,
            transfer_extension,
            is_enabled,
            omniplay_webhook_url,
            insert_date
        ) VALUES (
            :secretary_uuid,
            :domain_uuid,
            :secretary_name,
            :company_name,
            :extension,
            :processing_mode,
            :personality_prompt,
            :greeting_message,
            :farewell_message,
            :stt_provider_uuid,
            :tts_provider_uuid,
            :llm_provider_uuid,
            :embeddings_provider_uuid,
            :realtime_provider_uuid,
            :tts_voice_id,
            :language,
            :max_turns,
            :transfer_extension,
            :is_enabled,
            :omniplay_webhook_url,
            NOW()
        )";
        
        $parameters['secretary_uuid'] = $secretary_uuid;
        $parameters['domain_uuid'] = $domain_uuid;
        $parameters['secretary_name'] = $data['secretary_name'];
        $parameters['company_name'] = $data['company_name'] ?? null;
        $parameters['extension'] = $data['extension'] ?? null;
        $parameters['processing_mode'] = $data['processing_mode'] ?? 'turn_based';
        $parameters['personality_prompt'] = $data['system_prompt'] ?? null;
        $parameters['greeting_message'] = $data['greeting_message'] ?? 'Olá! Como posso ajudar?';
        $parameters['farewell_message'] = $data['farewell_message'] ?? 'Foi um prazer ajudar! Até logo!';
        $parameters['stt_provider_uuid'] = !empty($data['stt_provider_uuid']) ? $data['stt_provider_uuid'] : null;
        $parameters['tts_provider_uuid'] = !empty($data['tts_provider_uuid']) ? $data['tts_provider_uuid'] : null;
        $parameters['llm_provider_uuid'] = !empty($data['llm_provider_uuid']) ? $data['llm_provider_uuid'] : null;
        $parameters['embeddings_provider_uuid'] = !empty($data['embeddings_provider_uuid']) ? $data['embeddings_provider_uuid'] : null;
        $parameters['realtime_provider_uuid'] = !empty($data['realtime_provider_uuid']) ? $data['realtime_provider_uuid'] : null;
        $parameters['tts_voice_id'] = $data['tts_voice'] ?? null;
        $parameters['language'] = $data['language'] ?? 'pt-BR';
        $parameters['max_turns'] = $data['max_turns'] ?? 20;
        $parameters['transfer_extension'] = $data['transfer_extension'] ?? '200';
        $parameters['is_enabled'] = $data['is_active'] ?? true;
        $parameters['omniplay_webhook_url'] = $data['webhook_url'] ?? null;
        
        $this->database->sql = $sql;
        $this->database->parameters = $parameters;
        $this->database->execute();
        
        return $secretary_uuid;
    }
    
    /**
     * Update existing secretary
     */
    public function update($secretary_uuid, $data, $domain_uuid) {
        $sql = "UPDATE v_voice_secretaries SET
            secretary_name = :secretary_name,
            company_name = :company_name,
            extension = :extension,
            processing_mode = :processing_mode,
            personality_prompt = :personality_prompt,
            greeting_message = :greeting_message,
            farewell_message = :farewell_message,
            stt_provider_uuid = :stt_provider_uuid,
            tts_provider_uuid = :tts_provider_uuid,
            llm_provider_uuid = :llm_provider_uuid,
            embeddings_provider_uuid = :embeddings_provider_uuid,
            realtime_provider_uuid = :realtime_provider_uuid,
            tts_voice_id = :tts_voice_id,
            language = :language,
            max_turns = :max_turns,
            transfer_extension = :transfer_extension,
            is_enabled = :is_enabled,
            omniplay_webhook_url = :omniplay_webhook_url,
            update_date = NOW()
            WHERE voice_secretary_uuid = :secretary_uuid 
            AND domain_uuid = :domain_uuid";
        
        $parameters['secretary_uuid'] = $secretary_uuid;
        $parameters['domain_uuid'] = $domain_uuid;
        $parameters['secretary_name'] = $data['secretary_name'];
        $parameters['company_name'] = $data['company_name'] ?? null;
        $parameters['extension'] = $data['extension'] ?? null;
        $parameters['processing_mode'] = $data['processing_mode'] ?? 'turn_based';
        $parameters['personality_prompt'] = $data['system_prompt'] ?? null;
        $parameters['greeting_message'] = $data['greeting_message'] ?? null;
        $parameters['farewell_message'] = $data['farewell_message'] ?? null;
        $parameters['stt_provider_uuid'] = !empty($data['stt_provider_uuid']) ? $data['stt_provider_uuid'] : null;
        $parameters['tts_provider_uuid'] = !empty($data['tts_provider_uuid']) ? $data['tts_provider_uuid'] : null;
        $parameters['llm_provider_uuid'] = !empty($data['llm_provider_uuid']) ? $data['llm_provider_uuid'] : null;
        $parameters['embeddings_provider_uuid'] = !empty($data['embeddings_provider_uuid']) ? $data['embeddings_provider_uuid'] : null;
        $parameters['realtime_provider_uuid'] = !empty($data['realtime_provider_uuid']) ? $data['realtime_provider_uuid'] : null;
        $parameters['tts_voice_id'] = $data['tts_voice'] ?? null;
        $parameters['language'] = $data['language'] ?? 'pt-BR';
        $parameters['max_turns'] = $data['max_turns'] ?? 20;
        $parameters['transfer_extension'] = $data['transfer_extension'] ?? '200';
        $parameters['is_enabled'] = $data['is_active'] ?? true;
        $parameters['omniplay_webhook_url'] = $data['webhook_url'] ?? null;
        
        $this->database->sql = $sql;
        $this->database->parameters = $parameters;
        $this->database->execute();
        
        return true;
    }
    
    /**
     * Delete secretary
     */
    public function delete($secretary_uuid, $domain_uuid) {
        $sql = "DELETE FROM v_voice_secretaries 
                WHERE voice_secretary_uuid = :secretary_uuid 
                AND domain_uuid = :domain_uuid";
        
        $parameters['secretary_uuid'] = $secretary_uuid;
        $parameters['domain_uuid'] = $domain_uuid;
        
        $this->database->sql = $sql;
        $this->database->parameters = $parameters;
        $this->database->execute();
        
        return true;
    }
    
    /**
     * Get providers for dropdown (by type)
     */
    public function get_providers($type, $domain_uuid) {
        $sql = "SELECT voice_ai_provider_uuid, provider_name
                FROM v_voice_ai_providers 
                WHERE domain_uuid = :domain_uuid 
                AND provider_type = :type 
                AND is_enabled = true 
                ORDER BY priority ASC, provider_name ASC";
        
        $parameters['domain_uuid'] = $domain_uuid;
        $parameters['type'] = $type;
        
        $this->database->sql = $sql;
        $this->database->parameters = $parameters;
        return $this->database->execute('all', PDO::FETCH_ASSOC);
    }
}
?>
