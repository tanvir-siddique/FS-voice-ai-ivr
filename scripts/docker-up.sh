#!/bin/bash
# =============================================================================
# Voice AI IVR - Docker Up Script
# =============================================================================
# Usage:
#   ./scripts/docker-up.sh              # Start production
#   ./scripts/docker-up.sh dev          # Start development mode
#   ./scripts/docker-up.sh ollama       # Start with Ollama LLM
#   ./scripts/docker-up.sh all          # Start all services
#   ./scripts/docker-up.sh logs         # Show logs
#   ./scripts/docker-up.sh stop         # Stop all
# =============================================================================

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Navigate to project root
cd "$(dirname "$0")/.."

# Check .env file
if [ ! -f ".env" ]; then
    echo -e "${YELLOW}Warning: .env file not found. Copying from .env.example${NC}"
    cp .env.example .env
    echo -e "${RED}Please edit .env with your configuration before continuing${NC}"
    exit 1
fi

# Parse command
COMMAND="${1:-up}"
PROFILES=""

case $COMMAND in
    dev|development)
        PROFILES="--profile dev"
        echo -e "${BLUE}=== Starting Development Mode ===${NC}"
        ;;
    ollama)
        PROFILES="--profile ollama"
        echo -e "${BLUE}=== Starting with Ollama LLM ===${NC}"
        ;;
    all)
        PROFILES="--profile dev --profile ollama"
        echo -e "${BLUE}=== Starting All Services ===${NC}"
        ;;
    logs)
        echo -e "${BLUE}=== Showing Logs ===${NC}"
        docker compose logs -f
        exit 0
        ;;
    stop|down)
        echo -e "${BLUE}=== Stopping Services ===${NC}"
        docker compose --profile dev --profile ollama down
        echo -e "${GREEN}✓ All services stopped${NC}"
        exit 0
        ;;
    restart)
        echo -e "${BLUE}=== Restarting Services ===${NC}"
        docker compose restart
        exit 0
        ;;
    status)
        echo -e "${BLUE}=== Service Status ===${NC}"
        docker compose ps
        exit 0
        ;;
    up|start)
        echo -e "${BLUE}=== Starting Production Mode ===${NC}"
        ;;
    *)
        echo "Usage: $0 {up|dev|ollama|all|logs|stop|restart|status}"
        exit 1
        ;;
esac

# Start services
echo -e "${YELLOW}Starting services...${NC}"
docker compose $PROFILES up -d --build

# Wait for health checks
echo -e "${YELLOW}Waiting for services to be healthy...${NC}"
sleep 5

# Check status
echo ""
echo -e "${BLUE}=== Service Status ===${NC}"
docker compose ps

# Check health
echo ""
echo -e "${BLUE}=== Health Check ===${NC}"

# Voice AI Service
if curl -s http://localhost:8100/health | grep -q "healthy"; then
    echo -e "${GREEN}✓ Voice AI Service: healthy${NC}"
else
    echo -e "${RED}✗ Voice AI Service: not responding${NC}"
fi

# Redis
if docker exec voice-ai-redis redis-cli ping | grep -q "PONG"; then
    echo -e "${GREEN}✓ Redis: healthy${NC}"
else
    echo -e "${RED}✗ Redis: not responding${NC}"
fi

# Ollama (if running)
if docker ps | grep -q "voice-ai-ollama"; then
    if curl -s http://localhost:11434/api/tags > /dev/null 2>&1; then
        echo -e "${GREEN}✓ Ollama: healthy${NC}"
    else
        echo -e "${YELLOW}○ Ollama: starting...${NC}"
    fi
fi

echo ""
echo -e "${GREEN}Services started!${NC}"
echo -e "Voice AI Service: ${BLUE}http://localhost:8100${NC}"
echo -e "API Docs:         ${BLUE}http://localhost:8100/docs${NC}"
