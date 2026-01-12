"""
Middleware for Voice AI Service.

Inclui rate limiting e outras proteções.
"""

from fastapi import Request, HTTPException
from starlette.middleware.base import BaseHTTPMiddleware
from starlette.responses import JSONResponse
import structlog

from services.rate_limiter import rate_limiter, DEFAULT_LIMITS

logger = structlog.get_logger()


class RateLimitMiddleware(BaseHTTPMiddleware):
    """
    Rate limiting middleware.
    
    ⚠️ MULTI-TENANT: Aplica limites por domain_uuid.
    """
    
    # Endpoints que requerem rate limiting
    RATE_LIMITED_ENDPOINTS = {
        "/api/v1/transcribe": "transcribe",
        "/transcribe": "transcribe",
        "/api/v1/synthesize": "synthesize",
        "/synthesize": "synthesize",
        "/api/v1/chat": "chat",
        "/chat": "chat",
        "/api/v1/documents": "documents",
        "/documents": "documents",
    }
    
    async def dispatch(self, request: Request, call_next):
        # Verificar se endpoint requer rate limiting
        path = request.url.path
        
        # Encontrar endpoint correspondente
        endpoint_key = None
        for pattern, key in self.RATE_LIMITED_ENDPOINTS.items():
            if path.startswith(pattern):
                endpoint_key = key
                break
        
        if not endpoint_key:
            # Endpoint não requer rate limiting
            return await call_next(request)
        
        # Extrair domain_uuid do request
        domain_uuid = await self._extract_domain_uuid(request)
        
        if not domain_uuid:
            # Sem domain_uuid, deixar o endpoint validar
            return await call_next(request)
        
        # Verificar rate limit
        allowed, info = await rate_limiter.check_rate_limit(
            domain_uuid=domain_uuid,
            endpoint=endpoint_key,
        )
        
        if not allowed:
            logger.warning(
                "Rate limit exceeded",
                domain_uuid=domain_uuid,
                endpoint=endpoint_key,
                info=info,
            )
            
            return JSONResponse(
                status_code=429,
                content={
                    "error": "Rate limit exceeded",
                    "detail": f"Too many requests for {endpoint_key}",
                    "retry_after": info.get("retry_after", 60),
                    "limit_type": info.get("limit_type", "minute"),
                },
                headers={
                    "Retry-After": str(info.get("retry_after", 60)),
                    "X-RateLimit-Remaining-Minute": str(info.get("remaining_minute", 0)),
                    "X-RateLimit-Remaining-Hour": str(info.get("remaining_hour", 0)),
                },
            )
        
        # Adicionar headers de rate limit na resposta
        response = await call_next(request)
        
        response.headers["X-RateLimit-Remaining-Minute"] = str(info.get("remaining_minute", 0))
        response.headers["X-RateLimit-Remaining-Hour"] = str(info.get("remaining_hour", 0))
        
        return response
    
    async def _extract_domain_uuid(self, request: Request) -> str | None:
        """
        Extract domain_uuid from request.
        
        Tries:
        1. Query parameter
        2. JSON body (for POST)
        3. Header
        """
        # 1. Query parameter
        domain_uuid = request.query_params.get("domain_uuid")
        if domain_uuid:
            return domain_uuid
        
        # 2. Header
        domain_uuid = request.headers.get("X-Domain-UUID")
        if domain_uuid:
            return domain_uuid
        
        # 3. JSON body (para POST) - não podemos ler o body aqui facilmente
        # sem consumir o stream, então deixamos para o endpoint
        
        return None


class RequestLoggingMiddleware(BaseHTTPMiddleware):
    """Middleware para logging de requests."""
    
    async def dispatch(self, request: Request, call_next):
        # Skip health checks
        if request.url.path in ["/health", "/", "/docs", "/openapi.json"]:
            return await call_next(request)
        
        logger.info(
            "Request started",
            method=request.method,
            path=request.url.path,
            client_ip=request.client.host if request.client else None,
        )
        
        response = await call_next(request)
        
        logger.info(
            "Request completed",
            method=request.method,
            path=request.url.path,
            status_code=response.status_code,
        )
        
        return response
