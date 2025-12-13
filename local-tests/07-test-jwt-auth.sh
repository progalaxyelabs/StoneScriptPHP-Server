#!/bin/bash
set -e

# Test Case 7: JWT Authentication with Email+Password
# Tests: Email+password registration, login, JWT token generation and validation

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${YELLOW}========================================${NC}"
echo -e "${YELLOW}Test Case 7: JWT Authentication${NC}"
echo -e "${YELLOW}========================================${NC}"
echo ""

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SERVER_DIR="$(dirname "$SCRIPT_DIR")"
FRAMEWORK_DIR="$(dirname "$(dirname "$SCRIPT_DIR")")/StoneScriptPHP"
TEST_DIR="/tmp/test-stonescriptphp-jwt-auth"
TEST_PORT=9157
DB_PORT=5437
DB_NAME="test_jwt_auth"
DB_USER="postgres"
DB_PASS="postgres"

echo "📋 Configuration:"
echo "  Framework: $FRAMEWORK_DIR"
echo "  Server: $SERVER_DIR"
echo "  Test Dir: $TEST_DIR"
echo "  Test Port: $TEST_PORT"
echo "  DB Port: $DB_PORT"
echo ""

# Cleanup function
cleanup() {
    echo -e "\n${YELLOW}🧹 Cleaning up...${NC}"

    # Stop docker compose
    if [ -d "$TEST_DIR/api" ]; then
        cd "$TEST_DIR/api"
        docker compose down -v 2>/dev/null || true
    fi

    # Remove test directory
    if [ -d "$TEST_DIR" ]; then
        echo "  Removing test directory: $TEST_DIR"
        rm -rf "$TEST_DIR"
    fi

    echo -e "${GREEN}  Cleanup complete${NC}"
}
trap cleanup EXIT

# Step 1: Create test directory and copy server
echo -e "${YELLOW}📁 Step 1: Setting up test environment...${NC}"
if [ -d "$TEST_DIR" ]; then
    rm -rf "$TEST_DIR"
fi
mkdir -p "$TEST_DIR"
cp -r "$SERVER_DIR" "$TEST_DIR/api"
echo -e "${GREEN}  ✓ Test environment created${NC}"

# Step 2: Update composer.json to use local framework
echo -e "\n${YELLOW}🔧 Step 2: Configuring local framework dependency...${NC}"
cd "$TEST_DIR/api"

# Update composer.json to use path repository
cat > composer.json << 'COMPOSER_EOF'
{
    "name": "progalaxyelabs/stonescriptphp-server",
    "description": "StoneScriptPHP Server - API Backend",
    "type": "project",
    "license": "MIT",
    "autoload": {
        "psr-4": {
            "App\\": "src/"
        }
    },
    "require": {
        "php": ">=8.2",
        "progalaxyelabs/stonescriptphp": "*"
    },
    "repositories": [
        {
            "type": "path",
            "url": "FRAMEWORK_PATH_PLACEHOLDER",
            "options": {
                "symlink": false
            }
        }
    ],
    "minimum-stability": "dev",
    "prefer-stable": true
}
COMPOSER_EOF

# Replace placeholder with actual framework path
sed -i "s|FRAMEWORK_PATH_PLACEHOLDER|$FRAMEWORK_DIR|g" composer.json

echo -e "${GREEN}  ✓ Composer configured for local framework${NC}"

# Step 3: Install dependencies
echo -e "\n${YELLOW}📦 Step 3: Installing dependencies...${NC}"
composer install --no-interaction --quiet
echo -e "${GREEN}  ✓ Dependencies installed${NC}"

# Step 4: Create database schema
echo -e "\n${YELLOW}🗄️  Step 4: Creating database schema...${NC}"

mkdir -p src/postgresql/schema

# Create users table
cat > src/postgresql/schema/001_users.pgsql << 'SQL_EOF'
CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    display_name VARCHAR(255),
    status VARCHAR(50) DEFAULT 'active',
    email_verified BOOLEAN DEFAULT TRUE,
    last_login_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_status ON users(status);
SQL_EOF

echo -e "${GREEN}  ✓ Database schema created${NC}"

# Step 5: Create auth routes
echo -e "\n${YELLOW}🛣️  Step 5: Creating authentication routes...${NC}"

mkdir -p src/Routes/Auth

# Copy RegisterRoute template
cp "$FRAMEWORK_DIR/src/Templates/Auth/email-password/RegisterRoute.php.template" \
   "src/Routes/Auth/RegisterRoute.php"

