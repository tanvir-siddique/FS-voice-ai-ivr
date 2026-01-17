"""
ESL Command Interface - Abstração para comandos ESL via Inbound ou Outbound.

Este módulo fornece uma interface unificada para enviar comandos ao FreeSWITCH
independente de estar usando ESL Inbound (AsyncESLClient) ou ESL Outbound
(DualModeEventRelay).

No modo DUAL:
- Comandos são enviados via ESL Outbound (conexão já existente)
- Se Outbound não disponível, fallback para ESL Inbound

No modo WEBSOCKET-only:
- Comandos são enviados via ESL Inbound

Referências:
- voice-ai-ivr/openspec/changes/dual-mode-esl-websocket/L7_REVIEW.md
"""

import logging
import os
from abc import ABC, abstractmethod
from typing import Any, Optional

logger = logging.getLogger(__name__)


class ESLCommandInterface(ABC):
    """Interface abstrata para comandos ESL."""
    
    @property
    @abstractmethod
    def is_connected(self) -> bool:
        """Verifica se está conectado ao FreeSWITCH."""
        pass
    
    @abstractmethod
    async def execute_api(self, command: str) -> Optional[str]:
        """
        Executa comando API.
        
        Args:
            command: Comando FreeSWITCH (ex: "uuid_hold on <uuid>")
            
        Returns:
            Resultado do comando ou None se falhou
        """
        pass
    
    @abstractmethod
    async def uuid_kill(self, uuid: str, cause: str = "NORMAL_CLEARING") -> bool:
        """
        Encerra uma chamada.
        
        Args:
            uuid: UUID da chamada
            cause: Hangup cause
            
        Returns:
            True se sucesso
        """
        pass
    
    @abstractmethod
    async def uuid_hold(self, uuid: str, on: bool = True) -> bool:
        """
        Coloca/retira chamada em espera.
        
        Args:
            uuid: UUID da chamada
            on: True para hold, False para unhold
            
        Returns:
            True se sucesso
        """
        pass
    
    @abstractmethod
    async def uuid_break(self, uuid: str) -> bool:
        """
        Interrompe mídia sendo reproduzida.
        
        Args:
            uuid: UUID da chamada
            
        Returns:
            True se sucesso
        """
        pass
    
    @abstractmethod
    async def uuid_broadcast(self, uuid: str, path: str, leg: str = "aleg") -> bool:
        """
        Reproduz mídia na chamada.
        
        Args:
            uuid: UUID da chamada
            path: Caminho do arquivo ou stream
            leg: Qual perna ("aleg", "bleg", "both")
            
        Returns:
            True se sucesso
        """
        pass
    
    @abstractmethod
    async def uuid_exists(self, uuid: str) -> bool:
        """
        Verifica se uma chamada existe.
        
        Args:
            uuid: UUID da chamada
            
        Returns:
            True se chamada existe
        """
        pass
    
    # Métodos avançados (apenas ESL Inbound)
    # Estes métodos retornam None/False no Outbound adapter
    
    async def originate(self, dial_string: str, app: str, timeout: int = 60, 
                       variables: dict = None) -> Optional[str]:
        """
        Cria nova chamada (B-leg).
        
        Args:
            dial_string: Dial string (ex: "user/1001@domain")
            app: Aplicação a executar (ex: "&park()")
            timeout: Timeout em segundos
            variables: Variáveis de canal
            
        Returns:
            UUID da nova chamada ou None se falhou
        """
        return None
    
    async def uuid_bridge(self, uuid1: str, uuid2: str) -> bool:
        """
        Conecta duas chamadas.
        
        Args:
            uuid1: UUID da primeira chamada
            uuid2: UUID da segunda chamada
            
        Returns:
            True se bridge criado com sucesso
        """
        return False
    
    async def subscribe_events(self, events: list) -> bool:
        """
        Subscreve a eventos ESL.
        
        Args:
            events: Lista de nomes de eventos
            
        Returns:
            True se subscrito com sucesso
        """
        return False
    
    async def wait_for_event(self, event_names: list, uuid: str = None, 
                            timeout: float = 30.0):
        """
        Aguarda um evento específico.
        
        Args:
            event_names: Lista de nomes de eventos
            uuid: UUID para filtrar (opcional)
            timeout: Timeout em segundos
            
        Returns:
            Evento recebido ou None se timeout
        """
        return None


