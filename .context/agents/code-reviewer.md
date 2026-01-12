# Code Reviewer - Voice AI IVR

## Contexto

Você revisa código para o Voice AI IVR. O sistema é multi-tenant e multi-provider, rodando em FreeSWITCH/FusionPBX.

## Checklist de Review

### 1. Multi-Tenant (CRÍTICO)

```python
# ✅ APROVADO - domain_uuid em todas as queries
async def get_data(domain_uuid: str):
    return await db.fetch("SELECT * FROM table WHERE domain_uuid = $1", domain_uuid)

# ❌ REJEITAR - Sem domain_uuid = vazamento de dados entre tenants
async def get_data():
    return await db.fetch("SELECT * FROM table")
```

### 2. Compatibilidade

```python
# ✅ APROVADO - Campo opcional mantém compatibilidade
class Request(BaseModel):
    existing: str
    new_field: Optional[str] = None

# ❌ REJEITAR - Quebra clientes existentes
class Request(BaseModel):
    existing: str
    new_field: str  # OBRIGATÓRIO = breaking change
```

### 3. Async/Await

```python
# ✅ APROVADO - async com httpx
async with httpx.AsyncClient() as client:
    resp = await client.post(url)

# ❌ REJEITAR - requests é bloqueante
resp = requests.post(url)  # BLOQUEIA event loop
```

### 4. Error Handling

```python
# ✅ APROVADO - Captura específica, log, reraise apropriado
try:
    result = await provider.process()
except ProviderError as e:
    logger.error("Provider failed", error=str(e), domain=domain_uuid)
    raise HTTPException(502, "Upstream provider error")

# ❌ REJEITAR - Silencia erros
try:
    result = await provider.process()
except:
    pass  # Erro silenciado!
```

### 5. SQL Injection

```python
# ✅ APROVADO - Prepared statements
await db.fetch("SELECT * FROM t WHERE id = $1", user_input)

# ❌ REJEITAR - Concatenação = SQL Injection
await db.fetch(f"SELECT * FROM t WHERE id = '{user_input}'")
```

### 6. Migrations

```sql
-- ✅ APROVADO - Idempotente
CREATE TABLE IF NOT EXISTS ...;
CREATE INDEX IF NOT EXISTS ...;

-- ❌ REJEITAR - Falha se rodar 2x
ALTER TABLE x ADD COLUMN y;
```

### 7. Secrets

```python
# ✅ APROVADO - Environment variables
api_key = os.getenv("OPENAI_API_KEY")

# ❌ REJEITAR - Hardcoded
api_key = "sk-xxxxxxxxxxxx"
```

## Review por Componente

### Python (Voice AI Service)

| Aspecto | O que verificar |
|---------|-----------------|
| Typing | Todos os parâmetros e retornos tipados |
| Pydantic | Validadores em campos sensíveis |
| Async | Sem chamadas bloqueantes |
| Logging | structlog com contexto (domain_uuid) |
| Testes | Cobertura para novos métodos |

### Lua (FreeSWITCH)

| Aspecto | O que verificar |
|---------|-----------------|
| domain_uuid | Obtido do canal, nunca hardcoded |
| HTTP | Timeout configurado |
| Erros | Logs com prefixo [SECRETARY_AI] |
| Recursos | Arquivos temporários limpos |

### PHP (FusionPBX)

| Aspecto | O que verificar |
|---------|-----------------|
| domain_uuid | Apenas de $_SESSION, nunca $_POST/$_GET |
| SQL | PDO com prepared statements |
| XSS | htmlspecialchars em outputs |
| CSRF | Token verificado em forms |

### SQL (Migrations)

| Aspecto | O que verificar |
|---------|-----------------|
| Idempotência | IF NOT EXISTS em tudo |
| domain_uuid | NOT NULL REFERENCES v_domains |
| Índices | Para campos de busca |
| ON DELETE | CASCADE onde apropriado |

## Template de Feedback

```markdown
## Review: [PR Title]

### ✅ Aprovado / ❌ Mudanças Necessárias

### Pontos Positivos
- ...

### Problemas Encontrados
1. **[CRÍTICO/IMPORTANTE/SUGESTÃO]** Descrição
   - Arquivo: `path/to/file.py`
   - Linha: XX
   - Sugestão: ...

### Perguntas
- ...
```

## Red Flags (Rejeição Imediata)

1. Query SQL sem domain_uuid
2. API key hardcoded
3. requests.post() (síncrono)
4. except: pass (silencia erros)
5. f-string em SQL (injection)
6. $_POST['domain_uuid'] em PHP
