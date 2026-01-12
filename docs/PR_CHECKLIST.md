# Checklist de Revisão de PR - Voice AI IVR

## ✅ Multi-Tenant (OBRIGATÓRIO)

Antes de aprovar qualquer PR, verifique:

### Banco de Dados (Migrations)
- [ ] Tabela tem coluna `domain_uuid UUID NOT NULL REFERENCES v_domains(domain_uuid)`?
- [ ] Existe índice `CREATE INDEX ... ON tabela(domain_uuid)`?
- [ ] Índices compostos incluem domain_uuid quando necessário?
- [ ] `ON DELETE CASCADE` está configurado para domain_uuid?

### Python (FastAPI)
- [ ] Endpoint recebe `domain_uuid` como parâmetro obrigatório?
- [ ] Request model herda de `BaseRequest` (que exige domain_uuid)?
- [ ] Há validação explícita se domain_uuid está presente?
- [ ] Queries ao banco filtram por domain_uuid?
- [ ] ProviderManager recebe domain_uuid?
- [ ] Logs incluem domain_uuid para rastreabilidade?

### Lua (FreeSWITCH)
- [ ] Script obtém domain_uuid via `session:getVariable("domain_uuid")`?
- [ ] Há verificação se domain_uuid é válido?
- [ ] Todas as chamadas HTTP incluem domain_uuid no payload?
- [ ] Queries SQL ao banco filtram por domain_uuid?

### PHP (FusionPBX)
- [ ] Usa `$_SESSION['domain_uuid']` (não $_POST ou $_GET)?
- [ ] Formulários incluem domain_uuid como hidden field?
- [ ] Queries SQL incluem domain_uuid na cláusula WHERE?
- [ ] Não confia em domain_uuid vindo do cliente?

---

## ✅ Qualidade de Código

### Geral
- [ ] Código segue o estilo do projeto?
- [ ] Nomes de variáveis/funções são claros?
- [ ] Não há código comentado desnecessário?
- [ ] Não há secrets/credenciais hardcoded?

### Python
- [ ] Type hints estão presentes?
- [ ] Docstrings estão presentes nas funções públicas?
- [ ] Exceções são tratadas adequadamente?
- [ ] Async/await usado corretamente?

### SQL
- [ ] Migration é idempotente (IF NOT EXISTS)?
- [ ] Constraints CHECK são válidas?
- [ ] Índices são apropriados para as queries?

---

## ✅ Testes

- [ ] Testes unitários foram adicionados/atualizados?
- [ ] Testes passam localmente?
- [ ] Cobertura não diminuiu?

---

## ✅ Documentação

- [ ] README foi atualizado se necessário?
- [ ] Comentários explicam código complexo?
- [ ] tasks.md foi atualizado com o progresso?

---

## 🚨 Red Flags (Rejeitar PR se encontrar)

- ❌ Query sem domain_uuid: `SELECT * FROM tabela`
- ❌ Endpoint sem validação de domain_uuid
- ❌ `$_POST['domain_uuid']` ou `$_GET['domain_uuid']` em PHP
- ❌ Credenciais/API keys hardcoded
- ❌ Execução de SQL sem prepared statements
- ❌ Dados de um tenant acessíveis por outro

---

## Como Usar Este Checklist

1. **Antes de criar PR:** Revisar seu próprio código com este checklist
2. **Durante review:** Usar como guia para verificação
3. **Ao aprovar:** Confirmar que todos os itens obrigatórios foram verificados

---

**Última atualização:** Janeiro 2026
