"""
AWS Bedrock LLM Provider.

Uses Amazon Bedrock for access to various foundation models.
Supports Claude, Llama, Titan, and more.
"""

import os
import json
from typing import List, Optional

from .base import BaseLLM, Message, ChatResult


class AWSBedrockLLM(BaseLLM):
    """
    AWS Bedrock LLM provider.
    
    Config:
        aws_access_key_id: AWS access key
        aws_secret_access_key: AWS secret key
        region_name: AWS region (default: us-east-1)
        model_id: Bedrock model ID (default: anthropic.claude-3-sonnet-20240229-v1:0)
    """
    
    provider_name = "aws_bedrock"
    
    # Popular model IDs
    MODELS = {
        "claude-3-sonnet": "anthropic.claude-3-sonnet-20240229-v1:0",
        "claude-3-haiku": "anthropic.claude-3-haiku-20240307-v1:0",
        "claude-instant": "anthropic.claude-instant-v1",
        "llama-3-70b": "meta.llama3-70b-instruct-v1:0",
        "llama-3-8b": "meta.llama3-8b-instruct-v1:0",
        "titan-text": "amazon.titan-text-express-v1",
    }
    
    def __init__(self, config: dict):
        super().__init__(config)
        self.aws_access_key_id = config.get("aws_access_key_id")
        self.aws_secret_access_key = config.get("aws_secret_access_key")
        self.region_name = config.get("region_name", "us-east-1")
        
        # Allow model alias or full ID
        model = config.get("model_id") or config.get("model", "claude-3-sonnet")
        self.model_id = self.MODELS.get(model, model)
    
    def _get_client(self):
        """Get boto3 Bedrock runtime client."""
        try:
            import boto3
        except ImportError:
            raise ImportError(
                "boto3 not installed. Install with: pip install boto3"
            )
        
        kwargs = {"region_name": self.region_name}
        if self.aws_access_key_id:
            kwargs["aws_access_key_id"] = self.aws_access_key_id
        if self.aws_secret_access_key:
            kwargs["aws_secret_access_key"] = self.aws_secret_access_key
        
        return boto3.client("bedrock-runtime", **kwargs)
    
    async def chat(
        self,
        messages: List[Message],
        temperature: float = 0.7,
        max_tokens: int = 500,
        system_prompt: Optional[str] = None,
    ) -> ChatResult:
        """
        Generate chat completion using AWS Bedrock.
        
        Args:
            messages: Conversation history
            temperature: Creativity (0.0 to 1.0)
            max_tokens: Maximum tokens in response
            system_prompt: Optional system prompt
            
        Returns:
            ChatResult with generated response
        """
        import asyncio
        
        client = self._get_client()
        
        # Build request body based on model type
        if "anthropic" in self.model_id:
            body = self._build_claude_request(
                messages, temperature, max_tokens, system_prompt
            )
        elif "meta.llama" in self.model_id:
            body = self._build_llama_request(
                messages, temperature, max_tokens, system_prompt
            )
        else:
            body = self._build_titan_request(
                messages, temperature, max_tokens, system_prompt
            )
        
        # Invoke model
        loop = asyncio.get_event_loop()
        response = await loop.run_in_executor(
            None,
            lambda: client.invoke_model(
                modelId=self.model_id,
                body=json.dumps(body),
                contentType="application/json",
                accept="application/json",
            ),
        )
        
        # Parse response
        response_body = json.loads(response["body"].read())
        
        if "anthropic" in self.model_id:
            response_text = response_body["content"][0]["text"]
            tokens_used = response_body.get("usage", {}).get("input_tokens", 0) + \
                         response_body.get("usage", {}).get("output_tokens", 0)
        elif "meta.llama" in self.model_id:
            response_text = response_body.get("generation", "")
            tokens_used = 0
        else:
            response_text = response_body.get("results", [{}])[0].get("outputText", "")
            tokens_used = 0
        
        # Parse action from response
        action, extension, department = self.parse_action(response_text)
        
        return ChatResult(
            text=response_text,
            action=action,
            transfer_extension=extension,
            transfer_department=department,
            tokens_used=tokens_used,
            finish_reason="stop",
        )
    
    def _build_claude_request(
        self,
        messages: List[Message],
        temperature: float,
        max_tokens: int,
        system_prompt: Optional[str],
    ) -> dict:
        """Build request for Claude models."""
        api_messages = []
        
        for msg in messages:
            if msg.role == "system":
                continue
            api_messages.append({
                "role": msg.role,
                "content": msg.content,
            })
        
        return {
            "anthropic_version": "bedrock-2023-05-31",
            "max_tokens": max_tokens,
            "temperature": temperature,
            "system": system_prompt or "",
            "messages": api_messages,
        }
    
    def _build_llama_request(
        self,
        messages: List[Message],
        temperature: float,
        max_tokens: int,
        system_prompt: Optional[str],
    ) -> dict:
        """Build request for Llama models."""
        prompt_parts = []
        
        if system_prompt:
            prompt_parts.append(f"<|begin_of_text|><|start_header_id|>system<|end_header_id|>\n{system_prompt}<|eot_id|>")
        
        for msg in messages:
            role = msg.role
            prompt_parts.append(f"<|start_header_id|>{role}<|end_header_id|>\n{msg.content}<|eot_id|>")
        
        prompt_parts.append("<|start_header_id|>assistant<|end_header_id|>\n")
        
        return {
            "prompt": "\n".join(prompt_parts),
            "max_gen_len": max_tokens,
            "temperature": temperature,
        }
    
    def _build_titan_request(
        self,
        messages: List[Message],
        temperature: float,
        max_tokens: int,
        system_prompt: Optional[str],
    ) -> dict:
        """Build request for Titan models."""
        prompt_parts = []
        
        if system_prompt:
            prompt_parts.append(f"System: {system_prompt}")
        
        for msg in messages:
            role = "Human" if msg.role == "user" else "Assistant"
            prompt_parts.append(f"{role}: {msg.content}")
        
        prompt_parts.append("Assistant:")
        
        return {
            "inputText": "\n\n".join(prompt_parts),
            "textGenerationConfig": {
                "maxTokenCount": max_tokens,
                "temperature": temperature,
            },
        }
    
    async def is_available(self) -> bool:
        """Check if AWS Bedrock is available."""
        try:
            import boto3
            if self.aws_access_key_id or os.environ.get("AWS_ACCESS_KEY_ID"):
                return True
            return False
        except ImportError:
            return False
