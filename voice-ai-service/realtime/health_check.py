"""
Health Check client for Realtime WebSocket Server.
Performs a proper WebSocket handshake to verify server health without generating log errors.
"""

import asyncio
import sys
import logging
from websockets.asyncio.client import connect

# Configure logging to suppress verbose output during health check
logging.basicConfig(level=logging.ERROR)

async def check():
    uri = "ws://localhost:8085/health"
    try:
        # Try to connect. The server should accept and immediately close with 1000 OK
        async with connect(uri, open_timeout=5) as ws:
            await ws.wait_closed()
            
        # If we got here without exception, connection was successful
        sys.exit(0)
    except Exception as e:
        # print(f"Health check failed: {e}", file=sys.stderr)
        sys.exit(1)

if __name__ == "__main__":
    asyncio.run(check())
