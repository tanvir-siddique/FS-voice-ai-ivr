"""
Webhook API - Integration with OmniPlay and external systems.

MULTI-TENANT: All operations require domain_uuid.
"""

from typing import List, Optional, Dict, Any

import httpx
from fastapi import APIRouter, HTTPException, BackgroundTasks
from pydantic import BaseModel, Field

from models.request import BaseRequest
from config.settings import settings


router = APIRouter(prefix="/webhooks", tags=["webhooks"])


class WebhookPayload(BaseModel):
    """Standard webhook payload for OmniPlay integration."""
    event: str = Field(..., description="Event type: voice_ai_conversation")
    domain_uuid: str
    conversation_uuid: str
    caller_id: str
    secretary_name: Optional[str] = None
    summary: Optional[str] = None
    action: str  # hangup, transfer
    transfer_target: Optional[str] = None
    duration_seconds: Optional[int] = None
    messages: List[Dict[str, Any]] = Field(default_factory=list)
    timestamp: str


class SendWebhookRequest(BaseRequest):
    """Request to send webhook to OmniPlay."""
    webhook_url: str
    api_key: Optional[str] = None
    payload: WebhookPayload


class WebhookResponse(BaseModel):
    """Response from webhook sending."""
    success: bool
    status_code: Optional[int] = None
    message: str


async def send_webhook_async(
    url: str,
    payload: dict,
    api_key: Optional[str] = None,
    timeout: int = 30,
) -> dict:
    """
    Send webhook to external URL.
    
    Returns:
        Dict with success, status_code, message
    """
    headers = {
        "Content-Type": "application/json",
        "X-Webhook-Source": "voice-ai-secretary",
        "X-Voice-AI-Version": "1.0",
    }
    
    if api_key:
        headers["Authorization"] = f"Bearer {api_key}"
    
    try:
        async with httpx.AsyncClient(timeout=timeout) as client:
            response = await client.post(url, json=payload, headers=headers)
            
            return {
                "success": 200 <= response.status_code < 300,
                "status_code": response.status_code,
                "message": f"HTTP {response.status_code}",
            }
    
    except httpx.TimeoutException:
        return {
            "success": False,
            "status_code": None,
            "message": "Request timeout",
        }
    except httpx.RequestError as e:
        return {
            "success": False,
            "status_code": None,
            "message": str(e),
        }


@router.post("/send", response_model=WebhookResponse)
async def send_webhook(request: SendWebhookRequest, background_tasks: BackgroundTasks):
    """
    Send a webhook to an external URL.
    
    Used by Lua scripts or other services to trigger webhooks.
    """
    if not request.domain_uuid:
        raise HTTPException(status_code=400, detail="domain_uuid is required")
    
    # Send synchronously for immediate feedback
    result = await send_webhook_async(
        url=request.webhook_url,
        payload=request.payload.dict(),
        api_key=request.api_key,
    )
    
    return WebhookResponse(**result)


class OmniPlayTicketRequest(BaseRequest):
    """Request to create a ticket in OmniPlay."""
    omniplay_url: str
    api_key: str
    caller_id: str
    caller_name: Optional[str] = None
    conversation_uuid: str
    summary: str
    transcript: str
    channel: str = "voice"
    queue_id: Optional[str] = None


class OmniPlayTicketResponse(BaseModel):
    """Response from OmniPlay ticket creation."""
    success: bool
    ticket_id: Optional[str] = None
    message: str


@router.post("/omniplay/ticket", response_model=OmniPlayTicketResponse)
async def create_omniplay_ticket(request: OmniPlayTicketRequest):
    """
    Create a ticket in OmniPlay from a voice AI conversation.
    
    This endpoint is called when a conversation ends and needs
    to be escalated or tracked in OmniPlay.
    """
    if not request.domain_uuid:
        raise HTTPException(status_code=400, detail="domain_uuid is required")
    
    # Build OmniPlay ticket payload
    # Adjust this based on your OmniPlay API
    ticket_payload = {
        "contact": {
            "number": request.caller_id,
            "name": request.caller_name or request.caller_id,
        },
        "channel": request.channel,
        "queueId": request.queue_id,
        "body": request.transcript,
        "metadata": {
            "source": "voice-ai-secretary",
            "conversation_uuid": request.conversation_uuid,
            "summary": request.summary,
        },
    }
    
    headers = {
        "Content-Type": "application/json",
        "Authorization": f"Bearer {request.api_key}",
    }
    
    try:
        async with httpx.AsyncClient(timeout=30) as client:
            response = await client.post(
                f"{request.omniplay_url}/api/v1/tickets",
                json=ticket_payload,
                headers=headers,
            )
            
            if 200 <= response.status_code < 300:
                data = response.json()
                return OmniPlayTicketResponse(
                    success=True,
                    ticket_id=data.get("id") or data.get("ticketId"),
                    message="Ticket created successfully",
                )
            else:
                return OmniPlayTicketResponse(
                    success=False,
                    message=f"OmniPlay returned {response.status_code}: {response.text}",
                )
    
    except Exception as e:
        return OmniPlayTicketResponse(
            success=False,
            message=str(e),
        )


@router.get("/health")
async def webhook_health():
    """Health check for webhook service."""
    return {"status": "ok", "service": "webhooks"}
