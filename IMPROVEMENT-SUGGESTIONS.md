# StoneScriptPHP Framework - Improvement Suggestions

**Date**: 2025-12-31
**Project**: ProGalaxy Platform
**Framework Version**: StoneScriptPHP v2.2.0
**Submitted By**: ProGalaxy Development Team

---

## Executive Summary

This document outlines improvement suggestions for the StoneScriptPHP framework based on real-world usage in the ProGalaxy Platform. These suggestions aim to improve developer experience, reduce common configuration errors, and provide better testing infrastructure.

---

## 1. JWT Authentication Middleware - Path Matching Issues

### Problem

**JWT middleware path matching doesn't account for URL rewriting by web servers.**

In our nginx configuration, all requests to `/api/*` are rewritten to `/index.php`, but the actual route path processed by the framework is without the `/api/` prefix. This creates confusion when configuring `excludedPaths` in JwtAuthMiddleware.

**Example Configuration Issue:**

```php
// nginx.conf - Rewrites /api/user/access → /index.php
location / {
    try_files $uri $uri/ /index.php?$query_string;
}

// routes.php - Route registered WITHOUT /api prefix
'POST' => [
    '/user/access' => App\Routes\PostUserAccessRoute::class,
]

// index.php - Developer naturally tries to exclude /api/user/access
$router->use(new JwtAuthMiddleware(
    jwtHandler: $jwtHandler,
    excludedPaths: [
        '/api/user/access',  // ❌ Doesn't work!
    ]
));

// What actually works:
excludedPaths: [
    '/user/access',  // ✅ Works, but confusing
]
```

