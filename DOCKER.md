# Docker Deployment Guide

This directory contains **sample Docker configurations** for reference. In production, you'll typically have a multi-service setup with your own `docker-compose.yaml` at the project root.

## Files Overview

### Development
- **`Dockerfile.dev`** - Development image using `php -S` built-in server
- **`docker-compose.dev.yaml`** - Sample development setup (PostgreSQL + API)

### Production
- **`Dockerfile.prod`** - Production image using PHP-FPM + Nginx
- **`docker-compose.prod.yaml`** - Sample production setup
- **`docker/`** - Nginx and Supervisor configurations

---

## Development Setup

**⚠️ For development/testing only. DO NOT use in production.**

The development setup uses PHP's built-in server (`php -S`), which is:
- ✅ Great for local development
- ✅ Easy to use and debug
- ❌ **NOT suitable for production** (single-threaded, not secure, limited features)

### Quick Start (Development)

```bash
# Start development environment
docker compose -f docker-compose.dev.yaml up -d

# Run migrations
docker exec -it stonescriptphp-app php stone migrate up

# Create admin user
docker exec -it stonescriptphp-app php stone create:admin

# View logs
docker compose -f docker-compose.dev.yaml logs -f app
```

---

## Production Setup

**✅ Production-ready with PHP-FPM + Nginx**

The production setup uses:
- **PHP-FPM** - FastCGI Process Manager for high performance
- **Nginx** - Industry-standard web server
- **Supervisor** - Process manager for PHP-FPM + Nginx
- **OPcache** - Optimized bytecode caching
- **Alpine Linux** - Minimal footprint (~50MB base image)

### Build Production Image

```bash
# Build the production image
docker build -f Dockerfile.prod -t stonescriptphp-api:latest .

# Or with docker-compose
docker compose -f docker-compose.prod.yaml build
```

### Deploy to Production

```bash
# Start production environment
docker compose -f docker-compose.prod.yaml up -d

# Run migrations
docker exec -it stonescriptphp-app php stone migrate up

# Check health
curl http://localhost:8000/api/health
```

---

## Real-World Project Structure

In production, you'll typically have a structure like this:

```
my-project/
├── docker-compose.yaml          # Your main compose file
├── .env                          # Environment variables
├── api/                          # This StoneScriptPHP API
│   ├── src/
│   ├── public/
│   ├── Dockerfile.prod          # Use the production Dockerfile
│   └── composer.json
├── www/                          # Frontend (React/Angular/etc)
│   ├── nginx.conf
│   └── Dockerfile
├── worker/                       # Background workers
│   └── Dockerfile
└── db/
    └── init.sql
```

### Sample Project-Level docker-compose.yaml

```yaml
version: '3.8'

services:
  # Database
  postgres:
    image: postgres:16-alpine
    environment:
      POSTGRES_DB: myapp
      POSTGRES_USER: myapp
      POSTGRES_PASSWORD: ${DB_PASSWORD}
    volumes:
      - postgres_data:/var/lib/postgresql/data
      - ./db/init.sql:/docker-entrypoint-initdb.d/init.sql
    networks:
      - myapp-network

  # API (StoneScriptPHP)
  api:
    build:
      context: ./api
      dockerfile: Dockerfile.prod
    environment:
      DATABASE_HOST: postgres
      DATABASE_DBNAME: myapp
      APP_ENV: production
    depends_on:
      - postgres
      - redis
    networks:
      - myapp-network

  # Frontend
  www:
    build: ./www
    ports:
      - "80:80"
      - "443:443"
    depends_on:
      - api
    networks:
      - myapp-network

  # Redis Cache
  redis:
    image: redis:7-alpine
    networks:
      - myapp-network

  # Background Worker
  worker:
    build: ./worker
    depends_on:
      - postgres
      - redis
    networks:
      - myapp-network

volumes:
  postgres_data:

networks:
  myapp-network:
    driver: bridge
```

---

## Environment Variables

### Required

```env
# Database
DB_DATABASE=stonescriptphp
DB_USERNAME=postgres
DB_PASSWORD=your-secure-password

# JWT
JWT_SECRET=your-jwt-secret
JWT_ISSUER=your-domain.com

# CORS
ALLOWED_ORIGINS=https://yourdomain.com
```

### Optional

