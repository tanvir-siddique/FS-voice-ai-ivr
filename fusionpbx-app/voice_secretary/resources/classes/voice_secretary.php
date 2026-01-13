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
        
        $database = new database;
        return $database->select($sql, $parameters, 'all');
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
        
        $database = new database;
        return $database->select($sql, $parameters, 'row');
    }
    
    /**
     * Create new secretary
     */
    public function create($data, $domain_uuid) {
        $secretary_uuid = uuid();
        
        // FusionPBX padrão: use o nome lógico do array (voice_secretaries) para salvar em v_voice_secretaries
        $array['voice_secretaries'][0]['voice_secretary_uuid'] = $secretary_uuid;
        $array['voice_secretaries'][0]['domain_uuid'] = $domain_uuid;
        $array['voice_secretaries'][0]['secretary_name'] = $data['secretary_name'];
        $array['voice_secretaries'][0]['company_name'] = $data['company_name'] ?? null;
        $array['voice_secretaries'][0]['extension'] = $data['extension'] ?? null;
        $array['voice_secretaries'][0]['processing_mode'] = $data['processing_mode'] ?? 'turn_based';
        $array['voice_secretaries'][0]['personality_prompt'] = $data['system_prompt'] ?? null;
        $array['voice_secretaries'][0]['greeting_message'] = $data['greeting_message'] ?? 'Olá! Como posso ajudar?';
        $array['voice_secretaries'][0]['farewell_message'] = $data['farewell_message'] ?? 'Foi um prazer ajudar! Até logo!';
        $array['voice_secretaries'][0]['stt_provider_uuid'] = !empty($data['stt_provider_uuid']) ? $data['stt_provider_uuid'] : null;
        $array['voice_secretaries'][0]['tts_provider_uuid'] = !empty($data['tts_provider_uuid']) ? $data['tts_provider_uuid'] : null;
        $array['voice_secretaries'][0]['llm_provider_uuid'] = !empty($data['llm_provider_uuid']) ? $data['llm_provider_uuid'] : null;
        $array['voice_secretaries'][0]['embeddings_provider_uuid'] = !empty($data['embeddings_provider_uuid']) ? $data['embeddings_provider_uuid'] : null;
        $array['voice_secretaries'][0]['realtime_provider_uuid'] = !empty($data['realtime_provider_uuid']) ? $data['realtime_provider_uuid'] : null;
        $array['voice_secretaries'][0]['tts_voice_id'] = $data['tts_voice'] ?? null;
        $array['voice_secretaries'][0]['language'] = $data['language'] ?? 'pt-BR';
        $array['voice_secretaries'][0]['max_turns'] = $data['max_turns'] ?? 20;
        $array['voice_secretaries'][0]['transfer_extension'] = $data['transfer_extension'] ?? '200';
        $array['voice_secretaries'][0]['omniplay_webhook_url'] = $data['webhook_url'] ?? null;
        $array['voice_secretaries'][0]['enabled'] = $data['is_active'] ?? true;
        
        $database = new database;
        $database->app_name = 'voice_secretary';
        $database->app_uuid = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';
        $database->save($array);
        
        return $secretary_uuid;
    }
    
    /**
     * Update existing secretary
     */
    public function update($secretary_uuid, $data, $domain_uuid) {
        $array['voice_secretaries'][0]['voice_secretary_uuid'] = $secretary_uuid;
        $array['voice_secretaries'][0]['domain_uuid'] = $domain_uuid;
        $array['voice_secretaries'][0]['secretary_name'] = $data['secretary_name'];
        $array['voice_secretaries'][0]['company_name'] = $data['company_name'] ?? null;
        $array['voice_secretaries'][0]['extension'] = $data['extension'] ?? null;
        $array['voice_secretaries'][0]['processing_mode'] = $data['processing_mode'] ?? 'turn_based';
        $array['voice_secretaries'][0]['personality_prompt'] = $data['system_prompt'] ?? null;
        $array['voice_secretaries'][0]['greeting_message'] = $data['greeting_message'] ?? null;
        $array['voice_secretaries'][0]['farewell_message'] = $data['farewell_message'] ?? null;
        $array['voice_secretaries'][0]['stt_provider_uuid'] = !empty($data['stt_provider_uuid']) ? $data['stt_provider_uuid'] : null;
        $array['voice_secretaries'][0]['tts_provider_uuid'] = !empty($data['tts_provider_uuid']) ? $data['tts_provider_uuid'] : null;
        $array['voice_secretaries'][0]['llm_provider_uuid'] = !empty($data['llm_provider_uuid']) ? $data['llm_provider_uuid'] : null;
        $array['voice_secretaries'][0]['embeddings_provider_uuid'] = !empty($data['embeddings_provider_uuid']) ? $data['embeddings_provider_uuid'] : null;
        $array['voice_secretaries'][0]['realtime_provider_uuid'] = !empty($data['realtime_provider_uuid']) ? $data['realtime_provider_uuid'] : null;
        $array['voice_secretaries'][0]['tts_voice_id'] = $data['tts_voice'] ?? null;
        $array['voice_secretaries'][0]['language'] = $data['language'] ?? 'pt-BR';
        $array['voice_secretaries'][0]['max_turns'] = $data['max_turns'] ?? 20;
        $array['voice_secretaries'][0]['transfer_extension'] = $data['transfer_extension'] ?? '200';
        $array['voice_secretaries'][0]['omniplay_webhook_url'] = $data['webhook_url'] ?? null;
        $array['voice_secretaries'][0]['enabled'] = $data['is_active'] ?? true;
        
        $database = new database;
        $database->app_name = 'voice_secretary';
        $database->app_uuid = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';
        $database->save($array);
        
        return true;
    }
    
    /**
     * Delete secretary(ies)
     * @param mixed $uuids Single UUID string or array of UUIDs
     * @param string $domain_uuid Domain UUID for security
     */
    public function delete($records, $domain_uuid) {
        if (!is_array($records)) {
            $records = [$records];
        }
        
        $database = new database;
        
        foreach ($records as $record) {
            //extract UUID from record (can be array with 'uuid' key or direct UUID string)
            $secretary_uuid = is_array($record) ? ($record['uuid'] ?? null) : $record;
            
            if (is_uuid($secretary_uuid)) {
                $sql = "DELETE FROM v_voice_secretaries 
                        WHERE voice_secretary_uuid = :secretary_uuid 
                        AND domain_uuid = :domain_uuid";
                
                $parameters = [];
                $parameters['secretary_uuid'] = $secretary_uuid;
                $parameters['domain_uuid'] = $domain_uuid;
                
                $database->execute($sql, $parameters);
            }
        }
        
        return true;
    }
    
    /**
     * Toggle enabled status for secretary(ies)
     * @param mixed $uuids Single UUID string or array of UUIDs
     * @param string $domain_uuid Domain UUID for security
     */
    public function toggle($records, $domain_uuid) {
        if (!is_array($records)) {
            $records = [$records];
        }
        
        $database = new database;
        
        foreach ($records as $record) {
            //extract UUID from record (can be array with 'uuid' key or direct UUID string)
            $secretary_uuid = is_array($record) ? ($record['uuid'] ?? null) : $record;
            
            if (is_uuid($secretary_uuid)) {
                //get current status
                $sql = "SELECT enabled FROM v_voice_secretaries 
                        WHERE voice_secretary_uuid = :secretary_uuid 
                        AND domain_uuid = :domain_uuid";
                
                $parameters = [];
                $parameters['secretary_uuid'] = $secretary_uuid;
                $parameters['domain_uuid'] = $domain_uuid;
                
                $row = $database->select($sql, $parameters, 'row');
                
                if ($row) {
                    //toggle status
                    $new_status = ($row['enabled'] == 'true' || $row['enabled'] === true) ? 'false' : 'true';
                    
                    $sql_update = "UPDATE v_voice_secretaries 
                                   SET enabled = :enabled 
                                   WHERE voice_secretary_uuid = :secretary_uuid 
                                   AND domain_uuid = :domain_uuid";
                    
                    $parameters_update = [];
                    $parameters_update['enabled'] = $new_status;
                    $parameters_update['secretary_uuid'] = $secretary_uuid;
                    $parameters_update['domain_uuid'] = $domain_uuid;
                    
                    $database->execute($sql_update, $parameters_update);
                }
            }
        }
        
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
        
        $database = new database;
        return $database->select($sql, $parameters, 'all');
    }
}
?>
