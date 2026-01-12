# Agents - Voice AI IVR

## Visão Geral

Estes playbooks fornecem instruções específicas para diferentes tipos de tarefas no projeto Voice AI IVR.

## Playbooks Disponíveis

### Desenvolvimento

| Playbook | Quando Usar |
|----------|-------------|
| [Feature Developer](./feature-developer.md) | Implementar nova funcionalidade end-to-end |
| [Backend Specialist](./backend-specialist.md) | Trabalhar no Voice AI Service (Python) |
| [Database Specialist](./database-specialist.md) | Criar/modificar migrations, queries |

### Qualidade

| Playbook | Quando Usar |
|----------|-------------|
| [Code Reviewer](./code-reviewer.md) | Revisar PRs, verificar padrões |
| [Test Writer](./test-writer.md) | Escrever testes unitários/integração |
| [Bug Fixer](./bug-fixer.md) | Investigar e corrigir bugs |

### Especialistas

| Playbook | Quando Usar |
|----------|-------------|
| [Architect Specialist](./architect-specialist.md) | Decisões de arquitetura |
| [Security Auditor](./security-auditor.md) | Auditoria de segurança |
| [Performance Optimizer](./performance-optimizer.md) | Otimização de performance |
| [Documentation Writer](./documentation-writer.md) | Escrever/atualizar docs |

### DevOps

| Playbook | Quando Usar |
|----------|-------------|
| [DevOps Specialist](./devops-specialist.md) | Deploy, CI/CD, infraestrutura |

## Como Usar

1. **Identifique a tarefa** - Feature? Bug? Review?
2. **Abra o playbook correspondente**
3. **Siga as instruções específicas**

## Regras Universais

Estas regras se aplicam a TODOS os playbooks:

### 1. Multi-Tenant SEMPRE
```python
# TODA query DEVE incluir domain_uuid
WHERE domain_uuid = $1
```

### 2. Não Quebrar Existente
```python
# Adicione, não modifique
new_field: Optional[str] = None  # Opcional = compatível
```

### 3. Logs com Contexto
```python
logger.info("Action", domain_uuid=domain_uuid, ...)
```

### 4. Migrations Idempotentes
```sql
CREATE TABLE IF NOT EXISTS ...
CREATE INDEX IF NOT EXISTS ...
```

## Hierarquia de Documentação

```
.context/
├── docs/          # O QUE o sistema faz
│   ├── project-overview.md
│   ├── architecture.md
│   └── ...
└── agents/        # COMO trabalhar no sistema
    ├── backend-specialist.md
    ├── code-reviewer.md
    └── ...
```

## Atualizações

Ao modificar significativamente o projeto:
1. Atualizar playbooks afetados
2. Revisar regras críticas
3. Adicionar novos exemplos se necessário
