#!/bin/bash
# Test Docker Setup Script
# This script tests the Docker configuration by:
# 1. Creating a test environment
# 2. Copying server code to test/api/
# 3. Copying framework to vendor/ (mimicking Packagist install)
# 4. Building and running containers
# 5. Checking for errors

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
TEST_DIR="./test-docker-env"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
FRAMEWORK_DIR="$(dirname "$SCRIPT_DIR")/StoneScriptPHP"

echo -e "${BLUE}╔════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║   StoneScriptPHP Docker Test Suite        ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════════╝${NC}"
echo ""

# Step 1: Cleanup and create test directory
echo -e "${YELLOW}[1/9]${NC} Cleaning up old test environment..."
if [ -d "$TEST_DIR" ]; then
    echo "  → Stopping existing containers..."
    (cd "$TEST_DIR" && docker compose down -v 2>/dev/null) || true
    echo "  → Removing old test directory..."
    rm -rf "$TEST_DIR"
fi

echo -e "${YELLOW}[2/9]${NC} Creating test directory structure..."
mkdir -p "$TEST_DIR/api"
mkdir -p "$TEST_DIR/db"
echo "  ✓ Created $TEST_DIR/"

# Step 2: Copy server code
echo -e "${YELLOW}[3/9]${NC} Copying server code to test/api/..."
rsync -a --exclude='test-docker-env' \
         --exclude='.git' \
         --exclude='vendor' \
         --exclude='logs' \
         --exclude='.env' \
         --exclude='keys' \
         "$SCRIPT_DIR/" "$TEST_DIR/api/"
echo "  ✓ Server code copied"

# Step 3: Run composer install in API directory
echo -e "${YELLOW}[4/9]${NC} Running composer install..."
(cd "$TEST_DIR/api" && composer install --no-interaction --optimize-autoloader)
echo "  ✓ Composer dependencies installed"

# Step 4: Copy framework to vendor (mimicking Packagist)
echo -e "${YELLOW}[5/9]${NC} Copying framework to vendor/progalaxyelabs/stonescriptphp..."
if [ ! -d "$FRAMEWORK_DIR" ]; then
    echo -e "${RED}  ✗ Framework directory not found: $FRAMEWORK_DIR${NC}"
    echo -e "${RED}  Please ensure the framework is at: $FRAMEWORK_DIR${NC}"
    exit 1
fi

# Remove existing framework installation
rm -rf "$TEST_DIR/api/vendor/progalaxyelabs/stonescriptphp"

# Copy framework
mkdir -p "$TEST_DIR/api/vendor/progalaxyelabs/stonescriptphp"
rsync -a --exclude='.git' \
         --exclude='vendor' \
         --exclude='node_modules' \
         --exclude='.claude' \
         "$FRAMEWORK_DIR/" "$TEST_DIR/api/vendor/progalaxyelabs/stonescriptphp/"
echo "  ✓ Framework copied (mimicking Packagist install)"

# Step 5: Create test Dockerfile
echo -e "${YELLOW}[6/9]${NC} Creating test Dockerfile..."
cat > "$TEST_DIR/api/Dockerfile.test" << 'EOF'
# Test Dockerfile - PHP-FPM + Nginx (Debian-based)
FROM php:8.3-fpm-bookworm

# Install Nginx, Supervisor, and dependencies
RUN apt-get update && apt-get install -y \
    nginx \
    supervisor \
    libpq-dev \
    postgresql-client \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    curl \
    git \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        gd \
        pdo \
        pdo_pgsql \
        zip

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Configure PHP for development
RUN { \
        echo 'display_errors=On'; \
        echo 'error_reporting=E_ALL'; \
        echo 'log_errors=On'; \
        echo 'error_log=/var/log/php_errors.log'; \
        echo 'max_execution_time=60'; \
        echo 'memory_limit=512M'; \
    } > /usr/local/etc/php/conf.d/development.ini

# Configure PHP-FPM
RUN echo "pm = dynamic" > /usr/local/etc/php-fpm.d/zz-custom.conf \
    && echo "pm.max_children = 20" >> /usr/local/etc/php-fpm.d/zz-custom.conf \
    && echo "pm.start_servers = 2" >> /usr/local/etc/php-fpm.d/zz-custom.conf \
    && echo "pm.min_spare_servers = 1" >> /usr/local/etc/php-fpm.d/zz-custom.conf \
    && echo "pm.max_spare_servers = 10" >> /usr/local/etc/php-fpm.d/zz-custom.conf

WORKDIR /var/www/html

# Copy Nginx and Supervisor configs
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/default.conf /etc/nginx/sites-available/default
RUN rm -f /etc/nginx/sites-enabled/default \
    && ln -s /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default

COPY docker/supervisord-dev.conf /etc/supervisor/conf.d/supervisord.conf

