"""
Document Processor for RAG.

Processes documents (PDF, DOCX, TXT) into chunks with embeddings.

⚠️ MULTI-TENANT: All operations MUST be filtered by domain_uuid.
"""

from typing import List, Optional
from dataclasses import dataclass
import hashlib


@dataclass
class DocumentChunk:
    """A chunk of a document with its embedding."""
    
    chunk_id: str
    document_id: str
    chunk_index: int
    content: str
    embedding: Optional[List[float]] = None
    token_count: int = 0


class DocumentProcessor:
    """
    Processes documents into chunks for RAG.
    
    Supports: PDF, DOCX, TXT, FAQ (structured Q&A).
    """
    
    def __init__(
        self,
        chunk_size: int = 500,
        chunk_overlap: int = 50,
    ):
        """
        Initialize processor.
        
        Args:
            chunk_size: Target tokens per chunk
            chunk_overlap: Overlap between chunks (for context continuity)
        """
        self.chunk_size = chunk_size
        self.chunk_overlap = chunk_overlap
    
    async def extract_text(self, file_path: str, document_type: str) -> str:
        """
        Extract text from a document file.
        
        Args:
            file_path: Path to the file
            document_type: Type of document (pdf, docx, txt)
            
        Returns:
            Extracted text content
        """
        if document_type == "txt":
            with open(file_path, "r", encoding="utf-8") as f:
                return f.read()
        
        elif document_type == "pdf":
            try:
                from pypdf import PdfReader
                reader = PdfReader(file_path)
                text_parts = []
                for page in reader.pages:
                    text_parts.append(page.extract_text())
                return "\n\n".join(text_parts)
            except ImportError:
                raise ImportError("pypdf not installed. Install with: pip install pypdf")
        
        elif document_type == "docx":
            try:
                from docx import Document
                doc = Document(file_path)
                text_parts = []
                for paragraph in doc.paragraphs:
                    text_parts.append(paragraph.text)
                return "\n\n".join(text_parts)
            except ImportError:
                raise ImportError("python-docx not installed. Install with: pip install python-docx")
        
        else:
            raise ValueError(f"Unsupported document type: {document_type}")
    
    def chunk_text(
        self,
        text: str,
        document_id: str,
    ) -> List[DocumentChunk]:
        """
        Split text into chunks.
        
        Args:
            text: Full text content
            document_id: ID of the source document
            
        Returns:
            List of DocumentChunk objects
        """
        # Simple sentence-based chunking
        # TODO: Use tiktoken for accurate token counting
        
        sentences = text.replace("\n", " ").split(". ")
        chunks = []
        current_chunk = []
        current_length = 0
        chunk_index = 0
        
        for sentence in sentences:
            sentence = sentence.strip()
            if not sentence:
                continue
            
            sentence_length = len(sentence.split())
            
            if current_length + sentence_length > self.chunk_size and current_chunk:
                # Save current chunk
                chunk_content = ". ".join(current_chunk) + "."
                chunk_id = hashlib.md5(f"{document_id}:{chunk_index}".encode()).hexdigest()
                
                chunks.append(DocumentChunk(
                    chunk_id=chunk_id,
                    document_id=document_id,
                    chunk_index=chunk_index,
                    content=chunk_content,
                    token_count=current_length,
                ))
                
                # Start new chunk with overlap
                overlap_sentences = current_chunk[-2:] if len(current_chunk) >= 2 else current_chunk
                current_chunk = overlap_sentences + [sentence]
                current_length = sum(len(s.split()) for s in current_chunk)
                chunk_index += 1
            else:
                current_chunk.append(sentence)
                current_length += sentence_length
        
        # Save last chunk
        if current_chunk:
            chunk_content = ". ".join(current_chunk) + "."
            chunk_id = hashlib.md5(f"{document_id}:{chunk_index}".encode()).hexdigest()
            
            chunks.append(DocumentChunk(
                chunk_id=chunk_id,
                document_id=document_id,
                chunk_index=chunk_index,
                content=chunk_content,
                token_count=current_length,
            ))
        
        return chunks
    
    async def process_document(
        self,
        file_path: str,
        document_type: str,
        document_id: str,
        embeddings_provider=None,
    ) -> List[DocumentChunk]:
        """
        Process a complete document: extract, chunk, and optionally embed.
        
        Args:
            file_path: Path to the file
            document_type: Type (pdf, docx, txt)
            document_id: ID for the document
            embeddings_provider: Optional embeddings provider for generating vectors
            
        Returns:
            List of processed chunks
        """
        # Extract text
        text = await self.extract_text(file_path, document_type)
        
        # Chunk
        chunks = self.chunk_text(text, document_id)
        
        # Generate embeddings if provider is available
        if embeddings_provider:
            texts = [chunk.content for chunk in chunks]
            embeddings = await embeddings_provider.embed_batch(texts)
            
            for chunk, emb_result in zip(chunks, embeddings):
                chunk.embedding = emb_result.embedding
        
        return chunks