```env
# Redis (if enabled)
REDIS_ENABLED=true
REDIS_HOST=redis
REDIS_PORT=6379

# OAuth
GOOGLE_CLIENT_ID=your-client-id
GOOGLE_CLIENT_SECRET=your-client-secret

# Email
ZEPTOMAIL_SEND_MAIL_TOKEN=your-token
ZEPTOMAIL_SENDER_EMAIL=noreply@yourdomain.com
```

---

## Performance Tuning

### PHP-FPM Configuration

Edit `docker/supervisord.conf` or override in Dockerfile.prod:

```ini
# In Dockerfile.prod
pm = dynamic
pm.max_children = 50          # Max concurrent PHP processes
pm.start_servers = 5          # Initial processes
pm.min_spare_servers = 5      # Minimum idle processes
pm.max_spare_servers = 35     # Maximum idle processes
pm.max_requests = 500         # Restart worker after N requests
```

### OPcache Configuration

OPcache is pre-configured in production. To adjust:

```ini
opcache.memory_consumption=256        # Increase for large apps
opcache.max_accelerated_files=10000   # Increase if needed
opcache.validate_timestamps=0         # Disable for production
```

### Nginx Tuning

Edit `docker/nginx.conf`:

```nginx
worker_processes auto;           # CPU cores
worker_connections 2048;         # Concurrent connections per worker
client_max_body_size 20M;       # Max upload size
```

---

## Monitoring & Logs

### View Logs

```bash
# Application logs
docker logs -f stonescriptphp-app

# Nginx access logs
docker exec stonescriptphp-app tail -f /var/log/nginx/access.log

# Nginx error logs
docker exec stonescriptphp-app tail -f /var/log/nginx/error.log

# PHP errors
docker exec stonescriptphp-app tail -f /var/log/php_errors.log
```

### Health Checks

```bash
# Check container health
docker ps

# Test health endpoint
curl http://localhost:8000/api/health

# Check PHP-FPM status
docker exec stonescriptphp-app ps aux | grep php-fpm
```

---

## Security Considerations

### Production Checklist

- ✅ Use `Dockerfile.prod` (not `Dockerfile.dev`)
- ✅ Set `APP_ENV=production` and `DEBUG_MODE=false`
- ✅ Use strong passwords for database
- ✅ Generate secure JWT_SECRET (minimum 32 characters)
- ✅ Set ALLOWED_ORIGINS to your actual domain
- ✅ Keep JWT keys secure (mount as read-only)
- ✅ Run containers as non-root user (www-data)
- ✅ Use HTTPS in production (terminate at load balancer or nginx)
- ✅ Keep Docker images updated
- ✅ Use Docker secrets for sensitive data (Swarm/Kubernetes)

### File Permissions

```bash
# JWT keys should be read-only
chmod 600 keys/jwt-private.pem
chmod 644 keys/jwt-public.pem
chown www-data:www-data keys/*.pem
```

---

## Troubleshooting

### "STDERR not found" Error

✅ **Fixed in Logger.php** - Now uses `php://stderr` instead of `\STDERR` constant

### Container Won't Start

```bash
# Check logs
docker logs stonescriptphp-app

# Check if port is in use
sudo lsof -i :8000

# Rebuild without cache
docker build --no-cache -f Dockerfile.prod -t stonescriptphp-api .
```

### Database Connection Issues

```bash
# Test database connectivity
docker exec stonescriptphp-app nc -zv postgres 5432

# Check environment variables
docker exec stonescriptphp-app env | grep DATABASE
```

---

## CI/CD Integration

### GitHub Actions Example

```yaml
name: Build and Deploy

on:
  push:
    branches: [main]

jobs:
  build:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3

      - name: Build Docker image
        run: docker build -f Dockerfile.prod -t myapp-api:${{ github.sha }} .

      - name: Push to registry
        run: |
          docker tag myapp-api:${{ github.sha }} registry.example.com/myapp-api:latest
          docker push registry.example.com/myapp-api:latest
```

---

## Additional Resources

- [PHP-FPM Documentation](https://www.php.net/manual/en/install.fpm.php)
- [Nginx Configuration](https://nginx.org/en/docs/)
- [Docker Best Practices](https://docs.docker.com/develop/dev-best-practices/)
- [StoneScriptPHP Documentation](https://github.com/progalaxyelabs/StoneScriptPHP)

---

## Questions?

- Framework: [StoneScriptPHP Issues](https://github.com/progalaxyelabs/StoneScriptPHP/issues)
- Server: [StoneScriptPHP-Server Issues](https://github.com/progalaxyelabs/StoneScriptPHP-Server/issues)
