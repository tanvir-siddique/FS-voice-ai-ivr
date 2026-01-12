"""
Rate Limiter for Voice AI Service.

⚠️ MULTI-TENANT: Rate limits são aplicados POR DOMÍNIO.
"""

import asyncio
from collections import defaultdict
from dataclasses import dataclass
from datetime import datetime, timedelta
from typing import Dict, Optional
import structlog

logger = structlog.get_logger()


@dataclass
class RateLimitConfig:
    """Rate limit configuration."""
    requests_per_minute: int = 60
    requests_per_hour: int = 1000
    requests_per_day: int = 10000
    burst_size: int = 10  # Máximo de requests em sequência rápida


@dataclass
class RateLimitState:
    """State for a single domain's rate limiting."""
    minute_count: int = 0
    hour_count: int = 0
    day_count: int = 0
    minute_reset: datetime = None
    hour_reset: datetime = None
    day_reset: datetime = None
    
    def __post_init__(self):
        now = datetime.utcnow()
        if self.minute_reset is None:
            self.minute_reset = now + timedelta(minutes=1)
        if self.hour_reset is None:
            self.hour_reset = now + timedelta(hours=1)
        if self.day_reset is None:
            self.day_reset = now + timedelta(days=1)


class RateLimiter:
    """
    In-memory rate limiter with per-domain limits.
    
    ⚠️ MULTI-TENANT: Cada domain_uuid tem seus próprios contadores.
    
    Para produção em escala, considerar usar Redis.
    """
    
    def __init__(self, default_config: Optional[RateLimitConfig] = None):
        self.default_config = default_config or RateLimitConfig()
        self._states: Dict[str, Dict[str, RateLimitState]] = defaultdict(dict)
        self._configs: Dict[str, RateLimitConfig] = {}
        self._lock = asyncio.Lock()
    
    def set_config(self, domain_uuid: str, config: RateLimitConfig):
        """Set custom rate limit config for a domain."""
        self._configs[domain_uuid] = config
        logger.info(
            "Rate limit config updated",
            domain_uuid=domain_uuid,
            rpm=config.requests_per_minute,
        )
    
    def get_config(self, domain_uuid: str) -> RateLimitConfig:
        """Get rate limit config for a domain."""
        return self._configs.get(domain_uuid, self.default_config)
    
    async def check_rate_limit(
        self,
        domain_uuid: str,
        endpoint: str,
    ) -> tuple[bool, Optional[Dict]]:
        """
        Check if request is allowed under rate limits.
        
        Args:
            domain_uuid: Domain UUID (MULTI-TENANT)
            endpoint: Endpoint name (transcribe, synthesize, chat)
            
        Returns:
            Tuple of (allowed: bool, info: dict with remaining/retry_after)
        """
        async with self._lock:
            config = self.get_config(domain_uuid)
            
            # Inicializar estado se necessário
            if endpoint not in self._states[domain_uuid]:
                self._states[domain_uuid][endpoint] = RateLimitState()
            
            state = self._states[domain_uuid][endpoint]
            now = datetime.utcnow()
            
            # Reset contadores se período expirou
            if now >= state.minute_reset:
                state.minute_count = 0
                state.minute_reset = now + timedelta(minutes=1)
            
            if now >= state.hour_reset:
                state.hour_count = 0
                state.hour_reset = now + timedelta(hours=1)
            
            if now >= state.day_reset:
                state.day_count = 0
                state.day_reset = now + timedelta(days=1)
            
            # Verificar limites
            info = {
                "remaining_minute": config.requests_per_minute - state.minute_count,
                "remaining_hour": config.requests_per_hour - state.hour_count,
                "remaining_day": config.requests_per_day - state.day_count,
            }
            
            # Verificar se excedeu limite por minuto
            if state.minute_count >= config.requests_per_minute:
                retry_after = (state.minute_reset - now).total_seconds()
                info["retry_after"] = max(1, int(retry_after))
                info["limit_type"] = "minute"
                logger.warning(
                    "Rate limit exceeded (minute)",
                    domain_uuid=domain_uuid,
                    endpoint=endpoint,
                    count=state.minute_count,
                    limit=config.requests_per_minute,
                )
                return False, info
            
            # Verificar limite por hora
            if state.hour_count >= config.requests_per_hour:
                retry_after = (state.hour_reset - now).total_seconds()
                info["retry_after"] = max(1, int(retry_after))
                info["limit_type"] = "hour"
                logger.warning(
                    "Rate limit exceeded (hour)",
                    domain_uuid=domain_uuid,
                    endpoint=endpoint,
                    count=state.hour_count,
                    limit=config.requests_per_hour,
                )
                return False, info
            
            # Verificar limite diário
            if state.day_count >= config.requests_per_day:
                retry_after = (state.day_reset - now).total_seconds()
                info["retry_after"] = max(1, int(retry_after))
                info["limit_type"] = "day"
                logger.warning(
                    "Rate limit exceeded (day)",
                    domain_uuid=domain_uuid,
                    endpoint=endpoint,
                    count=state.day_count,
                    limit=config.requests_per_day,
                )
                return False, info
            
            # Incrementar contadores
            state.minute_count += 1
            state.hour_count += 1
            state.day_count += 1
            
            return True, info
    
    async def get_stats(self, domain_uuid: str) -> Dict:
        """Get rate limit stats for a domain."""
        config = self.get_config(domain_uuid)
        stats = {
            "config": {
                "requests_per_minute": config.requests_per_minute,
                "requests_per_hour": config.requests_per_hour,
                "requests_per_day": config.requests_per_day,
            },
            "endpoints": {},
        }
        
        if domain_uuid in self._states:
            for endpoint, state in self._states[domain_uuid].items():
                stats["endpoints"][endpoint] = {
                    "minute_count": state.minute_count,
                    "hour_count": state.hour_count,
                    "day_count": state.day_count,
                    "minute_remaining": max(0, config.requests_per_minute - state.minute_count),
                    "hour_remaining": max(0, config.requests_per_hour - state.hour_count),
                    "day_remaining": max(0, config.requests_per_day - state.day_count),
                }
        
        return stats
    
    async def reset(self, domain_uuid: str, endpoint: Optional[str] = None):
        """Reset rate limit counters for a domain."""
        async with self._lock:
            if endpoint:
                if endpoint in self._states[domain_uuid]:
                    del self._states[domain_uuid][endpoint]
            else:
                self._states[domain_uuid] = {}
            
            logger.info(
                "Rate limits reset",
                domain_uuid=domain_uuid,
                endpoint=endpoint,
            )


# Singleton instance
rate_limiter = RateLimiter()


# Default configs por endpoint (podem ser sobrescritos por domínio)
DEFAULT_LIMITS = {
    "transcribe": RateLimitConfig(
        requests_per_minute=30,
        requests_per_hour=500,
        requests_per_day=5000,
    ),
    "synthesize": RateLimitConfig(
        requests_per_minute=60,
        requests_per_hour=1000,
        requests_per_day=10000,
    ),
    "chat": RateLimitConfig(
        requests_per_minute=60,
        requests_per_hour=1000,
        requests_per_day=10000,
    ),
    "documents": RateLimitConfig(
        requests_per_minute=10,
        requests_per_hour=100,
        requests_per_day=500,
    ),
}
