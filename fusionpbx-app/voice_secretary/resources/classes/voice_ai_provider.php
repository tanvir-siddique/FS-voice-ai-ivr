<?php
/**
 * Voice AI Provider Class
 * 
 * CRUD operations for AI providers (STT, TTS, LLM, Embeddings).
 * ⚠️ MULTI-TENANT: ALL operations MUST use domain_uuid parameter.
 *
 * @package voice_secretary
 */

class voice_ai_provider {
    
    private $database;
    
    /**
     * Available provider types
     */
    const TYPES = ['stt', 'tts', 'llm', 'embeddings', 'realtime'];
    
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
        'realtime' => [
            'openai_realtime' => 'OpenAI Realtime API',
            'elevenlabs_conv' => 'ElevenLabs Conversational',
            'gemini_live' => 'Google Gemini Live',
            'custom_pipeline' => 'Custom Pipeline (Deepgram+Groq+Piper)',
        ],
    ];
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->database = new database;
    }
    
    /**
     * List all providers for a domain
     */
    public function get_list($domain_uuid, $type = null) {
        $sql = "SELECT * FROM v_voice_ai_providers 
                WHERE domain_uuid = :domain_uuid";
        
        $parameters['domain_uuid'] = $domain_uuid;
        
        if ($type) {
            $sql .= " AND provider_type = :type";
            $parameters['type'] = $type;
        }
        
        $sql .= " ORDER BY provider_type ASC, priority ASC, provider_name ASC";
        
        $this->database->sql = $sql;
        $this->database->parameters = $parameters;
        return $this->database->execute('all', PDO::FETCH_ASSOC);
    }
    
    /**
     * Get single provider by UUID
     */
    public function get($provider_uuid, $domain_uuid) {
        $sql = "SELECT * FROM v_voice_ai_providers 
                WHERE voice_ai_provider_uuid = :provider_uuid 
                AND domain_uuid = :domain_uuid";
        
        $parameters['provider_uuid'] = $provider_uuid;
        $parameters['domain_uuid'] = $domain_uuid;
        
        $this->database->sql = $sql;
        $this->database->parameters = $parameters;
        $result = $this->database->execute('all', PDO::FETCH_ASSOC);
        
        return isset($result[0]) ? $result[0] : null;
    }
    
    /**
     * Create new provider
     */
    public function create($data, $domain_uuid) {
        $provider_uuid = uuid();
        
        // Validate type
        if (!in_array($data['provider_type'], self::TYPES)) {
            throw new Exception("Invalid provider type: " . $data['provider_type']);
        }
        
        $sql = "INSERT INTO v_voice_ai_providers (
            voice_ai_provider_uuid,
            domain_uuid,
            provider_type,
            provider_name,
            config,
            is_enabled,
            is_default,
            priority,
            insert_date
        ) VALUES (
            :provider_uuid,
            :domain_uuid,
            :provider_type,
            :provider_name,
            :config,
            :is_enabled,
            :is_default,
            :priority,
            NOW()
        )";
        
        $parameters['provider_uuid'] = $provider_uuid;
        $parameters['domain_uuid'] = $domain_uuid;
        $parameters['provider_type'] = $data['provider_type'];
        $parameters['provider_name'] = $data['provider_name'];
        $parameters['config'] = json_encode($data['config'] ?? []);
        $parameters['is_enabled'] = $data['is_enabled'] ?? true;
        $parameters['is_default'] = $data['is_default'] ?? false;
        $parameters['priority'] = $data['priority'] ?? 10;
        
        // If setting as default, unset other defaults of same type
        if (!empty($data['is_default'])) {
            $this->unset_defaults($data['provider_type'], $domain_uuid);
        }
        
        $this->database->sql = $sql;
        $this->database->parameters = $parameters;
        $this->database->execute();
        
        return $provider_uuid;
    }
    
    /**
     * Update existing provider
     */
    public function update($provider_uuid, $data, $domain_uuid) {
        // If changing is_default to true, unset others first
        if (!empty($data['is_default'])) {
            $provider = $this->get($provider_uuid, $domain_uuid);
            if ($provider) {
                $this->unset_defaults($provider['provider_type'], $domain_uuid);
            }
        }
        
        $sql = "UPDATE v_voice_ai_providers SET
            config = :config,
            is_enabled = :is_enabled,
            is_default = :is_default,
            priority = :priority,
            update_date = NOW()
            WHERE voice_ai_provider_uuid = :provider_uuid 
            AND domain_uuid = :domain_uuid";
        
        $parameters['provider_uuid'] = $provider_uuid;
        $parameters['domain_uuid'] = $domain_uuid;
        $parameters['config'] = json_encode($data['config'] ?? []);
        $parameters['is_enabled'] = $data['is_enabled'] ?? true;
        $parameters['is_default'] = $data['is_default'] ?? false;
        $parameters['priority'] = $data['priority'] ?? 10;
        
        $this->database->sql = $sql;
        $this->database->parameters = $parameters;
        $this->database->execute();
        
        return true;
    }
    
    /**
     * Delete provider
     */
    public function delete($provider_uuid, $domain_uuid) {
        $sql = "DELETE FROM v_voice_ai_providers 
                WHERE voice_ai_provider_uuid = :provider_uuid 
                AND domain_uuid = :domain_uuid";
        
        $parameters['provider_uuid'] = $provider_uuid;
        $parameters['domain_uuid'] = $domain_uuid;
        
        $this->database->sql = $sql;
        $this->database->parameters = $parameters;
        $this->database->execute();
        
        return true;
    }
    
    /**
     * Unset all default providers of a type
     */
    private function unset_defaults($type, $domain_uuid) {
        $sql = "UPDATE v_voice_ai_providers 
                SET is_default = false 
                WHERE domain_uuid = :domain_uuid 
                AND provider_type = :type";
        
        $parameters['domain_uuid'] = $domain_uuid;
        $parameters['type'] = $type;
        
        $this->database->sql = $sql;
        $this->database->parameters = $parameters;
        $this->database->execute();
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
            // Realtime providers
            'openai_realtime' => [
                ['name' => 'api_key', 'label' => 'API Key', 'type' => 'password', 'required' => true],
                ['name' => 'model', 'label' => 'Model', 'type' => 'text', 'default' => 'gpt-4o-realtime-preview'],
            ],
            'elevenlabs_conv' => [
                ['name' => 'api_key', 'label' => 'API Key', 'type' => 'password', 'required' => true],
                ['name' => 'agent_id', 'label' => 'Agent ID', 'type' => 'text'],
            ],
            'gemini_live' => [
                ['name' => 'api_key', 'label' => 'API Key', 'type' => 'password', 'required' => true],
                ['name' => 'model', 'label' => 'Model', 'type' => 'text', 'default' => 'gemini-2.0-flash-exp'],
            ],
        ];
        
        return $fields[$provider_name] ?? [];
    }
}
?>
