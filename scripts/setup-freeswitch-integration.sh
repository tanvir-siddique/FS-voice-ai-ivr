#!/bin/bash
# =============================================================================
# Voice AI IVR - FreeSWITCH Integration Setup
# =============================================================================
# Run this on the HOST where FreeSWITCH is installed to configure integration
# with the Voice AI Docker containers.
#
# Usage: sudo ./scripts/setup-freeswitch-integration.sh
# =============================================================================

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Configuration
FREESWITCH_SCRIPTS_DIR="${FREESWITCH_SCRIPTS_DIR:-/usr/share/freeswitch/scripts}"
FREESWITCH_DIALPLAN_DIR="${FREESWITCH_DIALPLAN_DIR:-/etc/freeswitch/dialplan/default}"
VOICE_AI_URL="${VOICE_AI_URL:-http://localhost:8100}"
PROJECT_DIR="$(dirname "$0")/.."

echo -e "${BLUE}=== Voice AI IVR - FreeSWITCH Integration Setup ===${NC}"
echo ""

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}Please run as root: sudo $0${NC}"
    exit 1
fi

# Check FreeSWITCH installation
if [ ! -d "$FREESWITCH_SCRIPTS_DIR" ]; then
    echo -e "${RED}FreeSWITCH scripts directory not found: $FREESWITCH_SCRIPTS_DIR${NC}"
    echo "Set FREESWITCH_SCRIPTS_DIR environment variable if different"
    exit 1
fi

echo -e "${YELLOW}FreeSWITCH scripts: $FREESWITCH_SCRIPTS_DIR${NC}"
echo -e "${YELLOW}FreeSWITCH dialplan: $FREESWITCH_DIALPLAN_DIR${NC}"
echo -e "${YELLOW}Voice AI URL: $VOICE_AI_URL${NC}"
echo ""

# 1. Copy Lua scripts
echo -e "${BLUE}1. Installing Lua scripts...${NC}"

# Create lib directory
mkdir -p "$FREESWITCH_SCRIPTS_DIR/lib"

# Copy scripts
cp -v "$PROJECT_DIR/freeswitch/scripts/secretary_ai.lua" "$FREESWITCH_SCRIPTS_DIR/"
cp -v "$PROJECT_DIR/freeswitch/scripts/lib/"*.lua "$FREESWITCH_SCRIPTS_DIR/lib/"

# Update config.lua with correct URL
if [ -f "$FREESWITCH_SCRIPTS_DIR/lib/config.lua" ]; then
    sed -i "s|http://localhost:8100|$VOICE_AI_URL|g" "$FREESWITCH_SCRIPTS_DIR/lib/config.lua"
    echo -e "${GREEN}✓ Updated Voice AI URL in config.lua${NC}"
fi

# Set permissions
chown -R freeswitch:freeswitch "$FREESWITCH_SCRIPTS_DIR"
chmod 755 "$FREESWITCH_SCRIPTS_DIR"/*.lua
chmod 755 "$FREESWITCH_SCRIPTS_DIR/lib"/*.lua

echo -e "${GREEN}✓ Lua scripts installed${NC}"

# 2. Copy dialplan
echo -e "${BLUE}2. Installing dialplan...${NC}"

cp -v "$PROJECT_DIR/freeswitch/dialplan/secretary_extension.xml" "$FREESWITCH_DIALPLAN_DIR/"
cp -v "$PROJECT_DIR/freeswitch/dialplan/secretary.xml" "$FREESWITCH_DIALPLAN_DIR/"

chown freeswitch:freeswitch "$FREESWITCH_DIALPLAN_DIR/secretary"*.xml
chmod 644 "$FREESWITCH_DIALPLAN_DIR/secretary"*.xml

echo -e "${GREEN}✓ Dialplan installed${NC}"

# 3. Test Voice AI connection
echo -e "${BLUE}3. Testing Voice AI connection...${NC}"

if curl -s "$VOICE_AI_URL/health" | grep -q "healthy"; then
    echo -e "${GREEN}✓ Voice AI Service is healthy${NC}"
else
    echo -e "${YELLOW}⚠ Voice AI Service not responding at $VOICE_AI_URL${NC}"
    echo "  Make sure Docker containers are running: docker compose up -d"
fi

# 4. Reload FreeSWITCH
echo -e "${BLUE}4. Reloading FreeSWITCH dialplan...${NC}"

if command -v fs_cli &> /dev/null; then
    fs_cli -x "reloadxml"
    echo -e "${GREEN}✓ FreeSWITCH reloaded${NC}"
else
    echo -e "${YELLOW}⚠ fs_cli not found. Please reload FreeSWITCH manually:${NC}"
    echo "  fs_cli -x 'reloadxml'"
fi

# 5. Summary
echo ""
echo -e "${GREEN}=== Installation Complete ===${NC}"
echo ""
echo "Next steps:"
echo "1. Configure a secretary in FusionPBX"
echo "2. Set up an extension to route to the secretary (e.g., 8000)"
echo "3. Test by calling the extension"
echo ""
echo "Files installed:"
echo "  - $FREESWITCH_SCRIPTS_DIR/secretary_ai.lua"
echo "  - $FREESWITCH_SCRIPTS_DIR/lib/*.lua"
echo "  - $FREESWITCH_DIALPLAN_DIR/secretary*.xml"
echo ""
echo "To test:"
echo "  curl $VOICE_AI_URL/health"
echo "  fs_cli -x 'lua secretary_ai.lua'"