class ESLOutboundAdapter(ESLCommandInterface):
    """
    Adaptador que usa ESL Outbound (DualModeEventRelay) para comandos.
    
    NOTA: Este adaptador é SÍNCRONO por baixo (greenswitch), mas
    expõe interface async para compatibilidade com código asyncio.
    """
    
    def __init__(self, call_uuid: str):
        """
        Args:
            call_uuid: UUID da chamada para obter o relay
        """
        self._call_uuid = call_uuid
        self._relay = None
    
    def _get_relay(self):
        """Obtém relay de forma lazy."""
        if self._relay is None:
            from .event_relay import get_relay
            self._relay = get_relay(self._call_uuid)
        return self._relay
    
    @property
    def is_connected(self) -> bool:
        relay = self._get_relay()
        return relay is not None and relay._connected
    
    async def execute_api(self, command: str) -> Optional[str]:
        relay = self._get_relay()
        if not relay:
            return None
        return relay.execute_api(command)
    
    async def uuid_kill(self, uuid: str, cause: str = "NORMAL_CLEARING") -> bool:
        relay = self._get_relay()
        if not relay:
            return False
        return relay.hangup(cause)
    
    async def uuid_hold(self, uuid: str, on: bool = True) -> bool:
        relay = self._get_relay()
        if not relay:
            return False
        return relay.uuid_hold(on)
    
    async def uuid_break(self, uuid: str) -> bool:
        relay = self._get_relay()
        if not relay:
            return False
        return relay.uuid_break()
    
    async def uuid_broadcast(self, uuid: str, path: str, leg: str = "aleg") -> bool:
        relay = self._get_relay()
        if not relay:
            return False
        return relay.uuid_broadcast(path, leg)
    
    async def uuid_exists(self, uuid: str) -> bool:
        """
        Verifica se UUID existe.
        
        NOTA: ESL Outbound não suporta api(), então sempre retorna False.
        Fallback para ESL Inbound será usado pelo HybridAdapter.
        """
        # ESL Outbound não pode executar api(), retorna False para fallback
        return False


class ESLInboundAdapter(ESLCommandInterface):
    """
    Adaptador que usa ESL Inbound (AsyncESLClient) para comandos.
    """
    
    def __init__(self, esl_client=None):
        """
        Args:
            esl_client: AsyncESLClient (opcional, usa singleton se não fornecido)
        """
        self._esl = esl_client
    
    def _get_esl(self):
        """Obtém ESL client de forma lazy."""
        if self._esl is None:
            from ..handlers.esl_client import get_esl_client
            self._esl = get_esl_client()
        return self._esl
    
    @property
    def is_connected(self) -> bool:
        return self._get_esl().is_connected
    
    async def _ensure_connected(self) -> bool:
        """Garante conexão ESL."""
        esl = self._get_esl()
        if not esl.is_connected:
            try:
                await esl.connect()
                return True
            except Exception as e:
                logger.error(f"ESL Inbound connection failed: {e}")
                return False
        return True
    
    async def execute_api(self, command: str) -> Optional[str]:
        if not await self._ensure_connected():
            return None
        try:
            return await self._get_esl().execute_api(command)
        except Exception as e:
            logger.error(f"ESL Inbound execute_api failed: {e}")
            return None
    
    async def uuid_kill(self, uuid: str, cause: str = "NORMAL_CLEARING") -> bool:
        if not await self._ensure_connected():
            return False
        try:
            return await self._get_esl().uuid_kill(uuid, cause)
        except Exception as e:
            logger.error(f"ESL Inbound uuid_kill failed: {e}")
            return False
    
    async def uuid_hold(self, uuid: str, on: bool = True) -> bool:
        if not await self._ensure_connected():
            return False
        try:
            return await self._get_esl().uuid_hold(uuid, on)
        except Exception as e:
            logger.error(f"ESL Inbound uuid_hold failed: {e}")
            return False
    
    async def uuid_break(self, uuid: str) -> bool:
        if not await self._ensure_connected():
            return False
        try:
            return await self._get_esl().uuid_break(uuid)
        except Exception as e:
            logger.error(f"ESL Inbound uuid_break failed: {e}")
            return False
    
    async def uuid_broadcast(self, uuid: str, path: str, leg: str = "aleg") -> bool:
        if not await self._ensure_connected():
            return False
        try:
            return await self._get_esl().uuid_broadcast(uuid, path, leg)
        except Exception as e:
            logger.error(f"ESL Inbound uuid_broadcast failed: {e}")
            return False
    
    async def uuid_exists(self, uuid: str) -> bool:
        if not await self._ensure_connected():
            return False
        try:
            return await self._get_esl().uuid_exists(uuid)
        except Exception as e:
            logger.error(f"ESL Inbound uuid_exists failed: {e}")
            return False
    
    # Métodos avançados (apenas disponíveis no ESL Inbound)
    
    async def originate(self, dial_string: str, app: str, timeout: int = 60,
                       variables: dict = None) -> Optional[str]:
        if not await self._ensure_connected():
            return None
        try:
            return await self._get_esl().originate(
                dial_string=dial_string,
                app=app,
                timeout=timeout,
                variables=variables
            )
        except Exception as e:
            logger.error(f"ESL Inbound originate failed: {e}")
            return None
    
    async def uuid_bridge(self, uuid1: str, uuid2: str) -> bool:
        if not await self._ensure_connected():
            return False
        try:
            return await self._get_esl().uuid_bridge(uuid1, uuid2)
        except Exception as e:
            logger.error(f"ESL Inbound uuid_bridge failed: {e}")
            return False
    
    async def subscribe_events(self, events: list) -> bool:
        if not await self._ensure_connected():
            return False
        try:
            return await self._get_esl().subscribe_events(events)
        except Exception as e:
            logger.error(f"ESL Inbound subscribe_events failed: {e}")
            return False
    
    async def wait_for_event(self, event_names: list, uuid: str = None,
                            timeout: float = 30.0):
        if not await self._ensure_connected():
            return None
        try:
            return await self._get_esl().wait_for_event(
                event_names=event_names,
                uuid=uuid,
                timeout=timeout
            )
        except Exception as e:
            logger.error(f"ESL Inbound wait_for_event failed: {e}")
            return None


