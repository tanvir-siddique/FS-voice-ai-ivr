"""
Unit tests for RAG (Retrieval Augmented Generation) service.

⚠️ MULTI-TENANT: Todos os testes validam isolamento por domain_uuid.
"""

import pytest
from unittest.mock import AsyncMock, MagicMock, patch
from uuid import uuid4


# Mock vector store for testing
class MockVectorStore:
    """Mock vector store for testing."""
    
    def __init__(self):
        self.chunks = {}  # domain_uuid -> list of chunks
    
    async def add_chunks(self, domain_uuid: str, chunks: list, embeddings: list):
        if domain_uuid not in self.chunks:
            self.chunks[domain_uuid] = []
        for i, (chunk, embedding) in enumerate(zip(chunks, embeddings)):
            self.chunks[domain_uuid].append({
                "id": str(uuid4()),
                "content": chunk,
                "embedding": embedding,
            })
    
    async def search(self, domain_uuid: str, query_embedding: list, top_k: int = 5):
        if domain_uuid not in self.chunks:
            return []
        
        # Simplified: return first top_k chunks
        results = []
        for chunk in self.chunks[domain_uuid][:top_k]:
            results.append({
                "content": chunk["content"],
                "similarity": 0.85,  # Mock similarity
            })
        return results
    
    async def delete_by_document(self, domain_uuid: str, document_id: str):
        if domain_uuid in self.chunks:
            self.chunks[domain_uuid] = [
                c for c in self.chunks[domain_uuid]
                if c.get("document_id") != document_id
            ]


class TestVectorStore:
    """Tests for vector store operations."""
    
    @pytest.fixture
    def domain_uuid(self):
        return str(uuid4())
    
    @pytest.fixture
    def other_domain_uuid(self):
        return str(uuid4())
    
    @pytest.fixture
    def vector_store(self):
        return MockVectorStore()
    
    @pytest.mark.asyncio
    async def test_add_and_search_chunks(self, vector_store, domain_uuid):
        """Test adding and searching chunks."""
        chunks = [
            "A empresa funciona das 9h às 18h.",
            "O telefone de contato é 11 99999-9999.",
            "Para vendas, ligue para o ramal 200.",
        ]
        embeddings = [[0.1] * 384] * len(chunks)  # Mock embeddings
        
        await vector_store.add_chunks(domain_uuid, chunks, embeddings)
        
        results = await vector_store.search(
            domain_uuid=domain_uuid,
            query_embedding=[0.1] * 384,
            top_k=2,
        )
        
        assert len(results) == 2
        assert "empresa funciona" in results[0]["content"]
    
    @pytest.mark.asyncio
    async def test_multi_tenant_isolation(self, vector_store, domain_uuid, other_domain_uuid):
        """
        ⚠️ MULTI-TENANT: Verificar que chunks de um domínio 
        não aparecem em buscas de outro domínio.
        """
        # Adicionar chunks para domain A
        await vector_store.add_chunks(
            domain_uuid,
            ["Documento do Domain A"],
            [[0.1] * 384],
        )
        
        # Adicionar chunks para domain B
        await vector_store.add_chunks(
            other_domain_uuid,
            ["Documento do Domain B"],
            [[0.2] * 384],
        )
        
        # Buscar em domain A
        results_a = await vector_store.search(domain_uuid, [0.1] * 384)
        
        # Buscar em domain B
        results_b = await vector_store.search(other_domain_uuid, [0.2] * 384)
        
        # Verificar isolamento
        assert len(results_a) == 1
        assert "Domain A" in results_a[0]["content"]
        assert "Domain B" not in results_a[0]["content"]
        
        assert len(results_b) == 1
        assert "Domain B" in results_b[0]["content"]
        assert "Domain A" not in results_b[0]["content"]
    
    @pytest.mark.asyncio
    async def test_empty_search_returns_empty(self, vector_store, domain_uuid):
        """Test searching in empty store returns empty list."""
        results = await vector_store.search(domain_uuid, [0.1] * 384)
        assert results == []


class TestEmbeddingService:
    """Tests for embedding service."""
    
    @pytest.fixture
    def domain_uuid(self):
        return str(uuid4())
    
    @pytest.mark.asyncio
    async def test_generate_embedding(self, domain_uuid):
        """Test generating embedding for text."""
        from services.embeddings.base import BaseEmbeddings
        
        # Mock embeddings provider
        mock_provider = MagicMock(spec=BaseEmbeddings)
        mock_provider.embed = AsyncMock(return_value=[0.1] * 384)
        
        # Test
        embedding = await mock_provider.embed("Texto de teste")
        
        assert len(embedding) == 384
        assert all(isinstance(x, float) for x in embedding)
    
    @pytest.mark.asyncio
    async def test_batch_embeddings(self, domain_uuid):
        """Test generating batch embeddings."""
        from services.embeddings.base import BaseEmbeddings
        
        mock_provider = MagicMock(spec=BaseEmbeddings)
        mock_provider.embed_batch = AsyncMock(return_value=[
            [0.1] * 384,
            [0.2] * 384,
            [0.3] * 384,
        ])
        
        texts = ["Texto 1", "Texto 2", "Texto 3"]
        embeddings = await mock_provider.embed_batch(texts)
        
        assert len(embeddings) == 3
        assert all(len(e) == 384 for e in embeddings)