# Copy application (with vendor already installed)
COPY . .

# Create directories
RUN mkdir -p logs keys \
    && chown -R www-data:www-data logs keys \
    && chmod -R 755 logs

EXPOSE 8000

HEALTHCHECK --interval=30s --timeout=3s --start-period=10s --retries=3 \
    CMD curl -f http://localhost:8000/api/health || exit 1

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
EOF
echo "  ✓ Dockerfile.test created"

# Step 6: Create docker-compose.yaml
echo -e "${YELLOW}[7/9]${NC} Creating docker-compose.yaml..."
cat > "$TEST_DIR/docker-compose.yaml" << 'EOF'
version: '3.8'

services:
  # PostgreSQL Database
  postgres:
    image: postgres:16-alpine
    container_name: stonescriptphp-test-db
    restart: unless-stopped
    environment:
      POSTGRES_DB: stonescriptphp_test
      POSTGRES_USER: postgres
      POSTGRES_PASSWORD: testpassword123
    ports:
      - "5433:5432"
    volumes:
      - postgres_data:/var/lib/postgresql/data
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U postgres"]
      interval: 10s
      timeout: 5s
      retries: 5
    networks:
      - test-network

  # API Service
  api:
    build:
      context: ./api
      dockerfile: Dockerfile.test
    container_name: stonescriptphp-test-api
    restart: unless-stopped
    depends_on:
      postgres:
        condition: service_healthy
    environment:
      # Database configuration
      DATABASE_HOST: postgres
      DATABASE_PORT: 5432
      DATABASE_DBNAME: stonescriptphp_test
      DATABASE_USER: postgres
      DATABASE_PASSWORD: testpassword123
      DATABASE_TIMEOUT: 30
      DATABASE_APPNAME: StoneScriptPHP-Test

      # Application configuration
      APP_NAME: StoneScriptPHP-Test
      APP_ENV: development
      APP_PORT: 8000
      DEBUG_MODE: true
      TIMEZONE: UTC

      # JWT configuration (dummy values for testing)
      JWT_PRIVATE_KEY_PATH: ./keys/jwt-private.pem
      JWT_PUBLIC_KEY_PATH: ./keys/jwt-public.pem
      JWT_SECRET: test-secret-key-for-docker-testing
      JWT_EXPIRY: 3600
      JWT_ISSUER: stonescriptphp-test

      # CORS
      ALLOWED_ORIGINS: http://localhost:4200

    ports:
      - "8001:8000"
    volumes:
      # Mount source for hot reload
      - ./api:/var/www/html
      # Persist logs
      - ./api/logs:/var/www/html/logs
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:8000/api/health"]
      interval: 30s
      timeout: 3s
      retries: 3
      start_period: 15s
    networks:
      - test-network

volumes:
  postgres_data:
    driver: local

networks:
  test-network:
    driver: bridge
EOF
echo "  ✓ docker-compose.yaml created"

# Step 7: Generate JWT keys
echo -e "${YELLOW}[8/9]${NC} Generating JWT keys..."
mkdir -p "$TEST_DIR/api/keys"
openssl genrsa -out "$TEST_DIR/api/keys/jwt-private.pem" 2048 2>/dev/null
openssl rsa -in "$TEST_DIR/api/keys/jwt-private.pem" -pubout -out "$TEST_DIR/api/keys/jwt-public.pem" 2>/dev/null
chmod 600 "$TEST_DIR/api/keys/jwt-private.pem"
chmod 644 "$TEST_DIR/api/keys/jwt-public.pem"
echo "  ✓ JWT keys generated"

# Step 8: Build and start containers
echo -e "${YELLOW}[9/9]${NC} Building and starting Docker containers..."
echo ""
cd "$TEST_DIR"

echo -e "${BLUE}Building images...${NC}"
docker compose build --no-cache

echo ""
echo -e "${BLUE}Starting containers...${NC}"
docker compose up -d

echo ""
echo -e "${BLUE}Waiting for services to be healthy...${NC}"
sleep 5

# Wait for health checks
MAX_WAIT=60
WAITED=0
while [ $WAITED -lt $MAX_WAIT ]; do
    if docker compose ps | grep -q "healthy"; then
        echo -e "${GREEN}  ✓ Services are healthy${NC}"
        break
    fi
    echo "  Waiting for health checks... (${WAITED}s/${MAX_WAIT}s)"
    sleep 5
    WAITED=$((WAITED + 5))
done

if [ $WAITED -ge $MAX_WAIT ]; then
    echo -e "${RED}  ✗ Services did not become healthy in time${NC}"
    echo ""
    echo -e "${YELLOW}Container status:${NC}"
    docker compose ps
    echo ""
    echo -e "${YELLOW}API logs:${NC}"
    docker compose logs api
    exit 1
