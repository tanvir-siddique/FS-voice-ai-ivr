# 📦 Integração MinIO - Voice AI ↔ OmniPlay

Este documento descreve a integração do Voice AI com o MinIO compartilhado do OmniPlay para armazenamento de gravações de chamadas.

## 🏗️ Arquitetura

```
┌─────────────────────────────────────────────────────────────────┐
│                    SERVIDOR VOICE AI                            │
│                                                                 │
│  1. Chamada termina                                             │
│  2. HandoffHandler coleta áudio da sessão                       │
│  3. MinioUploader faz upload para bucket voice-recordings       │
│  4. POST /api/tickets/realtime-handoff com recording_url        │
└───────────────────────────┬─────────────────────────────────────┘
                            │
                            │ Upload direto + HTTPS
                            ▼
┌─────────────────────────────────────────────────────────────────┐
│                    MINIO COMPARTILHADO                          │
│                                                                 │
│  Endpoint: storage.netplay.net.br                               │
│  Bucket:   voice-recordings                                     │
│                                                                 │
│  Estrutura:                                                     │
│  └── voice-recordings/                                          │
│      └── company_{id}/                                          │
│          └── voice/                                             │
│              └── {YYYY}/{MM}/{DD}/                              │
│                  └── {call_uuid}.mp3                            │
└───────────────────────────┬─────────────────────────────────────┘
                            │
                            │ URL pública
                            ▼
┌─────────────────────────────────────────────────────────────────┐
│                    SERVIDOR OMNIPLAY                            │
│                                                                 │
│  5. VoiceHandoffService recebe recording_url                    │
│  6. Cria Message com mediaUrl no ticket                         │
│  7. Atendente ouve gravação diretamente via URL                 │
└─────────────────────────────────────────────────────────────────┘
```

## ⚙️ Configuração

### Servidor Voice AI (.env ou docker-compose)

```bash
# MinIO - Storage compartilhado com OmniPlay
MINIO_ENDPOINT=storage.netplay.net.br
MINIO_ACCESS_KEY=<chave_de_acesso>
MINIO_SECRET_KEY=<chave_secreta>
MINIO_BUCKET=voice-recordings
MINIO_USE_SSL=true
MINIO_REGION=us-east-1
MINIO_PUBLIC_URL=https://storage.netplay.net.br

# OmniPlay Integration
OMNIPLAY_API_URL=https://omniplay.netplay.net.br
VOICE_AI_SERVICE_TOKEN=<token_compartilhado>
```

### Servidor OmniPlay (.env)

```bash
# Token para autenticação do Voice AI
VOICE_AI_SERVICE_TOKEN=<mesmo_token_do_voice_ai>
```

## 🔐 Criando Credenciais no MinIO

### Via MinIO Console (UI)

1. Acesse https://storage.netplay.net.br/
2. Faça login como admin
3. Vá em **Identity** → **Service Accounts**
4. Clique em **Create Service Account**
5. Configure:
   - Description: `voice-ai-realtime`
   - Access Key: (auto-gerado ou personalizado)
   - Secret Key: (auto-gerado)
6. Copie as chaves geradas

### Via MinIO CLI (mc)

```bash
# Configurar alias
mc alias set omniplay https://storage.netplay.net.br ADMIN_ACCESS_KEY ADMIN_SECRET_KEY

# Criar bucket
mc mb omniplay/voice-recordings

# Criar usuário de serviço
mc admin user add omniplay voice-ai-service SUA_SENHA_FORTE

# Criar política de acesso
cat > /tmp/voice-ai-policy.json << 'EOF'
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": [
        "s3:PutObject",
        "s3:GetObject",
        "s3:ListBucket"
      ],
      "Resource": [
        "arn:aws:s3:::voice-recordings",
        "arn:aws:s3:::voice-recordings/*"
      ]
    }
  ]
}
EOF

# Aplicar política
mc admin policy add omniplay voice-ai-policy /tmp/voice-ai-policy.json
mc admin policy set omniplay voice-ai-policy user=voice-ai-service
```

