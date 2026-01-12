# Security - Voice AI IVR

## Visão Geral

O sistema lida com dados sensíveis:
- **Áudio de chamadas** (voz dos clientes)
- **Transcrições** (conteúdo das conversas)
- **API Keys** (provedores de IA)
- **Dados de contato** (telefones)

## Autenticação e Autorização

### Multi-Tenant Isolation

Cada request DEVE conter `domain_uuid` válido:

```python
class BaseRequest(BaseModel):
    domain_uuid: UUID  # OBRIGATÓRIO

    @field_validator('domain_uuid')
    def validate_domain_uuid(cls, v):
        if not v:
            raise ValueError('domain_uuid is required')
        return v
```

**CRÍTICO**: O `domain_uuid` vem do FreeSWITCH (trusted source), NUNCA do cliente.

### Validação de Domain

```python
# Middleware valida domain existe
async def validate_domain(domain_uuid: UUID) -> bool:
    query = "SELECT 1 FROM v_domains WHERE domain_uuid = $1"
    result = await db.fetchval(query, domain_uuid)
    return result is not None
```

### FusionPBX (PHP)

```php
// Validar domain_uuid no PHP
class domain_validator {
    public function validate($domain_uuid) {
        // Nunca aceitar domain_uuid via POST/GET
        // Sempre usar $_SESSION['domain_uuid']
        if ($domain_uuid != $_SESSION['domain_uuid']) {
            throw new Exception('Invalid domain');
        }
    }
}
```

## Proteção de API Keys

### Armazenamento

API Keys são armazenadas **criptografadas** no PostgreSQL:

```sql
-- Nunca em plain text!
INSERT INTO v_voice_ai_providers (config) VALUES (
    pgp_sym_encrypt(
        '{"api_key": "sk-..."}',
        current_setting('app.encryption_key')
    )
);
```

### Recuperação

```python
async def get_decrypted_config(provider_uuid: UUID) -> dict:
    query = """
        SELECT pgp_sym_decrypt(
            config::bytea, 
            current_setting('app.encryption_key')
        )::json
        FROM v_voice_ai_providers
        WHERE provider_uuid = $1
    """
```

### Variáveis de Ambiente

```bash
# .env (NUNCA commitado)
OPENAI_API_KEY=sk-...
ENCRYPTION_KEY=32-byte-random-key

# Permissões restritivas
chmod 600 .env
```

## Rate Limiting

### Por Domain

```python
class RateLimiter:
    async def check_rate_limit(self, domain_uuid: str, 
                                endpoint: str) -> bool:
        key = f"rate:{domain_uuid}:{endpoint}"
        count = await redis.incr(key)
        
        if count == 1:
            await redis.expire(key, 60)  # 1 minuto
        
        limits = {
            "transcribe": 100,  # 100/min
            "synthesize": 100,
            "chat": 50,
        }
        
        return count <= limits.get(endpoint, 30)
```

### Resposta de Rate Limit

```python
if not await rate_limiter.check_rate_limit(domain_uuid, "chat"):
    raise HTTPException(
        status_code=429,
        detail="Rate limit exceeded"
    )
```

## Validação de Input

### Áudio

```python
def validate_audio(audio_bytes: bytes) -> bool:
    # Tamanho máximo: 25MB
    if len(audio_bytes) > 25 * 1024 * 1024:
        raise ValueError("Audio too large")
    
    # Formato válido (magic bytes)
    if not is_valid_audio_format(audio_bytes):
        raise ValueError("Invalid audio format")
```

### Documentos

```python
ALLOWED_EXTENSIONS = {'pdf', 'docx', 'txt'}
MAX_FILE_SIZE = 10 * 1024 * 1024  # 10MB

def validate_document(file: UploadFile):
    ext = file.filename.split('.')[-1].lower()
    if ext not in ALLOWED_EXTENSIONS:
        raise ValueError("Invalid file type")
    
    # Verificar conteúdo real, não apenas extensão
    mime = magic.from_buffer(file.file.read(1024), mime=True)
    file.file.seek(0)
    
    if mime not in ALLOWED_MIMES:
        raise ValueError("File content doesn't match extension")
```

## Logging e Auditoria

### O que logamos

```python
# Logs estruturados
logger.info("chat_request", extra={
    "domain_uuid": domain_uuid,
    "secretary_uuid": secretary_uuid,
    "caller_id_hash": hash(caller_id),  # Nunca log telefone real
    "latency_ms": latency,
})
```

### O que NÃO logamos

- ❌ API Keys
- ❌ Telefones completos
- ❌ Conteúdo de transcrições em logs gerais
- ❌ Áudio em base64

### Retenção

```python
# Configurável por tenant
DATA_RETENTION_DAYS = {
    "conversations": 90,
    "audio_files": 30,
    "logs": 365,
}
```

## Comunicação Segura

### TLS

```yaml
# docker-compose.yml para produção
services:
  traefik:
    labels:
      - "traefik.http.routers.voice-ai.tls=true"
      - "traefik.http.routers.voice-ai.tls.certresolver=letsencrypt"
```

### Internal Network

```yaml
networks:
  voice-ai-network:
    driver: bridge
    internal: true  # Sem acesso externo direto
```

## Checklist de Segurança

### Deploy

- [ ] TLS habilitado
- [ ] API Keys em variáveis de ambiente
- [ ] .env não commitado
- [ ] Rate limiting ativo
- [ ] Logs sem dados sensíveis
- [ ] Backup criptografado
- [ ] Firewall configurado

### Código

- [ ] domain_uuid validado em todo request
- [ ] Input sanitizado
- [ ] Sem SQL injection (parametrized queries)
- [ ] Sem path traversal (validação de paths)
- [ ] Dependências atualizadas

### Auditoria

- [ ] Logs de acesso
- [ ] Logs de erro
- [ ] Monitoramento de rate limit
- [ ] Alertas de comportamento anômalo

## Vulnerabilidades Conhecidas

| Risco | Mitigação | Status |
|-------|-----------|--------|
| API Key exposure | Criptografia em repouso | ✅ Implementado |
| SSRF via webhook | Whitelist de IPs | 🔄 Em andamento |
| DoS via upload | Rate limit + size limit | ✅ Implementado |
| SQL Injection | Parametrized queries | ✅ Implementado |
| Path traversal | Validação de paths | ✅ Implementado |

---
*Gerado em: 2026-01-12*
