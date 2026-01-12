# 🤖 Agent Playbooks - Voice AI IVR

Playbooks para auxiliar agentes de IA no desenvolvimento do projeto.

## Playbooks Disponíveis

| Agente | Descrição | Arquivo |
|--------|-----------|---------|
| **Architect** | Arquitetura, decisões técnicas | [architect-specialist.md](./architect-specialist.md) |
| **Backend** | Python/FastAPI, providers, APIs | [backend-specialist.md](./backend-specialist.md) |
| **Database** | PostgreSQL, migrations | [database-specialist.md](./database-specialist.md) |
| **DevOps** | Docker, deployment, FreeSWITCH | [devops-specialist.md](./devops-specialist.md) |
| **Frontend** | PHP FusionPBX (em breve) | [frontend-specialist.md](./frontend-specialist.md) |
| **Test Writer** | Testes unitários/integração | [test-writer.md](./test-writer.md) |
| **Code Reviewer** | Code review, padrões | [code-reviewer.md](./code-reviewer.md) |
| **Security** | Segurança, multi-tenant | [security-auditor.md](./security-auditor.md) |
| **Bug Fixer** | Debug, troubleshooting | [bug-fixer.md](./bug-fixer.md) |

## Uso

Cada playbook contém:
- **Papel**: Responsabilidades do agente
- **Stack**: Tecnologias relevantes
- **Padrões**: Convenções do projeto
- **Tarefas Comuns**: How-tos
- **Cuidados**: Do's and Don'ts

## Regras Gerais (Todos os Agentes)

1. **Multi-Tenant**: Sempre filtrar por `domain_uuid`
2. **Async**: Usar `async/await` para I/O
3. **Logs**: Estruturados, sem dados sensíveis
4. **Testes**: Cobertura mínima de 80%
5. **Documentação**: Manter OpenSpec atualizado

## Links Úteis

- [OpenSpec Proposals](/openspec/changes/)
- [API Docs](http://localhost:8100/docs)
- [Documentação](../.context/docs/)

---
*Última atualização: 2026-01-12*