fi

echo ""
echo -e "${BLUE}╔════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║           Test Results                     ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════════╝${NC}"
echo ""

# Check container status
echo -e "${YELLOW}Container Status:${NC}"
docker compose ps
echo ""

# Check API logs for errors
echo -e "${YELLOW}Checking API logs for errors...${NC}"
API_LOGS=$(docker compose logs api 2>&1)

# Look for common error patterns
ERRORS_FOUND=0

if echo "$API_LOGS" | grep -i "error" | grep -v "error_log" | grep -v "display_errors" | grep -q .; then
    echo -e "${RED}  ✗ Errors found in logs:${NC}"
    echo "$API_LOGS" | grep -i "error" | grep -v "error_log" | grep -v "display_errors" | head -10
    ERRORS_FOUND=1
fi

if echo "$API_LOGS" | grep -i "fatal" | grep -q .; then
    echo -e "${RED}  ✗ Fatal errors found:${NC}"
    echo "$API_LOGS" | grep -i "fatal"
    ERRORS_FOUND=1
fi

if echo "$API_LOGS" | grep -i "warning" | grep -v "pm.start_servers" | grep -q .; then
    echo -e "${YELLOW}  ⚠ Warnings found:${NC}"
    echo "$API_LOGS" | grep -i "warning" | grep -v "pm.start_servers" | head -5
fi

if [ $ERRORS_FOUND -eq 0 ]; then
    echo -e "${GREEN}  ✓ No critical errors found in logs${NC}"
fi

echo ""

# Test health endpoint
echo -e "${YELLOW}Testing health endpoint...${NC}"
sleep 2  # Give it a moment
HEALTH_RESPONSE=$(curl -s http://localhost:8001/api/health || echo "FAILED")

if echo "$HEALTH_RESPONSE" | grep -q "healthy"; then
    echo -e "${GREEN}  ✓ Health endpoint responding correctly${NC}"
    echo "  Response: $HEALTH_RESPONSE"
else
    echo -e "${RED}  ✗ Health endpoint failed${NC}"
    echo "  Response: $HEALTH_RESPONSE"
    ERRORS_FOUND=1
fi

echo ""

# Test home endpoint
echo -e "${YELLOW}Testing home endpoint...${NC}"
HOME_RESPONSE=$(curl -s http://localhost:8001/ || echo "FAILED")

if echo "$HOME_RESPONSE" | grep -q "visit home page"; then
    echo -e "${GREEN}  ✓ Home endpoint responding correctly${NC}"
else
    echo -e "${RED}  ✗ Home endpoint failed${NC}"
    echo "  Response: $HOME_RESPONSE"
    ERRORS_FOUND=1
fi

echo ""

# Check if Nginx is running
echo -e "${YELLOW}Checking Nginx process...${NC}"
if docker exec stonescriptphp-test-api ps aux | grep -q "nginx"; then
    echo -e "${GREEN}  ✓ Nginx is running${NC}"
else
    echo -e "${RED}  ✗ Nginx is not running${NC}"
    ERRORS_FOUND=1
fi

# Check if PHP-FPM is running
echo -e "${YELLOW}Checking PHP-FPM process...${NC}"
if docker exec stonescriptphp-test-api ps aux | grep -q "php-fpm"; then
    echo -e "${GREEN}  ✓ PHP-FPM is running${NC}"
else
    echo -e "${RED}  ✗ PHP-FPM is not running${NC}"
    ERRORS_FOUND=1
fi

echo ""
echo -e "${BLUE}╔════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║           Summary                          ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════════╝${NC}"
echo ""

if [ $ERRORS_FOUND -eq 0 ]; then
    echo -e "${GREEN}✓ All tests passed!${NC}"
    echo ""
    echo -e "Test environment is running at:"
    echo -e "  API: ${BLUE}http://localhost:8001${NC}"
    echo -e "  Health: ${BLUE}http://localhost:8001/api/health${NC}"
    echo -e "  Database: ${BLUE}localhost:5433${NC}"
    echo ""
    echo -e "To view logs:"
    echo -e "  ${YELLOW}cd $TEST_DIR && docker compose logs -f api${NC}"
    echo ""
    echo -e "To stop and cleanup:"
    echo -e "  ${YELLOW}cd $TEST_DIR && docker compose down -v${NC}"
    echo ""
    exit 0
else
    echo -e "${RED}✗ Tests failed - errors found${NC}"
    echo ""
    echo -e "Full logs:"
    echo -e "  ${YELLOW}cd $TEST_DIR && docker compose logs${NC}"
    echo ""
    echo -e "To cleanup:"
    echo -e "  ${YELLOW}cd $TEST_DIR && docker compose down -v${NC}"
    echo ""
    exit 1
fi
