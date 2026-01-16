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

### 1.3 Criar Dialplan ESL via FusionPBX (Interface Visual)

> ⚠️ **IMPORTANTE**: O FusionPBX armazena dialplans no banco de dados PostgreSQL (tabela `v_dialplans`), NÃO em arquivos XML. Não edite arquivos diretamente em `/etc/freeswitch/dialplan/`.

#### Opção 1: Via Interface Visual do FusionPBX (Recomendado)

**Passo 1:** Acesse o FusionPBX como administrador

**Passo 2:** Navegue até **Dialplan → Dialplan Manager**

**Passo 3:** Clique em **+ Add** (canto superior direito)

**Passo 4:** Preencha os campos:

| Campo | Valor | Descrição |
|-------|-------|-----------|
| **Name** | `voice_ai_esl_8000` | Nome único do dialplan |
| **Number** | `8000` | Número/ramal que acionará o Voice AI |
| **Context** | `${domain_name}` ou `public` | Contexto (use o do seu domínio) |
| **Order** | `5` | Ordem baixa = executa antes de outros |
| **Enabled** | `true` | Ativar o dialplan |
| **Continue** | `false` | **CRÍTICO**: Impede cair em "not-found" |
| **Description** | `Voice AI ESL - Secretária Virtual` | Descrição para referência |

**Passo 5:** Na seção **Dialplan Details**, adicione as seguintes linhas na ordem:

| Tag | Type | Data |
|-----|------|------|
| **condition** | `field` | `destination_number` |
| **condition** | `expression` | `^8000$` |
| **action** | `set` | `domain_uuid=${domain_uuid}` |
| **action** | `set` | `secretary_uuid=SEU_UUID_DA_SECRETARIA` |
| **action** | `set` | `absolute_codec_string=PCMU` |
| **action** | `answer` | *(deixe vazio)* |
| **action** | `socket` | `127.0.0.1:8022 async full` |

**Passo 6:** Clique em **Save**

**Passo 7:** Limpe o cache e recarregue:

```bash
# Limpar cache do FusionPBX
rm -rf /var/cache/fusionpbx/*

# Recarregar XML no FreeSWITCH
fs_cli -x "reloadxml"
```

---

#### Opção 2: Via SQL Direto no Banco de Dados

Se preferir usar SQL (útil para automação ou bulk insert):

```sql
-- Inserir dialplan Voice AI ESL
INSERT INTO v_dialplans (
    dialplan_uuid,
    domain_uuid,
    dialplan_name,
    dialplan_number,
    dialplan_context,
    dialplan_continue,
    dialplan_order,
    dialplan_enabled,
    dialplan_description,
    dialplan_xml
)
SELECT 
    gen_random_uuid(),
    domain_uuid,
    'voice_ai_esl_8000',
    '8000',
    domain_name,  -- ou 'public' para chamadas externas
    'false',      -- CRÍTICO: impede cair em not-found
    5,            -- Ordem baixa = executa primeiro
    'true',
    'Voice AI ESL - Secretária Virtual',
    '<extension name="voice_ai_esl_8000" continue="false">
  <condition field="destination_number" expression="^8000$">
    <action application="set" data="domain_uuid=${domain_uuid}"/>
    <action application="set" data="secretary_uuid=SEU_UUID_DA_SECRETARIA"/>
    <action application="set" data="absolute_codec_string=PCMU"/>
    <action application="answer"/>
    <action application="socket" data="127.0.0.1:8022 async full"/>
  </condition>
</extension>'
FROM v_domains
WHERE domain_name = 'seu.dominio.com.br'
LIMIT 1;
```

Executar:

```bash
sudo -u postgres psql fusionpbx < seu_script.sql
```

---

#### Opção 3: Criar Dialplan para Range de Ramais (8000-8999)

Para criar um dialplan que atenda vários ramais Voice AI:

**Via Interface:**

| Campo | Valor |
|-------|-------|
| **Name** | `voice_ai_esl_range` |
| **Number** | `8XXX` |
| **Expression** | `^(8\d{3})$` |

**Via SQL:**

