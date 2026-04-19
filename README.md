# StoneScriptPHP Server

**A minimal, composable API server skeleton for building PostgreSQL-backed REST APIs.**

Clean starting point with zero bloat - add only what you need through CLI commands.

## Features

- 🎯 **Minimal by default** - Only database connection, no pre-configured auth or models
- 🔧 **Composable authentication** - Choose email/password, OAuth, API keys, or combine them
- 📦 **Migration-based** - Version-controlled database schema
- 🚀 **Production-ready** - Docker support, RBAC, JWT, rate limiting
- ⚡ **Developer-friendly** - CLI code generation, hot reload

---

## Quick Start

### 1. Create Project

```bash
composer create-project progalaxyelabs/stonescriptphp-server my-api
cd my-api
```

### 2. Setup Environment

```bash
# Interactive setup wizard (recommended)
php stone setup

# Or manually create .env with database credentials
cp .env.example .env
```

### 3. Choose Your Authentication

**Email/Password Authentication:**
```bash
php stone generate auth:email-password
php stone migrate up
php stone seed rbac
php stone create:admin
```

**Google OAuth:**
```bash
php stone generate auth:google
php stone migrate up
```

**API Keys:**
```bash
php stone generate auth:api-key
php stone migrate up
```

**Or combine multiple methods!**

### 4. Start Development

```bash
# Start Docker-based development server (recommended)
composer dev
# API running at http://localhost:8000

# Or use framework's built-in server
php stone serve
# API running at http://localhost:9100
```

---

## Architecture

**What's included by default:**
- Database connection setup
- Routing infrastructure
- Environment configuration
- Docker setup
- CLI tools

**What's NOT included (generate as needed):**
- ❌ No authentication routes
- ❌ No user models or tables
- ❌ No default roles or permissions
- ❌ No seeders

**Philosophy:** Start minimal, add incrementally via CLI commands.

---

## Docker Development

StoneScriptPHP-Server provides a Docker-first development experience with Nginx + PHP-FPM.

### Development Commands

```bash
# Start development server (builds image if needed, runs detached)
composer dev
# or
composer serve

# Stop development server
composer stop

# Restart server
composer restart

# View application logs (follow mode)
composer logs

# Access container shell
composer shell

# Rebuild image (after Dockerfile changes)
composer build

# Clean up (removes container and image)
composer clean
```

### What's Included

- **Nginx** - Production-grade web server with proper URL rewriting
- **PHP-FPM** - High-performance PHP processor
- **Supervisor** - Process manager for Nginx + PHP-FPM
- **Development tools** - git, vim, curl, postgres client, etc.

### Live Reload

Your code is mounted as a volume - changes are reflected immediately without rebuilding.

### Database Connection

Connect to your external PostgreSQL instance via `.env`:
```env
DB_HOST=localhost      # or your database host
DB_PORT=5432
DB_NAME=stonescriptphp
DB_USER=postgres
DB_PASSWORD=postgres
```

---

## CLI Commands

### Authentication

```bash
# Generate authentication methods (composable)
php stone generate auth:email-password    # Traditional auth
php stone generate auth:google            # Google OAuth
php stone generate auth:linkedin          # LinkedIn OAuth
php stone generate auth:apple             # Apple OAuth
php stone generate auth:api-key           # API key auth
```

### Database

```bash
php stone migrate status    # Check migration status
php stone migrate up        # Run pending migrations
php stone migrate down      # Rollback last batch
php stone migrate verify    # Check for schema drift
```

### Seeding

```bash
php stone seed rbac         # Seed roles & permissions
```

### User Management

```bash
php stone create:admin      # Create system admin (interactive)
```

### Code Generation

```bash
php stone generate route POST /auth/login       # Generate route handler
php stone generate model get_user.pgsql         # Generate model from SQL function
php stone generate client                       # Generate TypeScript client
php stone generate jwt                          # Generate JWT keypair
```

### Development

```bash
php stone serve             # Start dev server
php stone stop              # Stop dev server
php stone test              # Run tests
```

---

## Example Workflow

**Building an API with email/password auth:**

```bash
# 1. Create project
composer create-project progalaxyelabs/stonescriptphp-server my-api
cd my-api

# 2. Setup database
php stone setup

# 3. Add email/password authentication
php stone generate auth:email-password

# 4. Run migrations
php stone migrate up

# 5. Seed RBAC (roles & permissions)
php stone seed rbac

# 6. Create admin user
php stone create:admin
# Enter: admin@example.com, password, Admin User

# 7. Start server
php stone serve

# 8. Test login
curl -X POST http://localhost:9100/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"your-password"}'
```

---

## Token Exchange (External Auth Mode)

When using external authentication (mode: 'external' in `config/auth.php`), your API validates JWTs from a central auth service but may need to issue **platform tokens** with additional claims like roles.

### Flow

```
1. User authenticates with external auth service → receives identity token
2. Client calls POST /api/auth/exchange with identity token
3. API validates token against auth service JWKS
4. API looks up user roles from tenant database
5. API signs and returns platform token with roles
```

