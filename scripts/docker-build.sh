#!/bin/bash
# =============================================================================
# Voice AI IVR - Docker Build Script
# =============================================================================
# Usage:
#   ./scripts/docker-build.sh          # Build production
#   ./scripts/docker-build.sh dev      # Build development
#   ./scripts/docker-build.sh --push   # Build and push to registry
# =============================================================================

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
IMAGE_NAME="${DOCKER_REGISTRY:-}voice-ai-service"
VERSION="${VERSION:-$(git describe --tags --always 2>/dev/null || echo 'latest')}"
BUILD_TARGET="production"
PUSH=false

# Parse arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        dev|development)
            BUILD_TARGET="development"
            shift
            ;;
        --push)
            PUSH=true
            shift
            ;;
        --version)
            VERSION="$2"
            shift 2
            ;;
        *)
            echo -e "${RED}Unknown option: $1${NC}"
            exit 1
            ;;
    esac
done

echo -e "${BLUE}=== Voice AI Service - Docker Build ===${NC}"
echo -e "Image:   ${GREEN}${IMAGE_NAME}:${VERSION}${NC}"
echo -e "Target:  ${GREEN}${BUILD_TARGET}${NC}"
echo ""

# Navigate to project root
cd "$(dirname "$0")/.."

# Build image
echo -e "${YELLOW}Building Docker image...${NC}"

docker build \
    --target "${BUILD_TARGET}" \
    --tag "${IMAGE_NAME}:${VERSION}" \
    --tag "${IMAGE_NAME}:latest" \
    --build-arg BUILD_DATE="$(date -u +'%Y-%m-%dT%H:%M:%SZ')" \
    --build-arg VERSION="${VERSION}" \
    --file voice-ai-service/Dockerfile \
    voice-ai-service/

echo -e "${GREEN}✓ Build complete: ${IMAGE_NAME}:${VERSION}${NC}"

# Push if requested
if [ "$PUSH" = true ]; then
    echo -e "${YELLOW}Pushing to registry...${NC}"
    docker push "${IMAGE_NAME}:${VERSION}"
    docker push "${IMAGE_NAME}:latest"
    echo -e "${GREEN}✓ Push complete${NC}"
fi

# Show image info
echo ""
echo -e "${BLUE}Image info:${NC}"
docker images "${IMAGE_NAME}" --format "table {{.Repository}}\t{{.Tag}}\t{{.Size}}\t{{.CreatedAt}}"
