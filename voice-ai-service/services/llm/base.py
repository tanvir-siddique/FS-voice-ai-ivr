"""
Base interface for LLM providers.

All LLM providers MUST implement this interface.
"""

from abc import ABC, abstractmethod
from dataclasses import dataclass, field
from typing import Optional, List


@dataclass
class Message:
    """Chat message."""
    
    role: str  # system, user, assistant
    content: str


@dataclass
class ChatResult:
    """Result from chat completion."""
    
    text: str
    action: str = "continue"  # continue, transfer, hangup
    transfer_extension: Optional[str] = None
    transfer_department: Optional[str] = None
    intent: Optional[str] = None
    tokens_used: int = 0
    finish_reason: Optional[str] = None


class BaseLLM(ABC):
    """
    Abstract base class for LLM providers.
    
    All implementations MUST:
    - Be stateless (no domain-specific data stored)
    - Accept config dict in __init__
    - Implement chat() method
    """
    
    provider_name: str = "base"
    
    def __init__(self, config: dict):
        """
        Initialize the provider with configuration.
        
        Args:
            config: Provider-specific configuration (API keys, models, etc.)
        """
        self.config = config
    
    @abstractmethod
    async def chat(
        self,
        messages: List[Message],
        temperature: float = 0.7,
        max_tokens: int = 500,
        system_prompt: Optional[str] = None,
    ) -> ChatResult:
        """
        Generate a chat completion.
        
        Args:
            messages: Conversation history
            temperature: Creativity (0.0 to 1.0)
            max_tokens: Maximum tokens in response
            system_prompt: System prompt to prepend
            
        Returns:
            ChatResult with generated response
            
        Raises:
            Exception: If generation fails
        """
        pass
    
    @abstractmethod
    async def is_available(self) -> bool:
        """
        Check if the provider is available and properly configured.
        
        Returns:
            True if provider is ready to use
        """
        pass
    
    def get_name(self) -> str:
        """Get provider name."""
        return self.provider_name
    
    def parse_action(self, response_text: str) -> tuple:
        """
        Parse action from LLM response.
        
        Looks for patterns like:
        - [TRANSFERIR:100] or [TRANSFER:100]
        - [ENCERRAR] or [HANGUP]
        
        Returns:
            Tuple of (action, extension, department)
        """
        import re
        
        text = response_text.upper()
        
        # Check for transfer
        transfer_match = re.search(r'\[TRANSFER(?:IR)?[:\s]+(\d+)(?:[,\s]+(.+?))?\]', text)
        if transfer_match:
            extension = transfer_match.group(1)
            department = transfer_match.group(2) if transfer_match.group(2) else None
            return "transfer", extension, department
        
        # Check for hangup
        if '[ENCERRAR]' in text or '[HANGUP]' in text or '[DESLIGAR]' in text:
            return "hangup", None, None
        
        return "continue", None, None
