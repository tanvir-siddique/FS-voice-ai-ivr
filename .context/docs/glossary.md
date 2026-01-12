# Glossary - Voice AI IVR

## Termos de Negócio

### Secretária Virtual
Sistema de IA que atende chamadas telefônicas de forma conversacional, simulando uma secretária humana. Pode responder perguntas, transferir chamadas e registrar interações.

### URA / IVR
**Unidade de Resposta Audível** / **Interactive Voice Response**. Sistema tradicional de menus telefônicos ("Pressione 1 para..."). Este projeto visa substituir URAs robóticas por conversas naturais.

### Tenant / Domínio
Uma organização/empresa isolada no sistema multi-tenant. Cada tenant tem seus próprios dados, configurações e usuários. Identificado por `domain_uuid`.

### Ramal / Extension
Número interno de telefone dentro de um PBX. Ex: ramal 200 para vendas, ramal 300 para suporte.

### Transferência
Ação de redirecionar uma chamada para outro destino (ramal, fila, número externo).

## Termos Técnicos

### STT (Speech-to-Text)
Tecnologia que converte áudio falado em texto. Exemplos: Whisper, Azure Speech, Google Speech-to-Text.

### TTS (Text-to-Speech)
Tecnologia que converte texto em áudio falado. Exemplos: ElevenLabs, Piper, Azure Neural TTS.

### LLM (Large Language Model)
Modelo de linguagem de grande escala treinado em vastos conjuntos de dados. Capaz de entender contexto e gerar respostas coerentes. Exemplos: GPT-4, Claude, Gemini.

### RAG (Retrieval Augmented Generation)
Técnica que combina busca em documentos com geração de texto por LLM. Permite que a IA responda com base em conhecimento específico da empresa.

### Embedding
Representação vetorial de texto em um espaço de alta dimensão. Permite medir similaridade semântica entre textos.

### Vector Store
Banco de dados otimizado para armazenar e buscar embeddings. Exemplos: pgvector, ChromaDB, Pinecone.

### Chunk
Pedaço de um documento maior, tipicamente 500-1000 tokens. Documentos são divididos em chunks para processamento RAG.

### FreeSWITCH
Software de telefonia de código aberto. Funciona como PBX, gateway, switch. Suporta SIP, WebRTC, diversos codecs.

### FusionPBX
Interface web PHP para gerenciar FreeSWITCH. Fornece multi-tenancy, interface amigável, provisioning de telefones.

### mod_lua
Módulo do FreeSWITCH que permite executar scripts Lua dentro do processamento de chamadas.

### ESL (Event Socket Library)
API do FreeSWITCH para controle externo via socket TCP. Permite aplicações externas controlarem chamadas.

### Dialplan
Configuração XML do FreeSWITCH que define o roteamento de chamadas. Determina qual script/ação executar para cada chamada.

### Session
No FreeSWITCH, representa uma chamada ativa. Contém informações como caller_id, domain_uuid, variáveis de canal.

## Providers

### OpenAI
Empresa que desenvolve GPT-4, Whisper, TTS. API comercial de alta qualidade.

### Anthropic
Empresa que desenvolve Claude. Focada em IA segura e alinhada.

### Azure Cognitive Services
Suite de IA da Microsoft. Inclui Speech (STT/TTS), OpenAI (LLM), Embeddings.

### Google Cloud AI
Suite de IA do Google. Inclui Speech-to-Text, Text-to-Speech, Gemini (LLM).

### AWS AI
Suite de IA da Amazon. Inclui Transcribe (STT), Polly (TTS), Bedrock (LLM).

### ElevenLabs
Especialista em síntese de voz realista. Conhecido por vozes expressivas e clonagem.

### Deepgram
Especialista em transcrição de áudio. Conhecido por baixa latência e alta precisão.

### Groq
Provedor de inferência LLM ultra-rápida. Usa hardware proprietário (LPU).

### Ollama
Software para rodar LLMs localmente. Não requer API key ou internet.

### Piper
TTS de código aberto. Roda localmente, várias vozes em português.

## Acrônimos

| Sigla | Significado |
|-------|-------------|
| API | Application Programming Interface |
| CRUD | Create, Read, Update, Delete |
| JWT | JSON Web Token |
| PBX | Private Branch Exchange |
| SIP | Session Initiation Protocol |
| UUID | Universally Unique Identifier |
| JSONB | JSON Binary (PostgreSQL) |
| REST | Representational State Transfer |
| TLS | Transport Layer Security |
| mTLS | Mutual TLS |

## Convenções de Código

### Prefixos de Tabelas
- `v_voice_*` - Tabelas do módulo Voice AI
- `v_domains` - Tabela de domínios (tenants) do FusionPBX

### Sufixos de UUID
- `*_uuid` - Identificador único (Primary Key ou Foreign Key)
- `domain_uuid` - Sempre referencia o tenant

### Nomenclatura de Arquivos
- `services/stt/*.py` - Providers de STT
- `services/tts/*.py` - Providers de TTS
- `services/llm/*.py` - Providers de LLM
- `services/rag/*.py` - Componentes de RAG
- `api/*.py` - Endpoints FastAPI
- `models/*.py` - Schemas Pydantic
