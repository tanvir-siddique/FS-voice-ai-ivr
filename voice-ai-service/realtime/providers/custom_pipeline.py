"""
Custom Pipeline Provider - Low-cost local/hybrid approach.

Referências:
- openspec/changes/voice-ai-realtime/design.md: Decision 4
- .context/docs/architecture.md: Providers locais

Pipeline:
- STT: Deepgram Nova (streaming) ou Whisper local
- LLM: Groq (Llama) ou Ollama local
- TTS: Piper local

Ideal para:
- Redução de custos
- Privacidade de dados
- Ambientes offline
"""

import asyncio
import logging
from typing import Any, AsyncIterator, Dict, Optional
import io

from .base import (
    BaseRealtimeProvider,
    ProviderEvent,
    ProviderEventType,
    RealtimeConfig,
)

logger = logging.getLogger(__name__)


class CustomPipelineProvider(BaseRealtimeProvider):
    """
    Provider custom que combina múltiplos serviços:
    - STT: Deepgram (streaming) ou Whisper local
    - LLM: Groq (ultra-rápido) ou Ollama local
    - TTS: Piper local ou Coqui
    
    Vantagens:
    - Menor custo por minuto
    - Flexibilidade de providers
    - Suporte a ambiente offline
    
    Sample rates:
    - Input: 16kHz PCM16
    - Output: 16kHz PCM16 (depende do TTS)
    """
    
    def __init__(self, credentials: Dict[str, Any], config: RealtimeConfig):
        super().__init__(credentials, config)
        
        # Configuração de providers
        self.stt_provider = credentials.get("stt_provider", "deepgram")
        self.llm_provider = credentials.get("llm_provider", "groq")
        self.tts_provider = credentials.get("tts_provider", "piper")
        
        # Credenciais
        self.deepgram_key = credentials.get("deepgram_key")
        self.groq_key = credentials.get("groq_key")
        
        # Clients
        self._stt_client = None
        self._llm_client = None
        self._tts = None
        
        # Buffers
        self._audio_buffer = b""
        self._text_buffer = ""
        
        # VAD
        self._vad = None
        self._is_speaking = False
        
        # Event queue
        self._event_queue: asyncio.Queue[ProviderEvent] = asyncio.Queue()
        self._process_task: Optional[asyncio.Task] = None
        
        # Conversation
        self._messages: list = []
    
    @property
    def name(self) -> str:
        return "custom_pipeline"
    
    @property
    def input_sample_rate(self) -> int:
        return 16000
    
    @property
    def output_sample_rate(self) -> int:
        return 16000
    
    async def connect(self) -> None:
        """Inicializa os componentes do pipeline."""
        if self._connected:
            return
        
        # Inicializar VAD (Silero)
        await self._init_vad()
        
        # Inicializar STT
        await self._init_stt()
        
        # Inicializar LLM
        await self._init_llm()
        
        # Inicializar TTS
        await self._init_tts()
        
        self._connected = True
        
        logger.info("Custom pipeline initialized", extra={
            "domain_uuid": self.config.domain_uuid,
            "stt": self.stt_provider,
            "llm": self.llm_provider,
            "tts": self.tts_provider,
        })
    
    async def _init_vad(self) -> None:
        """Inicializa Silero VAD."""
        try:
            import torch
            
            model, utils = torch.hub.load(
                repo_or_dir='snakers4/silero-vad',
                model='silero_vad',
                force_reload=False
            )
            self._vad = model
            logger.info("Silero VAD initialized")
        except Exception as e:
            logger.warning(f"VAD not available: {e}")
    
    async def _init_stt(self) -> None:
        """Inicializa STT."""
        if self.stt_provider == "deepgram" and self.deepgram_key:
            from deepgram import DeepgramClient, LiveOptions
            self._stt_client = DeepgramClient(self.deepgram_key)
            logger.info("Deepgram STT initialized")
        else:
            # Whisper local fallback
            try:
                from faster_whisper import WhisperModel
                self._stt_client = WhisperModel("base", device="cpu")
                logger.info("Whisper local STT initialized")
            except ImportError:
                logger.warning("No STT provider available")
    
    async def _init_llm(self) -> None:
        """Inicializa LLM."""
        if self.llm_provider == "groq" and self.groq_key:
            from groq import AsyncGroq
            self._llm_client = AsyncGroq(api_key=self.groq_key)
            logger.info("Groq LLM initialized")
        else:
            # Ollama local fallback
            try:
                import ollama
                self._llm_client = ollama
                logger.info("Ollama local LLM initialized")
            except ImportError:
                logger.warning("No LLM provider available")
    
    async def _init_tts(self) -> None:
        """Inicializa TTS."""
        # Piper local por padrão (mais rápido e leve)
        try:
            from services.tts.piper_local import PiperLocalTTS
            self._tts = PiperLocalTTS({})
            logger.info("Piper local TTS initialized")
        except Exception as e:
            logger.warning(f"Piper TTS not available: {e}")
    
    async def configure(self) -> None:
        """Configura o pipeline."""
        # Adicionar system prompt
        if self.config.system_prompt:
            self._messages = [
                {"role": "system", "content": self.config.system_prompt}
            ]
        
        # Enviar primeira mensagem
        if self.config.first_message:
            audio = await self._synthesize(self.config.first_message)
            if audio:
                await self._event_queue.put(ProviderEvent(
                    type=ProviderEventType.AUDIO_DELTA,
                    data={"audio": audio}
                ))
                await self._event_queue.put(ProviderEvent(
                    type=ProviderEventType.AUDIO_DONE,
                    data={}
                ))
    
    async def send_audio(self, audio_bytes: bytes) -> None:
        """
        Processa áudio do usuário.
        
        Acumula em buffer e processa quando detecta fim de fala.
        """
        if not self._connected:
            return
        
        self._audio_buffer += audio_bytes
        
        # VAD: detectar se está falando
        is_speech = await self._detect_speech(audio_bytes)
        
        if is_speech and not self._is_speaking:
            self._is_speaking = True
            await self._event_queue.put(ProviderEvent(
                type=ProviderEventType.SPEECH_STARTED,
                data={}
            ))
        
        if not is_speech and self._is_speaking:
            self._is_speaking = False
            await self._event_queue.put(ProviderEvent(
                type=ProviderEventType.SPEECH_STOPPED,
                data={}
            ))
            
            # Processar buffer acumulado
            if len(self._audio_buffer) > 3200:  # > 100ms de áudio
                asyncio.create_task(self._process_audio_buffer())
    
    async def _detect_speech(self, audio_bytes: bytes) -> bool:
        """Detecta fala usando VAD."""
        if self._vad is None:
            # Fallback: baseado em energia
            import numpy as np
            samples = np.frombuffer(audio_bytes, dtype=np.int16)
            energy = np.sqrt(np.mean(samples ** 2))
            return energy > 500
        
        try:
            import torch
            import numpy as np
            
            samples = np.frombuffer(audio_bytes, dtype=np.int16).astype(np.float32) / 32768.0
            tensor = torch.from_numpy(samples)
            
            confidence = self._vad(tensor, 16000).item()
            return confidence > self.config.vad_threshold
        except Exception:
            return True
    
    async def _process_audio_buffer(self) -> None:
        """Processa buffer de áudio: STT → LLM → TTS."""
        audio_data = self._audio_buffer
        self._audio_buffer = b""
        
        if len(audio_data) < 3200:
            return
        
        try:
            # 1. STT
            text = await self._transcribe(audio_data)
            if not text:
                return
            
            await self._event_queue.put(ProviderEvent(
                type=ProviderEventType.USER_TRANSCRIPT,
                data={"transcript": text}
            ))
            
            # 2. LLM
            self._messages.append({"role": "user", "content": text})
            response = await self._generate_response()
            if not response:
                return
            
            self._messages.append({"role": "assistant", "content": response})
            
            await self._event_queue.put(ProviderEvent(
                type=ProviderEventType.TRANSCRIPT_DONE,
                data={"transcript": response}
            ))
            
            # 3. TTS
            audio = await self._synthesize(response)
            if audio:
                await self._event_queue.put(ProviderEvent(
                    type=ProviderEventType.AUDIO_DELTA,
                    data={"audio": audio}
                ))
                await self._event_queue.put(ProviderEvent(
                    type=ProviderEventType.AUDIO_DONE,
                    data={}
                ))
            
            await self._event_queue.put(ProviderEvent(
                type=ProviderEventType.RESPONSE_DONE,
                data={}
            ))
            
        except Exception as e:
            logger.error(f"Pipeline processing error: {e}")
    
    async def _transcribe(self, audio_bytes: bytes) -> Optional[str]:
        """STT: Transcreve áudio para texto."""
        if self._stt_client is None:
            return None
        
        try:
            if self.stt_provider == "deepgram":
                # Deepgram sync transcription
                from deepgram import PrerecordedOptions
                
                options = PrerecordedOptions(
                    model="nova-2",
                    language="pt-BR",
                    smart_format=True,
                )
                
                response = await self._stt_client.listen.prerecorded.v("1").transcribe_file(
                    {"buffer": audio_bytes, "mimetype": "audio/raw"},
                    options
                )
                
                return response.results.channels[0].alternatives[0].transcript
            else:
                # Whisper local
                import tempfile
                import soundfile as sf
                import numpy as np
                
                samples = np.frombuffer(audio_bytes, dtype=np.int16)
                
                with tempfile.NamedTemporaryFile(suffix=".wav", delete=True) as f:
                    sf.write(f.name, samples, 16000)
                    segments, _ = self._stt_client.transcribe(f.name, language="pt")
                    return " ".join([s.text for s in segments])
        
        except Exception as e:
            logger.error(f"STT error: {e}")
            return None
    
    async def _generate_response(self) -> Optional[str]:
        """LLM: Gera resposta."""
        if self._llm_client is None:
            return None
        
        try:
            if self.llm_provider == "groq":
                response = await self._llm_client.chat.completions.create(
                    model="llama-3.3-70b-versatile",
                    messages=self._messages,
                    max_tokens=200,
                )
                return response.choices[0].message.content
            else:
                # Ollama local
                response = self._llm_client.chat(
                    model="llama3.2",
                    messages=self._messages,
                )
                return response["message"]["content"]
        
        except Exception as e:
            logger.error(f"LLM error: {e}")
            return None
    
    async def _synthesize(self, text: str) -> Optional[bytes]:
        """TTS: Sintetiza texto para áudio."""
        if self._tts is None:
            return None
        
        try:
            return await self._tts.synthesize(text)
        except Exception as e:
            logger.error(f"TTS error: {e}")
            return None
    
    async def send_text(self, text: str) -> None:
        """Envia texto diretamente."""
        self._messages.append({"role": "user", "content": text})
        
        response = await self._generate_response()
        if response:
            self._messages.append({"role": "assistant", "content": response})
            
            audio = await self._synthesize(response)
            if audio:
                await self._event_queue.put(ProviderEvent(
                    type=ProviderEventType.AUDIO_DELTA,
                    data={"audio": audio}
                ))
    
    async def interrupt(self) -> None:
        """Interrompe processamento atual."""
        self._audio_buffer = b""
    
    async def send_function_result(
        self,
        function_name: str,
        result: Dict[str, Any],
        call_id: Optional[str] = None
    ) -> None:
        """Function calls não suportados no pipeline custom."""
        pass
    
    async def receive_events(self) -> AsyncIterator[ProviderEvent]:
        """Generator de eventos."""
        while self._connected:
            try:
                event = await asyncio.wait_for(self._event_queue.get(), timeout=1.0)
                yield event
                if event.type in (ProviderEventType.SESSION_ENDED, ProviderEventType.ERROR):
                    break
            except asyncio.TimeoutError:
                continue
            except asyncio.CancelledError:
                break
    
    async def disconnect(self) -> None:
        """Encerra pipeline."""
        self._connected = False
        
        if self._process_task:
            self._process_task.cancel()
            try:
                await self._process_task
            except asyncio.CancelledError:
                pass
        
        logger.info("Custom pipeline disconnected", extra={
            "domain_uuid": self.config.domain_uuid
        })