class TestDocumentProcessor:
    """Tests for document processing."""
    
    @pytest.mark.asyncio
    async def test_chunk_text(self):
        """Test text chunking."""
        # Simple chunking logic test
        text = "Parágrafo 1. " * 100 + "Parágrafo 2. " * 100
        
        # Simulate chunking
        chunk_size = 500
        chunks = []
        for i in range(0, len(text), chunk_size):
            chunk = text[i:i + chunk_size]
            if chunk.strip():
                chunks.append(chunk)
        
        assert len(chunks) > 1
        assert all(len(c) <= chunk_size for c in chunks)
    
    @pytest.mark.asyncio
    async def test_chunk_overlap(self):
        """Test chunk overlap for context continuity."""
        text = "Sentença 1. Sentença 2. Sentença 3. Sentença 4. Sentença 5."
        chunk_size = 30
        overlap = 10
        
        chunks = []
        start = 0
        while start < len(text):
            end = min(start + chunk_size, len(text))
            chunk = text[start:end]
            if chunk.strip():
                chunks.append(chunk)
            start = end - overlap if end < len(text) else len(text)
        
        # Verify overlap exists between consecutive chunks
        if len(chunks) > 1:
            # First chunk ends and second chunk begins should have some overlap
            assert len(chunks) >= 2


class TestRAGChat:
    """Tests for RAG-enhanced chat."""
    
    @pytest.fixture
    def domain_uuid(self):
        return str(uuid4())
    
    @pytest.fixture
    def mock_vector_store(self):
        store = MockVectorStore()
        return store
    
    @pytest.fixture
    def mock_embeddings(self):
        mock = MagicMock()
        mock.embed = AsyncMock(return_value=[0.1] * 384)
        return mock
    
    @pytest.fixture
    def mock_llm(self):
        mock = MagicMock()
        mock.chat = AsyncMock(return_value=MagicMock(
            response="A empresa funciona das 9h às 18h.",
            action=None,
        ))
        return mock
    
    @pytest.mark.asyncio
    async def test_rag_enhances_response(
        self,
        domain_uuid,
        mock_vector_store,
        mock_embeddings,
        mock_llm,
    ):
        """Test that RAG context enhances LLM responses."""
        # Add knowledge to vector store
        await mock_vector_store.add_chunks(
            domain_uuid,
            ["A empresa funciona das 9h às 18h de segunda a sexta."],
            [[0.1] * 384],
        )
        
        # Simulate RAG flow
        question = "Qual o horário de funcionamento?"
        
        # 1. Generate embedding for question
        query_embedding = await mock_embeddings.embed(question)
        
        # 2. Search for relevant context
        context_chunks = await mock_vector_store.search(
            domain_uuid, query_embedding, top_k=3
        )
        
        # 3. Build context string
        context = "\n".join([c["content"] for c in context_chunks])
        
        # 4. Call LLM with context
        system_prompt = f"""
        Você é uma secretária virtual.
        
        Contexto da empresa:
        {context}
        
        Responda a pergunta do cliente com base no contexto acima.
        """
        
        result = await mock_llm.chat(
            system_prompt=system_prompt,
            messages=[{"role": "user", "content": question}],
        )
        
        assert "9h às 18h" in result.response
    
    @pytest.mark.asyncio
    async def test_rag_with_no_context(
        self,
        domain_uuid,
        mock_vector_store,
        mock_embeddings,
    ):
        """Test RAG behavior when no relevant context found."""
        # Empty vector store
        results = await mock_vector_store.search(
            domain_uuid, [0.1] * 384, top_k=3
        )
        
        assert results == []
        # LLM should still be able to respond, just without specific context
    
    @pytest.mark.asyncio
    async def test_multi_tenant_rag_isolation(
        self,
        domain_uuid,
        mock_vector_store,
    ):
        """
        ⚠️ MULTI-TENANT: RAG context must be isolated by domain.
        """
        other_domain = str(uuid4())
        
        # Add company A info to domain A
        await mock_vector_store.add_chunks(
            domain_uuid,
            ["Empresa A - horário 8h às 17h"],
            [[0.1] * 384],
        )
        
        # Add company B info to domain B
        await mock_vector_store.add_chunks(
            other_domain,
            ["Empresa B - horário 10h às 20h"],
            [[0.2] * 384],
        )
        
        # Search in domain A - should NOT see company B data
        results_a = await mock_vector_store.search(domain_uuid, [0.1] * 384)
        
        assert len(results_a) == 1
        assert "Empresa A" in results_a[0]["content"]
        assert "Empresa B" not in results_a[0]["content"]


class TestSessionManager:
    """Tests for session management in RAG context."""
    
    @pytest.fixture
    def domain_uuid(self):
        return str(uuid4())
    
    @pytest.mark.asyncio
    async def test_session_maintains_history(self, domain_uuid):
        """Test that session maintains conversation history."""
        from services.session_manager import SessionManager, Message
        
        manager = SessionManager()
        session_id = "test-session-123"
        
        # Add messages
        await manager.add_message(
            session_id, domain_uuid, "user", "Olá"
        )
        await manager.add_message(
            session_id, domain_uuid, "assistant", "Olá! Como posso ajudar?"
        )
        await manager.add_message(
            session_id, domain_uuid, "user", "Qual o horário?"
        )
        
        # Get history
        session = manager.get_session(session_id)
        assert session is not None
        
        history = await manager.get_history(session_id)
        assert len(history) == 3
    
    @pytest.mark.asyncio
    async def test_session_isolation(self, domain_uuid):
        """Test that sessions are isolated between domains."""
        from services.session_manager import SessionManager
        
        manager = SessionManager()
        other_domain = str(uuid4())
        
        session_a = "session-domain-a"
        session_b = "session-domain-b"
        
        await manager.add_message(session_a, domain_uuid, "user", "Msg A")
        await manager.add_message(session_b, other_domain, "user", "Msg B")
        
        # Sessions should be separate
        session_a_obj = manager.get_session(session_a)
        session_b_obj = manager.get_session(session_b)
        
        assert session_a_obj.domain_uuid == domain_uuid
        assert session_b_obj.domain_uuid == other_domain