**Impact:**
- Wasted 2+ hours debugging why authentication endpoints were returning 401
- Non-intuitive configuration (excludedPaths don't match browser URLs)
- Easy to misconfigure in production

### Suggested Solutions

#### Option 1: Auto-detect URL prefix stripping (Recommended)

```php
// JwtAuthMiddleware.php
class JwtAuthMiddleware implements MiddlewareInterface
{
    private string $urlPrefix = '';

    public function __construct(
        RsaJwtHandler $jwtHandler,
        array $excludedPaths = [],
        string $headerName = 'Authorization',
        ?string $urlPrefix = null  // NEW: Auto-detect if null
    ) {
        // Auto-detect URL prefix by comparing REQUEST_URI with actual route
        if ($urlPrefix === null) {
            $requestUri = $_SERVER['REQUEST_URI'] ?? '';
            $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
            // Extract prefix (e.g., /api) from difference
            $this->urlPrefix = $this->detectUrlPrefix($requestUri, $scriptName);
        } else {
            $this->urlPrefix = $urlPrefix;
        }

        // Normalize excluded paths to work with or without prefix
        $this->excludedPaths = $this->normalizeExcludedPaths($excludedPaths);
    }

    private function normalizeExcludedPaths(array $paths): array
    {
        $normalized = [];
        foreach ($paths as $path) {
            // Add both versions: with and without prefix
            $normalized[] = $path;
            if ($this->urlPrefix && !str_starts_with($path, $this->urlPrefix)) {
                $normalized[] = $this->urlPrefix . $path;
            }
        }
        return $normalized;
    }
}
```

#### Option 2: Document the behavior clearly

Add clear documentation and examples to `JwtAuthMiddleware` class:

```php
/**
 * IMPORTANT: Excluded paths must match the INTERNAL route path,
 * not the external URL path seen by browsers.
 *
 * Example with nginx rewrite rules:
 * - Browser URL: POST /api/user/access
 * - Nginx rewrites to: /index.php
 * - Internal route: /user/access
 * - Exclude path: '/user/access' (NOT '/api/user/access')
 *
 * @param array $excludedPaths Array of route paths to exclude from JWT check
 */
public function __construct(
    RsaJwtHandler $jwtHandler,
    array $excludedPaths = [],
    string $headerName = 'Authorization'
) {
    // ...
}
```

#### Option 3: Add validation helper

```php
// Add static helper method to JwtAuthMiddleware
public static function validateExcludedPaths(array $excludedPaths, array $registeredRoutes): array
{
    $warnings = [];
    foreach ($excludedPaths as $path) {
        $found = false;
        foreach ($registeredRoutes as $method => $routes) {
            if (isset($routes[$path])) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            $warnings[] = "Excluded path '$path' does not match any registered route. Did you include a URL prefix by mistake?";
        }
    }
    return $warnings;
}

// Usage in index.php
if (DEBUG_MODE) {
    $warnings = JwtAuthMiddleware::validateExcludedPaths(
        ['/api/user/access', '/user/access'],
        $routesConfig
    );
    foreach ($warnings as $warning) {
        error_log("JWT Middleware Warning: $warning");
    }
}
```

---

## 2. Missing Database Helper Method - `result_as_single()`

### Problem

**Common pattern of returning single row from database lacks dedicated helper method.**

We implemented 6 database functions that return a single row (create, update, get by ID, etc.). The natural method name would be `Database::result_as_single()`, but it doesn't exist. Currently using workarounds:

```php
// What we want to write:
return Database::result_as_single($function_name, $rows, ModelClass::class);

// What we have to write:
$result = Database::result_as_table($function_name, $rows, ModelClass::class);
return $result[0] ?? null;

// Or use result_as_object() which has different semantics
```

**Impact:**
- Less readable code
- More verbose
- Easy to forget `?? null` fallback
- `result_as_object()` vs `result_as_single()` semantic confusion

### Suggested Solution

Add `result_as_single()` method to Database class:

```php
// Database.php
/**
 * Convert PostgreSQL function result to a single model object
 * Returns null if no rows found
 *
 * @param string $function_name Name of the database function
 * @param resource|array $rows Result from pg_query_params
 * @param string $class_name Fully qualified class name for mapping
 * @return object|null Single instance of $class_name or null
 */
public static function result_as_single(string $function_name, $rows, string $class_name): ?object
{
    $result = self::result_as_table($function_name, $rows, $class_name);
    return $result[0] ?? null;
}
```

**Usage Examples:**

```php
// Create operations (INSERT RETURNING)
class FnCreateQaTask {
    public static function run(...): ?CreateQaTaskModel {
        $rows = Database::fn('create_qa_task', [...]);
        return Database::result_as_single('create_qa_task', $rows, CreateQaTaskModel::class);
    }
}

// Get by ID operations (SELECT ... WHERE id = ...)
class FnGetQaTaskById {
    public static function run(int $id): ?GetQaTaskByIdModel {
        $rows = Database::fn('get_qa_task_by_id', [$id]);
        return Database::result_as_single('get_qa_task_by_id', $rows, GetQaTaskByIdModel::class);
    }
}

// Update operations (UPDATE ... RETURNING)
class FnUpdateQaTask {
    public static function run(...): ?UpdateQaTaskModel {
        $rows = Database::fn('update_qa_task', [...]);
        return Database::result_as_single('update_qa_task', $rows, UpdateQaTaskModel::class);
    }
}
```

**Benefits:**
- Clearer intent - "I expect exactly one row"
- Consistent naming with `result_as_table()` and `result_as_object()`
- Less boilerplate code
- Automatic null handling

---

## 3. API Testing Infrastructure

### Problem

**No built-in tools or guidance for testing API endpoints during development.**

Testing REST APIs requires:
1. Authentication (getting JWT tokens)
2. Making HTTP requests with proper headers
3. Token persistence between requests
4. Easy endpoint invocation

Developers end up writing custom bash scripts or using external tools (Postman, curl commands).

### Suggested Solution

#### Include API Testing Script Generator

Add a CLI command to generate testing infrastructure:

```bash
php stonescript generate:api-test-script
```

This should create:

**1. `tests/api-request.sh` - Reusable API testing script:**

```bash
#!/bin/bash

# Auto-generated by StoneScriptPHP
# Usage: ./tests/api-request.sh [endpoint] [method] [data]

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TOKEN_FILE="$SCRIPT_DIR/.token"
API_BASE_URL="${API_BASE_URL:-http://localhost:8000}"

# Function to authenticate and get token
get_token() {
    echo "Authenticating..." >&2

    # Try /user/access first (common pattern)
    local response=$(curl -s -X POST "$API_BASE_URL/user/access" \
        -H "Content-Type: application/json" \
        -d '{
            "signin_email": "'"${TEST_EMAIL:-test@example.com}"'",
            "password": "'"${TEST_PASSWORD:-test-password-123}"'"
        }')

    local token=$(echo "$response" | jq -r '.data.access_token // .access_token // empty')

    if [ -z "$token" ] || [ "$token" = "null" ]; then
        # Try /auth/login as fallback
        response=$(curl -s -X POST "$API_BASE_URL/auth/login" \
            -H "Content-Type: application/json" \
            -d '{
                "email": "'"${TEST_EMAIL:-test@example.com}"'",
                "password": "'"${TEST_PASSWORD:-test-password-123}"'"
            }')
        token=$(echo "$response" | jq -r '.data.access_token // .access_token // empty')
    fi

    if [ -z "$token" ] || [ "$token" = "null" ]; then
        echo "ERROR: Failed to get access token" >&2
        echo "$response" | jq . >&2
        exit 1
    fi

    echo "$token" > "$TOKEN_FILE"
    echo "✓ Token saved to $TOKEN_FILE" >&2
    echo "$token"
}

# Check if we have a token, get new one if needed
if [ ! -f "$TOKEN_FILE" ]; then
    get_token > /dev/null
fi

TOKEN=$(cat "$TOKEN_FILE" | tr -d '\n')

# Parse arguments
ENDPOINT="$1"
METHOD="${2:-GET}"
DATA="$3"

# Make the API request
if [ "$METHOD" = "GET" ]; then
    curl -s -X GET "$API_BASE_URL/$ENDPOINT" \
        -H "Authorization: Bearer $TOKEN"
elif [ -n "$DATA" ]; then
    curl -s -X "$METHOD" "$API_BASE_URL/$ENDPOINT" \
        -H "Content-Type: application/json" \
        -H "Authorization: Bearer $TOKEN" \
        -d "$DATA"
else
    curl -s -X "$METHOD" "$API_BASE_URL/$ENDPOINT" \
        -H "Content-Type: application/json" \
        -H "Authorization: Bearer $TOKEN"
fi
```

**2. `tests/.env.test` - Test configuration:**

```bash
# Test user credentials
TEST_EMAIL=test@example.com
TEST_PASSWORD=test-password-123

# API base URL
API_BASE_URL=http://localhost:8000

# Optional: Admin test credentials
ADMIN_TEST_EMAIL=admin@example.com
ADMIN_TEST_PASSWORD=admin-password-123
```

**3. `.gitignore` updates:**

```
# StoneScriptPHP testing
tests/.token
tests/.env.test.local
```

**4. `docs/API-TESTING.md` - Testing guide:**

```markdown
# API Testing Guide

## Quick Start

```bash
# Start your API server
php -S localhost:8000 -t public

# Test authentication
./tests/api-request.sh user/access POST '{"signin_email":"test@example.com","password":"test123"}'

# Test GET endpoint
./tests/api-request.sh users GET | jq .

# Test POST endpoint
./tests/api-request.sh users POST '{"name":"John Doe","email":"john@example.com"}' | jq .

# Test with query parameters
./tests/api-request.sh "users?limit=10&offset=0" GET | jq .
```

## Environment Variables

- `API_BASE_URL` - Base URL for API (default: http://localhost:8000)
- `TEST_EMAIL` - Test user email (default: test@example.com)
- `TEST_PASSWORD` - Test user password (default: test-password-123)

## Token Management

- Tokens are cached in `tests/.token`
- Delete this file to force re-authentication: `rm tests/.token`
- Tokens are automatically excluded from git

## Examples

### Create Resource
```bash
./tests/api-request.sh api/posts POST '{
  "title": "My Post",
  "content": "Post content here",
  "status": "published"
}' | jq .
```

### List Resources
```bash
./tests/api-request.sh api/posts GET | jq '.data.posts[] | {id, title}'
```

### Update Resource
```bash
./tests/api-request.sh api/posts/123 PATCH '{
  "title": "Updated Title"
}' | jq .
```

### Delete Resource
```bash
./tests/api-request.sh api/posts/123 DELETE | jq .
```
```