```sql
INSERT INTO v_dialplans (
    dialplan_uuid,
    domain_uuid,
    dialplan_name,
    dialplan_number,
    dialplan_context,
    dialplan_continue,
    dialplan_order,
    dialplan_enabled,
    dialplan_description,
    dialplan_xml
)
SELECT 
    gen_random_uuid(),
    domain_uuid,
    'voice_ai_esl_range',
    '8XXX',
    domain_name,
    'false',
    5,
    'true',
    'Voice AI ESL - Range 8000-8999',
    '<extension name="voice_ai_esl_range" continue="false">
  <condition field="destination_number" expression="^(8\d{3})$">
    <action application="log" data="INFO Voice AI ESL: Incoming call to $1 from ${caller_id_number}"/>
    <action application="set" data="domain_uuid=${domain_uuid}"/>
    <action application="set" data="absolute_codec_string=PCMU"/>
    <action application="set" data="rtp_use_timer_name=none"/>
    <action application="answer"/>
    <action application="socket" data="127.0.0.1:8022 async full"/>
  </condition>
</extension>'
FROM v_domains
WHERE domain_name = 'seu.dominio.com.br'
LIMIT 1;
```

### 1.4 Associar Secretária a um Ramal Específico

Para associar uma secretária específica a um ramal, você precisa do UUID da secretária.

**Encontrar o UUID da secretária no FusionPBX:**

```sql
-- Listar todas as secretárias do domínio
SELECT 
    voice_secretary_uuid,
    secretary_name,
    extension
FROM v_voice_secretaries
WHERE domain_uuid = 'SEU_DOMAIN_UUID';
```

**Ou via Interface:** Apps → Voice Secretary → Editar → Copiar UUID da URL

**Criar dialplan com secretária específica:**

```sql
UPDATE v_dialplans 
SET dialplan_xml = '<extension name="voice_ai_esl_vendas" continue="false">
  <condition field="destination_number" expression="^8001$">
    <action application="set" data="domain_uuid=96f6142d-02b1-49fa-8bcb-f98658bb831f"/>
    <action application="set" data="secretary_uuid=dc923a2f-b88a-4a2f-8029-d6e0c06893c5"/>
    <action application="set" data="absolute_codec_string=PCMU"/>
    <action application="answer"/>
    <action application="socket" data="127.0.0.1:8022 async full"/>
  </condition>
</extension>'
WHERE dialplan_name = 'voice_ai_esl_vendas';
```

### 1.5 Limpar Cache e Recarregar

> ⚠️ **CRÍTICO**: O FusionPBX usa cache de arquivos. Após qualquer alteração no dialplan, SEMPRE execute:

```bash
# 1. Limpar cache do FusionPBX
rm -rf /var/cache/fusionpbx/*

# 2. Recarregar XML no FreeSWITCH
fs_cli -x "reloadxml"

# 3. (Opcional) Limpar cache específico
fs_cli -x "xml_flush_cache dialplan"

# 4. Verificar se dialplan foi carregado
fs_cli -x "show dialplan" | grep voice_ai
```

**Via PHP (alternativa):**

```bash
php -r "
require '/var/www/fusionpbx/resources/require.php';
\$cache = new cache;
\$cache->delete('dialplan:seu.dominio.com.br');
echo 'Cache cleared';
"
```

### 1.6 Verificar Dialplan no Banco

```sql
-- Verificar se o dialplan existe e está correto
SELECT 
    dialplan_uuid,
    dialplan_name,
    dialplan_number,
    dialplan_order,
    dialplan_continue,
    dialplan_enabled,
    dialplan_xml
FROM v_dialplans
WHERE dialplan_name LIKE '%voice_ai%'
ORDER BY dialplan_order;
```

### 1.7 Estrutura da Tabela v_dialplans (Referência)

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `dialplan_uuid` | UUID | Identificador único |
| `domain_uuid` | UUID | Domínio (multi-tenant) |
| `dialplan_name` | VARCHAR | Nome único do dialplan |
| `dialplan_number` | VARCHAR | Número de referência |
| `dialplan_context` | VARCHAR | Contexto (domain_name ou public) |
| `dialplan_continue` | VARCHAR | 'true' ou 'false' |
| `dialplan_order` | INT | Ordem de execução (menor = primeiro) |
| `dialplan_enabled` | VARCHAR | 'true' ou 'false' |
| `dialplan_description` | TEXT | Descrição para referência |
| `dialplan_xml` | TEXT | **XML real usado pelo FreeSWITCH** |

