"""
RAG-enhanced Chat Service.

Integrates document retrieval with LLM chat.
MULTI-TENANT: All operations require domain_uuid.
"""

from typing import List, Optional, Dict, Any

from services.provider_manager import ProviderManager
from services.session_manager import SessionManager, get_session_manager
from services.rag.vector_store import BaseVectorStore, SearchResult
from services.rag.embedding_service import EmbeddingService
from services.llm.base import ChatResult, Message


class RAGChatService:
    """
    Chat service with RAG (Retrieval Augmented Generation).
    
    Retrieves relevant document chunks and includes them
    in the LLM context for knowledge-grounded responses.
    """
    
    def __init__(
        self,
        provider_manager: ProviderManager,
        vector_store: BaseVectorStore,
        embedding_service: EmbeddingService,
        session_manager: Optional[SessionManager] = None,
    ):
        """
        Initialize RAG Chat Service.
        
        Args:
            provider_manager: For loading LLM providers
            vector_store: For document chunk retrieval
            embedding_service: For query embedding
            session_manager: For conversation context
        """
        self.provider_manager = provider_manager
        self.vector_store = vector_store
        self.embedding_service = embedding_service
        self.session_manager = session_manager or get_session_manager()
    
    async def chat(
        self,
        domain_uuid: str,
        session_id: str,
        user_message: str,
        secretary_uuid: Optional[str] = None,
        system_prompt: Optional[str] = None,
        use_rag: bool = True,
        top_k: int = 3,
        temperature: float = 0.7,
        max_tokens: int = 500,
    ) -> ChatResult:
        """
        Process a chat message with optional RAG.
        
        Args:
            domain_uuid: Tenant identifier (REQUIRED)
            session_id: Session/call identifier
            user_message: User's message
            secretary_uuid: Secretary configuration
            system_prompt: System prompt (used if session is new)
            use_rag: Whether to retrieve documents
            top_k: Number of chunks to retrieve
            temperature: LLM temperature
            max_tokens: Max response tokens
            
        Returns:
            ChatResult with response
        """
        if not domain_uuid:
            raise ValueError("domain_uuid is required for multi-tenant isolation")
        
        # Get or create session
        session = await self.session_manager.get_session(session_id)
        if not session:
            session = await self.session_manager.create_session(
                session_id=session_id,
                domain_uuid=domain_uuid,
                secretary_uuid=secretary_uuid or "",
                caller_id="",
                system_prompt=system_prompt,
            )
        
        # Add user message to session
        session.add_message("user", user_message)
        
        # Retrieve relevant context if RAG enabled
        rag_context = []
        if use_rag:
            rag_context = await self._retrieve_context(
                domain_uuid=domain_uuid,
                query=user_message,
                top_k=top_k,
            )
            # Store in session for reference
            await self.session_manager.set_rag_context(session_id, rag_context)
        
        # Build messages for LLM
        messages = self._build_messages(
            session=session,
            rag_context=rag_context,
            system_prompt=system_prompt,
        )
        
        # Get LLM provider for this domain
        llm_provider = await self.provider_manager.get_llm_provider(domain_uuid)
        
        # Generate response
        result = await llm_provider.chat(
            messages=messages,
            temperature=temperature,
            max_tokens=max_tokens,
        )
        
        # Add assistant response to session
        session.add_message("assistant", result.text)
        
        return result
    
    async def _retrieve_context(
        self,
        domain_uuid: str,
        query: str,
        top_k: int = 3,
    ) -> List[str]:
        """
        Retrieve relevant document chunks.
        
        Args:
            domain_uuid: Tenant identifier
            query: User's query
            top_k: Number of chunks to retrieve
            
        Returns:
            List of relevant text chunks
        """
        try:
            # Generate query embedding
            query_embedding = await self.embedding_service.embed_text(
                domain_uuid=domain_uuid,
                text=query,
            )
            
            # Search for similar chunks
            results: List[SearchResult] = await self.vector_store.search(
                domain_uuid=domain_uuid,
                query_embedding=query_embedding,
                top_k=top_k,
            )
            
            # Filter by minimum similarity score
            MIN_SCORE = 0.5
            relevant = [r for r in results if r.score >= MIN_SCORE]
            
            return [r.content for r in relevant]
            
        except Exception:
            # If RAG fails, continue without context
            return []
    
    def _build_messages(
        self,
        session,
        rag_context: List[str],
        system_prompt: Optional[str] = None,
    ) -> List[Message]:
        """
        Build message list for LLM.
        
        Includes:
        1. System prompt (if provided)
        2. RAG context (if available)
        3. Conversation history
        """
        messages = []
        
        # System prompt with RAG context
        if system_prompt or rag_context:
            system_content = system_prompt or ""
            
            if rag_context:
                context_text = "\n\n---\n\n".join(rag_context)
                system_content += f"""

## Informações Relevantes da Base de Conhecimento:

{context_text}

---

Use as informações acima para responder às perguntas do cliente quando relevante.
Se não souber algo, diga que irá verificar e transferir para um atendente."""
            
            messages.append(Message(role="system", content=system_content))
        
        # Add conversation history (last 10 messages)
        for msg_dict in session.get_history(max_messages=10):
            if msg_dict["role"] != "system":  # Skip system messages in history
                messages.append(Message(
                    role=msg_dict["role"],
                    content=msg_dict["content"],
                ))
        
        return messages
    
    async def end_conversation(
        self,
        session_id: str,
        transfer_target: Optional[str] = None,
    ) -> Dict[str, Any]:
        """
        End a conversation and return summary.
        
        Args:
            session_id: Session to end
            transfer_target: If transferred, the target
            
        Returns:
            Conversation summary for logging
        """
        session = await self.session_manager.end_session(
            session_id=session_id,
            transfer_target=transfer_target,
        )
        
        if not session:
            return {}
        
        return {
            "session_id": session.session_id,
            "domain_uuid": session.domain_uuid,
            "secretary_uuid": session.secretary_uuid,
            "caller_id": session.caller_id,
            "duration_seconds": session.get_duration_seconds(),
            "message_count": len(session.messages),
            "transfer_target": session.transfer_target,
            "messages": [
                {
                    "role": m.role,
                    "content": m.content,
                    "timestamp": m.timestamp,
                }
                for m in session.messages
            ],
        }
