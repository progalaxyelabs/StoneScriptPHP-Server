# ⚠️ CRITICAL: Routing Fix & Infrastructure Changes

## TL;DR - What Changed

**PHP's built-in server (`php -S`) does NOT properly handle URL rewrites.** This causes routing failures in StoneScriptPHP applications. We've fixed this by migrating to **Nginx + PHP-FPM** for both development and production.

---

## Problems Discovered

### 1. PHP Built-in Server Routing Issues

**Problem:**
```bash
php -S 127.0.0.1:9100 -t public  # ❌ Does NOT handle URL rewrites correctly
```

The PHP built-in server (`php -S`) was designed for quick testing only and has serious limitations:
- ❌ Inconsistent URL rewriting behavior
- ❌ Cannot properly handle routes like `/api/users/:id`
- ❌ Different behavior from production servers
- ❌ Not suitable for real development work

**Impact:**
- Routes fail intermittently
- Different behavior between dev and production
- Debugging nightmares

### 2. Framework Namespace Bug

**Location:** `StoneScriptPHP/src/Routing/Router.php:261`

**Bug:**
```php
// ❌ Wrong - checking for non-existent namespace
if (!($handler instanceof \Framework\IRouteHandler)) {
```

**Fix:**
```php
// ✅ Correct - using actual StoneScriptPHP namespace
if (!($handler instanceof \StoneScriptPHP\IRouteHandler)) {
```

**Error Message:** `"Handler not implemented correctly"` on all routes

**Status:** ✅ **FIXED** in this version (should be reported to StoneScriptPHP maintainers)

### 3. PHP-FPM Compatibility

**Problem:** Missing STDIN, STDOUT, STDERR constants when running under PHP-FPM

**Fix:** Add constant definitions in `public/index.php` before any other code:
```php
// Define constants for PHP-FPM compatibility
if (!defined('STDIN')) define('STDIN', fopen('php://stdin', 'r'));
if (!defined('STDOUT')) define('STDOUT', fopen('php://stdout', 'w'));
if (!defined('STDERR')) define('STDERR', fopen('php://stderr', 'w'));
```

---

## Solutions Implemented

### ✅ New Infrastructure: Nginx + PHP-FPM + Supervisor

Both **development and production** now use the same reliable stack:

| Component | Purpose | Why |
|-----------|---------|-----|
| **Nginx** | Web server | Proper URL rewriting, production-grade |
| **PHP-FPM** | PHP processor | Better performance, process management |
| **Supervisor** | Process manager | Keeps both Nginx and PHP-FPM running |

### Development vs Production Differences

| Aspect | Development ([Dockerfile.dev](Dockerfile.dev)) | Production ([Dockerfile.prod](Dockerfile.prod)) |
|--------|-------------|------------|
| Base Image | `php:8.3-fpm-bookworm` (Debian) | `php:8.3-fpm-alpine` (Alpine) |
| Tools | Full dev tools (vim, git, htop, etc.) | Minimal runtime only |
| Errors | Displayed on screen | Logged only |
| OPcache | Disabled | Enabled |
| Dependencies | Dev + production packages | Production only |
| Size | ~500MB | ~150MB |

---

## File Structure

### Nginx Configuration

```
docker/
├── nginx.conf              # Main Nginx config
├── default.conf            # Server block with URL rewriting
├── supervisord.conf        # Production process management
└── supervisord-dev.conf    # Development process management
```

### Critical Nginx Config ([docker/default.conf](docker/default.conf#L21-L24))

```nginx
location / {
    # This is the KEY to proper routing!
    # Falls back to index.php for all requests
    try_files $uri $uri/ /index.php$is_args$args;
}
```

This single line fixes all the routing issues that `php -S` couldn't handle.

---

## How to Use

### Development

#### Option 1: Using the CLI (Recommended)
```bash
php stone serve
# Now uses Docker with Nginx + PHP-FPM automatically!
# Access at: http://localhost:8000
```

#### Option 2: Using Docker Compose
```bash
docker-compose up -d
```

#### Option 3: Manual Docker
```bash
docker build -f Dockerfile.dev -t stonescriptphp-server:dev .
docker run -d -p 8000:8000 -v $(pwd):/var/www/html --env-file .env stonescriptphp-server:dev
```

