# 🤝 Handoff OmniPlay - Integração Voz ↔ Tickets

## O Que É

O **Handoff OmniPlay** é a funcionalidade que conecta o sistema de voz com o OmniPlay (plataforma omnichannel). Quando a IA detecta que o cliente precisa falar com um humano, o sistema:

1. **Verifica** se há atendentes online
2. **Transfere** a chamada se possível
3. **Cria ticket** automaticamente se não houver atendentes

---

## 🎯 Cenários de Uso

### Cenário 1: Cliente pede atendente e há alguém online
```
Cliente: "Quero falar com um atendente humano"
IA: [detecta keyword "atendente"]
Sistema: [verifica atendentes online → encontrou 2]
IA: "Claro! Vou transferir você agora..."
Chamada: [transferida para ramal 200]
```

### Cenário 2: Cliente pede atendente mas ninguém está online
```
Cliente: "Preciso falar com alguém de verdade"
IA: [detecta keyword "alguém"]
Sistema: [verifica atendentes online → nenhum]
IA: "No momento não temos atendentes disponíveis. Vou anotar 
     sua solicitação e alguém entrará em contato em breve."
Sistema: [cria ticket pending no OmniPlay]
Sistema: [anexa transcrição e gravação]
Cliente: "Ok, obrigado"
IA: "Seu protocolo é 2026011512345. Obrigado por ligar!"
Chamada: [encerrada]
```

### Cenário 3: Conversa longa sem resolução (max_turns)
```
[Após 20 turnos de conversa...]
Sistema: [atingiu max_ai_turns = 20]
IA: "Percebo que precisamos de mais ajuda. Vou transferir..."
[Segue fluxo do Cenário 1 ou 2]
```

### Cenário 4: Fora do horário comercial
```
[Chamada às 22:00, time_condition configurada para 08:00-18:00]
Sistema: [verifica horário → fora do expediente]
IA: "Nosso horário de atendimento humano é das 8h às 18h. 
     Posso anotar sua mensagem para retorno amanhã."
[Cria ticket automaticamente]
```

---

## 🔧 Configuração no FusionPBX

### Campos na Secretária Virtual

| Campo | Descrição | Exemplo |
|-------|-----------|---------|
| **Enable Handoff** | Liga/desliga funcionalidade | ✅ Enabled |
| **Transfer Extension** | Ramal/grupo para transferir | `200` |
| **Handoff Timeout** | Tempo máximo para transfer | `30` segundos |
| **Check Extension Presence** | Verificar se ramal está online | ✅ Enabled |
| **Business Hours** | Time Condition do FusionPBX | `horario_comercial` |
| **Max AI Turns** | Turnos antes de handoff automático | `20` |
| **Handoff Keywords** | Palavras que ativam handoff | `atendente,humano,pessoa` |
| **Fallback to Ticket** | Criar ticket quando sem atendentes | ✅ Enabled |
| **Ticket Queue** | Fila do OmniPlay para atribuir | `5` |
| **OmniPlay Company ID** | ID da empresa no OmniPlay | `1` |

---

## 🌐 APIs Envolvidas

### 1. Verificar Atendentes Online

**Endpoint:** `GET /api/voice/agents/online`

**Headers:**
```http
Authorization: Bearer <SERVICE_TOKEN>
X-Service-Name: voice-ai-realtime
X-Company-Id: 1
```

**Response:**
```json
{
  "hasOnlineAgents": true,
  "agents": [
    {
      "id": 5,
      "name": "João Silva",
      "extension": "200",
      "status": "online"
    },
    {
      "id": 8,
      "name": "Maria Santos",
      "extension": "201",
      "status": "away"
    }
  ],
  "dialString": "user/200 & user/201",
  "strategy": "ring_all"
}
```

### 2. Criar Ticket de Handoff

**Endpoint:** `POST /api/tickets/realtime-handoff`

**Headers:**
```http
Authorization: Bearer <SERVICE_TOKEN>
X-Service-Name: voice-ai-realtime
X-Company-Id: 1
Content-Type: application/json
```

