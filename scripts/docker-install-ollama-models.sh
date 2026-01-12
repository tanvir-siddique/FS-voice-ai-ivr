#!/bin/bash
# =============================================================================
# Voice AI IVR - Install Ollama Models
# =============================================================================
# Downloads LLM models for local inference
# =============================================================================

set -e

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}=== Installing Ollama Models ===${NC}"

# Check if Ollama is running
if ! docker ps | grep -q "voice-ai-ollama"; then
    echo -e "${YELLOW}Starting Ollama container...${NC}"
    cd "$(dirname "$0")/.."
    docker compose --profile ollama up -d ollama
    sleep 10
fi

# Models to install
MODELS=(
    "llama3.2"           # Fast, good for chat (2GB)
    "llama3.2:1b"        # Ultra-fast, smaller (1.3GB)
    # "mistral"          # Alternative (4GB)
    # "phi"              # Microsoft, small (1.6GB)
)

# Install models
for model in "${MODELS[@]}"; do
    echo -e "${YELLOW}Pulling model: ${model}${NC}"
    docker exec voice-ai-ollama ollama pull "$model"
    echo -e "${GREEN}✓ Installed: ${model}${NC}"
done

echo ""
echo -e "${GREEN}=== Installed Models ===${NC}"
docker exec voice-ai-ollama ollama list

echo ""
echo -e "${BLUE}To test:${NC}"
echo "docker exec -it voice-ai-ollama ollama run llama3.2 'Olá, como vai?'"
