"""
Voice AI Service - Secretária Virtual com IA
FastAPI application for STT, TTS, LLM, and RAG processing.

⚠️ MULTI-TENANT: TODOS os endpoints MUST receber domain_uuid como parâmetro obrigatório.
"""

import os
from contextlib import asynccontextmanager

from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware

from config.settings import settings
from api import transcribe, synthesize, chat, documents, conversations, webhook
from api.middleware import RateLimitMiddleware, RequestLoggingMiddleware


@asynccontextmanager
async def lifespan(app: FastAPI):
    """Application lifecycle manager."""
    from services.database import db
    
    # Startup
    print(f"🎙️ Voice AI Service starting on {settings.HOST}:{settings.PORT}")
    print(f"📁 Data directory: {settings.DATA_DIR}")
    print(f"🗄️ Database: {settings.DB_HOST}:{settings.DB_PORT}/{settings.DB_NAME}")
    
    # Initialize database pool
    try:
        await db.get_pool()
        print("✅ Database connection pool initialized")
    except Exception as e:
        # IMPORTANT: keep details for debugging infra (pg_hba / auth / network)
        print(
            "⚠️ Database connection failed (will use fallback providers): "
            f"{type(e).__name__}: {e!s} | repr={e!r}"
        )
    
    yield
    
    # Shutdown
    print("🛑 Voice AI Service shutting down")
    
    # Close database pool
    await db.close_pool()
    print("✅ Database pool closed")


app = FastAPI(
    title="Voice AI Service",
    description="""
## Serviço de IA para Secretária Virtual

Fornece endpoints para processamento de voz com IA integrada ao FreeSWITCH/FusionPBX.

### Multi-Tenant
⚠️ **OBRIGATÓRIO**: Todos os endpoints requerem `domain_uuid` para isolamento de dados.

### Funcionalidades
- **STT (Speech-to-Text)**: Transcrição de áudio usando Whisper, Azure, Google, AWS, Deepgram
- **TTS (Text-to-Speech)**: Síntese de voz usando Piper, OpenAI, ElevenLabs, Azure, Google, AWS
- **LLM (Chat)**: Processamento de linguagem usando OpenAI, Anthropic, Groq, Ollama, etc.
- **RAG (Knowledge Base)**: Base de conhecimento com embeddings e busca vetorial
- **Conversations**: Histórico de conversas

### Rate Limiting
Limites por domínio:
- Transcribe: 30/min, 500/hora
- Synthesize: 60/min, 1000/hora
- Chat: 60/min, 1000/hora
- Documents: 10/min, 100/hora
    """,
    version="1.0.0",
    lifespan=lifespan,
    docs_url="/docs",
    redoc_url="/redoc",
)

# Rate Limiting Middleware (multi-tenant)
app.add_middleware(RateLimitMiddleware)

# Request Logging Middleware
app.add_middleware(RequestLoggingMiddleware)

# CORS - apenas localhost por padrão
app.add_middleware(
    CORSMiddleware,
    allow_origins=["http://127.0.0.1", "http://localhost"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Include routers
app.include_router(transcribe.router, prefix="/api/v1", tags=["STT"])
app.include_router(synthesize.router, prefix="/api/v1", tags=["TTS"])
app.include_router(chat.router, prefix="/api/v1", tags=["Chat"])
app.include_router(documents.router, prefix="/api/v1", tags=["Documents"])
app.include_router(conversations.router, prefix="/api/v1", tags=["Conversations"])
app.include_router(webhook.router, prefix="/api/v1", tags=["Webhooks"])


@app.get("/health")
async def health_check():
    """Health check endpoint."""
    return {
        "status": "healthy",
        "service": "voice-ai-service",
        "version": "1.0.0",
    }


@app.get("/")
async def root():
    """Root endpoint with API info."""
    return {
        "service": "Voice AI Service",
        "description": "Secretária Virtual com IA para FreeSWITCH/FusionPBX",
        "version": "1.0.0",
        "endpoints": {
            "health": "/health",
            "transcribe": "POST /api/v1/transcribe",
            "synthesize": "POST /api/v1/synthesize",
            "chat": "POST /api/v1/chat",
            "documents": "POST /api/v1/documents",
            "conversations": "POST /api/v1/conversations",
            "webhooks": "POST /api/v1/webhooks/send",
        },
        "docs": "/docs",
    }


if __name__ == "__main__":
    import uvicorn
    
    uvicorn.run(
        "main:app",
        host=settings.HOST,
        port=settings.PORT,
        reload=settings.DEBUG,
    )
