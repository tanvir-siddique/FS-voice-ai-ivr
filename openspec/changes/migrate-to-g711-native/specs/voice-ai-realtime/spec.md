# Voice AI Realtime - G.711 Hybrid/Native Support

## ADDED Requirements

### Requirement: G.711 Hybrid Mode

O sistema DEVE suportar modo híbrido onde G.711 é usado apenas no output (OpenAI → FreeSWITCH).

#### Scenario: Modo híbrido ativado
- **GIVEN** uma secretária com `audio_format = "g711"` e `g711_mode = "hybrid"`
- **WHEN** uma chamada é iniciada
- **THEN** o input para OpenAI DEVE usar PCM16 24kHz
- **AND** o output da OpenAI DEVE usar G.711 μ-law 8kHz
- **AND** o mod_audio_stream DEVE reproduzir G.711 sem transcoding

#### Scenario: Playback otimizado
- **GIVEN** uma chamada em modo híbrido
- **WHEN** a OpenAI envia áudio de resposta
- **THEN** o áudio G.711 DEVE ser enviado direto ao mod_audio_stream
- **AND** nenhuma conversão DEVE ser feita no Voice AI

### Requirement: G.711 Full Mode

O sistema DEVE suportar modo completo onde G.711 é usado em input e output.

#### Scenario: Modo completo ativado
- **GIVEN** uma secretária com `audio_format = "g711"` e `g711_mode = "full"`
- **WHEN** uma chamada é iniciada
- **THEN** o áudio L16 do FreeSWITCH DEVE ser convertido para G.711 no Python
- **AND** o input para OpenAI DEVE usar `audio/pcmu`
- **AND** o output da OpenAI DEVE usar `audio/pcmu`
- **AND** o resample 8kHz↔24kHz DEVE ser eliminado

#### Scenario: Conversão L16→G.711
- **GIVEN** uma chamada em modo completo
- **WHEN** áudio L16 PCM é recebido do FreeSWITCH
- **THEN** o Voice AI DEVE converter para G.711 usando `audioop.lin2ulaw()`
- **AND** a latência de conversão DEVE ser < 1ms

### Requirement: Echo Cancellation com G.711

O Echo Canceller DEVE funcionar corretamente com áudio em modo G.711.

#### Scenario: AEC em modo completo
- **GIVEN** uma chamada em modo G.711 completo
- **WHEN** o caller usa viva-voz
- **THEN** o áudio L16 original DEVE ser usado para AEC (Speex requer L16)
- **AND** a conversão para G.711 DEVE ocorrer após o AEC
- **AND** o eco DEVE ser removido antes de enviar à OpenAI

#### Scenario: AEC em modo híbrido
- **GIVEN** uma chamada em modo híbrido
- **WHEN** o caller usa viva-voz
- **THEN** o AEC DEVE funcionar normalmente (input é L16)

### Requirement: Barge-in Detection com G.711

O sistema de detecção de interrupção DEVE funcionar em todos os modos.

#### Scenario: Barge-in em modo completo
- **GIVEN** uma chamada em modo G.711 completo
- **WHEN** o caller interrompe o agente
- **THEN** o cálculo de RMS DEVE usar áudio L16 (antes da conversão)
- **AND** a interrupção DEVE ser detectada corretamente

## MODIFIED Requirements

### Requirement: Audio Format Configuration

O sistema DEVE permitir configurar o formato de áudio por secretária.

#### Scenario: Configuração de formato e modo
- **GIVEN** uma secretária no FusionPBX
- **WHEN** o administrador configura áudio
- **THEN** `audio_format` DEVE aceitar: `g711`, `pcm16`
- **AND** `g711_mode` DEVE aceitar: `hybrid`, `full`
- **AND** o padrão DEVE ser `audio_format=pcm16` para compatibilidade

#### Scenario: Migração de secretárias existentes
- **GIVEN** secretárias existentes sem configuração de áudio
- **WHEN** o sistema é atualizado
- **THEN** o comportamento DEVE ser `pcm16` (compatibilidade)
- **AND** nenhuma mudança de comportamento DEVE ocorrer
