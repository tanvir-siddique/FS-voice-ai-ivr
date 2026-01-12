# Security - Voice AI IVR

## Modelo de Segurança Multi-Tenant

### Princípio Fundamental

> **NUNCA confie em `domain_uuid` vindo do request.**  
> **SEMPRE use `domain_uuid` da sessão autenticada (PHP) ou do canal FreeSWITCH (Lua).**

### Isolamento de Dados

```
┌─────────────────────────────────────────────────────────────┐
│                     TENANT A (domain_uuid_A)                │
│  ┌─────────────┐ ┌─────────────┐ ┌─────────────────────┐   │
│  │ Secretárias │ │ Documentos  │ │ Histórico Conversas │   │
│  │      A      │ │      A      │ │          A          │   │
│  └─────────────┘ └─────────────┘ └─────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
                         ║ ISOLADO ║
┌─────────────────────────────────────────────────────────────┐
│                     TENANT B (domain_uuid_B)                │
│  ┌─────────────┐ ┌─────────────┐ ┌─────────────────────┐   │
│  │ Secretárias │ │ Documentos  │ │ Histórico Conversas │   │
│  │      B      │ │      B      │ │          B          │   │
│  └─────────────┘ └─────────────┘ └─────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
```

## Validação de domain_uuid

### PHP (FusionPBX)

```php
// fusionpbx-app/voice_secretary/resources/classes/domain_validator.php

// SEMPRE usar da sessão
$domain_uuid = $_SESSION['domain_uuid'];

// Se request tentar manipular, logar e bloquear
if (isset($_POST['domain_uuid']) && $_POST['domain_uuid'] !== $domain_uuid) {
    error_log("SECURITY ALERT: domain_uuid manipulation attempt");
    http_response_code(403);
    die('Access Denied');
}
```

### Python (Voice AI Service)

```python
# Todos os endpoints exigem domain_uuid
class BaseRequest(BaseModel):
    domain_uuid: str  # Obrigatório
    
    @field_validator('domain_uuid')
    @classmethod
    def validate_uuid(cls, v):
        if not is_valid_uuid(v):
            raise ValueError('Invalid domain_uuid format')
        return v
```

### Lua (FreeSWITCH)

```lua
-- Obtém do canal (definido pelo dialplan, não manipulável)
local domain_uuid = session:getVariable("domain_uuid")

if not domain_uuid or domain_uuid == "" then
    log("ERR", "domain_uuid not found")
    session:hangup("INVALID_CALL")
    return
end
```

## Gestão de Secrets

### API Keys de Providers

**NÃO fazer:**
```python
# ❌ Nunca hardcode
OPENAI_KEY = "sk-xxxx"
```

**Fazer:**
```python
# ✅ Variáveis de ambiente
OPENAI_KEY = os.getenv("OPENAI_API_KEY")

# ✅ Banco de dados (por tenant)
config = await db.fetchrow("""
    SELECT config FROM v_voice_ai_providers
    WHERE domain_uuid = $1 AND provider_type = 'llm'
""", domain_uuid)
api_key = config['config']['api_key']
```

### Criptografia de Dados Sensíveis

Para API keys armazenadas no banco:

```python
# config/settings.py
from cryptography.fernet import Fernet

ENCRYPTION_KEY = os.getenv("VOICE_AI_ENCRYPTION_KEY")

def encrypt_config(config: dict) -> str:
    f = Fernet(ENCRYPTION_KEY)
    return f.encrypt(json.dumps(config).encode()).decode()

def decrypt_config(encrypted: str) -> dict:
    f = Fernet(ENCRYPTION_KEY)
    return json.loads(f.decrypt(encrypted.encode()))
```

## Comunicação entre Serviços

### Voice AI Service ↔ FreeSWITCH

```
┌──────────────┐         localhost:8100           ┌─────────────────┐
│  FreeSWITCH  │ ─────────────────────────────── │ Voice AI Service│
│   (Lua)      │       HTTP (sem TLS)            │    (FastAPI)    │
└──────────────┘                                  └─────────────────┘
```

**Riscos mitigados:**
- Comunicação apenas em localhost
- Firewall bloqueia porta 8100 externamente
- domain_uuid validado em cada request

### FusionPBX ↔ Voice AI Service

```
┌──────────────┐         localhost:8100           ┌─────────────────┐
│   FusionPBX  │ ─────────────────────────────── │ Voice AI Service│
│    (PHP)     │       HTTP (sem TLS)            │    (FastAPI)    │
└──────────────┘                                  └─────────────────┘
```

**Para produção com serviços separados:**
- Usar TLS mútuo (mTLS)
- API key entre serviços internos
- Rede privada (VPN/VLAN)

## Rate Limiting

### Por Tenant

```python
# config/settings.py
RATE_LIMITS = {
    "transcribe": "60/minute",  # 60 transcrições/min por tenant
    "synthesize": "100/minute", # 100 sínteses/min
    "chat": "120/minute",       # 120 chats/min
}
```

### Implementação

```python
from fastapi import Request
from slowapi import Limiter

limiter = Limiter(key_func=lambda request: request.state.domain_uuid)

@app.post("/transcribe")
@limiter.limit("60/minute")
async def transcribe(request: Request, data: TranscribeRequest):
    pass
```

## Logs de Segurança

### Eventos a Logar

| Evento | Severidade | Dados |
|--------|------------|-------|
| Tentativa de manipular domain_uuid | CRITICAL | IP, user, domain tentado |
| API key inválida | WARNING | provider, domain |
| Rate limit excedido | WARNING | domain, endpoint |
| Falha de autenticação | WARNING | IP, endpoint |
| Acesso cross-tenant detectado | CRITICAL | domain_a, domain_b, user |

### Formato de Log

```json
{
  "timestamp": "2026-01-12T10:30:00Z",
  "level": "CRITICAL",
  "event": "domain_uuid_manipulation",
  "details": {
    "source_domain": "uuid-a",
    "attempted_domain": "uuid-b",
    "ip": "192.168.1.100",
    "user_uuid": "xxx",
    "endpoint": "/api/v1/secretaries"
  }
}
```

## Checklist de Segurança

### Desenvolvimento

- [ ] Todas as queries SQL usam `WHERE domain_uuid = $1`
- [ ] Nenhum endpoint aceita domain_uuid do body sem validação
- [ ] API keys não estão em código-fonte
- [ ] Logs não contêm secrets

### Deploy

- [ ] Porta 8100 fechada externamente (firewall)
- [ ] Variáveis de ambiente configuradas
- [ ] ENCRYPTION_KEY definida e segura (32 bytes, base64)
- [ ] Backups de banco criptografados
- [ ] Rotação de API keys documentada

### Monitoramento

- [ ] Alertas para tentativas de cross-tenant
- [ ] Dashboard de rate limiting por tenant
- [ ] Auditoria de acesso a dados sensíveis
