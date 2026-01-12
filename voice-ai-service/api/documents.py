"""
Documents API endpoint (Knowledge Base / RAG).

⚠️ MULTI-TENANT: domain_uuid é OBRIGATÓRIO em todas as requisições.
"""

from fastapi import APIRouter, HTTPException, UploadFile, File, Form, Query
from typing import Optional, List
from uuid import UUID, uuid4
from pydantic import BaseModel
import structlog

from models.request import DocumentUploadRequest
from models.response import DocumentUploadResponse
from services.database import db

logger = structlog.get_logger()

router = APIRouter()


class ChunkInfo(BaseModel):
    """Information about a document chunk."""
    chunk_uuid: str
    chunk_index: int
    content: str
    token_count: Optional[int] = None
    similarity_score: Optional[float] = None


class ChunksResponse(BaseModel):
    """Response for document chunks endpoint."""
    document_id: str
    document_name: Optional[str] = None
    chunks: List[ChunkInfo]
    total_chunks: int


@router.post("/documents", response_model=DocumentUploadResponse)
async def upload_document(request: DocumentUploadRequest) -> DocumentUploadResponse:
    """
    Upload a document to the knowledge base.
    
    Args:
        request: DocumentUploadRequest with domain_uuid, document details
        
    Returns:
        DocumentUploadResponse with document ID
        
    Raises:
        HTTPException: If upload fails
    """
    # MULTI-TENANT: Validar domain_uuid
    if not request.domain_uuid:
        raise HTTPException(
            status_code=400,
            detail="domain_uuid is required for multi-tenant isolation",
        )
    
    try:
        # TODO: Implement document processing
        # 1. Extract text from file (PDF, DOCX, TXT)
        # 2. Split into chunks
        # 3. Generate embeddings
        # 4. Store in vector database (filtered by domain_uuid)
        
        document_id = uuid4()
        chunk_count = 0  # TODO: Count actual chunks
        
        return DocumentUploadResponse(
            document_id=document_id,
            document_name=request.document_name,
            chunk_count=chunk_count,
            status="processed",
        )
        
    except ValueError as e:
        raise HTTPException(status_code=400, detail=str(e))
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Upload failed: {str(e)}")


@router.get("/documents")
async def list_documents(domain_uuid: UUID):
    """
    List documents for a domain.
    
    Args:
        domain_uuid: Domain UUID for multi-tenant isolation
        
    Returns:
        List of documents
    """
    # MULTI-TENANT: Validar domain_uuid
    if not domain_uuid:
        raise HTTPException(
            status_code=400,
            detail="domain_uuid is required for multi-tenant isolation",
        )
    
    # TODO: Query database filtered by domain_uuid
    return {
        "documents": [],
        "total": 0,
    }


@router.get("/documents/{document_id}/chunks", response_model=ChunksResponse)
async def get_document_chunks(
    document_id: UUID,
    domain_uuid: UUID = Query(..., description="Domain UUID for multi-tenant isolation"),
    limit: int = Query(50, ge=1, le=200, description="Maximum chunks to return"),
    offset: int = Query(0, ge=0, description="Offset for pagination"),
):
    """
    Get chunks of a document (for debugging RAG).
    
    Args:
        document_id: Document UUID
        domain_uuid: Domain UUID for multi-tenant isolation
        limit: Maximum number of chunks to return
        offset: Pagination offset
        
    Returns:
        ChunksResponse with chunk details
    """
    # MULTI-TENANT: Validar domain_uuid
    if not domain_uuid:
        raise HTTPException(
            status_code=400,
            detail="domain_uuid is required for multi-tenant isolation",
        )
    
    logger.info(
        "Fetching document chunks",
        document_id=str(document_id),
        domain_uuid=str(domain_uuid),
        limit=limit,
        offset=offset,
    )
    
    try:
        pool = await db.get_pool()
        
        # First, verify document exists and belongs to domain
        document = await pool.fetchrow(
            """
            SELECT document_uuid, document_name, processing_status
            FROM v_voice_documents
            WHERE document_uuid = $1 AND domain_uuid = $2
            """,
            document_id,
            domain_uuid,
        )
        
        if not document:
            raise HTTPException(
                status_code=404,
                detail=f"Document {document_id} not found in domain {domain_uuid}",
            )
        
        # Get chunks
        chunks = await pool.fetch(
            """
            SELECT 
                chunk_uuid,
                chunk_index,
                content,
                token_count
            FROM v_voice_document_chunks
            WHERE document_uuid = $1 AND domain_uuid = $2
            ORDER BY chunk_index
            LIMIT $3 OFFSET $4
            """,
            document_id,
            domain_uuid,
            limit,
            offset,
        )
        
        # Get total count
        total = await pool.fetchval(
            """
            SELECT COUNT(*) FROM v_voice_document_chunks
            WHERE document_uuid = $1 AND domain_uuid = $2
            """,
            document_id,
            domain_uuid,
        )
        
        return ChunksResponse(
            document_id=str(document_id),
            document_name=document["document_name"],
            chunks=[
                ChunkInfo(
                    chunk_uuid=str(row["chunk_uuid"]),
                    chunk_index=row["chunk_index"],
                    content=row["content"],
                    token_count=row["token_count"],
                )
                for row in chunks
            ],
            total_chunks=total or 0,
        )
        
    except HTTPException:
        raise
    except Exception as e:
        logger.error("Failed to fetch chunks", error=str(e))
        raise HTTPException(status_code=500, detail=f"Failed to fetch chunks: {str(e)}")


@router.delete("/documents/{document_id}")
async def delete_document(document_id: UUID, domain_uuid: UUID):
    """
    Delete a document from the knowledge base.
    
    Args:
        document_id: Document UUID
        domain_uuid: Domain UUID for multi-tenant isolation
        
    Returns:
        Success message
    """
    # MULTI-TENANT: Validar domain_uuid
    if not domain_uuid:
        raise HTTPException(
            status_code=400,
            detail="domain_uuid is required for multi-tenant isolation",
        )
    
    logger.info(
        "Deleting document",
        document_id=str(document_id),
        domain_uuid=str(domain_uuid),
    )
    
    try:
        pool = await db.get_pool()
        
        # MULTI-TENANT: SEMPRE verificar domain_uuid antes de deletar
        result = await pool.execute(
            """
            DELETE FROM v_voice_documents
            WHERE document_uuid = $1 AND domain_uuid = $2
            """,
            document_id,
            domain_uuid,
        )
        
        # Chunks são deletados automaticamente via ON DELETE CASCADE
        
        if result == "DELETE 0":
            raise HTTPException(
                status_code=404,
                detail=f"Document {document_id} not found in domain {domain_uuid}",
            )
        
        return {"status": "deleted", "document_id": str(document_id)}
        
    except HTTPException:
        raise
    except Exception as e:
        logger.error("Failed to delete document", error=str(e))
        raise HTTPException(status_code=500, detail=f"Delete failed: {str(e)}")
