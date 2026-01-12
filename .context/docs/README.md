# 📚 Documentation Index - Voice AI IVR

Bem-vindo à documentação do **Voice AI IVR** - Secretária Virtual Inteligente para FreeSWITCH/FusionPBX.

## 🚀 Quick Links

| Documento | Descrição |
|-----------|-----------|
| [Project Overview](./project-overview.md) | Visão geral do projeto, stack, modos de operação |
| [Architecture](./architecture.md) | Arquitetura técnica, componentes, padrões |
| [Data Flow](./data-flow.md) | Fluxos de dados, turn-based vs realtime |
| [Development Workflow](./development-workflow.md) | Setup, comandos, branching |
| [Testing Strategy](./testing-strategy.md) | Testes unitários, integração, E2E |
| [Security](./security.md) | Multi-tenant, API keys, validação |
| [Glossary](./glossary.md) | Termos técnicos e de domínio |
| [Tooling](./tooling.md) | Scripts, IDE, debugging |

## 📁 Estrutura do Projeto

```
voice-ai-ivr/
├── voice-ai-service/      # Backend Python (FastAPI)
├── freeswitch/            # Scripts Lua + Dialplan XML
├── fusionpbx-app/         # UI PHP para FusionPBX
├── database/              # Migrations SQL
├── scripts/               # Shell scripts utilitários
├── openspec/              # Documentação OpenSpec
├── .context/              # Esta documentação
└── docker-compose.yml     # Orquestração Docker
```

## 🎯 Modos de Operação

### v1 - Turn-based
- Latência: 2-5 segundos
- Custo: Baixo
- Ideal para: IVRs simples, FAQ

### v2 - Realtime
- Latência: 300-500ms
- Full-duplex, barge-in
- Ideal para: Atendimento premium

## 🔗 Links Externos

- [OpenSpec Proposals](/openspec/changes/)
- [API Docs](http://localhost:8100/docs)
- [GitHub](https://github.com/julianotarga/voice-ai-ivr)

## 📊 Status do Projeto

| Componente | Status |
|------------|--------|
| v1 Turn-based API | ✅ Implementado |
| v2 Realtime Bridge | 🔄 Em desenvolvimento |
| FusionPBX UI | 🔄 Em desenvolvimento |
| Docker | ✅ Implementado |
| Testes | 🔄 Em andamento |

---
*Última atualização: 2026-01-12*
