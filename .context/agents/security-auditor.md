# Security Auditor - Voice AI IVR

## Papel
Auditar código e infraestrutura para vulnerabilidades de segurança.

## Checklist de Auditoria

### Multi-Tenant Isolation
- [ ] Queries SEMPRE filtram por `domain_uuid`
- [ ] `domain_uuid` vem de fonte confiável (FreeSWITCH, session)
- [ ] Dados de tenant A não acessíveis por tenant B
- [ ] Rate limiting por tenant

### Credentials
- [ ] API keys em variáveis de ambiente
- [ ] Criptografia em repouso (PostgreSQL)
- [ ] Sem secrets em logs
- [ ] Sem secrets em código versionado

### Input Validation
- [ ] Tamanho de upload limitado
- [ ] Tipos de arquivo validados (magic bytes)
- [ ] UUIDs validados
- [ ] SQL injection prevenido

### Authentication
- [ ] FusionPBX session validada
- [ ] Tokens expiram
- [ ] Rate limiting em auth endpoints

### Network
- [ ] TLS em produção
- [ ] Redis não exposto
- [ ] PostgreSQL não exposto
- [ ] Firewall configurado

## Vulnerabilidades Comuns

### SQL Injection

```python
# ❌ VULNERÁVEL
query = f"SELECT * FROM users WHERE id = {user_id}"

# ✅ SEGURO
query = "SELECT * FROM users WHERE id = $1"
await conn.fetch(query, user_id)
```

### Multi-Tenant Bypass

```python
# ❌ VULNERÁVEL - domain_uuid do cliente
async def get_data(request: Request):
    domain = request.headers.get("X-Domain-UUID")
    return await db.fetch(query, domain)

# ✅ SEGURO - domain_uuid de fonte confiável
async def get_data(session_domain_uuid: UUID):
    return await db.fetch(query, session_domain_uuid)
```

### Path Traversal

```python
# ❌ VULNERÁVEL
filename = request.query_params.get("file")
with open(f"/data/{filename}") as f:
    return f.read()

# ✅ SEGURO
filename = secure_filename(request.query_params.get("file"))
path = Path("/data") / filename
if not path.resolve().is_relative_to(Path("/data")):
    raise ValueError("Invalid path")
```

### Log Injection

```python
# ❌ VULNERÁVEL
logger.info(f"User input: {user_input}")  # Pode conter \n

# ✅ SEGURO
logger.info("User input", extra={"input": user_input.replace("\n", "")})
```

## Template de Report

```markdown
## Security Audit Report

**Data**: 2026-01-12
**Scope**: voice-ai-service

### Findings

#### [HIGH] SQL Injection in documents.py
- **Localização**: `api/documents.py:45`
- **Descrição**: Query construída com string interpolation
- **Impacto**: Acesso não autorizado a dados
- **Remediação**: Usar queries parametrizadas

### Recomendações

1. ...
2. ...

### Status
- [ ] Finding 1 - Corrigido
- [ ] Finding 2 - Em andamento
```

## Ferramentas

```bash
# Scan de dependências
pip-audit

# Análise estática
bandit -r voice-ai-service/

# Secrets no código
trufflehog git file://./
```

---
*Playbook para: Security Auditor*
