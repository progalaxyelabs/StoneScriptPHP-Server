# StoneScriptPHP Server - Production Readiness Test Report

**Test Date:** 2025-12-22
**Version:** v2.2.3
**Project:** Simple CRUD Todos App

## Test Methodology
Testing if a production-ready server can be built using ONLY the `php stone` CLI commands without manual workarounds.

---

## Findings

### ✅ What Works

### ❌ Gaps & Issues Found

### ⚠️ Workarounds Needed

### 📝 Manual Steps Required

---

## Test Log

### Test 1: Database Migrations & Schema Management

**Expectation:** Should be able to define database schema and apply it using CLI commands.

**Commands Tested:**
```bash
php stone migrate --help
php stone migrate verify
```

**Findings:**

❌ **CRITICAL: No way to create/manage table schemas**
- Only `.pgsql` function files are supported
- `schema.sql` files are completely ignored
- No `php stone generate migration` or `php stone generate table` commands
- **Manual workaround required:** Must manually execute SQL to create tables

❌ **CRITICAL: Migration commands incomplete**
- Only `migrate verify` works
- `migrate up`, `migrate down`, `migrate status`, `migrate generate` all marked as "COMING SOON"
- Cannot apply migrations through CLI
- **Manual workaround required:** Must manually run SQL files in PostgreSQL

⚠️ **migrate verify doesn't validate dependencies**
- Created `get_todos.pgsql` function that references non-existent `todos` table
- `migrate verify` reported "No drift detected"
- Does not check if referenced tables exist
- **Risk:** False positive - says everything is fine when database is incomplete

**Conclusion:** Migration system is NOT production-ready. Missing critical features.

---

### Test 2: Route Generation

**Expectation:** CLI should generate working route handlers with proper scaffolding.

**Command Tested:**
```bash
php stone generate route get /api/todos
```

**Findings:**

✅ **Route generation works**
- Creates Contract interface
- Creates Request DTO
- Creates Response DTO
- Creates Route handler class
- Updates routes.php automatically

❌ **BUG: Inconsistent `::class` syntax in routes.php**
```php
'/' => App\Routes\HomeRoute,          // Missing ::class
'/api/health' => App\Routes\HealthRoute,  // Missing ::class
'/api/todos' => \App\Routes\GetApiTodosRoute::class,  // Has ::class
```
- Causes "Undefined constant" errors at runtime
- **Manual fix required:** Add `::class` to existing routes

⚠️ **Generated code is just skeleton - requires significant manual work**
- DTOs are empty classes with TODO comments
- Route handler has placeholder `throw new \Exception('Not Implemented')`
- No guidance on how to properly use the DTO pattern
- No examples or documentation

⚠️ **Complex DTO pattern may be overkill for simple APIs**
- Every route needs Request DTO, Response DTO, Contract interface, and Handler
- For simple CRUD, this is 4 files per endpoint
- No simpler alternative for basic routes

**Conclusion:** Route generation works but produces buggy code and requires extensive manual implementation.

---

### Test 3: Database Initialization for Production Deployment

**Expectation:** When deploying to production, PostgreSQL container should automatically run all schema/function files from `src/App/Functions/` during first initialization.

**Setup Tested:**
- PostgreSQL uses standard `/docker-entrypoint-initdb.d/` for init scripts
- StoneScriptPHP stores functions in `api/src/App/Functions/*.pgsql`

**Findings:**

❌ **CRITICAL: No automatic database initialization**
- PostgreSQL container's `/docker-entrypoint-initdb.d/` is empty
- No volume mount connecting `src/App/Functions/` to init directory
- **Missing from docker-compose.yaml:**
  ```yaml
  postgres:
    volumes:
      - ./api/src/App/Functions:/docker-entrypoint-initdb.d:ro
  ```

❌ **No build/deployment script provided**
- No `php stone build` or `php stone deploy` command
- No script to combine all `.pgsql` files into single init script
- Developers expected to manually handle production database setup

❌ **No migration runner on app startup**
- API container doesn't run `php stone migrate up` on start (which doesn't exist anyway)
- No health check ensuring database schema is applied before serving requests
- **Risk:** App starts serving 500 errors because tables don't exist

**Impact on Production:**
1. **Fresh deployment fails** - Database is empty, app crashes
2. **Manual intervention required** - DevOps must SSH and run SQL manually
3. **No reproducibility** - Each environment setup is different
4. **Deployment complexity** - Requires custom scripts outside the framework

**What's needed:**
- Automatic schema initialization from source files
- OR: `php stone db:init` command to apply all migrations
- OR: Docker entrypoint script that runs migrations before starting API
- OR: Health check endpoint that triggers migrations if needed

**Conclusion:** No production deployment story. Framework assumes developer will manually set up database.

**UPDATE - CRITICAL BUG FOUND:**

❌ **PACKAGING BUG: `src/postgresql` directory not included in composer package**

**Evidence:**
```bash
# Source repo HAS the directory:
/StoneScriptPHP-Server/src/postgresql/
  ├── functions/
  └── tables/

# Installed package is MISSING it:
/api/src/
  ├── App/
  └── config/
  # postgresql/ directory is MISSING!
```

**Impact:**
- Even though source repo has structure for postgresql schema files
- The directory doesn't get installed via `composer create-project`
- This explains why there's no automatic database initialization
- **Root cause:** composer.json likely missing this directory in distribution

**What should happen:**
1. `src/postgresql` should be included in package
2. Docker volume mount: `./api/src/postgresql:/docker-entrypoint-initdb.d:ro`
3. PostgreSQL auto-runs all `.sql` and `.pgsql` files on first start

**This is a distribution/packaging bug, not just missing feature.**

---

