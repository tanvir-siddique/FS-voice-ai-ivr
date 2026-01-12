# Realtime AI Providers
# Factory Pattern conforme .context/agents/backend-specialist.md e
# openspec/changes/voice-ai-realtime/design.md (Decision 4)

from .base import BaseRealtimeProvider, ProviderEvent, ProviderEventType, RealtimeConfig
from .factory import RealtimeProviderFactory
from .openai_realtime import OpenAIRealtimeProvider
from .elevenlabs_conv import ElevenLabsConversationalProvider
from .gemini_live import GeminiLiveProvider
from .custom_pipeline import CustomPipelineProvider

__all__ = [
    # Base
    "BaseRealtimeProvider",
    "ProviderEvent",
    "ProviderEventType",
    "RealtimeConfig",
    "RealtimeProviderFactory",
    # Providers
    "OpenAIRealtimeProvider",
    "ElevenLabsConversationalProvider",
    "GeminiLiveProvider",
    "CustomPipelineProvider",
]