# Copy LoginRoute template
cp "$FRAMEWORK_DIR/src/Templates/Auth/email-password/LoginRoute.php.template" \
   "src/Routes/Auth/LoginRoute.php"

# Create routes configuration
cat > src/routes.php << 'PHP_EOF'
<?php

use Framework\Routing\Router;
use Framework\Routing\Middleware\JwtAuthMiddleware;
use Framework\Auth\RsaJwtHandler;

use App\Routes\Auth\RegisterRoute;
use App\Routes\Auth\LoginRoute;

$router = new Router();
$jwtHandler = new RsaJwtHandler();

// Public routes
$router->post('/api/auth/register', RegisterRoute::class);
$router->post('/api/auth/login', LoginRoute::class);

// Protected routes
$router->use(new JwtAuthMiddleware($jwtHandler, [
    '/api/auth/register',
    '/api/auth/login'
]));

$router->get('/api/auth/me', function($request) {
    $user = auth();
    return new Framework\ApiResponse('success', 'User profile', [
        'user_id' => $user->user_id,
        'email' => $user->email,
        'display_name' => $user->display_name
    ]);
});

$router->handle();
PHP_EOF

# Update index.php to use routes
cat > public/index.php << 'PHP_EOF'
<?php

define('ROOT_PATH', dirname(__DIR__));

require_once ROOT_PATH . '/vendor/autoload.php';
require_once ROOT_PATH . '/functions.php';

// Load routes
require_once ROOT_PATH . '/src/routes.php';
PHP_EOF

echo -e "${GREEN}  ✓ Authentication routes created${NC}"

# Step 6: Create .env file
echo -e "\n${YELLOW}⚙️  Step 6: Creating environment configuration...${NC}"

cat > .env << ENV_EOF
DEBUG_MODE=true
TIMEZONE=UTC

DATABASE_HOST=postgres
DATABASE_PORT=5432
DATABASE_USER=$DB_USER
DATABASE_PASSWORD=$DB_PASS
DATABASE_DBNAME=$DB_NAME
DATABASE_TIMEOUT=30
DATABASE_APPNAME=JWTAuthTest

# Disable email verification for testing
EMAIL_VERIFICATION_ENABLED=false

# App configuration
APP_NAME=JWT Auth Test
APP_URL=http://localhost:$TEST_PORT
APP_ENV=development
ENV_EOF

echo -e "${GREEN}  ✓ Environment configured${NC}"

# Step 7: Create docker-compose.yml
echo -e "\n${YELLOW}🐳 Step 7: Creating Docker Compose configuration...${NC}"

cat > docker-compose.yml << COMPOSE_EOF
services:
  postgres:
    image: postgres:15-alpine
    environment:
      POSTGRES_USER: $DB_USER
      POSTGRES_PASSWORD: $DB_PASS
      POSTGRES_DB: $DB_NAME
    ports:
      - "$DB_PORT:5432"
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U $DB_USER -d $DB_NAME"]
      interval: 2s
      timeout: 5s
      retries: 10
    volumes:
      - ./src/postgresql/schema:/docker-entrypoint-initdb.d

  api:
    build:
      context: .
      dockerfile_inline: |
        FROM php:8.2-cli-alpine
        RUN apk add --no-cache postgresql-dev \\
            && docker-php-ext-install pdo pdo_pgsql
        WORKDIR /app
        COPY . /app
        CMD ["php", "stone", "serve", "--host=0.0.0.0", "--port=8000"]
    ports:
      - "$TEST_PORT:8000"
    environment:
      DATABASE_HOST: postgres
      DATABASE_PORT: 5432
      DATABASE_USER: $DB_USER
      DATABASE_PASSWORD: $DB_PASS
      DATABASE_DBNAME: $DB_NAME
      EMAIL_VERIFICATION_ENABLED: "false"
      APP_ENV: development
    depends_on:
      postgres:
        condition: service_healthy
COMPOSE_EOF

echo -e "${GREEN}  ✓ Docker Compose configured${NC}"

# Step 8: Start services
echo -e "\n${YELLOW}🚀 Step 8: Starting services...${NC}"
docker compose up -d --build

# Wait for services to be ready
echo "  Waiting for services to start..."
sleep 5

# Wait for API to be responsive
MAX_RETRIES=30
RETRY_COUNT=0
while ! curl -s http://localhost:$TEST_PORT/api/health > /dev/null; do
    RETRY_COUNT=$((RETRY_COUNT+1))
    if [ $RETRY_COUNT -ge $MAX_RETRIES ]; then
        echo -e "${RED}  ✗ API failed to start${NC}"
        docker compose logs api
        exit 1
    fi
    echo "  Waiting for API... ($RETRY_COUNT/$MAX_RETRIES)"
    sleep 1