### Setup

**1. Generate platform signing keys:**
```bash
openssl genrsa -out keys/platform-private.pem 2048
openssl rsa -in keys/platform-private.pem -pubout -out keys/platform-public.pem
```

**2. Configure `config/auth.php`:**
```php
return [
    'mode' => 'external',
    'server' => [
        'url' => 'http://auth-service:3139',  // Auth service URL
        'issuer' => 'https://auth.example.com', // JWT issuer claim
        'paths' => [
            'jwks' => '/api/auth/jwks',
        ],
    ],
    'platform_jwt' => [
        'private_key_path' => __DIR__ . '/../../keys/platform-private.pem',
        'public_key_path' => __DIR__ . '/../../keys/platform-public.pem',
        'algorithm' => 'RS256',
        'key_id' => 'platform-key-1',
        'ttl' => 3600,
    ],
];
```

**3. Customize role lookup in `TokenExchangeRoute.php`:**

The skeleton uses `Database::fn('get_user_role', [$identityId])`. Replace this with your tenant DB schema:

```php
// Option A: user_roles junction table
$roleResult = Database::fn('get_user_roles_by_identity', [$identityId]);

// Option B: Single role column
$roleResult = Database::fn('get_user_by_identity', [$identityId]);
$role = $roleResult[0]['role'] ?? 'member';
```

### Usage

```bash
# Exchange identity token for platform token
curl -X POST http://localhost:9100/api/auth/exchange \
  -H "Authorization: Bearer <identity-token>"

# Response
{
  "status": "ok",
  "data": {
    "access_token": "<platform-token>",
    "token_type": "Bearer",
    "expires_in": 3600,
    "role": "admin",
    "roles": ["admin", "member"]
  }
}
```

### Platform Token Claims

The issued platform token includes:
- `identity_id`, `tenant_id`, `tenant_slug` - from identity token
- `role`, `roles` - from tenant database lookup
- `token_type: "platform"` - distinguishes from identity tokens
- Standard JWT claims (`iat`, `exp`, `iss`, `sub`)

---

## Migrations

Migrations are stored in `migrations/` and run in order:

```
migrations/
├── 001_create_users_table.sql           # Base users table
├── 002_add_email_password_auth.sql      # Email/password columns
├── 003_add_oauth_providers.sql          # OAuth providers table
├── 004_create_api_keys_table.sql        # API keys table
└── 005_create_rbac_tables.sql           # RBAC tables
```

Each `generate auth:*` command adds the necessary migrations. They're composable - run in any order!

---

## Docker Deployment

```bash
# Start with docker-compose
docker compose up -d

# Run migrations inside container
docker exec -it stonescriptphp-app php stone migrate up

# Create admin user
docker exec -it stonescriptphp-app php stone create:admin
```

See `docker-compose.yaml` for configuration.

---

## Default Roles (after `php stone seed rbac`)

| Role | Permissions |
|------|-------------|
| `super_admin` | All permissions |
| `admin` | Most permissions except critical ones |
| `moderator` | Content management + user viewing |
| `user` | Basic content permissions |
| `guest` | Read-only access |

Customize by editing the seeder or creating your own roles.

---

## Environment Variables

Required variables (created by `php stone setup`):

```env
# App
APP_NAME=StoneScriptPHP
APP_ENV=development
APP_PORT=9100

# Database
DATABASE_HOST=localhost
DATABASE_PORT=5432
DATABASE_USER=postgres
DATABASE_PASSWORD=your-password
DATABASE_DBNAME=your-database

# JWT (auto-generated)
JWT_PRIVATE_KEY_PATH=./keys/jwt-private.pem
JWT_PUBLIC_KEY_PATH=./keys/jwt-public.pem
JWT_EXPIRY=3600

# Optional: OAuth
GOOGLE_CLIENT_ID=your-client-id
GOOGLE_CLIENT_SECRET=your-client-secret
```

---

## Project Structure

```
my-api/
├── migrations/              # Database migrations (generated)
├── public/
│   └── index.php           # Entry point
├── src/
│   ├── App/
│   │   ├── Routes/         # Route handlers
│   │   ├── DTO/            # Data Transfer Objects
│   │   ├── Lib/            # Custom libraries
│   │   └── AppEnv.php      # Application environment config
│   ├── config/
│   │   ├── routes.php      # Route definitions
│   │   └── allowed-origins.php  # CORS config
│   └── postgresql/
│       ├── tables/         # Table schemas
│       └── functions/      # SQL functions
├── composer.json
├── docker-compose.yaml
└── .env
```

---

## Contributing

This is the application skeleton. For framework contributions, see [StoneScriptPHP](https://github.com/progalaxyelabs/StoneScriptPHP).

---

## License

MIT License - see LICENSE file for details.

---

## Next Steps

- [Framework Documentation](https://github.com/progalaxyelabs/StoneScriptPHP)
- [Docker Deployment Guide](DOCKER.md)
- [High Level Design](HLD.md)
- [Website](https://stonescriptphp.org)
