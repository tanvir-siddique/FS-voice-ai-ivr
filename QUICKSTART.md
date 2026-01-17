# 🚀 Voice AI IVR - Guia de Implantação

**Versão:** 1.0  
**Data:** Janeiro 2026  
**Status:** Produção

---

## 📋 Índice

1. [Visão Geral da Arquitetura](#1-visão-geral-da-arquitetura)
2. [Pré-requisitos](#2-pré-requisitos)
3. [FASE 1: Configurar Variáveis de Ambiente](#3-fase-1-configurar-variáveis-de-ambiente)
4. [FASE 2: Iniciar Containers Docker](#4-fase-2-iniciar-containers-docker)
5. [FASE 3: Executar Migrations no FusionPBX](#5-fase-3-executar-migrations-no-fusionpbx)
6. [FASE 4: Instalar App no FusionPBX](#6-fase-4-instalar-app-no-fusionpbx)
7. [FASE 5: Criar Dialplan](#7-fase-5-criar-dialplan)
8. [FASE 6: Configurar Secretária Virtual](#8-fase-6-configurar-secretária-virtual)
9. [FASE 7: Testar Sistema](#9-fase-7-testar-sistema)
10. [Troubleshooting](#10-troubleshooting)

---

## 1. Visão Geral da Arquitetura

```
┌─────────────────────────────────────────────────────────────────────────┐
│                              SERVIDOR                                    │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│  ┌──────────────────┐      ┌──────────────────────────────────────────┐ │
│  │   FusionPBX      │      │          Docker Containers               │ │
│  │   (bare metal)   │      │                                          │ │
│  │                  │      │  ┌──────────────────┐                    │ │
│  │  ┌────────────┐  │      │  │ voice-ai-realtime│ ← ESL (8022)       │ │
│  │  │ FreeSWITCH │──┼──────┼──│   (Python)       │ ← WebSocket (8085) │ │
│  │  │            │  │      │  └──────────────────┘                    │ │
│  │  └────────────┘  │      │                                          │ │
│  │        │         │      │  ┌──────────────────┐                    │ │
│  │        ↓         │      │  │ voice-ai-service │ ← HTTP (8100)      │ │
│  │  ┌────────────┐  │      │  │  (STT/TTS/LLM)   │                    │ │
│  │  │ PostgreSQL │←─┼──────┼──│                  │                    │ │
│  │  │ (5432)     │  │      │  └──────────────────┘                    │ │
│  │  └────────────┘  │      │                                          │ │
│  │                  │      │  ┌──────────────────┐                    │ │
│  │  ┌────────────┐  │      │  │     Redis        │ ← (6379)           │ │
│  │  │ FusionPBX  │  │      │  │   (cache)        │                    │ │
│  │  │    UI      │  │      │  └──────────────────┘                    │ │
│  │  └────────────┘  │      │                                          │ │
│  └──────────────────┘      └──────────────────────────────────────────┘ │
│                                                                          │
└─────────────────────────────────────────────────────────────────────────┘
```

### Portas Utilizadas

| Serviço | Porta | Protocolo | Descrição |
|---------|-------|-----------|-----------|
| voice-ai-realtime | 8022 | TCP | ESL Outbound (FreeSWITCH → Voice AI) |
| voice-ai-realtime | 8085 | TCP | WebSocket (mod_audio_stream) |
| voice-ai-service | 8100 | TCP | API REST (STT/TTS/LLM) |
| Redis | 6379 | TCP | Cache e Rate Limiting |
| PostgreSQL | 5432 | TCP | Banco FusionPBX (no host) |

---

## 2. Pré-requisitos

### No Servidor

- [x] FusionPBX instalado e funcionando
- [x] FreeSWITCH com ESL habilitado (porta 8021)
- [x] PostgreSQL acessível
- [x] Docker e Docker Compose instalados
- [x] Portas 8022, 8085, 8100 disponíveis

### Verificar ESL

```bash
# Verificar se ESL está habilitado
fs_cli -x "module_exists mod_event_socket"
# Resposta esperada: true

# Verificar porta
ss -tlnp | grep 8021
```

### Chaves de API (pelo menos uma)

- [ ] OpenAI API Key (para GPT-4 Realtime)
- [ ] ElevenLabs API Key (para Conversational AI)
- [ ] Google API Key (para Gemini)

---

## 3. FASE 1: Configurar Variáveis de Ambiente

### 3.1 Copiar template

```bash
cd /caminho/para/voice-ai-ivr
cp .env.example .env
```

### 3.2 Editar .env

```bash
nano .env
```

**Configurações OBRIGATÓRIAS:**

```env
# ============================================================
# DATABASE - Conexão com PostgreSQL do FusionPBX
# ============================================================
DB_HOST=host.docker.internal      # Ou IP do servidor
DB_PORT=5432
DB_NAME=fusionpbx
DB_USER=fusionpbx
DB_PASS=SUA_SENHA_DO_POSTGRES     # ⚠️ OBRIGATÓRIO

# ============================================================
# ESL - Conexão com FreeSWITCH
# ============================================================
ESL_HOST=host.docker.internal     # Ou IP do FreeSWITCH
ESL_PORT=8021
ESL_PASSWORD=ClueCon              # ⚠️ Altere se mudou no FreeSWITCH

# ============================================================
# AI PROVIDERS - Pelo menos um é necessário
# ============================================================
# ElevenLabs (Recomendado para Realtime)
ELEVENLABS_API_KEY=sk_xxxxxxxxxxxxxxxxxxxxxxxx

# OpenAI (GPT-4 Realtime ou Whisper)
OPENAI_API_KEY=sk-xxxxxxxxxxxxxxxxxxxxxxxx

# Google (Gemini)
GOOGLE_API_KEY=AIzaxxxxxxxxxxxxxxxxxxxxxxxx

# ============================================================
# MODO DE ÁUDIO
# ============================================================
# websocket = Via mod_audio_stream (mais simples)
# rtp = Via ESL + RTP direto (menor latência)
AUDIO_MODE=rtp
```

---

## 4. FASE 2: Iniciar Containers Docker

### 4.1 Build e Start

```bash
cd /caminho/para/voice-ai-ivr

# Build das imagens
docker compose build

# Iniciar containers
docker compose up -d
```

### 4.2 Verificar Status

```bash
# Ver logs
docker compose logs -f voice-ai-realtime

# Verificar se iniciou corretamente
docker compose ps
```

**Saída esperada:**
```
voice-ai-realtime  | 2026-01-17 10:42:22 - Voice AI Realtime Server
voice-ai-realtime  | 2026-01-17 10:42:22 - Mode: RTP
voice-ai-realtime  | 2026-01-17 10:42:23 - Starting ESL server on 0.0.0.0:8022
voice-ai-realtime  | 2026-01-17 10:42:23 - Successfully bound to port 8022
```

### 4.3 Testar Conectividade

```bash
# Do servidor, testar ESL
telnet localhost 8022
# Deve conectar (CTRL+] depois quit para sair)

# Testar API
curl http://localhost:8100/health
# {"status":"ok"}
```

---

## 5. FASE 3: Executar Migrations no FusionPBX

### 5.1 Copiar migrations

```bash
# No servidor do FusionPBX
cd /caminho/para/voice-ai-ivr/database/migrations

# Listar migrations
ls -la *.sql
```

### 5.2 Executar migrations na ordem

```bash
# Conectar ao PostgreSQL do FusionPBX
sudo -u postgres psql fusionpbx

# OU com senha
psql -h localhost -U fusionpbx -d fusionpbx
```

**Executar cada migration:**

```sql
-- 1. Tabelas principais
\i 001_create_voice_secretaries.sql
\i 002_create_voice_ai_providers.sql
\i 003_create_voice_transfer_destinations.sql

-- 2. Transfer Rules
\i 004_create_voice_transfer_rules.sql

-- 3. Configurações de domínio
\i 010_create_voice_secretary_settings.sql

-- 4. Time Conditions (Business Hours)
\i 015_add_time_condition_to_secretaries.sql

-- 5. Fallback Options
\i 016_add_fallback_options.sql

-- 6. OmniPlay Integration
\i 017_create_omniplay_integration_tables.sql

-- Verificar
\dt v_voice_*
```

**Saída esperada:**
```
               List of relations
 Schema |             Name              | Type  
--------+-------------------------------+-------
 public | v_voice_ai_providers          | table
 public | v_voice_omniplay_cache        | table
 public | v_voice_omniplay_settings     | table
 public | v_voice_secretaries           | table
 public | v_voice_secretary_settings    | table
 public | v_voice_transfer_destinations | table
 public | v_voice_transfer_rules        | table
```

---

## 6. FASE 4: Instalar App no FusionPBX

### 6.1 Copiar arquivos do app

```bash
# Copiar app para FusionPBX
cp -r /caminho/para/voice-ai-ivr/fusionpbx-app/voice_secretary /var/www/fusionpbx/app/

# Ajustar permissões
chown -R www-data:www-data /var/www/fusionpbx/app/voice_secretary
chmod -R 755 /var/www/fusionpbx/app/voice_secretary
```

### 6.2 Registrar menu no FusionPBX

1. Acesse FusionPBX como **superadmin**
2. Vá em **Advanced → Upgrade → Menu Manager**
3. Clique em **+ Add**
4. Preencha:
   - **Menu Name:** `voice_secretary`
   - **Menu Title:** `Voice Secretary`
   - **Menu Parent:** `apps` ou `status`
5. Salve

### 6.3 Limpar cache

```bash
rm -rf /var/cache/fusionpbx/*
```

### 6.4 Acessar

Navegue até: **FusionPBX → Applications → Voice Secretary**

---

## 7. FASE 5: Criar Dialplan

### 7.1 Via Interface FusionPBX (Recomendado)

1. Acesse **Dialplan → Dialplan Manager**
2. Clique em **+ Add**
3. Preencha:

| Campo | Valor |
|-------|-------|
| **Name** | `voice_ai_esl_8000` |
| **Number** | `8000` |
| **Context** | `${domain_name}` |
| **Order** | `5` |
| **Enabled** | `true` |
| **Continue** | `false` ⚠️ CRÍTICO |
| **Description** | `Voice AI ESL - Secretária Virtual` |

4. Na seção **Dialplan Details**, adicione na ordem:

| Tag | Type | Data |
|-----|------|------|
| condition | field | `destination_number` |
| condition | expression | `^8000$` |
| action | set | `domain_uuid=${domain_uuid}` |
| action | set | `secretary_uuid=COLE_UUID_AQUI` |
| action | set | `absolute_codec_string=PCMU` |
| action | answer | *(vazio)* |
| action | socket | `127.0.0.1:8022 async full` |

5. Clique em **Save**

### 7.2 Recarregar FreeSWITCH

```bash
# Limpar cache FusionPBX
rm -rf /var/cache/fusionpbx/*

# Recarregar dialplan
fs_cli -x "reloadxml"

# Verificar se carregou
fs_cli -x "show dialplan" | grep voice_ai
```

---

## 8. FASE 6: Configurar Secretária Virtual

### 8.1 Criar Secretária

1. Acesse **Voice Secretary** no FusionPBX
2. Clique em **+ Add Secretary**
3. Preencha:

| Campo | Valor |
|-------|-------|
| **Nome** | `Assistente Virtual` |
| **Extension** | `8000` (mesmo do dialplan) |
| **Processing Mode** | `Realtime` |
| **Provider** | ElevenLabs ou OpenAI |
| **Voice** | Escolha uma voz |
| **System Prompt** | Instruções para a IA |
| **Greeting** | Mensagem de boas-vindas |

4. **Salve** e anote o **UUID** gerado

### 8.2 Atualizar Dialplan com UUID

Volte ao dialplan criado e substitua `COLE_UUID_AQUI` pelo UUID real.

### 8.3 Configurar Provider de IA

1. Na aba **AI Providers**, clique em **+ Add**
2. Configure:

**Para ElevenLabs:**
```
Provider Type: realtime
Provider Name: elevenlabs
API Key: sk_xxxxxxxxxxxxxxxxxxxxxxxx
Agent ID: (opcional, se usar agente pré-configurado)
```

**Para OpenAI:**
```
Provider Type: realtime
Provider Name: openai
API Key: sk-xxxxxxxxxxxxxxxxxxxxxxxx
Model: gpt-4o-realtime-preview
```

---

## 9. FASE 7: Testar Sistema

### 9.1 Teste de Chamada

1. Faça uma chamada para o ramal **8000**
2. Aguarde a saudação da IA
3. Converse normalmente

### 9.2 Verificar Logs

```bash
# Logs do Voice AI Realtime
docker compose logs -f voice-ai-realtime

# Logs do FreeSWITCH
tail -f /var/log/freeswitch/freeswitch.log
```

### 9.3 Checklist de Validação

- [ ] Chamada conecta ao ramal 8000
- [ ] Áudio da IA é reproduzido
- [ ] IA responde às perguntas
- [ ] Transferência funciona (se configurada)
- [ ] Callback funciona (se configurado)

---

## 10. Troubleshooting

### Problema: "Connection Refused" na porta 8022

**Causa:** Container não está rodando ou porta não exposta.

**Solução:**
```bash
docker compose ps
docker compose up -d voice-ai-realtime
ss -tlnp | grep 8022
```

### Problema: Chamada cai imediatamente

**Causa:** Dialplan com `continue="true"` ou sem `answer`.

**Solução:** Verificar se `continue="false"` e se tem a action `answer`.

### Problema: Sem áudio da IA

**Causa:** API Key inválida ou provider não configurado.

**Solução:**
```bash
# Verificar logs
docker compose logs voice-ai-realtime | grep -i error

# Testar API key
curl -H "Authorization: Bearer $ELEVENLABS_API_KEY" https://api.elevenlabs.io/v1/user
```

### Problema: "Database connection failed"

**Causa:** PostgreSQL não acessível do container.

**Solução:**
```bash
# Verificar se DB aceita conexões remotas
psql -h localhost -U fusionpbx -d fusionpbx -c "SELECT 1"

# Verificar pg_hba.conf
cat /etc/postgresql/*/main/pg_hba.conf | grep -v "^#"
```

### Problema: ESL "Authentication Failed"

**Causa:** Senha ESL diferente da configurada.

**Solução:**
```bash
# Verificar senha no FreeSWITCH
cat /etc/freeswitch/autoload_configs/event_socket.conf.xml | grep password

# Atualizar .env
ESL_PASSWORD=senha_correta

# Reiniciar container
docker compose restart voice-ai-realtime
```

---

## 📞 Suporte

- **Logs detalhados:** `docker compose logs -f`
- **Status dos serviços:** `docker compose ps`
- **Documentação completa:** `/docs/` neste repositório

---

## ✅ Checklist Final

- [ ] .env configurado com credenciais corretas
- [ ] Containers rodando (`docker compose ps`)
- [ ] Migrations executadas no PostgreSQL
- [ ] App instalado no FusionPBX
- [ ] Dialplan criado e recarregado
- [ ] Secretária configurada com UUID
- [ ] Provider de IA configurado com API Key
- [ ] Teste de chamada bem-sucedido
