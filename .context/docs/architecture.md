# Architecture - Voice AI IVR

## Visão Geral da Arquitetura

O sistema segue uma arquitetura híbrida com separação clara de responsabilidades:

```
┌─────────────────────────────────────────────────────────────────────┐
│                         CHAMADA TELEFÔNICA                          │
└─────────────────────────────────────────────────────────────────────┘
                                   │
                                   ▼
┌─────────────────────────────────────────────────────────────────────┐
│                         FREESWITCH (mod_lua)                        │
│  ┌───────────────┐  ┌───────────────┐  ┌───────────────────────┐   │
│  │ Dialplan XML  │──│secretary_ai.lua│──│ HTTP Client (lib/http)│   │
│  └───────────────┘  └───────────────┘  └───────────────────────┘   │
└─────────────────────────────────────────────────────────────────────┘
                                   │ HTTP API
                                   ▼
┌─────────────────────────────────────────────────────────────────────┐
│                    VOICE AI SERVICE (FastAPI)                       │
│  ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌─────────────┐   │
│  │   STT   │ │   TTS   │ │   LLM   │ │   RAG   │ │ Conversations│   │
│  │ Factory │ │ Factory │ │ Factory │ │ Service │ │   Service    │   │
│  └────┬────┘ └────┬────┘ └────┬────┘ └────┬────┘ └──────┬──────┘   │
│       │          │          │          │              │            │
│       └──────────┴──────────┴──────────┴──────────────┘            │
│                              │                                      │
│                    ┌─────────┴─────────┐                           │
│                    │  ProviderManager  │                           │
│                    │  (Multi-Tenant)   │                           │
│                    └─────────┬─────────┘                           │
└──────────────────────────────┼──────────────────────────────────────┘
                               │
                               ▼
┌─────────────────────────────────────────────────────────────────────┐
│                        POSTGRESQL (FusionPBX)                       │
│  ┌───────────────┐  ┌───────────────┐  ┌───────────────────────┐   │
│  │ v_voice_*     │  │ v_domains     │  │ pgvector (embeddings) │   │
│  │ (6 tabelas)   │  │ (multi-tenant)│  │                       │   │
│  └───────────────┘  └───────────────┘  └───────────────────────┘   │
└─────────────────────────────────────────────────────────────────────┘
                               │
                               ▼
┌─────────────────────────────────────────────────────────────────────┐
│                        FUSIONPBX (PHP)                              │
│  ┌───────────────────────────────────────────────────────────────┐ │
│  │                 voice_secretary App                            │ │
│  │  ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌─────────┐ │ │
│  │  │Secretary│ │Documents│ │Transfer │ │Conversa-│ │Providers│ │ │
│  │  │  CRUD   │ │  CRUD   │ │  Rules  │ │  tions  │ │  Config │ │ │
│  │  └─────────┘ └─────────┘ └─────────┘ └─────────┘ └─────────┘ │ │
│  └───────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────────┘
```

## Padrões Arquiteturais

### 1. Factory Pattern (Multi-Provider)

Cada tipo de serviço (STT, TTS, LLM, Embeddings) usa uma Factory para instanciar providers:

```python
# services/stt/factory.py
def create_stt_provider(provider_name: str, config: dict) -> BaseSTT:
    return _providers[provider_name](config)
```

**Benefícios:**
- Adicionar novo provider = criar classe + registrar na factory
- Zero alteração no código existente
- Configuração via banco de dados (por tenant)

### 2. ProviderManager (Multi-Tenant)

Gerencia instâncias de providers por domínio:

```python
class ProviderManager:
    async def get_stt_provider(self, domain_uuid: str) -> BaseSTT:
        config = await self._load_config(domain_uuid, "stt")
        return create_stt_provider(config["provider_name"], config)
```

### 3. Session Manager (Contexto de Conversa)

Mantém histórico e contexto para cada chamada ativa:

```python
class SessionManager:
    sessions: Dict[session_id, Session]
    
    async def add_message(session_id, role, content)
    async def get_history(session_id, max_messages=10)
```

### 4. RAG (Retrieval Augmented Generation)

Fluxo para respostas baseadas em documentos:

1. **Upload**: Documento → Extração → Chunking → Embeddings → VectorStore
2. **Query**: Pergunta → Embedding → Busca Vetorial → Contexto → LLM

## Decisões Técnicas

### D1: Python + FastAPI vs Node.js

**Escolha**: Python + FastAPI

**Motivos:**
- Ecossistema de IA maduro (OpenAI, Anthropic, sentence-transformers)
- async/await nativo com alta performance
- Typing forte com Pydantic
- Facilidade de integração com Whisper, faster-whisper

### D2: pgvector vs ChromaDB vs SQLite-vec

**Escolha**: pgvector (com fallback para ChromaDB)

**Motivos:**
- Reutiliza PostgreSQL do FusionPBX
- Sem serviço adicional para gerenciar
- Queries SQL padrão com `<=>` (cosine distance)
- ChromaDB como alternativa para dev/testes

### D3: Lua no FreeSWITCH vs Python ESL

**Escolha**: Lua (mod_lua)

**Motivos:**
- Já embarcado no FreeSWITCH
- Baixa latência (mesmo processo)
- Simplicidade para fluxos de chamada
- Python service apenas para IA (separação de responsabilidades)

### D4: Multi-Tenant via domain_uuid

**Implementação:**
- TODAS as tabelas têm `domain_uuid NOT NULL`
- TODOS os endpoints exigem `domain_uuid` como parâmetro
- PHP usa `$_SESSION['domain_uuid']` (nunca do request)
- Lua obtém `session:getVariable("domain_uuid")`

## Fluxo de uma Chamada

```
1. [FREESWITCH] Chamada entra → Dialplan roteia para secretary_ai.lua
2. [LUA] Carrega config da secretária (filtrado por domain_uuid)
3. [LUA] Reproduz saudação (TTS)
4. [LOOP]
   4.1 [LUA] Grava áudio do cliente
   4.2 [PYTHON] Transcreve (STT)
   4.3 [PYTHON] Busca contexto RAG (se habilitado)
   4.4 [PYTHON] Processa com LLM (+ histórico)
   4.5 [PYTHON] Retorna resposta + ação
   4.6 [LUA] Reproduz resposta (TTS)
   4.7 [LUA] Se ação="transfer" → transfere
   4.8 [LUA] Se ação="hangup" → despede e desliga
5. [LUA] Salva conversa no banco
6. [LUA] Envia webhook para OmniPlay (opcional)
```

## Escalabilidade

| Componente | Estratégia de Escala |
|------------|---------------------|
| FreeSWITCH | Cluster SIP (Kamailio) |
| Voice AI Service | Horizontal (múltiplas instâncias) |
| PostgreSQL | Réplicas de leitura |
| Providers | Rate limiting por tenant |

## Segurança

- **Autenticação**: JWT no FusionPBX, API interna sem auth (localhost only)
- **Multi-Tenant**: Isolamento total via `domain_uuid`
- **Secrets**: API keys em variáveis de ambiente ou tabela `v_voice_ai_providers` (JSONB criptografado)
- **Rate Limiting**: Por tenant (configurável em settings)
