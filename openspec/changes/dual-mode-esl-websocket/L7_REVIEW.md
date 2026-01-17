# Revisão L7: Arquitetura ESL no Modo Dual

**Data:** 2026-01-17
**Revisores:** Claude AI + Juliano Targa

## 📊 Resumo Executivo

O sistema tem um **problema arquitetural crítico**: no modo DUAL, as operações de controle de chamada (transfer, hold, etc.) dependem do ESL Inbound que não está funcionando, enquanto o ESL Outbound está ativo mas subutilizado.

---

## 🔴 Problemas Identificados

### 1. Dependência Total do ESL Inbound

**Arquivos afetados:**
- `session.py` - hold_call(), unhold_call(), check_extension_available()
- `transfer_manager.py` - TODAS as operações de transferência

**Operações que usam ESL Inbound:**
```
uuid_kill()          - Encerrar chamada
uuid_hold()          - Colocar em espera
uuid_exists()        - Verificar se chamada existe
originate()          - Criar nova chamada (B-leg)
uuid_bridge()        - Conectar duas chamadas
uuid_broadcast()     - Tocar música/anúncio
subscribe_events()   - Subscrever eventos
execute_api()        - Comandos genéricos
```

**Problema:** Todas falham se ESL Inbound não conectar.

---

### 2. ESL Outbound Subutilizado

**Status atual:**
- ✅ Conexão ESL Outbound (porta 8022) funcionando
- ✅ Eventos sendo recebidos (HANGUP, DTMF, BRIDGE)
- ❌ Apenas `hangup()` implementado para comandos de volta
- ❌ Faltam: hold, transfer, broadcast, etc.

**Capacidades do ESL Outbound (greenswitch):**
```python
session.api(cmd)       # Executa comando API
session.execute(app)   # Executa dialplan application
session.hangup(cause)  # Desliga a chamada
```

---

### 3. Incompatibilidade gevent ↔ asyncio

**Problema:**
- ESL Outbound roda em gevent (greenlet)
- Código de negócios roda em asyncio
- Chamadas cross-thread são complexas

**Solução atual (parcial):**
- `asyncio.run_coroutine_threadsafe()` para despachar eventos
- Funciona para eventos (Outbound → asyncio)
- NÃO funciona bem para comandos (asyncio → Outbound)

---

### 4. Configuração FreeSWITCH Incorreta

**Erro observado:**
```
ESL authentication failed: Content-Type: text/disconnect-notice
Disconnected, goodbye.
```

**Causa provável:** FreeSWITCH recusa conexões ESL de IPs externos.

**Verificar:**
```bash
cat /etc/freeswitch/autoload_configs/event_socket.conf.xml
```

**Correção necessária:**
```xml
<param name="listen-ip" value="0.0.0.0"/>  <!-- NÃO 127.0.0.1 -->
<param name="listen-port" value="8021"/>
<param name="password" value="ClueCon"/>
<param name="apply-inbound-acl" value="loopback.auto,docker"/>  <!-- Adicionar Docker -->
```

---

## ✅ Solução Proposta (2 Partes)

### Parte A: Corrigir ESL Inbound (CRÍTICO)

O ESL Inbound é o padrão correto para enviar comandos ao FreeSWITCH.

1. **Configurar FreeSWITCH para aceitar conexões Docker:**
   ```xml
   <!-- /etc/freeswitch/autoload_configs/event_socket.conf.xml -->
   <param name="listen-ip" value="0.0.0.0"/>
   <param name="apply-inbound-acl" value="loopback.auto"/>
   ```

2. **Adicionar ACL para Docker:**
   ```xml
   <!-- /etc/freeswitch/autoload_configs/acl.conf.xml -->
   <list name="docker" default="allow">
     <node type="allow" cidr="172.17.0.0/16"/>
     <node type="allow" cidr="172.18.0.0/16"/>
     <node type="allow" cidr="host.docker.internal/32"/>
   </list>
   ```

3. **Recarregar config:**
   ```bash
   fs_cli -x "reloadacl"
   fs_cli -x "reload mod_event_socket"
   ```

### Parte B: Expandir ESL Outbound (Fallback)

Adicionar métodos ao `DualModeEventRelay` para operações básicas:

```python
# Já implementado:
def hangup(cause) -> bool

# A implementar:
def uuid_hold(on: bool) -> bool
def execute_api(cmd) -> Optional[str]
def uuid_break() -> bool
def uuid_broadcast(path, leg) -> bool
```

---

## 📋 Checklist de Correções

### Prioridade Alta (Crítico)

- [ ] Configurar `listen-ip: 0.0.0.0` no event_socket.conf.xml
- [ ] Adicionar ACL para Docker (ou remover acl check)
- [ ] Testar conexão ESL Inbound do container Docker
- [ ] Verificar se TransferManager funciona após correção

### Prioridade Média (Robustez)

- [ ] Adicionar `uuid_hold()` ao DualModeEventRelay
- [ ] Adicionar `uuid_break()` ao DualModeEventRelay  
- [ ] Adicionar `uuid_broadcast()` ao DualModeEventRelay
- [ ] Criar fallback em session.py para hold/unhold via Outbound

### Prioridade Baixa (Futuro)

- [ ] Refatorar TransferManager para aceitar ESL interface abstrata
- [ ] Implementar ESLCommandInterface que suporte Inbound e Outbound
- [ ] Adicionar health check de ESL Inbound no startup

---

## 🧪 Teste de Validação

### 1. Verificar ESL Inbound

```bash
# Do servidor FreeSWITCH
nc -l 8021
# Verificar se escuta em todas interfaces

# Do container Docker
docker exec -it voice-ai-realtime python -c "
import socket
s = socket.create_connection(('host.docker.internal', 8021), timeout=5)
print('ESL Inbound conectou!')
s.close()
"
```

### 2. Verificar ESL Outbound

```bash
# Verificar se container está recebendo conexões
docker logs voice-ai-realtime 2>&1 | grep "ESL EventRelay"
# Deve mostrar: "ESL EventRelay connected with linger"
```

### 3. Teste End-to-End

1. Fazer chamada para secretária
2. Dizer "tchau" → Deve desligar via ESL Outbound
3. Pedir "transferir para X" → Deve funcionar via ESL Inbound
4. Pedir "espera um momento" → Deve colocar em hold

---

## 📁 Arquivos Modificados

| Arquivo | Alteração | Status |
|---------|-----------|--------|
| `esl/event_relay.py` | Adicionado `hangup()`, `execute_api()` | ✅ Done |
| `session.py` | Modificado `stop()` para usar ESL Outbound | ✅ Done |
| `session.py` | Modificar `hold_call()`, `unhold_call()` | ⏳ Pendente |
| `transfer_manager.py` | Nenhuma alteração necessária (usa ESL Inbound) | - |

---

## 🔑 Conclusão

O problema principal é **configuração**, não código. O ESL Inbound não está aceitando conexões do Docker.

**Ação imediata:**
1. Corrigir `event_socket.conf.xml` no FreeSWITCH
2. Adicionar ACL para IPs Docker
3. Testar conexão antes de modificar mais código

**Depois da correção:**
- `hangup` → funciona via ESL Outbound (já implementado)
- `hold/unhold` → funciona via ESL Inbound (já implementado, precisa conexão)
- `transfer` → funciona via ESL Inbound (já implementado, precisa conexão)

---

**Autor:** Claude AI + Juliano Targa