### Benefits

1. **Faster Development** - Test endpoints immediately without Postman
2. **Reproducible** - Share test scripts with team via git
3. **CI/CD Ready** - Use same scripts in automated tests
4. **Token Management** - Automatic token caching and refresh
5. **Documentation** - Examples show developers how to use the API

---

## 4. Common PostgreSQL Extensions Auto-Installation

### Problem

**Required PostgreSQL extensions not installed by default.**

We encountered: `ERROR: function gen_random_bytes(integer) does not exist`

This required manual installation of `pgcrypto` extension. Common extensions like `pgcrypto` (random generation), `uuid-ossp` (UUIDs), `pg_trgm` (text search) are frequently needed but not documented.

### Suggested Solution

#### Option 1: Include migration for common extensions

Add to framework starter template:

```sql
-- migrations/001_install_common_extensions.sql
-- Safe to run multiple times

CREATE EXTENSION IF NOT EXISTS pgcrypto;   -- Random generation, encryption
CREATE EXTENSION IF NOT EXISTS "uuid-ossp"; -- UUID generation
CREATE EXTENSION IF NOT EXISTS pg_trgm;     -- Trigram text search
CREATE EXTENSION IF NOT EXISTS unaccent;    -- Remove accents from text
CREATE EXTENSION IF NOT EXISTS hstore;      -- Key-value storage
```

