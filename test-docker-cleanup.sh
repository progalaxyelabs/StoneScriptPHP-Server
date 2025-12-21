#!/bin/bash
# Cleanup script for Docker test environment

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

TEST_DIR="./test-docker-env"

echo -e "${BLUE}╔════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║   Cleaning up Docker test environment     ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════════╝${NC}"
echo ""

if [ ! -d "$TEST_DIR" ]; then
    echo -e "${YELLOW}No test environment found to clean up.${NC}"
    exit 0
fi

echo -e "${YELLOW}Stopping containers...${NC}"
(cd "$TEST_DIR" && docker compose down -v 2>/dev/null) || true
echo -e "${GREEN}  ✓ Containers stopped${NC}"

echo -e "${YELLOW}Removing test directory...${NC}"
rm -rf "$TEST_DIR"
echo -e "${GREEN}  ✓ Test directory removed${NC}"

echo -e "${YELLOW}Removing dangling images...${NC}"
docker image prune -f > /dev/null 2>&1 || true
echo -e "${GREEN}  ✓ Dangling images removed${NC}"

echo ""
echo -e "${GREEN}✓ Cleanup complete!${NC}"