done

echo -e "${GREEN}  ✓ Services started${NC}"

# Step 9: Test Registration
echo -e "\n${YELLOW}🧪 Step 9: Testing user registration...${NC}"

REGISTER_RESPONSE=$(curl -s -X POST http://localhost:$TEST_PORT/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "password": "password123",
    "display_name": "Test User"
  }')

echo "  Response: $REGISTER_RESPONSE"

# Check if registration was successful
if echo "$REGISTER_RESPONSE" | grep -q '"status":"success"'; then
    echo -e "${GREEN}  ✓ User registered successfully${NC}"

    # Extract JWT token
    TOKEN=$(echo "$REGISTER_RESPONSE" | grep -o '"token":"[^"]*"' | cut -d'"' -f4)
    echo "  JWT Token: ${TOKEN:0:50}..."
else
    echo -e "${RED}  ✗ Registration failed${NC}"
    exit 1
fi

# Step 10: Test Login
echo -e "\n${YELLOW}🧪 Step 10: Testing user login...${NC}"

LOGIN_RESPONSE=$(curl -s -X POST http://localhost:$TEST_PORT/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "password": "password123"
  }')

echo "  Response: $LOGIN_RESPONSE"

if echo "$LOGIN_RESPONSE" | grep -q '"status":"success"'; then
    echo -e "${GREEN}  ✓ Login successful${NC}"

    # Extract JWT token
    LOGIN_TOKEN=$(echo "$LOGIN_RESPONSE" | grep -o '"token":"[^"]*"' | cut -d'"' -f4)
    echo "  JWT Token: ${LOGIN_TOKEN:0:50}..."
else
    echo -e "${RED}  ✗ Login failed${NC}"
    exit 1
fi

# Step 11: Test Protected Route with JWT
echo -e "\n${YELLOW}🧪 Step 11: Testing protected route with JWT...${NC}"

ME_RESPONSE=$(curl -s -X GET http://localhost:$TEST_PORT/api/auth/me \
  -H "Authorization: Bearer $LOGIN_TOKEN")

echo "  Response: $ME_RESPONSE"

if echo "$ME_RESPONSE" | grep -q '"email":"test@example.com"'; then
    echo -e "${GREEN}  ✓ JWT authentication successful${NC}"
    echo -e "${GREEN}  ✓ Protected route accessible${NC}"
else
    echo -e "${RED}  ✗ JWT authentication failed${NC}"
    exit 1
fi

# Step 12: Test Invalid JWT
echo -e "\n${YELLOW}🧪 Step 12: Testing invalid JWT...${NC}"

INVALID_RESPONSE=$(curl -s -X GET http://localhost:$TEST_PORT/api/auth/me \
  -H "Authorization: Bearer invalid_token_here")

echo "  Response: $INVALID_RESPONSE"

if echo "$INVALID_RESPONSE" | grep -q '"status":"error"'; then
    echo -e "${GREEN}  ✓ Invalid JWT correctly rejected${NC}"
else
    echo -e "${RED}  ✗ Invalid JWT not rejected${NC}"
    exit 1
fi

# Step 13: Test Duplicate Registration
echo -e "\n${YELLOW}🧪 Step 13: Testing duplicate registration...${NC}"

DUP_RESPONSE=$(curl -s -X POST http://localhost:$TEST_PORT/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "password": "different123",
    "display_name": "Another User"
  }')

echo "  Response: $DUP_RESPONSE"

if echo "$DUP_RESPONSE" | grep -q "already exists"; then
    echo -e "${GREEN}  ✓ Duplicate email correctly rejected${NC}"
else
    echo -e "${RED}  ✗ Duplicate email not rejected${NC}"
    exit 1
fi

# Summary
echo -e "\n${GREEN}========================================${NC}"
echo -e "${GREEN}✅ All JWT Authentication Tests Passed!${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""
echo "Test Results:"
echo "  ✅ User Registration (with email verification disabled)"
echo "  ✅ User Login"
echo "  ✅ JWT Token Generation"
echo "  ✅ JWT Token Validation"
echo "  ✅ Protected Route Access"
echo "  ✅ Invalid JWT Rejection"
echo "  ✅ Duplicate Email Prevention"
echo ""
echo "JWT Authentication is working correctly!"
echo ""