#### Option 2: Document in setup guide

Add to framework documentation:

```markdown
## PostgreSQL Extensions

Common extensions used by StoneScriptPHP applications:

- **pgcrypto** - Cryptographic functions, random generation
  ```sql
  CREATE EXTENSION IF NOT EXISTS pgcrypto;
  ```

- **uuid-ossp** - UUID generation
  ```sql
  CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
  ```

- **pg_trgm** - Full-text search with trigrams
  ```sql
  CREATE EXTENSION IF NOT EXISTS pg_trgm;
  ```

Install all at once:
```bash
psql -U postgres -d your_database << 'EOF'
CREATE EXTENSION IF NOT EXISTS pgcrypto;
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS pg_trgm;
EOF
```
```

---

## 5. Route Registration Validation

### Problem

**No validation that excluded JWT paths actually exist in routes.**

Easy to typo or misconfigure excluded paths with no feedback until runtime 401 errors occur.

### Suggested Solution

Add validation in DEBUG mode:

```php
// Router.php or JwtAuthMiddleware.php
public function validateConfiguration(array $routesConfig, array $excludedPaths): array
{
    if (!defined('DEBUG_MODE') || !DEBUG_MODE) {
        return [];
    }

    $warnings = [];
    $allRoutes = [];

    // Flatten all routes
    foreach ($routesConfig as $method => $routes) {
        foreach ($routes as $path => $handler) {
            $allRoutes[] = $path;
        }
    }

    // Check each excluded path
    foreach ($excludedPaths as $excludedPath) {
        if (!in_array($excludedPath, $allRoutes)) {
            $warnings[] = "JWT excluded path '$excludedPath' not found in registered routes";

            // Suggest similar paths
            $similar = $this->findSimilarPaths($excludedPath, $allRoutes);
            if ($similar) {
                $warnings[] = "  Did you mean: " . implode(', ', $similar);
            }
        }
    }

    return $warnings;
}

private function findSimilarPaths(string $needle, array $haystack): array
{
    $similar = [];
    foreach ($haystack as $path) {
        if (levenshtein($needle, $path) < 5) {
            $similar[] = $path;
        }
    }
    return $similar;
}
```