class ESLHybridAdapter(ESLCommandInterface):
    """
    Adaptador híbrido que tenta ESL Outbound primeiro, depois Inbound.
    
    Este é o adaptador recomendado para o modo DUAL.
    """
    
    def __init__(self, call_uuid: str, esl_client=None):
        """
        Args:
            call_uuid: UUID da chamada
            esl_client: AsyncESLClient (opcional)
        """
        self._call_uuid = call_uuid
        self._outbound = ESLOutboundAdapter(call_uuid)
        self._inbound = ESLInboundAdapter(esl_client)
    
    @property
    def is_connected(self) -> bool:
        return self._outbound.is_connected or self._inbound.is_connected
    
    async def execute_api(self, command: str) -> Optional[str]:
        # Outbound primeiro
        if self._outbound.is_connected:
            result = await self._outbound.execute_api(command)
            if result is not None:
                return result
        
        # Fallback para Inbound
        return await self._inbound.execute_api(command)
    
    async def uuid_kill(self, uuid: str, cause: str = "NORMAL_CLEARING") -> bool:
        # Outbound primeiro
        if self._outbound.is_connected:
            if await self._outbound.uuid_kill(uuid, cause):
                logger.debug(f"uuid_kill via ESL Outbound: {uuid}")
                return True
        
        # Fallback para Inbound
        result = await self._inbound.uuid_kill(uuid, cause)
        if result:
            logger.debug(f"uuid_kill via ESL Inbound: {uuid}")
        return result
    
    async def uuid_hold(self, uuid: str, on: bool = True) -> bool:
        # Outbound primeiro
        if self._outbound.is_connected:
            if await self._outbound.uuid_hold(uuid, on):
                logger.debug(f"uuid_hold via ESL Outbound: {uuid}")
                return True
        
        # Fallback para Inbound
        result = await self._inbound.uuid_hold(uuid, on)
        if result:
            logger.debug(f"uuid_hold via ESL Inbound: {uuid}")
        return result
    
    async def uuid_break(self, uuid: str) -> bool:
        # Outbound primeiro
        if self._outbound.is_connected:
            if await self._outbound.uuid_break(uuid):
                logger.debug(f"uuid_break via ESL Outbound: {uuid}")
                return True
        
        # Fallback para Inbound
        result = await self._inbound.uuid_break(uuid)
        if result:
            logger.debug(f"uuid_break via ESL Inbound: {uuid}")
        return result
    
    async def uuid_broadcast(self, uuid: str, path: str, leg: str = "aleg") -> bool:
        # Outbound primeiro
        if self._outbound.is_connected:
            if await self._outbound.uuid_broadcast(uuid, path, leg):
                logger.debug(f"uuid_broadcast via ESL Outbound: {uuid}")
                return True
        
        # Fallback para Inbound
        result = await self._inbound.uuid_broadcast(uuid, path, leg)
        if result:
            logger.debug(f"uuid_broadcast via ESL Inbound: {uuid}")
        return result
    
    async def uuid_exists(self, uuid: str) -> bool:
        # Outbound primeiro
        if self._outbound.is_connected:
            result = await self._outbound.uuid_exists(uuid)
            if result:
                return True
        
        # Fallback para Inbound
        return await self._inbound.uuid_exists(uuid)
    
    # Métodos avançados - delegam diretamente para Inbound
    # (ESL Outbound não suporta esses métodos)
    
    async def originate(self, dial_string: str, app: str, timeout: int = 60,
                       variables: dict = None) -> Optional[str]:
        return await self._inbound.originate(dial_string, app, timeout, variables)
    
    async def uuid_bridge(self, uuid1: str, uuid2: str) -> bool:
        return await self._inbound.uuid_bridge(uuid1, uuid2)
    
    async def subscribe_events(self, events: list) -> bool:
        return await self._inbound.subscribe_events(events)
    
    async def wait_for_event(self, event_names: list, uuid: str = None,
                            timeout: float = 30.0):
        return await self._inbound.wait_for_event(event_names, uuid, timeout)


def get_esl_adapter(call_uuid: str, esl_client=None) -> ESLCommandInterface:
    """
    Factory para obter adaptador ESL apropriado baseado no modo.
    
    Args:
        call_uuid: UUID da chamada
        esl_client: AsyncESLClient (opcional)
        
    Returns:
        ESLCommandInterface apropriado para o modo atual
    """
    audio_mode = os.getenv("AUDIO_MODE", "websocket").lower()
    
    if audio_mode == "dual":
        # Modo dual: usar adaptador híbrido
        return ESLHybridAdapter(call_uuid, esl_client)
    else:
        # Modo websocket-only: usar ESL Inbound
        return ESLInboundAdapter(esl_client)
