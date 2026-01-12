#!/bin/bash
# Health check script for Voice AI Service
# Can be used with monitoring systems (Nagios, Zabbix, etc.)

set -e

# Configuration
SERVICE_URL="${VOICE_AI_URL:-http://localhost:8100}"
TIMEOUT="${HEALTH_CHECK_TIMEOUT:-5}"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to check endpoint
check_endpoint() {
    local endpoint=$1
    local description=$2
    
    response=$(curl -s -o /dev/null -w "%{http_code}" --max-time "$TIMEOUT" "${SERVICE_URL}${endpoint}" 2>/dev/null || echo "000")
    
    if [ "$response" = "200" ]; then
        echo -e "${GREEN}✓${NC} $description: OK"
        return 0
    else
        echo -e "${RED}✗${NC} $description: FAILED (HTTP $response)"
        return 1
    fi
}

# Function to check service health
check_health() {
    echo "=== Voice AI Service Health Check ==="
    echo "URL: $SERVICE_URL"
    echo "Timeout: ${TIMEOUT}s"
    echo ""
    
    local failed=0
    
    # Check main health endpoint
    if ! check_endpoint "/health" "Health endpoint"; then
        failed=$((failed + 1))
    fi
    
    # Check API root
    if ! check_endpoint "/" "API root"; then
        failed=$((failed + 1))
    fi
    
    # Check docs endpoint
    if ! check_endpoint "/docs" "OpenAPI docs"; then
        failed=$((failed + 1))
    fi
    
    echo ""
    
    if [ $failed -gt 0 ]; then
        echo -e "${RED}UNHEALTHY${NC}: $failed check(s) failed"
        exit 2
    else
        echo -e "${GREEN}HEALTHY${NC}: All checks passed"
        exit 0
    fi
}

# Function to check systemd service status
check_systemd() {
    echo "=== Systemd Service Status ==="
    
    if systemctl is-active --quiet voice-ai-service; then
        echo -e "${GREEN}✓${NC} Service is running"
        
        # Get more details
        echo ""
        echo "Service details:"
        systemctl status voice-ai-service --no-pager | head -15
    else
        echo -e "${RED}✗${NC} Service is not running"
        
        echo ""
        echo "Last logs:"
        journalctl -u voice-ai-service --no-pager -n 20
        exit 2
    fi
}

# Function to check database connection
check_database() {
    echo "=== Database Connection Check ==="
    
    # Try to make a simple API call that requires DB
    response=$(curl -s --max-time "$TIMEOUT" "${SERVICE_URL}/api/v1/documents?domain_uuid=test" 2>/dev/null || echo "error")
    
    if echo "$response" | grep -q "error\|failed\|connection"; then
        echo -e "${YELLOW}⚠${NC} Database may have issues"
        echo "Response: $response"
    else
        echo -e "${GREEN}✓${NC} Database appears to be connected"
    fi
}

# Function for JSON output (for monitoring systems)
json_output() {
    response=$(curl -s --max-time "$TIMEOUT" "${SERVICE_URL}/health" 2>/dev/null || echo '{"status":"error"}')
    
    # Add timestamp
    timestamp=$(date -u +"%Y-%m-%dT%H:%M:%SZ")
    
    echo "$response" | jq --arg ts "$timestamp" '. + {checked_at: $ts}' 2>/dev/null || echo "{\"status\":\"error\",\"checked_at\":\"$timestamp\"}"
}

# Main
case "${1:-health}" in
    health)
        check_health
        ;;
    systemd)
        check_systemd
        ;;
    database)
        check_database
        ;;
    json)
        json_output
        ;;
    all)
        check_systemd
        echo ""
        check_health
        echo ""
        check_database
        ;;
    *)
        echo "Usage: $0 {health|systemd|database|json|all}"
        echo ""
        echo "Options:"
        echo "  health   - Check HTTP endpoints (default)"
        echo "  systemd  - Check systemd service status"
        echo "  database - Check database connectivity"
        echo "  json     - Output health as JSON"
        echo "  all      - Run all checks"
        exit 1
        ;;
esac
