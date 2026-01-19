# Design: Migração para G.711 Híbrido/Nativo

## Context

O Voice AI atualmente usa a seguinte cadeia de áudio:

```
FreeSWITCH (L16 PCM 8kHz) → Resample (8→24kHz) → OpenAI (PCM16 24kHz)
OpenAI (PCM16 24kHz) → Resample (24→8kHz) → FreeSWITCH (L16 PCM 8kHz)
```

**Descoberta importante**: mod_audio_stream v1.0.3 tem suporte parcial:
- **Envio (FS → WS)**: Apenas L16 PCM (não suporta G.711)
- **Recebimento (WS → FS)**: Suporta PCMU/PCMA nativo (otimizado)

Ref: https://github.com/amigniter/mod_audio_stream/issues/72

## Goals

- Fase 1: Otimizar playback com G.711 nativo (output)
- Fase 2: Eliminar resample de input com conversão no Python
- Reduzir latência total em ~10-15ms
- Manter compatibilidade com mod_audio_stream v1.0.3

## Non-Goals

- Modificar mod_audio_stream (complexo, fora de escopo)
- Suportar outros codecs (A-law, Opus)
- Alterar fluxo de transferência/handoff

## Decisions

### Decision 1: G.711 Híbrido (Fase 1)

Usar G.711 apenas no output (OpenAI → FreeSWITCH).

```python
# session.update para OpenAI GA
{
    "type": "session.update",
    "session": {
        "audio": {
            "input": {
                "format": {"type": "audio/pcm", "rate": 24000}  # Mantém PCM
            },
            "output": {
                "format": {"type": "audio/pcmu"}  # G.711 μ-law
            }
        }
    }
}
```

Fluxo:
```
FreeSWITCH (L16 8kHz) → Resample (8→24kHz) → OpenAI (PCM 24kHz)
OpenAI (G.711 8kHz) → mod_audio_stream (direto, sem transcoding) → FreeSWITCH
```

### Decision 2: Conversão L16→G.711 no Python (Fase 2)

Usar `audioop.lin2ulaw()` para converter L16 PCM para G.711 no Voice AI:

```python
import audioop

# L16 PCM 8kHz → G.711 µ-law
def pcm_to_ulaw(pcm_bytes: bytes) -> bytes:
    return audioop.lin2ulaw(pcm_bytes, 2)  # 2 = 16-bit

# G.711 µ-law → L16 PCM (para AEC)
def ulaw_to_pcm(ulaw_bytes: bytes) -> bytes:
    return audioop.ulaw2lin(ulaw_bytes, 2)
```

Fluxo final:
```
FreeSWITCH (L16 8kHz) → Python (L16→G.711) → OpenAI (G.711 8kHz)
OpenAI (G.711 8kHz) → mod_audio_stream (direto) → FreeSWITCH
```

### Decision 3: Manter L16 para AEC

O Speex AEC requer L16 PCM. Converter apenas para processamento:

```python
# Input flow com AEC
incoming_l16 = audio_from_freeswitch  # L16 8kHz
clean_l16 = aec.process(incoming_l16)  # AEC em L16
outgoing_ulaw = pcm_to_ulaw(clean_l16)  # Converter para G.711
send_to_openai(outgoing_ulaw)  # Enviar G.711
```

### Decision 4: Adaptar para 8kHz

Com G.711, sample rate é fixo em 8kHz:
- `sample_rate`: 8000
- `frame_size`: 160 samples (20ms @ 8kHz)
- `bytes_per_frame`: 160 bytes (G.711) ou 320 bytes (L16)

### Decision 5: Flag de configuração

```python
@dataclass
class RealtimeSessionConfig:
    # Audio format
    audio_format: str = "g711"  # "g711" ou "pcm16"
    # Fase de implementação
    # - "hybrid": G.711 só no output
    # - "full": G.711 input e output
    g711_mode: str = "hybrid"
```

## Alternatives Considered

### Alternative 1: Contribuir patch para mod_audio_stream
- Prós: Solução definitiva
- Contras: Tempo de desenvolvimento, aprovação incerta
- Decisão: Considerar após Fase 2 funcionar

### Alternative 2: mod_audio_fork
- Prós: Pode capturar G.711 nativo
- Contras: Abandonado, problemas de compatibilidade
- Rejeitado: Risco alto para produção

### Alternative 3: Media bug customizado em C
- Prós: Zero overhead
- Contras: Complexidade alta, manutenção difícil
- Rejeitado: Custo/benefício ruim

## Risks / Trade-offs

| Risco | Impacto | Mitigação |
|-------|---------|-----------|
| CPU da conversão Python | Baixo | audioop é C puro, ~0.1ms |
| Compatibilidade G.711 output | Baixo | Testar com canal PCMU |
| AEC com sample rate diferente | Médio | Manter L16 para AEC |

## Migration Plan

### Fase 1: G.711 Híbrido (output only)
1. Modificar session.update para output G.711
2. Testar playback sem transcoding
3. Medir latência

### Fase 2: G.711 Completo
1. Adicionar conversão L16→G.711 no input
2. Ajustar AEC para 8kHz
3. Remover resampler 8k↔24k
4. Testar end-to-end

### Rollback
- Flag `audio_format: "pcm16"` restaura comportamento atual

## Open Questions

1. ~~Qual versão do mod_audio_stream?~~ → v1.0.3 (confirmado)
2. ~~Suporta G.711 no envio?~~ → Não, apenas L16
3. ~~Suporta G.711 no recebimento?~~ → Sim, otimizado
4. A OpenAI cobra diferente por G.711 vs PCM16?
5. Deepgram e Gemini também suportam G.711?
