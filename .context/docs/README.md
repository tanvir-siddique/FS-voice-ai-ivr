# Voice AI IVR - Documentation Index

Bem-vindo à base de conhecimento do Voice AI IVR!

## Visão Rápida

**Voice AI IVR** é um módulo de Secretária Virtual com Inteligência Artificial para FreeSWITCH/FusionPBX. Transforma URAs robóticas em conversas naturais.

## Documentação

### Início Rápido
- [Project Overview](./project-overview.md) - O que é, como funciona, status

### Arquitetura
- [Architecture](./architecture.md) - Diagramas, padrões, decisões técnicas
- [Data Flow](./data-flow.md) - Fluxos de dados, integrações, observabilidade

### Desenvolvimento
- [Development Workflow](./development-workflow.md) - Setup, comandos, workflow
- [Testing Strategy](./testing-strategy.md) - Testes unitários, integração, e2e

### Referência
- [Glossary](./glossary.md) - Termos, acrônimos, convenções
- [Security](./security.md) - Multi-tenant, secrets, validação

### Ferramentas
- [Tooling](./tooling.md) - Linting, formatação, CI/CD

## Playbooks de Agentes

Instruções específicas para cada tipo de tarefa:

| Agente | Descrição |
|--------|-----------|
| [Backend Specialist](../agents/backend-specialist.md) | Python/FastAPI, providers, async |
| [Database Specialist](../agents/database-specialist.md) | PostgreSQL, migrations, pgvector |
| [Feature Developer](../agents/feature-developer.md) | Novas funcionalidades end-to-end |
| [Code Reviewer](../agents/code-reviewer.md) | Checklist de review, red flags |
| [Test Writer](../agents/test-writer.md) | pytest, fixtures, cobertura |

## Estrutura do Projeto

```
voice-ai-ivr/
├── voice-ai-service/     # 🐍 Python FastAPI - Core IA
├── freeswitch/           # 📞 Lua scripts - Telefonia
├── fusionpbx-app/        # 🌐 PHP - Interface web
├── database/             # 🗃️ SQL migrations
├── deploy/               # 🚀 Configs de deploy
├── docs/                 # 📚 Documentação adicional
└── .context/             # 🤖 Esta documentação
```

## Links Rápidos

- [OpenSpec Proposal](../../openspec/changes/add-voice-ai-ivr/proposal.md)
- [OpenSpec Tasks](../../openspec/changes/add-voice-ai-ivr/tasks.md)
- [Requirements.txt](../../voice-ai-service/requirements.txt)

## Manutenção

Esta documentação é gerada e mantida com auxílio do AI Context MCP.

Para atualizar:
```bash
# Regenerar scaffolding
mcp_ai-context_initializeContext

# Preencher com análise do código
mcp_ai-context_fillScaffolding
```
