---
status: unfilled
generated: 2026-01-12
---

# Mobile Specialist Agent Playbook

## Mission
Describe how the mobile specialist agent supports the team and when to engage it.

## Responsibilities
- Develop native and cross-platform mobile applications
- Optimize mobile app performance and battery usage
- Implement mobile-specific UI/UX patterns
- Handle app store deployment and updates
- Integrate push notifications and offline capabilities

## Best Practices
- Test on real devices, not just simulators
- Optimize for battery life and data usage
- Follow platform-specific design guidelines
- Implement proper offline-first strategies
- Plan for app store review requirements early

## Key Project Resources
- Documentation index: [docs/README.md](../docs/README.md)
- Agent handbook: [agents/README.md](./README.md)
- Agent knowledge base: [AGENTS.md](../../AGENTS.md)
- Contributor guide: [CONTRIBUTING.md](../../CONTRIBUTING.md)

## Repository Starting Points
- `database/` — TODO: Describe the purpose of this directory.
- `deploy/` — TODO: Describe the purpose of this directory.
- `docs/` — TODO: Describe the purpose of this directory.
- `freeswitch/` — TODO: Describe the purpose of this directory.
- `fusionpbx-app/` — TODO: Describe the purpose of this directory.
- `voice-ai-service/` — TODO: Describe the purpose of this directory.

## Key Files
**Pattern Implementations:**
- Factory: [`TestTTSFactory`](voice-ai-service/tests/unit/test_tts_providers.py), [`TestSTTFactory`](voice-ai-service/tests/unit/test_stt_providers.py), [`TestLLMFactory`](voice-ai-service/tests/unit/test_llm_providers.py), [`TestEmbeddingsFactory`](voice-ai-service/tests/unit/test_embeddings_providers.py)
- Service Layer: [`DatabaseService`](voice-ai-service/services/database.py), [`RAGChatService`](voice-ai-service/services/rag/rag_chat.py), [`EmbeddingService`](voice-ai-service/services/rag/embedding_service.py)

