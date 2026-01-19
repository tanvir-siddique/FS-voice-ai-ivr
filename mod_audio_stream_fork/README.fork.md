# mod_audio_stream Fork - G.711 Support

Este é um fork do [mod_audio_stream](https://github.com/amigniter/mod_audio_stream) com suporte adicionado para streaming de áudio G.711 (PCMU/PCMA).

## Modificações

### Nova funcionalidade: Formato de áudio G.711

O módulo original envia áudio apenas em formato L16 (Linear PCM 16-bit). Este fork adiciona suporte para enviar áudio codificado em G.711 µ-law (PCMU) ou A-law (PCMA).

### Uso

```bash
# Sintaxe original (L16 PCM)
uuid_audio_stream <uuid> start <wss-url> mono 8k

# Nova sintaxe com formato G.711
uuid_audio_stream <uuid> start <wss-url> mono 8k pcmu [metadata]
uuid_audio_stream <uuid> start <wss-url> mono 8k pcma [metadata]

# Formato L16 explícito (comportamento padrão)
uuid_audio_stream <uuid> start <wss-url> mono 8k l16 [metadata]
```

### Formatos suportados

| Formato | Aliases | Descrição | Sample Rate |
|---------|---------|-----------|-------------|
| `l16` | `linear`, `pcm` | Linear PCM 16-bit (padrão) | 8k, 16k |
| `pcmu` | `ulaw`, `mulaw` | G.711 µ-law | 8k apenas |
| `pcma` | `alaw` | G.711 A-law | 8k apenas |

**Nota**: G.711 só suporta 8000 Hz. Se tentar usar G.711 com sample rate diferente de 8k, o comando retornará erro.

### Benefícios do G.711

- **Menor banda**: G.711 usa 64 kbps vs 128 kbps do L16 @ 8kHz
- **Compatibilidade nativa**: OpenAI Realtime API aceita `audio/pcmu`
- **Menor latência**: Evita conversão no receptor

## Arquivos modificados

- `mod_audio_stream.h` - Adicionadas constantes de formato e campos no struct
- `mod_audio_stream.c` - Parsing do parâmetro format
- `audio_streamer_glue.h` - Atualizada assinatura da função init
- `audio_streamer_glue.cpp` - Inicialização do codec G.711 e encoding

## Compilação

```bash
# Dependências
apt-get install libfreeswitch-dev libspeexdsp-dev cmake

# Compilar
mkdir build && cd build
cmake ..
make

# Instalar
cp mod_audio_stream.so /usr/lib/freeswitch/mod/
```

## Licença

MIT License (mesma do projeto original)

## Autor das modificações

Fork criado para o projeto OmniPlay Voice AI - NetPlay
