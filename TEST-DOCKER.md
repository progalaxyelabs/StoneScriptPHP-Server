# Docker Test Suite

Automated testing scripts to validate the Docker setup before releasing to Packagist.

## Overview

These scripts create an isolated test environment that mimics how users will install and run StoneScriptPHP via Composer, ensuring the Docker configuration works correctly.

## What It Tests

1. ✅ Server code copying and structure
2. ✅ Framework installation (mimicking Packagist)
3. ✅ Dockerfile build process
4. ✅ Docker Compose multi-service setup
5. ✅ Nginx + PHP-FPM startup
6. ✅ Health endpoint functionality
7. ✅ Database connectivity
8. ✅ Error logging and detection
9. ✅ Process management (Supervisor)

## Files

- **`test-docker.sh`** - Main test script
- **`test-docker-cleanup.sh`** - Cleanup script
- **`TEST-DOCKER.md`** - This documentation

## Usage

### Run Tests

```bash
# Make sure you're in the server directory
cd /path/to/StoneScriptPHP-Server

# Run the test
./test-docker.sh
```

The script will:
1. Create `test-docker-env/` directory
2. Copy server code to `test-docker-env/api/`
3. Copy framework to `vendor/progalaxyelabs/stonescriptphp/` (mimicking Packagist)
4. Create test Dockerfile and docker-compose.yaml
5. Build and start containers
6. Run health checks
7. Report results

### Cleanup

```bash
# Stop containers and remove test environment
./test-docker-cleanup.sh

# Or manually
cd test-docker-env
docker compose down -v
cd ..
rm -rf test-docker-env
```

## Test Environment

The script creates a complete isolated environment:

```
test-docker-env/
├── docker-compose.yaml       # Test compose file
├── api/                       # Server code
│   ├── Dockerfile.test        # Test Dockerfile
│   ├── vendor/
│   │   └── progalaxyelabs/
│   │       └── stonescriptphp/  # Framework (copied, not from Packagist)
│   ├── public/
│   ├── src/
│   ├── docker/
│   └── ...
└── db/
```

### Services

**API Service:**
- Image: PHP 8.3 FPM + Nginx (Debian)
- Port: 8001 (to avoid conflicts with dev server)
- Volumes: Source mounted for inspection
- Environment: Development mode

**Database Service:**
- Image: PostgreSQL 16 Alpine
- Port: 5433 (to avoid conflicts)
- Database: stonescriptphp_test
- Credentials: postgres/postgres

## What Success Looks Like

```
╔════════════════════════════════════════════╗
║           Test Results                     ║
╚════════════════════════════════════════════╝

Container Status:
NAME                        STATUS              PORTS
stonescriptphp-test-api     Up (healthy)        0.0.0.0:8001->8000/tcp
stonescriptphp-test-db      Up (healthy)        0.0.0.0:5433->5432/tcp

Checking API logs for errors...
  ✓ No critical errors found in logs

Testing health endpoint...
  ✓ Health endpoint responding correctly
  Response: {"status":"healthy","timestamp":"2025-...","checks":{"database":"ok"}}

Testing home endpoint...
  ✓ Home endpoint responding correctly

Checking Nginx process...
  ✓ Nginx is running

Checking PHP-FPM process...
  ✓ PHP-FPM is running

╔════════════════════════════════════════════╗
║           Summary                          ║
╚════════════════════════════════════════════╝

✓ All tests passed!

Test environment is running at:
  API: http://localhost:8001
  Health: http://localhost:8001/api/health
  Database: localhost:5433
```

## Troubleshooting

### Build Failures

If the build fails:

```bash
# Check build logs
cd test-docker-env
docker compose build --no-cache --progress=plain

# Check for missing dependencies
ls -la api/vendor/progalaxyelabs/stonescriptphp/
```

### Container Won't Start

```bash
# Check logs
cd test-docker-env
docker compose logs api
docker compose logs postgres

# Check container status
docker compose ps -a

# Inspect container
docker exec -it stonescriptphp-test-api bash
```

### Health Check Fails

```bash
# Test health endpoint manually
curl http://localhost:8001/api/health

# Check Nginx logs
docker exec stonescriptphp-test-api tail -f /var/log/nginx/error.log

# Check PHP-FPM logs
docker exec stonescriptphp-test-api tail -f /var/log/php_errors.log

# Check database connectivity
docker exec stonescriptphp-test-api nc -zv postgres 5432
```

### Framework Not Found

If you see errors about missing framework files:

```bash
# Ensure framework directory exists at the correct path
ls -la ../StoneScriptPHP/

# The script expects:
# StoneScriptPHP-Server/  (current directory)
# StoneScriptPHP/         (sibling directory)
```

## Common Test Scenarios

### Test After Code Changes

```bash
# Clean up old test
./test-docker-cleanup.sh

# Run fresh test
./test-docker.sh
```

### Test With Different Configurations

Edit `test-docker.sh` and modify the docker-compose.yaml generation section to test different environment variables, ports, or configurations.

### Test Production Dockerfile

To test Dockerfile.prod instead of Dockerfile.dev:

```bash
# Edit test-docker.sh
# Change: dockerfile: Dockerfile.test
# To:     dockerfile: Dockerfile.prod

# Then copy Dockerfile.prod as Dockerfile.test
cp Dockerfile.prod test-docker-env/api/Dockerfile.test

# Run test
./test-docker.sh
```

## CI/CD Integration

This script is designed to be used in CI/CD pipelines:

```yaml
# GitHub Actions example
name: Test Docker Setup

on: [push, pull_request]

jobs:
  test-docker:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3

      - name: Checkout framework
        uses: actions/checkout@v3
        with:
          repository: progalaxyelabs/StoneScriptPHP
          path: StoneScriptPHP

      - name: Run Docker tests
        run: |
          cd StoneScriptPHP-Server
          ./test-docker.sh

      - name: Cleanup
        if: always()
        run: |
          cd StoneScriptPHP-Server
          ./test-docker-cleanup.sh
```

## Expected Test Duration

- Build: ~2-3 minutes (first time)
- Start: ~10-15 seconds
- Tests: ~5 seconds
- **Total: ~3-4 minutes**

Subsequent runs are faster due to Docker layer caching.

## Exit Codes

- `0` - All tests passed
- `1` - Tests failed (check output for details)

## Notes

- Test environment uses different ports (8001, 5433) to avoid conflicts
- All containers are prefixed with `stonescriptphp-test-`
- Test environment is fully isolated and can run alongside dev environment
- Framework is copied (not symlinked) to mimic real Packagist installation
- Cleanup script removes all test artifacts including Docker volumes

## Validation Checklist

Before releasing a new version, this test should verify:

- [x] Dockerfile builds without errors
- [x] Nginx starts correctly
- [x] PHP-FPM starts correctly
- [x] Supervisor manages both processes
- [x] Health endpoint returns 200 OK
- [x] Database connection works
- [x] No PHP errors in logs
- [x] Framework autoloader works
- [x] Routes are accessible
- [x] No permission issues

## Support

If tests fail and you can't determine why:

1. Check the full logs: `cd test-docker-env && docker compose logs`
2. Inspect the container: `docker exec -it stonescriptphp-test-api bash`
3. Review DOCKER.md for configuration details
4. Check GitHub issues for similar problems
