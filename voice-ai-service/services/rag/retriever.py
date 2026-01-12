"""
RAG Retriever Service.

Retrieves relevant document chunks for a given query.

⚠️ MULTI-TENANT: All queries MUST be filtered by domain_uuid.
"""

from typing import List, Optional
from dataclasses import dataclass
import numpy as np


@dataclass
class RetrievalResult:
    """Result from retrieval."""
    
    chunk_id: str
    document_id: str
    content: str
    score: float
    document_name: Optional[str] = None


class Retriever:
    """
    Retrieves relevant chunks from the knowledge base.
    
    Uses vector similarity search with embeddings.
    
    ⚠️ MULTI-TENANT: domain_uuid is REQUIRED for all operations.
    """
    
    def __init__(self, embeddings_provider, vector_store=None):
        """
        Initialize retriever.
        
        Args:
            embeddings_provider: Provider for generating query embeddings
            vector_store: Optional vector store for search (default: in-memory)
        """
        self.embeddings_provider = embeddings_provider
        self.vector_store = vector_store
        
        # In-memory fallback (for simple deployments)
        self._memory_store: dict = {}  # domain_uuid -> List[{embedding, chunk}]
    
    async def add_chunks(
        self,
        domain_uuid: str,
        chunks: List[dict],
    ):
        """
        Add chunks to the retriever.
        
        Args:
            domain_uuid: REQUIRED - Domain UUID for multi-tenant isolation
            chunks: List of chunks with embeddings
        """
        if not domain_uuid:
            raise ValueError("domain_uuid is required for multi-tenant isolation")
        
        if self.vector_store:
            # Use external vector store
            await self.vector_store.upsert(
                collection=f"voice_ai_{domain_uuid}",
                items=chunks,
            )
        else:
            # Use in-memory store
            if domain_uuid not in self._memory_store:
                self._memory_store[domain_uuid] = []
            self._memory_store[domain_uuid].extend(chunks)
    
    async def retrieve(
        self,
        domain_uuid: str,
        query: str,
        top_k: int = 5,
        secretary_uuid: Optional[str] = None,
    ) -> List[RetrievalResult]:
        """
        Retrieve relevant chunks for a query.
        
        Args:
            domain_uuid: REQUIRED - Domain UUID for multi-tenant isolation
            query: Query text
            top_k: Number of results to return
            secretary_uuid: Optional - filter to specific secretary's documents
            
        Returns:
            List of relevant chunks with scores
        """
        if not domain_uuid:
            raise ValueError("domain_uuid is required for multi-tenant isolation")
        
        # Generate query embedding
        query_result = await self.embeddings_provider.embed(query)
        query_embedding = np.array(query_result.embedding)
        
        if self.vector_store:
            # Use external vector store
            results = await self.vector_store.search(
                collection=f"voice_ai_{domain_uuid}",
                query_vector=query_embedding.tolist(),
                top_k=top_k,
                filter={"secretary_uuid": secretary_uuid} if secretary_uuid else None,
            )
            
            return [
                RetrievalResult(
                    chunk_id=r["id"],
                    document_id=r["document_id"],
                    content=r["content"],
                    score=r["score"],
                    document_name=r.get("document_name"),
                )
                for r in results
            ]
        else:
            # Use in-memory store with cosine similarity
            chunks = self._memory_store.get(domain_uuid, [])
            
            if not chunks:
                return []
            
            # Filter by secretary if specified
            if secretary_uuid:
                chunks = [c for c in chunks if c.get("secretary_uuid") == secretary_uuid or c.get("secretary_uuid") is None]
            
            # Calculate similarities
            scores = []
            for chunk in chunks:
                chunk_embedding = np.array(chunk["embedding"])
                # Cosine similarity
                similarity = np.dot(query_embedding, chunk_embedding) / (
                    np.linalg.norm(query_embedding) * np.linalg.norm(chunk_embedding)
                )
                scores.append((chunk, float(similarity)))
            
            # Sort by score and return top_k
            scores.sort(key=lambda x: x[1], reverse=True)
            
            return [
                RetrievalResult(
                    chunk_id=chunk["chunk_id"],
                    document_id=chunk["document_id"],
                    content=chunk["content"],
                    score=score,
                    document_name=chunk.get("document_name"),
                )
                for chunk, score in scores[:top_k]
            ]
    
    async def get_context(
        self,
        domain_uuid: str,
        query: str,
        top_k: int = 5,
        max_tokens: int = 2000,
        secretary_uuid: Optional[str] = None,
    ) -> tuple:
        """
        Get formatted context for LLM prompt.
        
        Args:
            domain_uuid: REQUIRED - Domain UUID for multi-tenant isolation
            query: Query text
            top_k: Number of chunks to retrieve
            max_tokens: Maximum tokens in context
            secretary_uuid: Optional - filter to specific secretary's documents
            
        Returns:
            Tuple of (context_string, source_documents)
        """
        results = await self.retrieve(
            domain_uuid=domain_uuid,
            query=query,
            top_k=top_k,
            secretary_uuid=secretary_uuid,
        )
        
        if not results:
            return "", []
        
        # Build context string
        context_parts = []
        sources = []
        current_tokens = 0
        
        for result in results:
            # Rough token estimate
            chunk_tokens = len(result.content.split())
            
            if current_tokens + chunk_tokens > max_tokens:
                break
            
            context_parts.append(f"[Fonte: {result.document_name or 'Documento'}]\n{result.content}")
            sources.append(result.document_name or result.document_id)
            current_tokens += chunk_tokens
        
        context = "\n\n---\n\n".join(context_parts)
        
        return context, sources