**Usage:**

```php
// index.php (in DEBUG mode)
if (DEBUG_MODE) {
    $warnings = $router->validateConfiguration($routesConfig, [
        '/user/access',
        '/api/user/access',  // Typo - will warn
    ]);

    foreach ($warnings as $warning) {
        error_log("[StoneScriptPHP] Configuration Warning: $warning");
    }
}
```

**Example output:**

```
[StoneScriptPHP] Configuration Warning: JWT excluded path '/api/user/access' not found in registered routes
[StoneScriptPHP] Configuration Warning:   Did you mean: /user/access
```

---

## 6. Environment Variable Documentation

### Problem

**Environment variables like `QA_WEBHOOK_SECRET` are used in code but not documented.**

We used `getenv('QA_WEBHOOK_SECRET')` but it took time to realize this needs to be in `.env` file.

### Suggested Solution

#### Add .env.example validation

```php
// config/env-validator.php
class EnvValidator
{
    public static function validateRequiredVars(array $required): array
    {
        $missing = [];
        foreach ($required as $var => $description) {
            if (getenv($var) === false) {
                $missing[] = [
                    'var' => $var,
                    'description' => $description
                ];
            }
        }
        return $missing;
    }

    public static function checkExample(): array
    {
        $warnings = [];
        if (!file_exists('.env') && !file_exists('.env.example')) {
            $warnings[] = "No .env or .env.example file found";
        }
        return $warnings;
    }
}

// Usage in routes that use env vars:
class PostQaWebhookRoute implements IRouteHandler
{
    public static function getRequiredEnvVars(): array
    {
        return [
            'QA_WEBHOOK_SECRET' => 'Secret key for validating webhook requests from work-management'
        ];
    }

    public function process(): ApiResponse
    {
        $expected_secret = getenv('QA_WEBHOOK_SECRET') ?: 'default-webhook-secret';

        if ($expected_secret === 'default-webhook-secret' && DEBUG_MODE) {
            error_log("WARNING: QA_WEBHOOK_SECRET not set, using default (insecure)");
        }

        // ... rest of code
    }
}
```

---

## Priority Recommendations

### High Priority (Immediate Impact)

1. **JWT Path Matching** - Option 1 (auto-detect) or at minimum Option 2 (documentation)
2. **`result_as_single()` method** - Commonly needed, simple to implement
3. **API Testing Script Generator** - Huge DX improvement

### Medium Priority (Quality of Life)

4. **Common PostgreSQL Extensions** - Document or auto-install
5. **Route Validation** - Helps catch configuration errors early

### Low Priority (Nice to Have)

6. **Environment Variable Validation** - Helpful but can be worked around

---

## Real-World Impact

These improvements are based on actual issues encountered during ProGalaxy Platform development:

- **JWT Path Issue**: 2+ hours debugging 401 errors
- **Missing `result_as_single()`**: Rewrote 6 database functions
- **No Testing Infrastructure**: Created custom script, should be standard
- **pgcrypto Missing**: 30 minutes to diagnose and fix
- **No Route Validation**: Would have caught JWT path issue immediately

**Total time saved with these improvements: ~4-6 hours per project**

---

## Conclusion

StoneScriptPHP is a solid framework, but these relatively small improvements would significantly enhance developer experience and reduce common configuration pitfalls. We're happy to contribute implementations for any of these suggestions if the team is interested.

---

**Contact**: ProGalaxy Development Team
**Framework**: StoneScriptPHP v2.2.0
**Project**: https://github.com/progalaxyelabs/progalaxy-platform
