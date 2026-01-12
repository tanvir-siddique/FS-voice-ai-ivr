"""
LM Studio Local LLM Provider.

Uses LM Studio's local inference server for completely offline LLM.
Compatible with OpenAI API format.
"""

from typing import List, Optional

import httpx

from .base import BaseLLM, Message, ChatResult


class LMStudioLLM(BaseLLM):
    """
    LM Studio local LLM provider.
    
    LM Studio provides a local OpenAI-compatible server.
    
    Config:
        base_url: LM Studio server URL (default: http://localhost:1234/v1)
        model: Model identifier (optional, LM Studio uses loaded model)
        timeout: Request timeout in seconds (default: 120)
    """
    
    provider_name = "lmstudio_local"
    
    def __init__(self, config: dict):
        super().__init__(config)
        self.base_url = config.get("base_url", "http://localhost:1234/v1")
        self.model = config.get("model", "local-model")
        self.timeout = config.get("timeout", 120)
    
    async def chat(
        self,
        messages: List[Message],
        temperature: float = 0.7,
        max_tokens: int = 500,
        system_prompt: Optional[str] = None,
    ) -> ChatResult:
        """
        Generate chat completion using LM Studio.
        
        Args:
            messages: Conversation history
            temperature: Creativity (0.0 to 1.0)
            max_tokens: Maximum tokens in response
            system_prompt: Optional system prompt
            
        Returns:
            ChatResult with generated response
        """
        # Build messages list (OpenAI format)
        api_messages = []
        
        # Add system prompt if provided
        if system_prompt:
            api_messages.append({
                "role": "system",
                "content": system_prompt,
            })
        
        # Add conversation messages
        for msg in messages:
            api_messages.append({
                "role": msg.role,
                "content": msg.content,
            })
        
        # Call LM Studio API (OpenAI-compatible)
        async with httpx.AsyncClient(timeout=self.timeout) as client:
            response = await client.post(
                f"{self.base_url}/chat/completions",
                json={
                    "model": self.model,
                    "messages": api_messages,
                    "temperature": temperature,
                    "max_tokens": max_tokens,
                    "stream": False,
                },
            )
            response.raise_for_status()
            data = response.json()
        
        # Extract response (OpenAI format)
        choice = data.get("choices", [{}])[0]
        message = choice.get("message", {})
        response_text = message.get("content", "")
        
        # Parse action from response
        action, extension, department = self.parse_action(response_text)
        
        # Get token usage
        usage = data.get("usage", {})
        tokens_used = usage.get("total_tokens", 0)
        
        return ChatResult(
            text=response_text,
            action=action,
            transfer_extension=extension,
            transfer_department=department,
            tokens_used=tokens_used,
            finish_reason=choice.get("finish_reason"),
        )
    
    async def is_available(self) -> bool:
        """Check if LM Studio is available."""
        try:
            async with httpx.AsyncClient(timeout=5) as client:
                response = await client.get(f"{self.base_url}/models")
                return response.status_code == 200
        except Exception:
            return False