**Service Files:**
- [`DatabaseService`](voice-ai-service/services/database.py#L20)
- [`RAGChatService`](voice-ai-service/services/rag/rag_chat.py#L17)
- [`EmbeddingService`](voice-ai-service/services/rag/embedding_service.py#L12)

## Architecture Context

### Services
Business logic and orchestration
- **Directories**: `voice-ai-service`, `voice-ai-service/tests`, `voice-ai-service/services`, `voice-ai-service/scripts`, `voice-ai-service/models`, `voice-ai-service/config`, `voice-ai-service/api`, `voice-ai-service/tests/unit`, `voice-ai-service/services/rag`, `voice-ai-service/services/stt`, `voice-ai-service/services/tts`, `voice-ai-service/services/embeddings`, `voice-ai-service/services/llm`
- **Symbols**: 139 total
- **Key exports**: [`lifespan`](voice-ai-service/main.py#L19), [`health_check`](voice-ai-service/main.py#L71), [`root`](voice-ai-service/main.py#L81), [`event_loop`](voice-ai-service/tests/conftest.py#L11), [`sample_domain_uuid`](voice-ai-service/tests/conftest.py#L19), [`openai_config`](voice-ai-service/tests/conftest.py#L25), [`anthropic_config`](voice-ai-service/tests/conftest.py#L34), [`groq_config`](voice-ai-service/tests/conftest.py#L43), [`ollama_config`](voice-ai-service/tests/conftest.py#L52), [`whisper_api_config`](voice-ai-service/tests/conftest.py#L61), [`openai_tts_config`](voice-ai-service/tests/conftest.py#L71), [`elevenlabs_config`](voice-ai-service/tests/conftest.py#L81), [`piper_config`](voice-ai-service/tests/conftest.py#L91), [`openai_embeddings_config`](voice-ai-service/tests/conftest.py#L100), [`local_embeddings_config`](voice-ai-service/tests/conftest.py#L109), [`Message`](voice-ai-service/services/session_manager.py#L16), [`Session`](voice-ai-service/services/session_manager.py#L25), [`SessionManager`](voice-ai-service/services/session_manager.py#L68), [`get_session_manager`](voice-ai-service/services/session_manager.py#L287), [`ProviderManager`](voice-ai-service/services/provider_manager.py#L22), [`DatabaseService`](voice-ai-service/services/database.py#L20), [`should_ignore`](voice-ai-service/scripts/check_domain_uuid.py#L59), [`check_file`](voice-ai-service/scripts/check_domain_uuid.py#L67), [`main`](voice-ai-service/scripts/check_domain_uuid.py#L122), [`TranscribeResponse`](voice-ai-service/models/response.py#L11), [`SynthesizeResponse`](voice-ai-service/models/response.py#L36), [`ChatResponse`](voice-ai-service/models/response.py#L57), [`DocumentUploadResponse`](voice-ai-service/models/response.py#L95), [`ProviderInfo`](voice-ai-service/models/response.py#L116), [`HealthResponse`](voice-ai-service/models/response.py#L128), [`ErrorResponse`](voice-ai-service/models/response.py#L137), [`BaseRequest`](voice-ai-service/models/request.py#L13), [`TranscribeRequest`](voice-ai-service/models/request.py#L22), [`SynthesizeRequest`](voice-ai-service/models/request.py#L39), [`ChatMessage`](voice-ai-service/models/request.py#L67), [`ChatRequest`](voice-ai-service/models/request.py#L81), [`DocumentUploadRequest`](voice-ai-service/models/request.py#L110), [`ProviderConfigRequest`](voice-ai-service/models/request.py#L136), [`Settings`](voice-ai-service/config/settings.py#L14), [`WebhookPayload`](voice-ai-service/api/webhook.py#L20), [`SendWebhookRequest`](voice-ai-service/api/webhook.py#L35), [`WebhookResponse`](voice-ai-service/api/webhook.py#L42), [`send_webhook_async`](voice-ai-service/api/webhook.py#L49), [`send_webhook`](voice-ai-service/api/webhook.py#L95), [`OmniPlayTicketRequest`](voice-ai-service/api/webhook.py#L114), [`OmniPlayTicketResponse`](voice-ai-service/api/webhook.py#L127), [`create_omniplay_ticket`](voice-ai-service/api/webhook.py#L135), [`webhook_health`](voice-ai-service/api/webhook.py#L196), [`transcribe_audio`](voice-ai-service/api/transcribe.py#L20), [`synthesize_text`](voice-ai-service/api/synthesize.py#L20), [`upload_document`](voice-ai-service/api/documents.py#L18), [`list_documents`](voice-ai-service/api/documents.py#L62), [`delete_document`](voice-ai-service/api/documents.py#L87), [`MessageInput`](voice-ai-service/api/conversations.py#L21), [`SaveConversationRequest`](voice-ai-service/api/conversations.py#L33), [`ConversationResponse`](voice-ai-service/api/conversations.py#L44), [`save_conversation`](voice-ai-service/api/conversations.py#L51), [`ConversationListItem`](voice-ai-service/api/conversations.py#L133), [`list_conversations`](voice-ai-service/api/conversations.py#L145), [`get_conversation`](voice-ai-service/api/conversations.py#L203), [`chat_with_secretary`](voice-ai-service/api/chat.py#L43), [`TestOpenAITTS`](voice-ai-service/tests/unit/test_tts_providers.py#L16), [`TestElevenLabsTTS`](voice-ai-service/tests/unit/test_tts_providers.py#L75), [`TestTTSFactory`](voice-ai-service/tests/unit/test_tts_providers.py#L104), [`TestOpenAIWhisperSTT`](voice-ai-service/tests/unit/test_stt_providers.py#L15), [`TestSTTFactory`](voice-ai-service/tests/unit/test_stt_providers.py#L63), [`TestOpenAILLM`](voice-ai-service/tests/unit/test_llm_providers.py#L16), [`TestAnthropicLLM`](voice-ai-service/tests/unit/test_llm_providers.py#L86), [`TestGroqLLM`](voice-ai-service/tests/unit/test_llm_providers.py#L118), [`TestOllamaLLM`](voice-ai-service/tests/unit/test_llm_providers.py#L153), [`TestLLMFactory`](voice-ai-service/tests/unit/test_llm_providers.py#L190), [`TestOpenAIEmbeddings`](voice-ai-service/tests/unit/test_embeddings_providers.py#L14), [`TestLocalEmbeddings`](voice-ai-service/tests/unit/test_embeddings_providers.py#L95), [`TestEmbeddingsFactory`](voice-ai-service/tests/unit/test_embeddings_providers.py#L129), [`SearchResult`](voice-ai-service/services/rag/vector_store.py#L21), [`BaseVectorStore`](voice-ai-service/services/rag/vector_store.py#L30), [`PgVectorStore`](voice-ai-service/services/rag/vector_store.py#L98), [`ChromaVectorStore`](voice-ai-service/services/rag/vector_store.py#L227), [`InMemoryVectorStore`](voice-ai-service/services/rag/vector_store.py#L352), [`create_vector_store`](voice-ai-service/services/rag/vector_store.py#L445), [`RetrievalResult`](voice-ai-service/services/rag/retriever.py#L15), [`Retriever`](voice-ai-service/services/rag/retriever.py#L25), [`RAGChatService`](voice-ai-service/services/rag/rag_chat.py#L17), [`EmbeddingService`](voice-ai-service/services/rag/embedding_service.py#L12), [`DocumentChunk`](voice-ai-service/services/rag/document_processor.py#L15), [`DocumentProcessor`](voice-ai-service/services/rag/document_processor.py#L26), [`WhisperLocalSTT`](voice-ai-service/services/stt/whisper_local.py#L14), [`OpenAIWhisperSTT`](voice-ai-service/services/stt/whisper_api.py#L17), [`GoogleSpeechSTT`](voice-ai-service/services/stt/google_speech.py#L13), [`register_provider`](voice-ai-service/services/stt/factory.py#L16), [`create_stt_provider`](voice-ai-service/services/stt/factory.py#L21), [`get_available_providers`](voice-ai-service/services/stt/factory.py#L44), [`DeepgramSTT`](voice-ai-service/services/stt/deepgram.py#L16), [`TranscriptionResult`](voice-ai-service/services/stt/base.py#L13), [`BaseSTT`](voice-ai-service/services/stt/base.py#L22), [`AzureSpeechSTT`](voice-ai-service/services/stt/azure_speech.py#L14), [`AWSTranscribeSTT`](voice-ai-service/services/stt/aws_transcribe.py#L15), [`PlayHTTTS`](voice-ai-service/services/tts/playht.py#L19), [`PiperLocalTTS`](voice-ai-service/services/tts/piper_local.py#L18), [`OpenAITTS`](voice-ai-service/services/tts/openai_tts.py#L19), [`GoogleCloudTTS`](voice-ai-service/services/tts/google_tts.py#L16), [`register_provider`](voice-ai-service/services/tts/factory.py#L16), [`create_tts_provider`](voice-ai-service/services/tts/factory.py#L21), [`get_available_providers`](voice-ai-service/services/tts/factory.py#L44), [`ElevenLabsTTS`](voice-ai-service/services/tts/elevenlabs.py#L19), [`CoquiLocalTTS`](voice-ai-service/services/tts/coqui_local.py#L17), [`SynthesisResult`](voice-ai-service/services/tts/base.py#L13), [`VoiceInfo`](voice-ai-service/services/tts/base.py#L22), [`BaseTTS`](voice-ai-service/services/tts/base.py#L31), [`AzureNeuralTTS`](voice-ai-service/services/tts/azure_neural.py#L17), [`AWSPollyTTS`](voice-ai-service/services/tts/aws_polly.py#L16), [`VoyageAIEmbeddings`](voice-ai-service/services/embeddings/voyage.py#L15), [`OpenAIEmbeddings`](voice-ai-service/services/embeddings/openai.py#L15), [`LocalEmbeddings`](voice-ai-service/services/embeddings/local.py#L15), [`register_provider`](voice-ai-service/services/embeddings/factory.py#L16), [`create_embeddings_provider`](voice-ai-service/services/embeddings/factory.py#L21), [`get_available_providers`](voice-ai-service/services/embeddings/factory.py#L44), [`CohereEmbeddings`](voice-ai-service/services/embeddings/cohere.py#L14), [`EmbeddingResult`](voice-ai-service/services/embeddings/base.py#L13), [`BaseEmbeddings`](voice-ai-service/services/embeddings/base.py#L22), [`AzureOpenAIEmbeddings`](voice-ai-service/services/embeddings/azure_openai.py#L14), [`OpenAILLM`](voice-ai-service/services/llm/openai.py#L15), [`OllamaLLM`](voice-ai-service/services/llm/ollama_local.py#L15), [`LMStudioLLM`](voice-ai-service/services/llm/lmstudio_local.py#L15), [`GroqLLM`](voice-ai-service/services/llm/groq.py#L15), [`GoogleGeminiLLM`](voice-ai-service/services/llm/google_gemini.py#L12), [`register_provider`](voice-ai-service/services/llm/factory.py#L16), [`create_llm_provider`](voice-ai-service/services/llm/factory.py#L21), [`get_available_providers`](voice-ai-service/services/llm/factory.py#L44), [`Message`](voice-ai-service/services/llm/base.py#L13), [`ChatResult`](voice-ai-service/services/llm/base.py#L21), [`BaseLLM`](voice-ai-service/services/llm/base.py#L33), [`AzureOpenAILLM`](voice-ai-service/services/llm/azure_openai.py#L14), [`AWSBedrockLLM`](voice-ai-service/services/llm/aws_bedrock.py#L15), [`AnthropicLLM`](voice-ai-service/services/llm/anthropic.py#L15)
## Key Symbols for This Agent
- [`Message`](voice-ai-service/services/session_manager.py#L16) (class)
- [`Session`](voice-ai-service/services/session_manager.py#L25) (class)
- [`SessionManager`](voice-ai-service/services/session_manager.py#L68) (class)
- [`ProviderManager`](voice-ai-service/services/provider_manager.py#L22) (class)
- [`DatabaseService`](voice-ai-service/services/database.py#L20) (class)

## Documentation Touchpoints
- [Documentation Index](../docs/README.md)
- [Project Overview](../docs/project-overview.md)
- [Architecture Notes](../docs/architecture.md)
- [Development Workflow](../docs/development-workflow.md)
- [Testing Strategy](../docs/testing-strategy.md)
- [Glossary & Domain Concepts](../docs/glossary.md)
- [Data Flow & Integrations](../docs/data-flow.md)
- [Security & Compliance Notes](../docs/security.md)
- [Tooling & Productivity Guide](../docs/tooling.md)

## Collaboration Checklist

1. Confirm assumptions with issue reporters or maintainers.
2. Review open pull requests affecting this area.
3. Update the relevant doc section listed above.
4. Capture learnings back in [docs/README.md](../docs/README.md).

## Hand-off Notes

Summarize outcomes, remaining risks, and suggested follow-up actions after the agent completes its work.
