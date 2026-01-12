"""
Session Manager - Manages conversation context and history.

MULTI-TENANT: Each session is scoped to a domain.
Stores conversation history for context in LLM calls.
"""

import time
import asyncio
from dataclasses import dataclass, field
from typing import Dict, List, Optional, Any
from datetime import datetime, timedelta


@dataclass
class Message:
    """A single message in a conversation."""
    role: str  # "user", "assistant", "system"
    content: str
    timestamp: float = field(default_factory=time.time)
    metadata: Dict[str, Any] = field(default_factory=dict)


@dataclass
class Session:
    """A conversation session."""
    session_id: str
    domain_uuid: str
    secretary_uuid: str
    caller_id: str
    
    # Conversation history
    messages: List[Message] = field(default_factory=list)
    
    # Session metadata
    created_at: float = field(default_factory=time.time)
    last_activity: float = field(default_factory=time.time)
    
    # Context from RAG
    rag_context: List[str] = field(default_factory=list)
    
    # Current state
    is_active: bool = True
    transfer_target: Optional[str] = None
    
    def add_message(self, role: str, content: str, **metadata):
        """Add a message to the conversation."""
        self.messages.append(Message(
            role=role,
            content=content,
            metadata=metadata,
        ))
        self.last_activity = time.time()
    
    def get_history(self, max_messages: int = 10) -> List[Dict]:
        """Get recent message history for LLM context."""
        recent = self.messages[-max_messages:]
        return [
            {"role": m.role, "content": m.content}
            for m in recent
        ]
    
    def get_duration_seconds(self) -> float:
        """Get session duration in seconds."""
        return time.time() - self.created_at


class SessionManager:
    """
    Manages conversation sessions.
    
    Thread-safe, supports multiple concurrent sessions.
    Sessions expire after inactivity.
    """
    
    def __init__(
        self,
        session_timeout_minutes: int = 30,
        cleanup_interval_minutes: int = 5,
    ):
        """
        Initialize session manager.
        
        Args:
            session_timeout_minutes: Sessions expire after this many minutes of inactivity
            cleanup_interval_minutes: How often to clean up expired sessions
        """
        self._sessions: Dict[str, Session] = {}
        self._lock = asyncio.Lock()
        self._timeout_seconds = session_timeout_minutes * 60
        self._cleanup_interval = cleanup_interval_minutes * 60
        self._cleanup_task: Optional[asyncio.Task] = None
    
    async def start(self):
        """Start the cleanup background task."""
        if self._cleanup_task is None:
            self._cleanup_task = asyncio.create_task(self._cleanup_loop())
    
    async def stop(self):
        """Stop the cleanup background task."""
        if self._cleanup_task:
            self._cleanup_task.cancel()
            try:
                await self._cleanup_task
            except asyncio.CancelledError:
                pass
            self._cleanup_task = None
    
    async def create_session(
        self,
        session_id: str,
        domain_uuid: str,
        secretary_uuid: str,
        caller_id: str,
        system_prompt: Optional[str] = None,
    ) -> Session:
        """
        Create a new session.
        
        Args:
            session_id: Unique session identifier (e.g., call UUID)
            domain_uuid: Tenant identifier (REQUIRED)
            secretary_uuid: Secretary configuration to use
            caller_id: Caller's phone number
            system_prompt: Optional system prompt to add
            
        Returns:
            New Session object
        """
        if not domain_uuid:
            raise ValueError("domain_uuid is required for multi-tenant isolation")
        
        session = Session(
            session_id=session_id,
            domain_uuid=domain_uuid,
            secretary_uuid=secretary_uuid,
            caller_id=caller_id,
        )
        
        # Add system prompt if provided
        if system_prompt:
            session.add_message("system", system_prompt)
        
        async with self._lock:
            self._sessions[session_id] = session
        
        return session
    
    async def get_session(self, session_id: str) -> Optional[Session]:
        """
        Get an existing session.
        
        Args:
            session_id: Session to retrieve
            
        Returns:
            Session or None if not found/expired
        """
        async with self._lock:
            session = self._sessions.get(session_id)
            
            if session and self._is_expired(session):
                del self._sessions[session_id]
                return None
            
            return session
    
    async def add_message(
        self,
        session_id: str,
        role: str,
        content: str,
        **metadata,
    ) -> bool:
        """
        Add a message to a session.
        
        Args:
            session_id: Session to update
            role: "user" or "assistant"
            content: Message content
            **metadata: Additional metadata
            
        Returns:
            True if message was added, False if session not found
        """
        session = await self.get_session(session_id)
        if session:
            session.add_message(role, content, **metadata)
            return True
        return False
    
    async def set_rag_context(
        self,
        session_id: str,
        context: List[str],
    ) -> bool:
        """
        Set RAG context for a session.
        
        Args:
            session_id: Session to update
            context: List of relevant document chunks
            
        Returns:
            True if context was set
        """
        session = await self.get_session(session_id)
        if session:
            session.rag_context = context
            return True
        return False
    
    async def end_session(
        self,
        session_id: str,
        transfer_target: Optional[str] = None,
    ) -> Optional[Session]:
        """
        End a session and return it for logging.
        
        Args:
            session_id: Session to end
            transfer_target: If call was transferred, the target
            
        Returns:
            The ended session, or None if not found
        """
        async with self._lock:
            session = self._sessions.pop(session_id, None)
            
            if session:
                session.is_active = False
                session.transfer_target = transfer_target
            
            return session
    
    async def get_active_sessions_count(self, domain_uuid: Optional[str] = None) -> int:
        """
        Get count of active sessions.
        
        Args:
            domain_uuid: Optional filter by domain
            
        Returns:
            Number of active sessions
        """
        async with self._lock:
            if domain_uuid:
                return sum(
                    1 for s in self._sessions.values()
                    if s.domain_uuid == domain_uuid and not self._is_expired(s)
                )
            return len(self._sessions)
    
    def _is_expired(self, session: Session) -> bool:
        """Check if a session has expired."""
        return time.time() - session.last_activity > self._timeout_seconds
    
    async def _cleanup_loop(self):
        """Background task to clean up expired sessions."""
        while True:
            try:
                await asyncio.sleep(self._cleanup_interval)
                await self._cleanup_expired()
            except asyncio.CancelledError:
                break
            except Exception:
                # Log error but continue
                pass
    
    async def _cleanup_expired(self):
        """Remove expired sessions."""
        async with self._lock:
            expired = [
                sid for sid, session in self._sessions.items()
                if self._is_expired(session)
            ]
            for sid in expired:
                del self._sessions[sid]


# Global session manager instance
_session_manager: Optional[SessionManager] = None


def get_session_manager() -> SessionManager:
    """Get the global session manager instance."""
    global _session_manager
    if _session_manager is None:
        _session_manager = SessionManager()
    return _session_manager
