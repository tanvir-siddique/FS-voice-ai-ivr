#!/bin/bash
# =============================================================================
# Voice AI IVR - Install Piper TTS Voices
# =============================================================================
# Downloads Portuguese voices for Piper TTS
# =============================================================================

set -e

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}=== Installing Piper TTS Voices ===${NC}"

# Directory for voices
VOICE_DIR="${PIPER_DATA_DIR:-./voice-ai-service/data/piper}"
mkdir -p "$VOICE_DIR"

# Base URL for Piper voices
BASE_URL="https://huggingface.co/rhasspy/piper-voices/resolve/main"

# Portuguese Brazilian voices
VOICES=(
    "pt_BR/faber/medium/pt_BR-faber-medium.onnx"
    "pt_BR/faber/medium/pt_BR-faber-medium.onnx.json"
)

# Download voices
for voice in "${VOICES[@]}"; do
    filename=$(basename "$voice")
    if [ ! -f "$VOICE_DIR/$filename" ]; then
        echo -e "${YELLOW}Downloading: $filename${NC}"
        curl -L -o "$VOICE_DIR/$filename" "$BASE_URL/$voice"
        echo -e "${GREEN}✓ Downloaded: $filename${NC}"
    else
        echo -e "${GREEN}✓ Already exists: $filename${NC}"
    fi
done

echo ""
echo -e "${GREEN}=== Piper Voices Installed ===${NC}"
ls -la "$VOICE_DIR"

echo ""
echo -e "${BLUE}Voice directory: $VOICE_DIR${NC}"