> **IMPORTANTE**: O campo `dialplan_xml` é o que realmente é executado pelo FreeSWITCH. Os registros em `v_dialplan_details` são apenas para a interface visual.

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

### Problema: DESTINATION_OUT_OF_ORDER

**Sintomas:**
- Chamada falha com erro `DESTINATION_OUT_OF_ORDER`
- Nenhum log no Voice AI

**Soluções:**

```bash
# 1. Verificar se dialplan existe no banco
sudo -u postgres psql fusionpbx -c "
SELECT dialplan_name, dialplan_enabled, dialplan_continue, dialplan_order 
FROM v_dialplans 
WHERE dialplan_name LIKE '%voice_ai%' OR dialplan_number = '8000';"

# 2. Verificar se dialplan_xml está correto
sudo -u postgres psql fusionpbx -c "
SELECT dialplan_xml 
FROM v_dialplans 
WHERE dialplan_name LIKE '%voice_ai%';"

# 3. Verificar se dialplan_continue = 'false'
# Se for 'true', a chamada pode cair em "not-found"

# 4. Limpar cache do FusionPBX
rm -rf /var/cache/fusionpbx/*
fs_cli -x "reloadxml"

# 5. Verificar ordem do dialplan (deve ser baixa, ex: 5)
# Dialplans com ordem alta podem ser sobrescritos por catch-all
```

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

# 3. Verificar IP no dialplan (campo dialplan_xml)
sudo -u postgres psql fusionpbx -c "
SELECT dialplan_xml FROM v_dialplans 
WHERE dialplan_name LIKE '%voice_ai%';" | grep socket

# 4. Se Docker, verificar IP do container
docker inspect voice-ai-realtime | grep IPAddress

# 5. Verificar logs do Voice AI
docker compose logs voice-ai-realtime | grep -i "connection\|accept"

# 6. Se Docker bridge, verificar rede
docker network inspect voice-ai-ivr_default
```

### Problema: Cache do FusionPBX

**Sintomas:**
- Alterações no dialplan não surtem efeito
- FreeSWITCH continua usando XML antigo

**Soluções:**

```bash
# 1. Verificar configuração do cache
cat /etc/fusionpbx/config.conf | grep cache

# 2. Limpar cache de arquivos
rm -rf /var/cache/fusionpbx/*

# 3. Limpar cache via PHP
php -r "
require '/var/www/fusionpbx/resources/require.php';
\$cache = new cache;
\$cache->delete('dialplan:');
echo 'Cache cleared';
"

# 4. Recarregar XML no FreeSWITCH
fs_cli -x "reloadxml"
fs_cli -x "xml_flush_cache dialplan"

# 5. Verificar se novo dialplan está ativo
fs_cli -x "show dialplan" | grep voice_ai
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

### Problema: Dialplan Details vs Dialplan XML

**Sintomas:**
- Interface do FusionPBX mostra uma coisa, FreeSWITCH executa outra
- Alterações na interface não funcionam

**Explicação:**

O FusionPBX usa **duas tabelas** para dialplans:

| Tabela | Uso |
|--------|-----|
| `v_dialplans` | Campo `dialplan_xml` é o **XML real** executado pelo FreeSWITCH |
| `v_dialplan_details` | Apenas para **exibição na interface** |

**Se os dois estiverem dessincronizados:**

```sql
-- Verificar se estão sincronizados
SELECT 
    p.dialplan_name,
    p.dialplan_xml,
    d.dialplan_detail_data
FROM v_dialplans p
LEFT JOIN v_dialplan_details d ON p.dialplan_uuid = d.dialplan_uuid
WHERE p.dialplan_name LIKE '%voice_ai%';
```

**Para sincronizar, use a interface do FusionPBX:**
1. Dialplan → Dialplan Manager → Selecione o dialplan
2. Faça qualquer alteração pequena
3. Clique em Save
4. Isso regenera o `dialplan_xml` a partir dos `dialplan_details`

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