**Body:**
```json
{
  "call_uuid": "abc123-def456-ghi789",
  "caller_id": "+5511999998888",
  "transcript": [
    {"role": "assistant", "text": "Olá! Como posso ajudar?", "timestamp": 1705000001000},
    {"role": "user", "text": "Quero falar com um atendente", "timestamp": 1705000005000},
    {"role": "assistant", "text": "Claro! Vou verificar...", "timestamp": 1705000006000}
  ],
  "summary": "Cliente solicitou atendimento humano. Não havia atendentes disponíveis.",
  "provider": "elevenlabs_conversational",
  "language": "pt-BR",
  "duration_seconds": 45,
  "turns": 3,
  "avg_latency_ms": 320,
  "handoff_reason": "keyword_detected",
  "queue_id": 5,
  "secretary_uuid": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
  "attach_recording": true,
  "conversation_id": "conv_123456789"
}
```

**Response:**
```json
{
  "ticket_id": 12345,
  "ticket_uuid": "tk-abc123",
  "contact_id": 678,
  "voice_conversation_id": 42,
  "status": "pending",
  "created_at": "2026-01-15T14:30:00Z"
}
```

---

## 📊 Fluxo Detalhado

```
┌──────────────────────────────────────────────────────────────────────────┐
│                         HANDOFF FLOW                                      │
└──────────────────────────────────────────────────────────────────────────┘

                    ┌─────────────┐
                    │  CONVERSA   │
                    │    EM       │
                    │  ANDAMENTO  │
                    └──────┬──────┘
                           │
              ┌────────────┴────────────┐
              ▼                         ▼
     ┌────────────────┐        ┌────────────────┐
     │ Keyword        │        │ Max Turns      │
     │ Detectada?     │        │ Atingido?      │
     └───────┬────────┘        └───────┬────────┘
             │ SIM                      │ SIM
             └──────────┬───────────────┘
                        ▼
              ┌─────────────────┐
              │ Verificar       │
              │ Business Hours  │
              └────────┬────────┘
                       │
          ┌────────────┴────────────┐
          │                         │
          ▼ DENTRO                  ▼ FORA
┌─────────────────┐        ┌─────────────────┐
│ Verificar       │        │ Mensagem:       │
│ Atendentes      │        │ "Fora do        │
│ Online          │        │ horário..."     │
└────────┬────────┘        └────────┬────────┘
         │                          │
    ┌────┴────┐                     │
    │         │                     │
    ▼ SIM     ▼ NÃO                 │
┌───────┐  ┌───────┐                │
│TRANSFER│  │ TICKET │◀──────────────┘
│ CALL  │  │ CREATE │
└───────┘  └───────┘
```

---

## 🗃️ Modelo de Dados

### VoiceConversation (OmniPlay)

```typescript
interface VoiceConversation {
  id: number;
  callUuid: string;           // UUID da chamada FreeSWITCH
  companyId: number;          // Multi-tenant
  contactId?: number;         // Contato identificado
  ticketId?: number;          // Ticket criado (se houver)
  callerNumber: string;       // Número do chamador
  provider: string;           // elevenlabs_conversational, openai_realtime
  transcript: object;         // JSON com transcrição completa
  summary?: string;           // Resumo gerado pela IA
  durationSeconds: number;    // Duração da chamada
  turns: number;              // Quantidade de turnos
  avgLatencyMs?: number;      // Latência média
  handoffReason?: string;     // keyword_detected, max_turns, user_request
  status: 'pending' | 'transferred' | 'ticket_created' | 'completed' | 'failed';
  createdAt: Date;
  updatedAt: Date;
}
```

### v_voice_secretaries (FusionPBX)

```sql
-- Campos de handoff adicionados
handoff_enabled          BOOLEAN DEFAULT true,
handoff_keywords         VARCHAR(500) DEFAULT 'atendente,humano,pessoa,operador',
handoff_timeout          INTEGER DEFAULT 30,
handoff_queue_id         INTEGER,
fallback_ticket_enabled  BOOLEAN DEFAULT true,
presence_check_enabled   BOOLEAN DEFAULT true,
time_condition_uuid      UUID,
omniplay_company_id      INTEGER
```

---

## 🔐 Autenticação

### Service Token (Máquina-a-Máquina)

O `voice-ai-realtime` usa um **Service Token** para autenticar com o OmniPlay:

```bash
# Gerar token
openssl rand -hex 32
# Exemplo: a1b2c3d4e5f6789012345678901234567890abcdef1234567890abcdef123456
```

**Backend (.env):**
```env
VOICE_AI_SERVICE_TOKEN=a1b2c3d4e5f6789012345678901234567890abcdef1234567890abcdef123456
```

