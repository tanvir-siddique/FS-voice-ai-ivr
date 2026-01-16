# 🔌 Guia Passo a Passo: Conexão Voice AI com ESL do FreeSWITCH

Este documento descreve como configurar a conexão entre o Voice AI Service e o FreeSWITCH via ESL (Event Socket Library).

## Índice

1. [Visão Geral da Arquitetura](#visão-geral-da-arquitetura)
2. [FASE 1: Configuração do FreeSWITCH](#fase-1-configuração-do-freeswitch)
3. [FASE 2: Configuração do Voice AI Service](#fase-2-configuração-do-voice-ai-service)
4. [FASE 3: Configuração de Rede](#fase-3-configuração-de-rede)
5. [FASE 4: Iniciar Serviços](#fase-4-iniciar-serviços)
6. [FASE 5: Testar Chamada](#fase-5-testar-chamada)
7. [FASE 6: Troubleshooting](#fase-6-troubleshooting)
8. [Referência de Código](#referência-de-código)

---

## Visão Geral da Arquitetura

```
┌─────────────────────────┐     TCP:8021 (ESL Inbound)     ┌──────────────────────────┐
│      FreeSWITCH         │◄───────────────────────────────►│     Voice AI Service     │
│                         │                                 │        (Python)          │
│   ┌─────────────────┐   │     TCP:8022 (ESL Outbound)     │                          │
│   │ Channel/Chamada │◄──┼────────────────────────────────►│ ├─ ESLOutboundServer     │
│   └─────────────────┘   │                                 │ ├─ AsyncESLClient        │
│                         │     UDP:10000-10100 (RTP)       │ ├─ RTPBridge             │
│     RTP Media           │◄───────────────────────────────►│ └─ RealtimeSession       │
└─────────────────────────┘                                 └──────────────────────────┘
```

### Dois Modos de Conexão ESL

| Modo | Porta | Direção | Uso |
|------|-------|---------|-----|
| **ESL Inbound** | 8021 | Voice AI → FreeSWITCH | Enviar comandos (transfer, broadcast, originate) |
| **ESL Outbound** | 8022 | FreeSWITCH → Voice AI | Receber e controlar chamadas |

---

## FASE 1: Configuração do FreeSWITCH

### 1.1 Verificar se ESL está habilitado

```bash
# Verificar módulo carregado
fs_cli -x "module_exists mod_event_socket"
# Resposta esperada: true

# Verificar porta ESL (8021)
netstat -tlnp | grep 8021
# ou
ss -tlnp | grep 8021
```

### 1.2 Configurar ESL (`event_socket.conf.xml`)

Edite o arquivo de configuração:

```bash
sudo nano /etc/freeswitch/autoload_configs/event_socket.conf.xml
```

Conteúdo recomendado:

```xml
<configuration name="event_socket.conf" description="Socket Client">
  <settings>
    <!-- Aceitar conexões de qualquer IP (ajuste para produção) -->
    <param name="listen-ip" value="0.0.0.0"/>
    
    <!-- Porta ESL Inbound (Voice AI → FreeSWITCH) -->
    <param name="listen-port" value="8021"/>
    
    <!-- Senha de autenticação (MUDE PARA PRODUÇÃO!) -->
    <param name="password" value="SUA_SENHA_SEGURA"/>
    
    <!-- ACL para segurança (opcional mas recomendado) -->
    <param name="apply-inbound-acl" value="lan"/>
    
    <!-- Não mapear NAT automaticamente -->
    <param name="nat-map" value="false"/>
  </settings>
</configuration>
```

> ⚠️ **IMPORTANTE**: A senha padrão `ClueCon` é conhecida publicamente. **SEMPRE** altere para uma senha segura em produção!

### 1.3 Instalar Dialplan ESL

Copie o dialplan para o FreeSWITCH:

```bash
# Copiar arquivo do projeto
sudo cp /path/to/voice-ai-ivr/freeswitch/dialplan/900_voice_ai_esl.xml \
        /etc/freeswitch/dialplan/default/

# Ou criar manualmente
sudo nano /etc/freeswitch/dialplan/default/900_voice_ai_esl.xml
```

Conteúdo do dialplan:

```xml
<include>
  <!-- 
    Voice AI ESL - Pattern: 8XXX (ramais 8000-8999 reservados para Voice AI)
    Ajuste o pattern conforme necessidade.
  -->
  <extension name="voice_ai_esl" continue="false">
    <condition field="destination_number" expression="^(8\d{3})$">
      <!-- Log da chamada -->
      <action application="log" data="INFO Voice AI ESL: Incoming call to $1 from ${caller_id_number}"/>
      
      <!-- Variáveis obrigatórias para o Voice AI -->
      <action application="set" data="domain_uuid=${domain_uuid}"/>
      <action application="set" data="secretary_uuid=${secretary_uuid}"/>
      
      <!-- Configuração de codec para RTP -->
      <action application="set" data="absolute_codec_string=PCMU"/>
      <action application="set" data="rtp_use_timer_name=none"/>
      
      <!-- Atender a chamada -->
      <action application="answer"/>
      
      <!-- 
        Conectar ao ESL Outbound Server do Voice AI
        
        Parâmetros:
        - IP:PORT = Endereço do Voice AI container
        - async = Execução assíncrona
        - full = Enviar todas as variáveis do canal
      -->
      <action application="socket" data="127.0.0.1:8022 async full"/>
    </condition>
  </extension>
</include>
```

### 1.4 Associar Secretária a um Ramal

Para associar uma secretária específica a um ramal:

```xml
<extension name="voice_ai_secretaria_vendas" continue="false">
  <condition field="destination_number" expression="^8001$">
    <action application="set" data="domain_uuid=${domain_uuid}"/>
    <!-- UUID da secretária configurada no FusionPBX -->
    <action application="set" data="secretary_uuid=dc923a2f-b88a-4a2f-8029-d6e0c06893c5"/>
    <action application="set" data="absolute_codec_string=PCMU"/>
    <action application="answer"/>
    <action application="socket" data="127.0.0.1:8022 async full"/>
  </condition>
</extension>
```

### 1.5 Recarregar configurações

```bash
# Recarregar XML (dialplan)
fs_cli -x "reloadxml"

# Recarregar módulo ESL (se alterou event_socket.conf.xml)
fs_cli -x "reload mod_event_socket"

# Verificar se dialplan foi carregado
fs_cli -x "show dialplan" | grep voice_ai
```

---

## FASE 2: Configuração do Voice AI Service

### 2.1 Variáveis de Ambiente

Crie ou edite o arquivo `.env` no diretório `voice-ai-ivr`:

```bash
# ============================================
# AUDIO MODE
# ============================================
# Opções: websocket, rtp, esl, dual
AUDIO_MODE=rtp

# ============================================
# ESL INBOUND (Voice AI → FreeSWITCH)
# Usado para enviar comandos: transfer, broadcast, originate
# ============================================
ESL_HOST=127.0.0.1
ESL_PORT=8021
ESL_PASSWORD=SUA_SENHA_SEGURA

# Timeouts (em segundos)
ESL_CONNECT_TIMEOUT=5.0
ESL_READ_TIMEOUT=30.0
ESL_RECONNECT_DELAY=2.0
ESL_MAX_RECONNECT_ATTEMPTS=3

# ============================================
# ESL OUTBOUND (FreeSWITCH → Voice AI)
# Servidor que recebe conexões do FreeSWITCH
# ============================================
ESL_SERVER_HOST=0.0.0.0
ESL_SERVER_PORT=8022
ESL_MAX_CONNECTIONS=100

# ============================================
# RTP Configuration
# ============================================
RTP_PORT_MIN=10000
RTP_PORT_MAX=10100
RTP_BIND_ADDRESS=0.0.0.0

# Jitter Buffer (em ms)
RTP_JITTER_MIN_MS=60
RTP_JITTER_MAX_MS=200
RTP_JITTER_TARGET_MS=100
```

### 2.2 Configuração no FusionPBX

Acesse no FusionPBX: **Apps → Voice Secretary → Settings**

Configure os campos ESL:

| Campo | Valor | Descrição |
|-------|-------|-----------|
| **ESL Host** | `127.0.0.1` | IP do FreeSWITCH |
| **ESL Port** | `8021` | Porta ESL Inbound |
| **ESL Password** | `SUA_SENHA` | Mesma senha do `event_socket.conf.xml` |
| **ESL Connect Timeout** | `5.0` | Timeout de conexão (segundos) |
| **ESL Read Timeout** | `30.0` | Timeout de leitura (segundos) |

### 2.3 Docker Compose

Edite o `docker-compose.yml`:

```yaml
services:
  voice-ai-realtime:
    build:
      context: ./voice-ai-service
      dockerfile: Dockerfile.realtime
      target: production
    container_name: voice-ai-realtime
    restart: unless-stopped
    
    ports:
      # WebSocket (mod_audio_stream) - fallback
      - "${VOICE_AI_REALTIME_PORT:-8085}:8085"
      # ESL Outbound (FreeSWITCH → Voice AI)
      - "${VOICE_AI_ESL_PORT:-8022}:8022"
      # RTP UDP (direct audio)
      - "${RTP_PORT_MIN:-10000}-${RTP_PORT_MAX:-10100}:10000-10100/udp"
    
    environment:
      # Audio Mode
      - AUDIO_MODE=${AUDIO_MODE:-rtp}
      
      # ESL Inbound (para comandos)
      - ESL_HOST=${ESL_HOST:-host.docker.internal}
      - ESL_PORT=${ESL_PORT:-8021}
      - ESL_PASSWORD=${ESL_PASSWORD}
      
      # ESL Outbound (servidor)
      - ESL_SERVER_HOST=0.0.0.0
      - ESL_SERVER_PORT=8022
      - ESL_MAX_CONNECTIONS=100
      
      # RTP
      - RTP_PORT_MIN=10000
      - RTP_PORT_MAX=10100
      - RTP_BIND_ADDRESS=0.0.0.0
      - RTP_JITTER_MIN_MS=60
      - RTP_JITTER_MAX_MS=200
      - RTP_JITTER_TARGET_MS=100
      
      # Database
      - DB_HOST=${DB_HOST:-host.docker.internal}
      - DB_PORT=${DB_PORT:-5432}
      - DB_NAME=${DB_NAME:-fusionpbx}
      - DB_USER=${DB_USER:-fusionpbx}
      - DB_PASS=${DB_PASS}
      
      # Redis
      - REDIS_HOST=redis
      - REDIS_PORT=6379
    
    extra_hosts:
      - "host.docker.internal:host-gateway"
    
    # Se FreeSWITCH está no mesmo host (melhor performance):
    # network_mode: host
```

### 2.4 Cenários de Rede Docker

#### Cenário 1: FreeSWITCH no mesmo host (Recomendado)

```yaml
voice-ai-realtime:
  network_mode: host
  environment:
    - ESL_HOST=127.0.0.1
```

No dialplan:
```xml
<action application="socket" data="127.0.0.1:8022 async full"/>
```

#### Cenário 2: FreeSWITCH em host diferente

```yaml
voice-ai-realtime:
  environment:
    - ESL_HOST=192.168.1.100  # IP do FreeSWITCH
```

No dialplan (FreeSWITCH):
```xml
<action application="socket" data="192.168.1.50:8022 async full"/>  <!-- IP do Voice AI -->
```

#### Cenário 3: Docker bridge network

```yaml
voice-ai-realtime:
  extra_hosts:
    - "host.docker.internal:host-gateway"
  environment:
    - ESL_HOST=host.docker.internal
```

---

## FASE 3: Configuração de Rede

### 3.1 Firewall (UFW)

```bash
# Permitir ESL Inbound (Voice AI → FreeSWITCH)
sudo ufw allow 8021/tcp comment "FreeSWITCH ESL Inbound"

# Permitir ESL Outbound (FreeSWITCH → Voice AI)
sudo ufw allow 8022/tcp comment "Voice AI ESL Outbound"

# Permitir RTP (áudio)
sudo ufw allow 10000:10100/udp comment "Voice AI RTP"

# Verificar regras
sudo ufw status numbered
```

### 3.2 Firewall (iptables)

```bash
# ESL Inbound
iptables -A INPUT -p tcp --dport 8021 -j ACCEPT

# ESL Outbound
iptables -A INPUT -p tcp --dport 8022 -j ACCEPT

# RTP
iptables -A INPUT -p udp --dport 10000:10100 -j ACCEPT
```

### 3.3 NAT (se aplicável)

Se FreeSWITCH e Voice AI estão em redes diferentes com NAT:

No `sip_profiles/internal.xml` do FreeSWITCH:

```xml
<param name="ext-rtp-ip" value="IP_EXTERNO_DO_FREESWITCH"/>
<param name="ext-sip-ip" value="IP_EXTERNO_DO_FREESWITCH"/>
```

---

## FASE 4: Iniciar Serviços

### 4.1 Iniciar Voice AI Service

**Com Docker Compose:**

```bash
cd /path/to/voice-ai-ivr

# Iniciar serviço
docker compose up -d voice-ai-realtime

# Verificar logs
docker compose logs -f voice-ai-realtime

# Verificar status
docker compose ps
```

**Sem Docker (desenvolvimento):**

```bash
cd /path/to/voice-ai-ivr/voice-ai-service

# Instalar dependências
pip install -r requirements.txt

# Iniciar servidor ESL
python -m realtime.esl.server --debug
```

### 4.2 Verificar conexão ESL Inbound

Do Voice AI, testar conexão com FreeSWITCH:

```bash
# Via telnet
telnet 127.0.0.1 8021

# Após conectar, digitar:
auth SUA_SENHA_SEGURA

# Resposta esperada:
# Content-Type: command/reply
# Reply-Text: +OK accepted

# Testar comando
api status

# Sair
exit
```

### 4.3 Verificar servidor ESL Outbound

Verificar se Voice AI está escutando:

```bash
# Verificar porta
ss -tlnp | grep 8022

# Testar conexão
nc -zv 127.0.0.1 8022

# Do container Docker
docker exec voice-ai-realtime ss -tlnp | grep 8022
```

---

## FASE 5: Testar Chamada

### 5.1 Originar chamada de teste

Via `fs_cli`:

```bash
# Originar chamada de um ramal para o Voice AI
fs_cli -x "originate user/1000 8000 XML default"

# Com variáveis específicas
fs_cli -x "originate {domain_uuid=abc123,secretary_uuid=def456}user/1000 8000 XML default"
```

### 5.2 Monitorar logs

**FreeSWITCH:**

```bash
# Logs em tempo real
tail -f /var/log/freeswitch/freeswitch.log | grep -i "voice\|esl\|socket"

# Via fs_cli
fs_cli -x "console loglevel debug"
```

**Voice AI:**

```bash
# Docker
docker compose logs -f voice-ai-realtime

# Sem Docker
# Os logs aparecem no terminal onde o servidor foi iniciado
```

### 5.3 Verificar eventos ESL

```bash
# Conectar ao ESL e subscrever eventos
telnet 127.0.0.1 8021
auth SUA_SENHA
event plain CHANNEL_CREATE CHANNEL_ANSWER CHANNEL_HANGUP

# Fazer uma chamada e observar os eventos
```

---

## FASE 6: Troubleshooting

### Problema: ESL Connection Refused

**Sintomas:**
- `Connection refused` ao conectar na porta 8021
- Voice AI não consegue enviar comandos

**Soluções:**

```bash
# 1. Verificar se FreeSWITCH está rodando
systemctl status freeswitch

# 2. Verificar se ESL está escutando
ss -tlnp | grep 8021

# 3. Verificar módulo ESL
fs_cli -x "module_exists mod_event_socket"

# 4. Verificar firewall
iptables -L -n | grep 8021
ufw status | grep 8021

# 5. Verificar configuração
cat /etc/freeswitch/autoload_configs/event_socket.conf.xml
```

### Problema: Authentication Failed

**Sintomas:**
- `Reply-Text: -ERR invalid` ao autenticar
- `ESL auth failed` nos logs

**Soluções:**

```bash
# 1. Verificar senha no event_socket.conf.xml
grep password /etc/freeswitch/autoload_configs/event_socket.conf.xml

# 2. Testar manualmente
telnet 127.0.0.1 8021
auth SENHA_CORRETA

# 3. Verificar variável de ambiente
echo $ESL_PASSWORD

# 4. Verificar configuração no FusionPBX
# Apps → Voice Secretary → Settings → ESL Password
```

### Problema: ESL Outbound não conecta

**Sintomas:**
- FreeSWITCH não consegue conectar ao Voice AI
- Chamada cai imediatamente após `socket` application

**Soluções:**

```bash
# 1. Verificar se Voice AI está escutando
ss -tlnp | grep 8022

# 2. Testar do FreeSWITCH
nc -zv 127.0.0.1 8022

# 3. Verificar IP no dialplan
# Se Docker, pode precisar do IP do container
docker inspect voice-ai-realtime | grep IPAddress

# 4. Verificar logs do Voice AI
docker compose logs voice-ai-realtime | grep -i "connection\|accept"

# 5. Se Docker bridge, verificar rede
docker network inspect voice-ai-ivr_default
```

### Problema: RTP não recebe áudio

**Sintomas:**
- Chamada conecta mas não há áudio
- Silêncio na ligação

**Soluções:**

```bash
# 1. Verificar se RTP está sendo enviado
tcpdump -i any udp port 10000-10100 -c 10

# 2. Verificar NAT
fs_cli -x "sofia status profile internal"

# 3. Verificar portas UDP abertas
ss -ulnp | grep 1000

# 4. Verificar codec
fs_cli -x "show channels" | grep PCMU

# 5. Verificar firewall para UDP
iptables -L -n | grep udp
```

### Problema: Timeout de reconexão

**Sintomas:**
- Voice AI desconecta e não reconecta
- `ESL reconnect failed` nos logs

**Soluções:**

```bash
# 1. Aumentar timeout
ESL_CONNECT_TIMEOUT=10.0
ESL_MAX_RECONNECT_ATTEMPTS=5

# 2. Verificar estabilidade da rede
ping -c 10 127.0.0.1

# 3. Verificar se FreeSWITCH não está sobrecarregado
fs_cli -x "show channels count"
fs_cli -x "status"
```

---

## Referência de Código

### AsyncESLClient (Python)

```python
from realtime.handlers.esl_client import get_esl_client, AsyncESLClient

# Usar singleton global
client = get_esl_client()

# Ou criar instância específica
client = AsyncESLClient(
    host="127.0.0.1",
    port=8021,
    password="SUA_SENHA"
)

# Conectar
await client.connect()

# Verificar conexão
if client.is_connected:
    print("Conectado!")

# Executar comando API
result = await client.execute_api("show calls")
print(result)

# Executar comando em background
job_uuid = await client.execute_bgapi("originate user/1000 &park()")

# Subscrever eventos
await client.subscribe_events(
    events=["CHANNEL_ANSWER", "CHANNEL_HANGUP"],
    uuid="call-uuid-aqui"  # opcional
)

# Registrar handler para evento
def on_answer(event):
    print(f"Chamada atendida: {event.uuid}")
    
handler_id = client.on_event("CHANNEL_ANSWER", None, on_answer)

# Aguardar evento específico (blocking)
event = await client.wait_for_event(
    event_names=["CHANNEL_ANSWER"],
    uuid="call-uuid-aqui",
    timeout=30.0
)

if event:
    print(f"Evento recebido: {event.name}")
```

### Comandos de Alto Nível

```python
# Reproduzir áudio
await client.uuid_broadcast(
    uuid="call-uuid",
    audio="local_stream://moh",  # música de espera
    leg="aleg"
)

# Interromper playback
await client.uuid_break(uuid="call-uuid", all_=True)

# Bridge entre duas chamadas
await client.uuid_bridge(uuid_a="call-1", uuid_b="call-2")

# Encerrar chamada
await client.uuid_kill(uuid="call-uuid", cause="NORMAL_CLEARING")

# Originar nova chamada
new_uuid = await client.originate(
    dial_string="user/1000@domain.com",
    app="&park()",
    timeout=30,
    variables={"domain_uuid": "abc123"}
)

# Obter variável de canal
value = await client.uuid_getvar(uuid="call-uuid", variable="caller_id_number")

# Definir variável de canal
await client.uuid_setvar(uuid="call-uuid", variable="my_var", value="my_value")

# Verificar se UUID existe
exists = await client.uuid_exists(uuid="call-uuid")

# Listar canais ativos
channels = await client.show_channels()
```

### ESL por Domínio (Multi-tenant)

```python
from realtime.handlers.esl_client import get_esl_for_domain

# Obtém cliente configurado para o domínio específico
# Busca configurações do banco de dados
client = await get_esl_for_domain(domain_uuid="abc123")

await client.connect()
# ... usar normalmente
```

---

## Resumo das Portas

| Porta | Protocolo | Direção | Descrição |
|-------|-----------|---------|-----------|
| **8021** | TCP | Voice AI → FreeSWITCH | ESL Inbound (comandos) |
| **8022** | TCP | FreeSWITCH → Voice AI | ESL Outbound (controle de chamadas) |
| **8085** | TCP | FreeSWITCH → Voice AI | WebSocket (fallback/mod_audio_stream) |
| **10000-10100** | UDP | Bidirecional | RTP (áudio) |

---

## Arquivos Relacionados

- `voice-ai-service/realtime/handlers/esl_client.py` - Cliente ESL assíncrono
- `voice-ai-service/realtime/esl/server.py` - Servidor ESL Outbound
- `voice-ai-service/realtime/esl/application.py` - Aplicação que trata chamadas
- `freeswitch/dialplan/900_voice_ai_esl.xml` - Dialplan ESL
- `fusionpbx-app/voice_secretary/settings.php` - Configurações no FusionPBX
- `docker-compose.yml` - Configuração Docker

---

## Referências Externas

- [FreeSWITCH ESL Documentation](https://developer.signalwire.com/freeswitch/FreeSWITCH-Explained/Client-and-Developer-Interfaces/Event-Socket-Library/)
- [greenswitch (ESL Python)](https://github.com/EvoluxBR/greenswitch)
- [FusionPBX Documentation](https://docs.fusionpbx.com/)

---

*Última atualização: Janeiro 2026*
