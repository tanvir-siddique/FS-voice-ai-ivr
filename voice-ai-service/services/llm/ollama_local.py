"""
Ollama Local LLM Provider.

Supports any model running on local Ollama instance.
Zero cost, completely offline.
"""

from typing import List, Optional

import httpx

from .base import BaseLLM, Message, ChatResult


class OllamaLLM(BaseLLM):
    """
    Ollama local LLM provider.
    
    Config:
        base_url: Ollama server URL (default: http://localhost:11434)
        model: Model name (default: llama3)
        timeout: Request timeout in seconds (default: 60)
    """
    
    provider_name = "ollama_local"
    
    def __init__(self, config: dict):
        super().__init__(config)
        self.base_url = config.get("base_url", "http://localhost:11434")
        self.model = config.get("model", "llama3")
        self.timeout = config.get("timeout", 60)
    
    async def chat(
        self,
        messages: List[Message],
        temperature: float = 0.7,
        max_tokens: int = 500,
        system_prompt: Optional[str] = None,
    ) -> ChatResult:
        """
        Generate chat completion using local Ollama.
        
        Args:
            messages: Conversation history
            temperature: Creativity (0.0 to 1.0)
            max_tokens: Maximum tokens in response
            system_prompt: Optional system prompt
            
        Returns:
            ChatResult with generated response
        """
        # Build messages list
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
        
        # Call Ollama API
        async with httpx.AsyncClient(timeout=self.timeout) as client:
            response = await client.post(
                f"{self.base_url}/api/chat",
                json={
                    "model": self.model,
                    "messages": api_messages,
                    "stream": False,
                    "options": {
                        "temperature": temperature,
                        "num_predict": max_tokens,
                    },
                },
            )
            response.raise_for_status()
            data = response.json()
        
        # Extract response
        response_text = data.get("message", {}).get("content", "")
        
        # Parse action from response
        action, extension, department = self.parse_action(response_text)
        
        # Ollama doesn't provide detailed token counts in non-streaming mode
        tokens_used = data.get("eval_count", 0) + data.get("prompt_eval_count", 0)
        
        return ChatResult(
            text=response_text,
            action=action,
            transfer_extension=extension,
            transfer_department=department,
            tokens_used=tokens_used,
            finish_reason="stop" if data.get("done") else None,
        )
    
    async def is_available(self) -> bool:
        """Check if Ollama is available."""
        try:
            async with httpx.AsyncClient(timeout=5) as client:
                response = await client.get(f"{self.base_url}/api/tags")
                return response.status_code == 200
        except Exception:
            return False