### Production

```bash
docker build -f Dockerfile.prod -t stonescriptphp-server:prod .
docker run -d -p 8000:8000 --env-file .env stonescriptphp-server:prod
```

---

## Verification

### Test Your Routes

```bash
# Health check (should work)
curl http://localhost:8000/api/health

# Test authentication exclusion
curl http://localhost:8000/

# Test a protected route (should require auth)
curl http://localhost:8000/api/protected
```

### Check Logs

```bash
# See all logs
docker logs stonescriptphp-dev

# Follow logs in real-time
docker logs -f stonescriptphp-dev

# Check Nginx access logs
docker exec stonescriptphp-dev tail -f /var/log/nginx/stonescriptphp-access.log
```

---

## Migration Guide

If you're using the old `php -S` approach:

### Before (❌ Don't use this)
```bash
php -S 127.0.0.1:9100 -t public
```

### After (✅ Use this)
```bash
# The CLI now handles this automatically
php stone serve

# Or use Docker directly
docker-compose up -d
```

### Update Your Scripts

If you have any startup scripts using `php -S`, replace them with Docker commands.

**Example `.vscode/tasks.json`:**
```json
{
    "label": "Start API Server",
    "type": "shell",
    "command": "php stone serve",  // ✅ Uses Docker now
    "problemMatcher": []
}
```

---

## Why This Matters

### Consistency
- **Same stack** in dev and production = fewer bugs
- **Same URL rewriting** behavior everywhere
- **Same performance** characteristics

### Reliability
- Nginx is battle-tested and handles millions of requests daily
- Proper process management with Supervisor
- Automatic restart on crashes

### Developer Experience
- Routes work correctly from day 1
- No mysterious routing bugs
- Better error messages
- Production-like environment locally

---

## Common Issues & Solutions

### "Cannot connect to Docker daemon"
```bash
# Start Docker Desktop or Docker service
sudo systemctl start docker  # Linux
# Or start Docker Desktop manually on Mac/Windows
```

### Port Already in Use
```bash
# Find what's using port 8000
lsof -i :8000  # Mac/Linux
netstat -ano | findstr :8000  # Windows

# Use a different port
php stone serve 9000
```

### Container Won't Start
```bash
# Check logs
docker logs stonescriptphp-dev

# Rebuild the image
docker build -f Dockerfile.dev -t stonescriptphp-server:dev .
```

---

## Performance Notes

### Development
- Hot reload works via volume mounts
- Code changes reflect immediately
- Debug mode enabled

### Production
- OPcache enabled for maximum performance
- Pre-built optimized autoloader
- No dev dependencies
- Smaller image size

---

## Report Framework Bug

The namespace bug in `StoneScriptPHP\Routing\Router.php:261` should be reported to the StoneScriptPHP maintainers:

**Bug Report Template:**
```markdown
Title: Incorrect namespace check in Router.php causes "Handler not implemented" error

Description:
Line 261 in src/Routing/Router.php checks for \Framework\IRouteHandler
but the actual interface is \StoneScriptPHP\IRouteHandler

This causes all routes to fail with "Handler not implemented correctly" error.

Fix:
Change line 261 from:
if (!($handler instanceof \Framework\IRouteHandler)) {

To:
if (!($handler instanceof \StoneScriptPHP\IRouteHandler)) {

Version: 2.2.0
```

---

## Additional Resources

- [Nginx Documentation](https://nginx.org/en/docs/)
- [PHP-FPM Configuration](https://www.php.net/manual/en/install.fpm.configuration.php)
- [Docker Best Practices](https://docs.docker.com/develop/dev-best-practices/)
- [Supervisor Configuration](http://supervisord.org/configuration.html)

---

## Summary Checklist

- ✅ Fixed namespace bug in Router.php
- ✅ Migrated from `php -S` to Nginx + PHP-FPM
- ✅ Updated `php stone serve` to use Docker
- ✅ Created production and development Dockerfiles
- ✅ Added Nginx configuration with proper URL rewriting
- ✅ Added Supervisor for process management
- ✅ Fixed PHP-FPM compatibility issues
- ✅ Ensured consistent dev/prod environments

**Result: Robust, production-ready infrastructure with reliable routing! 🎉**
