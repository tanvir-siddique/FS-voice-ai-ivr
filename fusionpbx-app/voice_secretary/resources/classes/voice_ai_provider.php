<?php
/**
 * Voice AI Provider Class
 * 
 * CRUD operations for AI providers (STT, TTS, LLM, Embeddings).
 * ⚠️ MULTI-TENANT: ALL operations MUST use domain_uuid from session.
 *
 * @package voice_secretary
 */

require_once "domain_validator.php";

class voice_ai_provider {
    
    private $database;
    private $domain_uuid;
    
    /**
     * Available provider types
     */
    const TYPES = ['stt', 'tts', 'llm', 'embeddings'];
    
    /**
     * Available providers by type
     */
    const PROVIDERS = [
        'stt' => [
            'whisper_local' => 'Whisper Local (faster-whisper)',
            'whisper_api' => 'OpenAI Whisper API',
            'azure_speech' => 'Azure Speech-to-Text',
            'google_speech' => 'Google Cloud STT',
            'aws_transcribe' => 'AWS Transcribe',
            'deepgram' => 'Deepgram Nova',
        ],
        'tts' => [
            'piper_local' => 'Piper TTS (local)',
            'coqui_local' => 'Coqui TTS (local)',
            'openai_tts' => 'OpenAI TTS',
            'elevenlabs' => 'ElevenLabs',
            'azure_neural' => 'Azure Neural TTS',
            'google_tts' => 'Google Cloud TTS',
            'aws_polly' => 'AWS Polly',
            'playht' => 'Play.ht',
        ],
        'llm' => [
            'openai' => 'OpenAI GPT',
            'azure_openai' => 'Azure OpenAI',
            'anthropic' => 'Anthropic Claude',
            'google_gemini' => 'Google Gemini',
            'aws_bedrock' => 'AWS Bedrock',
            'groq' => 'Groq (Llama)',
            'ollama_local' => 'Ollama (local)',
            'lmstudio_local' => 'LM Studio (local)',
        ],
        'embeddings' => [
            'openai_embeddings' => 'OpenAI Embeddings',
            'azure_embeddings' => 'Azure OpenAI Embeddings',
            'cohere' => 'Cohere Embed',
            'voyage' => 'Voyage AI',
            'local_embeddings' => 'Local (sentence-transformers)',
        ],
    ];
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->database = database::new();
        $this->domain_uuid = domain_validator::require_domain_uuid();
    }
    
    /**
     * List all providers for current domain
     */
    public function list($type = null) {
        $sql = "SELECT * FROM v_voice_ai_providers 
                WHERE domain_uuid = :domain_uuid";
        
        $parameters = [];
        domain_validator::add_to_parameters($parameters);
        
        if ($type) {
            $sql .= " AND provider_type = :type";
            $parameters['type'] = $type;
        }
        
        $sql .= " ORDER BY provider_type ASC, priority ASC, provider_name ASC";
        
        return $this->database->select($sql, $parameters);
    }
    
    /**
     * Get single provider by UUID
     */
    public function get($provider_uuid) {
        $sql = "SELECT * FROM v_voice_ai_providers 
                WHERE provider_uuid = :provider_uuid 
                AND domain_uuid = :domain_uuid";
        
        $parameters = [
            'provider_uuid' => $provider_uuid
        ];
        domain_validator::add_to_parameters($parameters);
        
        $rows = $this->database->select($sql, $parameters);
        return isset($rows[0]) ? $rows[0] : null;
    }
    
    /**
     * Create new provider
     */
    public function create($data) {
        $provider_uuid = uuid();
        
        // Validate type and provider name
        if (!in_array($data['provider_type'], self::TYPES)) {
            throw new Exception("Invalid provider type: " . $data['provider_type']);
        }
        
        if (!isset(self::PROVIDERS[$data['provider_type']][$data['provider_name']])) {
            throw new Exception("Invalid provider name: " . $data['provider_name']);
        }
        
        $sql = "INSERT INTO v_voice_ai_providers (
            provider_uuid,
            domain_uuid,
            provider_type,
            provider_name,
            config,
            is_active,
            is_default,
            priority,
            created_at
        ) VALUES (
            :provider_uuid,
            :domain_uuid,
            :provider_type,
            :provider_name,
            :config,
            :is_active,
            :is_default,
            :priority,
            NOW()
        )";
        
        $parameters = [
            'provider_uuid' => $provider_uuid,
            'provider_type' => $data['provider_type'],
            'provider_name' => $data['provider_name'],
            'config' => json_encode($data['config'] ?? []),
            'is_active' => $data['is_active'] ?? true,
            'is_default' => $data['is_default'] ?? false,
            'priority' => $data['priority'] ?? 10,
        ];
        domain_validator::add_to_parameters($parameters);
        
        // If setting as default, unset other defaults of same type
        if (!empty($data['is_default'])) {
            $this->unset_defaults($data['provider_type']);
        }
        
        $this->database->execute($sql, $parameters);
        
        return $provider_uuid;
    }
    
    /**
     * Update existing provider
     */
    public function update($provider_uuid, $data) {
        $set_parts = [];
        $parameters = [
            'provider_uuid' => $provider_uuid
        ];
        domain_validator::add_to_parameters($parameters);
        
        // If changing is_default to true, unset others first
        if (!empty($data['is_default'])) {
            $provider = $this->get($provider_uuid);
            if ($provider) {
                $this->unset_defaults($provider['provider_type']);
            }
        }
        
        $allowed_fields = ['config', 'is_active', 'is_default', 'priority'];
        
        foreach ($allowed_fields as $field) {
            if (array_key_exists($field, $data)) {
                $set_parts[] = "{$field} = :{$field}";
                if ($field === 'config') {
                    $parameters[$field] = json_encode($data[$field]);
                } else {
                    $parameters[$field] = $data[$field];
                }
            }
        }
        
        if (empty($set_parts)) {
            return false;
        }
        
        $set_parts[] = "updated_at = NOW()";
        
        $sql = "UPDATE v_voice_ai_providers 
                SET " . implode(', ', $set_parts) . "
                WHERE provider_uuid = :provider_uuid 
                AND domain_uuid = :domain_uuid";
        
        $this->database->execute($sql, $parameters);
        
        return true;
    }
    
    /**
     * Delete provider
     */
    public function delete($provider_uuid) {
        $sql = "DELETE FROM v_voice_ai_providers 
                WHERE provider_uuid = :provider_uuid 
                AND domain_uuid = :domain_uuid";
        
        $parameters = [
            'provider_uuid' => $provider_uuid
        ];
        domain_validator::add_to_parameters($parameters);
        
        $this->database->execute($sql, $parameters);
        
        return true;
    }
    
    /**
     * Unset all default providers of a type
     */
    private function unset_defaults($type) {
        $sql = "UPDATE v_voice_ai_providers 
                SET is_default = false 
                WHERE domain_uuid = :domain_uuid 
                AND provider_type = :type";
        
        $parameters = [
            'type' => $type
        ];
        domain_validator::add_to_parameters($parameters);
        
        $this->database->execute($sql, $parameters);
    }
    
    /**
     * Test provider connection
     */
    public function test_connection($provider_uuid) {
        $provider = $this->get($provider_uuid);
        if (!$provider) {
            return ['success' => false, 'message' => 'Provider not found'];
        }
        
        $service_url = $_ENV['VOICE_AI_SERVICE_URL'] ?? 'http://127.0.0.1:8089/api/v1';
        
        $payload = json_encode([
            'domain_uuid' => $this->domain_uuid,
            'provider_type' => $provider['provider_type'],
            'provider_name' => $provider['provider_name'],
            'config' => json_decode($provider['config'], true),
        ]);
        
        $ch = curl_init($service_url . '/providers/test');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code == 200) {
            return json_decode($response, true);
        }
        
        return ['success' => false, 'message' => 'Service unavailable'];
    }
    
    /**
     * Get config fields for a provider
     */
    public static function get_config_fields($provider_name) {
        $fields = [
            // STT providers
            'whisper_local' => [
                ['name' => 'model_size', 'label' => 'Model Size', 'type' => 'select', 'options' => ['tiny', 'base', 'small', 'medium', 'large']],
                ['name' => 'device', 'label' => 'Device', 'type' => 'select', 'options' => ['cpu', 'cuda']],
            ],
            'whisper_api' => [
                ['name' => 'api_key', 'label' => 'API Key', 'type' => 'password', 'required' => true],
            ],
            'azure_speech' => [
                ['name' => 'subscription_key', 'label' => 'Subscription Key', 'type' => 'password', 'required' => true],
                ['name' => 'region', 'label' => 'Region', 'type' => 'text', 'default' => 'brazilsouth'],
            ],
            'google_speech' => [
                ['name' => 'credentials_path', 'label' => 'Credentials JSON Path', 'type' => 'text', 'required' => true],
            ],
            'deepgram' => [
                ['name' => 'api_key', 'label' => 'API Key', 'type' => 'password', 'required' => true],
                ['name' => 'model', 'label' => 'Model', 'type' => 'select', 'options' => ['nova-2', 'nova', 'enhanced', 'base']],
            ],
            // TTS providers
            'openai_tts' => [
                ['name' => 'api_key', 'label' => 'API Key', 'type' => 'password', 'required' => true],
                ['name' => 'model', 'label' => 'Model', 'type' => 'select', 'options' => ['tts-1', 'tts-1-hd']],
                ['name' => 'voice', 'label' => 'Voice', 'type' => 'select', 'options' => ['alloy', 'echo', 'fable', 'onyx', 'nova', 'shimmer']],
            ],
            'elevenlabs' => [
                ['name' => 'api_key', 'label' => 'API Key', 'type' => 'password', 'required' => true],
                ['name' => 'voice_id', 'label' => 'Voice ID', 'type' => 'text'],
            ],
            // LLM providers
            'openai' => [
                ['name' => 'api_key', 'label' => 'API Key', 'type' => 'password', 'required' => true],
                ['name' => 'model', 'label' => 'Model', 'type' => 'select', 'options' => ['gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo', 'gpt-3.5-turbo']],
            ],
            'anthropic' => [
                ['name' => 'api_key', 'label' => 'API Key', 'type' => 'password', 'required' => true],
                ['name' => 'model', 'label' => 'Model', 'type' => 'select', 'options' => ['claude-3-opus-20240229', 'claude-3-sonnet-20240229', 'claude-3-haiku-20240307']],
            ],
            'ollama_local' => [
                ['name' => 'base_url', 'label' => 'Ollama URL', 'type' => 'text', 'default' => 'http://localhost:11434'],
                ['name' => 'model', 'label' => 'Model', 'type' => 'text', 'default' => 'llama3'],
            ],
        ];
        
        return $fields[$provider_name] ?? [];
    }
}
?>
