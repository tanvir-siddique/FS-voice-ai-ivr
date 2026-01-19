# Voice AI FAQ e Troubleshooting

## FAQ

### O caller nao ouve o audio do agent. O que verificar?
- Confirmar `mod_audio_stream v1.0.3+` carregado (`fs_cli -x "module_exists mod_audio_stream"`).
- Verificar se o bridge envia `rawAudio` header e chunks binarios.
- Conferir se o dialplan da extensao 8000 esta com `continue=false`.

### O ElevenLabs retorna policy violation (1008).
- Remover overrides de `voice_id`, `first_message`, `prompt`.
- Usar `use_agent_config=true` no provider.

### O agent recebe audio mas o caller nao ouve nada.
- Conferir logs do FreeSWITCH (`mod_audio_stream::play`).
- Testar `uuid_broadcast` com um arquivo `.r16` gerado.
- Se necessario, forcar `FS_PLAYBACK_MODE=streamAudio` no bridge.

### Qual sample rate usar?
- FreeSWITCH: 16kHz
- ElevenLabs: 16kHz
- OpenAI Realtime: 24kHz (resample necessario)
- Gemini Live: 24kHz output (resample necessario)

### Posso usar apenas TTS/STT locais?
Sim. O sistema suporta providers locais (Whisper/Piper/Ollama) por dominio.

## Troubleshooting Rapido

### Verificar conexao WebSocket
```bash
docker compose logs -f voice-ai-realtime | grep -i "connection"
```

### Verificar playback do mod_audio_stream
```bash
tail -f /var/log/freeswitch/freeswitch.log | grep -i "mod_audio_stream"
```

### Forcar fallback para streamAudio
```bash
export FS_PLAYBACK_MODE=streamAudio
export FS_STREAMAUDIO_FALLBACK=true
```

### Confirmar dialplan correto
```bash
fs_cli -x "show dialplan 8000"
```

### Teste direto com arquivo r16
```bash
fs_cli -x "uuid_broadcast <UUID> /tmp/test.r16 aleg"
```

## Variaveis uteis no bridge

- `FS_PLAYBACK_MODE=rawAudio|streamAudio`
- `FS_STREAMAUDIO_FALLBACK=true|false`
- `FS_STREAMAUDIO_FRAME_MS=1000` (recomendado: 1000ms+ para evitar áudio picotado)
