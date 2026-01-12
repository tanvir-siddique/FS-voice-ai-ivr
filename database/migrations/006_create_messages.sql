-- Migration: Create v_voice_messages table
-- Stores individual messages from conversations
-- MULTI-TENANT: domain_uuid is REQUIRED

-- =============================================
-- v_voice_messages
-- Individual messages in a conversation
-- =============================================

CREATE TABLE IF NOT EXISTS v_voice_messages (
    -- Primary key
    message_uuid UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    
    -- Multi-tenant isolation (OBRIGATÓRIO)
    domain_uuid UUID NOT NULL REFERENCES v_domains(domain_uuid) ON DELETE CASCADE,
    
    -- Relationship to conversation
    conversation_uuid UUID NOT NULL REFERENCES v_voice_conversations(conversation_uuid) ON DELETE CASCADE,
    
    -- Message content
    role VARCHAR(20) NOT NULL CHECK (role IN ('user', 'assistant', 'system')),
    content TEXT NOT NULL,
    
    -- Audio (optional)
    audio_file VARCHAR(500),
    audio_duration_ms INTEGER,
    
    -- Transcription metadata
    transcription_confidence DECIMAL(5,4),
    stt_provider VARCHAR(50),
    stt_latency_ms INTEGER,
    
    -- Synthesis metadata
    tts_provider VARCHAR(50),
    tts_voice VARCHAR(100),
    tts_latency_ms INTEGER,
    
    -- LLM metadata (for assistant messages)
    llm_provider VARCHAR(50),
    llm_model VARCHAR(100),
    llm_tokens_used INTEGER,
    llm_latency_ms INTEGER,
    
    -- Intent detection (for user messages)
    detected_intent VARCHAR(50),
    intent_confidence DECIMAL(5,4),
    
    -- RAG context used (for assistant messages)
    rag_chunks_used INTEGER DEFAULT 0,
    
    -- Timestamps
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    
    -- Message order in conversation
    sequence_number INTEGER NOT NULL
);

-- =============================================
-- INDEXES
-- =============================================

-- Multi-tenant index (OBRIGATÓRIO)
CREATE INDEX IF NOT EXISTS idx_voice_messages_domain 
    ON v_voice_messages(domain_uuid);

-- Conversation lookup (most common query)
CREATE INDEX IF NOT EXISTS idx_voice_messages_conversation 
    ON v_voice_messages(conversation_uuid, sequence_number);

-- Domain + conversation for multi-tenant queries
CREATE INDEX IF NOT EXISTS idx_voice_messages_domain_conversation 
    ON v_voice_messages(domain_uuid, conversation_uuid);

-- Role filter
CREATE INDEX IF NOT EXISTS idx_voice_messages_role 
    ON v_voice_messages(role);

-- Time-based queries
CREATE INDEX IF NOT EXISTS idx_voice_messages_created 
    ON v_voice_messages(created_at DESC);

-- Intent analysis
CREATE INDEX IF NOT EXISTS idx_voice_messages_intent 
    ON v_voice_messages(detected_intent) 
    WHERE detected_intent IS NOT NULL;

-- =============================================
-- COMMENTS
-- =============================================

COMMENT ON TABLE v_voice_messages IS 'Individual messages in voice AI conversations';
COMMENT ON COLUMN v_voice_messages.domain_uuid IS 'Tenant identifier - REQUIRED for multi-tenant isolation';
COMMENT ON COLUMN v_voice_messages.role IS 'Message sender: user (caller), assistant (AI), system (instructions)';
COMMENT ON COLUMN v_voice_messages.sequence_number IS 'Order of message in conversation (1-based)';
COMMENT ON COLUMN v_voice_messages.rag_chunks_used IS 'Number of document chunks used for RAG context';
