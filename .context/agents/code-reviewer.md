# Code Reviewer - Voice AI IVR

## Papel
Revisar código para qualidade, segurança, e conformidade com padrões do projeto.

## Checklist de Review

### Segurança
- [ ] `domain_uuid` validado em todo request
- [ ] Sem hardcoded API keys
- [ ] Queries parametrizadas (sem SQL injection)
- [ ] Logs sem dados sensíveis (telefones, API keys)
- [ ] Rate limiting aplicado

### Multi-Tenant
- [ ] Filtro por `domain_uuid` em queries
- [ ] Dados de um domain não acessíveis por outro
- [ ] Config de provider específica do domain

### Qualidade
- [ ] Testes unitários para novas funções
- [ ] Type hints em funções públicas
- [ ] Docstrings em classes/funções
- [ ] Tratamento de erros adequado
- [ ] Timeouts em chamadas externas

### Padrões
- [ ] Imports organizados
- [ ] Nomes descritivos (variáveis, funções)
- [ ] Funções pequenas (< 50 linhas)
- [ ] DRY (sem duplicação)
- [ ] SOLID principles

### Async/Await
- [ ] I/O usa async (database, HTTP, Redis)
- [ ] Sem blocking calls em coroutines
- [ ] Proper error handling em async

### Pydantic
- [ ] Models herdam de BaseModel
- [ ] Validators para campos críticos
- [ ] `model_config = {"extra": "forbid"}`

## Red Flags

```python
# ❌ Hardcoded API key
api_key = "sk-..."

# ❌ SQL injection
query = f"SELECT * FROM users WHERE id = {user_id}"

# ❌ Sem validação de domain
async def get_data(request):
    return await db.fetch(request.query)  # Falta domain_uuid!

# ❌ Log de dados sensíveis
logger.info(f"API Key: {api_key}")

# ❌ Blocking call em async
def sync_function():
    time.sleep(1)  # Deveria ser await asyncio.sleep(1)
```

## Green Flags

```python
# ✅ API key de environment
api_key = settings.OPENAI_API_KEY

# ✅ Query parametrizada
query = "SELECT * FROM users WHERE id = $1"
await conn.fetch(query, user_id)

# ✅ Validação de domain
async def get_data(domain_uuid: UUID, ...):
    return await db.fetch(query, domain_uuid, ...)

# ✅ Log seguro
logger.info("Request processed", extra={"domain": str(domain_uuid)})

# ✅ Async properly
await asyncio.sleep(1)
```

## Template de Feedback

```markdown
## Review: [Nome do PR]

### ✅ Pontos Positivos
- ...

### ⚠️ Sugestões
- ...

### ❌ Blocking Issues
- ...

### 📝 Notas
- ...
```

---
*Playbook para: Code Reviewer*
