# Docker Deployment Guide

This guide explains how to run StoneScriptPHP applications using Docker.

## Quick Start

### Option 1: Docker Compose (Recommended for Development)

```bash
# Start all services (app + PostgreSQL)
docker compose up -d

# View logs
docker compose logs -f app

# Stop all services
docker compose down

# Stop and remove volumes (WARNING: deletes database data)
docker compose down -v
```

Your API will be available at: http://localhost:8000

### Option 2: Standalone Docker

```bash
# Build the image
docker build -t my-stonescriptphp-api .

# Run with external PostgreSQL
docker run -d \
  -p 8000:8000 \
  -e DB_HOST=your-db-host \
  -e DB_DATABASE=your-db-name \
  -e DB_USERNAME=your-db-user \
  -e DB_PASSWORD=your-db-password \
  --name my-api \
  my-stonescriptphp-api

# View logs
docker logs -f my-api

# Stop
docker stop my-api
```

## Configuration

### Environment Variables

Create a `.env` file in your project root (docker-compose will use it):

```env
# Application
APP_PORT=8000
APP_ENV=development
APP_DEBUG=true

# Database
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=stonescriptphp
DB_USERNAME=postgres
DB_PASSWORD=your-secure-password

# Optional: Redis
# REDIS_PORT=6379
```

### Custom Port

Change the application port:

```bash
# Edit .env
APP_PORT=9100

# Or override in docker-compose
docker compose up -d -e APP_PORT=9100
```

## Database Setup

### Auto-Initialize Database Schema

The `docker-compose.yml` automatically runs SQL files on first start:

1. Place table schemas in: `src/postgresql/tables/*.pgsql`
2. Place functions in: `src/postgresql/functions/*.pgsql`
3. Start services: `docker compose up -d`

PostgreSQL will execute them in order on first container creation.

### Manual Database Setup

```bash
# Connect to PostgreSQL container
docker compose exec postgres psql -U postgres -d stonescriptphp

# Or from your host (if you have psql installed)
psql -h localhost -U postgres -d stonescriptphp

# Run SQL files manually
docker compose exec postgres psql -U postgres -d stonescriptphp -f /docker-entrypoint-initdb.d/01-tables/users.pgsql
```

### Migration Verification

```bash
# Run migration check inside container
docker compose exec app php stone migrate verify
```

## Development Workflow

### Hot Reload

The `docker-compose.yml` mounts your source code as a volume, so changes are reflected immediately:

```yaml
volumes:
  - .:/var/www/html  # Hot reload enabled
```

Edit your code locally, and the container will use the updated files.

### Running CLI Commands

```bash
# Generate a route
docker compose exec app php stone generate route login

# Generate a model
docker compose exec app php stone generate model get_user.pgsql

# Run tests
docker compose exec app php stone test

# Access container shell
docker compose exec app bash
```

### Installing Dependencies

```bash
# Add a new Composer package
docker compose exec app composer require vendor/package

# Update dependencies
docker compose exec app composer update
```

## Production Deployment

### Build Production Image

```bash
# Build with production optimizations
docker build -t my-api:1.0.0 .

# Tag for registry
docker tag my-api:1.0.0 your-registry.com/my-api:1.0.0

# Push to registry
docker push your-registry.com/my-api:1.0.0
```

### Production docker-compose.yml

```yaml
version: '3.8'

services:
  app:
    image: your-registry.com/my-api:1.0.0
    restart: always
    environment:
      APP_ENV: production
      APP_DEBUG: false
      DB_HOST: your-production-db.example.com
      DB_DATABASE: production_db
      DB_USERNAME: ${DB_USERNAME}  # Use secrets
      DB_PASSWORD: ${DB_PASSWORD}  # Use secrets
    ports:
      - "8000:8000"
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:8000/api/health"]
      interval: 30s
      timeout: 3s
      retries: 3
```

### Environment Secrets

Use Docker secrets or environment variables:

```bash
# Using Docker secrets (Docker Swarm)
echo "your-db-password" | docker secret create db_password -

# Using .env file (keep out of version control)
echo "DB_PASSWORD=your-secure-password" >> .env.production
docker compose --env-file .env.production up -d
```

## Troubleshooting

### Container won't start

```bash
# Check logs
docker compose logs app

# Common issues:
# 1. Port already in use - change APP_PORT in .env
# 2. Database not ready - wait for postgres healthcheck
# 3. Missing .env file - copy .env.example to .env
```

### Can't connect to database

```bash
# Verify postgres is running
docker compose ps postgres

# Check postgres logs
docker compose logs postgres

# Test connection from app container
docker compose exec app pg_isready -h postgres -U postgres
```

### Permission issues

```bash
# Fix logs directory permissions
chmod -R 777 logs/

# Or run container as specific user
docker compose run --user $(id -u):$(id -g) app php stone serve
```

### Health check failing

```bash
# Verify health endpoint exists
curl http://localhost:8000/api/health

# Check if server is listening
docker compose exec app netstat -tuln | grep 8000
```

## Docker Image Details

### What's Included

- **Base**: PHP 8.3 CLI (Debian Bookworm)
- **Extensions**: pdo_pgsql, gd, zip, redis
- **Tools**: Composer, git, curl, wget
- **Dev Tools**: vim, nano, net-tools, ping, procps

### Image Size Optimization

For smaller production images, create a `.dockerignore` file (already included):

```
tests/
docs/
*.md
.git
```

### Multi-stage Build (Advanced)

For even smaller images, use multi-stage builds:

```dockerfile
# Build stage
FROM composer:2 AS builder
WORKDIR /app
COPY composer.* ./
RUN composer install --no-dev --optimize-autoloader

# Runtime stage
FROM php:8.3-cli-alpine
COPY --from=builder /app/vendor /var/www/html/vendor
COPY . /var/www/html
```

## Kubernetes Deployment

See `k8s/` directory for Kubernetes manifests (if available).

Basic deployment:

```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: stonescriptphp-api
spec:
  replicas: 3
  selector:
    matchLabels:
      app: stonescriptphp-api
  template:
    metadata:
      labels:
        app: stonescriptphp-api
    spec:
      containers:
      - name: api
        image: your-registry.com/my-api:1.0.0
        ports:
        - containerPort: 8000
        env:
        - name: DB_HOST
          value: postgres-service
        - name: DB_PASSWORD
          valueFrom:
            secretKeyRef:
              name: db-credentials
              key: password
```

## Resources

- [Dockerfile Reference](https://docs.docker.com/engine/reference/builder/)
- [Docker Compose Reference](https://docs.docker.com/compose/compose-file/)
- [PHP Docker Official Images](https://hub.docker.com/_/php)
- [PostgreSQL Docker Official Images](https://hub.docker.com/_/postgres)