## 📁 Estrutura de Arquivos

```
voice-recordings/
├── company_1/
│   ├── voice/
│   │   └── 2026/
│   │       └── 01/
│   │           └── 15/
│   │               ├── abc-123-def-456.mp3
│   │               └── ghi-789-jkl-012.mp3
│   └── transcripts/
│       └── 2026/
│           └── 01/
│               └── 15/
│                   ├── abc-123-def-456.txt
│                   └── ghi-789-jkl-012.txt
├── company_2/
│   └── ...
```

## 🔄 Fluxo de Upload

```python
# voice-ai-ivr/voice-ai-service/realtime/handlers/handoff.py

async def initiate_handoff(self, ...):
    # 1. Coleta áudio da sessão (se disponível)
    audio_data = session.get_recording_buffer()
    
    # 2. Faz upload para MinIO
    recording_url = await self.upload_recording(audio_data)
    # → https://storage.netplay.net.br/voice-recordings/company_5/voice/2026/01/15/abc-123.mp3
    
    # 3. Envia para OmniPlay com a URL
    await self.create_fallback_ticket(
        ...,
        recording_url=recording_url
    )
```

## 📊 Metadados Armazenados

Cada arquivo tem metadados S3:

| Header | Valor | Descrição |
|--------|-------|-----------|
| `x-amz-meta-call-uuid` | `abc-123-def-456` | UUID da chamada |
| `x-amz-meta-company-id` | `5` | ID da empresa (multi-tenant) |
| `x-amz-meta-domain-uuid` | `xyz-789` | UUID do domain FusionPBX |
| `x-amz-meta-secretary-uuid` | `sec-456` | UUID da secretária |
| `x-amz-meta-uploaded-at` | `2026-01-15T12:30:00Z` | Data do upload |

## 🧪 Testando a Integração

### 1. Verificar conectividade

```bash
# No servidor Voice AI
curl -I https://storage.netplay.net.br/minio/health/live
```

### 2. Testar upload via Python

```python
from realtime.utils.minio_uploader import get_minio_uploader

uploader = get_minio_uploader()
print(f"MinIO available: {uploader.is_available}")

# Testar upload
result = uploader.upload_audio(
    audio_data=b"test audio data",
    call_uuid="test-123",
    company_id=1
)
print(f"Upload result: {result}")
```

### 3. Verificar no MinIO Console

1. Acesse https://storage.netplay.net.br/
2. Navegue até o bucket `voice-recordings`
3. Verifique se o arquivo de teste foi criado

## 🚨 Troubleshooting

### Erro: "MINIO_ACCESS_KEY or MINIO_SECRET_KEY not configured"

**Causa:** Variáveis de ambiente não definidas.

**Solução:**
```bash
# Verificar variáveis
docker exec voice-ai-service env | grep MINIO

# Reiniciar com variáveis
docker-compose down && docker-compose up -d
```

### Erro: "S3 Access Denied"

**Causa:** Permissões insuficientes no bucket.

**Solução:**
```bash
# Verificar política
mc admin policy info omniplay voice-ai-policy

# Recriar política com permissões corretas
```

### Erro: "SSL Certificate Error"

**Causa:** Certificado SSL não confiável.

**Solução:**
```bash
# Usar MINIO_USE_SSL=false para dev
# Ou adicionar CA ao container
```

## 📈 Monitoramento

### Métricas Prometheus (futuro)

```
# Uploads bem-sucedidos
voice_ai_minio_uploads_total{status="success"}

# Uploads com falha
voice_ai_minio_uploads_total{status="error"}

# Tamanho médio dos arquivos
voice_ai_minio_upload_bytes_sum / voice_ai_minio_upload_bytes_count
```

## 🔗 Referências

- [MinIO Python SDK](https://min.io/docs/minio/linux/developers/python/minio-py.html)
- [MinIO Admin Guide](https://min.io/docs/minio/linux/administration/identity-access-management.html)
- [OmniPlay MinioStorageService](../../backend/src/services/MinioStorageService.ts)
