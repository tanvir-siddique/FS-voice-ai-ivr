# ESL (Event Socket Library) Module
# FreeSWITCH direct integration via greenswitch
#
# Components:
# - server.py: ESL Outbound Server (receives calls from FS)
# - application.py: Voice AI Application handler (RTP mode)
# - event_relay.py: Dual mode event relay (WebSocket + ESL)
# - command_interface.py: Abstração para comandos ESL (Inbound/Outbound)
#
# Referências:
# - https://github.com/EvoluxBR/greenswitch
# - openspec/changes/refactor-esl-rtp-bridge/
# - openspec/changes/dual-mode-esl-websocket/

from .server import ESLOutboundServer, create_server
from .application import VoiceAIApplication
from .event_relay import (
    DualModeEventRelay,
    create_event_relay,
    set_main_asyncio_loop,
    get_main_asyncio_loop,
    # Correlação reversa (sessão → relay)
    notify_session_ended,
    get_relay,
)
from .command_interface import (
    ESLCommandInterface,
    ESLOutboundAdapter,
    ESLInboundAdapter,
    ESLHybridAdapter,
    get_esl_adapter,
)

__all__ = [
    # Server
    "ESLOutboundServer",
    "create_server",
    # RTP Mode
    "VoiceAIApplication",
    # Dual Mode
    "DualModeEventRelay",
    "create_event_relay",
    "set_main_asyncio_loop",
    "get_main_asyncio_loop",
    "notify_session_ended",
    "get_relay",
    # Command Interface (abstração Inbound/Outbound)
    "ESLCommandInterface",
    "ESLOutboundAdapter",
    "ESLInboundAdapter",
    "ESLHybridAdapter",
    "get_esl_adapter",
]