**voice-ai-realtime (docker-compose.yml):**
```yaml
environment:
  VOICE_AI_SERVICE_TOKEN: a1b2c3d4e5f6789012345678901234567890abcdef1234567890abcdef123456
  OMNIPLAY_API_URL: http://backend:8080
```

### Headers Obrigatórios

```http
Authorization: Bearer <VOICE_AI_SERVICE_TOKEN>
X-Service-Name: voice-ai-realtime
X-Company-Id: <omniplay_company_id>
```

---

## 📈 Métricas

### Prometheus Metrics

```
# Handoffs por razão
voice_handoff_total{reason="keyword_detected",outcome="transferred"} 150
voice_handoff_total{reason="keyword_detected",outcome="ticket_created"} 45
voice_handoff_total{reason="max_turns",outcome="ticket_created"} 12

# Latência do handoff
voice_handoff_duration_seconds_bucket{le="1"} 180
voice_handoff_duration_seconds_bucket{le="5"} 195
voice_handoff_duration_seconds_bucket{le="30"} 207
```

### Logs Estruturados

```json
{
  "level": "info",
  "message": "Handoff initiated",
  "call_uuid": "abc123",
  "domain_uuid": "xyz789",
  "reason": "keyword_detected",
  "keyword": "atendente",
  "online_agents": 2,
  "action": "transfer",
  "timestamp": "2026-01-15T14:30:00Z"
}
```

---

## 🧪 Testando o Handoff

### 1. Teste Manual via Softphone

1. Configurar Zoiper/MicroSIP conectado ao FreeSWITCH
2. Ligar para extensão 8000 (secretária)
3. Falar: "Quero falar com um atendente"
4. Verificar logs:
   ```bash
   docker compose logs -f voice-ai-realtime | grep -i handoff
   ```

### 2. Teste da API Diretamente

```bash
# Verificar atendentes online
curl -X GET "http://localhost:8080/api/voice/agents/online" \
  -H "Authorization: Bearer $SERVICE_TOKEN" \
  -H "X-Service-Name: voice-ai-realtime" \
  -H "X-Company-Id: 1"

# Criar ticket de handoff
curl -X POST "http://localhost:8080/api/tickets/realtime-handoff" \
  -H "Authorization: Bearer $SERVICE_TOKEN" \
  -H "X-Service-Name: voice-ai-realtime" \
  -H "X-Company-Id: 1" \
  -H "Content-Type: application/json" \
  -d '{
    "call_uuid": "test-123",
    "caller_id": "+5511999998888",
    "transcript": [{"role": "user", "text": "Teste", "timestamp": 1705000000000}],
    "summary": "Teste de handoff",
    "provider": "test",
    "handoff_reason": "manual_test"
  }'
```

---

## ⚠️ Troubleshooting

### Erro: "ERR_SESSION_EXPIRED" na API

**Causa:** Service Token inválido ou não configurado

**Solução:**
1. Verificar se `VOICE_AI_SERVICE_TOKEN` está no .env do backend
2. Verificar se o mesmo token está no docker-compose do voice-ai-realtime
3. Verificar se headers `X-Service-Name` e `X-Company-Id` estão presentes

### Erro: Ticket não é criado

**Causa:** `omniplay_company_id` não configurado na secretária

**Solução:**
1. Acessar FusionPBX → Voice Secretary → Edit
2. Preencher "OmniPlay Company ID" com o ID correto
3. Rodar migration `009_add_handoff_fields.sql` se coluna não existir

### Erro: Atendentes não aparecem como online

**Causa:** Usuários sem VoIP configurado ou offline

**Solução:**
1. No OmniPlay, verificar se usuários têm:
   - `voipEnabled: true`
   - `voipExtension` preenchido
   - `status: online` ou `away`
2. Verificar se WebRTC/Softphone está conectado

---

## 📚 Referências

- [`openspec/changes/add-realtime-handoff-omni/proposal.md`](../../openspec/changes/add-realtime-handoff-omni/proposal.md)
- [`openspec/changes/add-realtime-handoff-omni/design.md`](../../openspec/changes/add-realtime-handoff-omni/design.md)
- [`openspec/changes/add-realtime-handoff-omni/tasks.md`](../../openspec/changes/add-realtime-handoff-omni/tasks.md)

---

*Última atualização: Janeiro 2026*
