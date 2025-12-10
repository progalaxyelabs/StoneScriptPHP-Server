# ✅ StoneScriptPHP Server - Start Here!

**This is the recommended starting point for building APIs with StoneScriptPHP.**

Application skeleton with everything you need to build production-ready PostgreSQL APIs. The core framework ([stonescriptphp](https://github.com/progalaxyelabs/StoneScriptPHP)) is automatically installed as a dependency.

## 📦 Package Ecosystem

| Package | Purpose | When to Use |
|---------|---------|-------------|
| **[stonescriptphp-server](https://github.com/progalaxyelabs/StoneScriptPHP-Server)** | Application skeleton | ✅ **You are here** - Creating new projects |
| **[stonescriptphp](https://github.com/progalaxyelabs/StoneScriptPHP)** | Core framework | Advanced - Framework development, custom integrations |

---

## Quick Start

### Local Development

```bash
# Create a new project
composer create-project progalaxyelabs/stonescriptphp-server my-api
cd my-api

# Start development server
php stone serve
# Your API is running at http://localhost:9100
```

### Docker Deployment

```bash
# Start with Docker Compose (includes PostgreSQL)
docker compose up -d

# Your API is running at http://localhost:8000
```

See [DOCKER.md](DOCKER.md) for complete Docker documentation.

The setup wizard will:
1. Configure database connection
2. Generate JWT keypair for authentication
3. Create `.env` file with defaults

## What's Included

This skeleton provides everything you need:

✅ **Core Framework** - The [stonescriptphp](https://github.com/progalaxyelabs/StoneScriptPHP) framework installed as a dependency
✅ **CLI Tools** - `php stone` commands for code generation and project management
✅ **Project Structure** - Organized folders for routes, models, database, and configuration
✅ **Example Routes** - Sample `HomeRoute` to demonstrate the pattern
✅ **Environment Setup** - Type-safe `.env` configuration with validation
✅ **Database Templates** - Folders for PostgreSQL tables, functions, and migrations
✅ **Authentication Ready** - JWT configuration with keypair generation
✅ **Testing Setup** - PHPUnit configured and ready to use

## Project Structure

```
my-api/
├── src/
│   └── App/
│       ├── Config/
│       │   └── routes.php          # URL-to-route mappings
│       ├── Routes/                 # Route handlers
│       │   └── HomeRoute.php       # Example route
│       ├── Services/               # Business logic layer
│       ├── Database/
│       │   ├── Functions/          # Generated PHP models
│       │   └── postgres/
│       │       ├── tables/         # Table schemas (.pssql)
│       │       ├── functions/      # SQL functions (.pssql)
│       │       └── seeds/          # Seed data
│       └── Env.php                 # Application environment config
├── vendor/
│   └── progalaxyelabs/
│       └── stonescriptphp/         # ← Framework lives here
├── tests/                          # PHPUnit tests
├── stone                           # CLI tool
├── .env                            # Environment variables
└── composer.json
```

## Development Workflow

### 1. Define Database Schema

Create table in `src/App/Database/postgres/tables/`:

```sql
-- users_table.pssql
CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### 2. Create SQL Functions

Create function in `src/App/Database/postgres/functions/`:

```sql
-- get_users.pssql
CREATE OR REPLACE FUNCTION get_users()
RETURNS TABLE (
    id INTEGER,
    name VARCHAR(100),
    email VARCHAR(255)
) AS $$
BEGIN
    RETURN QUERY
    SELECT u.id, u.name, u.email
    FROM users u
    ORDER BY u.id DESC;
END;
$$ LANGUAGE plpgsql;
```

### 3. Generate PHP Model

```bash
php stone generate model get_users.pgsql
```

This creates `FnGetUsers.php` in `src/App/Database/Functions/` from the PostgreSQL function in `src/postgresql/functions/get_users.pgsql`

### 4. Create Route Handler

```bash
php stone generate route get-users
```

This creates `GetUsersRoute.php` in `src/App/Routes/`

### 5. Map URL to Route

Edit `src/App/Config/routes.php`:

```php
return [
    'GET' => [
        '/api/users' => GetUsersRoute::class,
    ],
    'POST' => [
        // Add POST routes here
    ],
];
```

### 6. Implement Route Logic

Edit the generated route class:

```php
class GetUsersRoute implements IRouteHandler
{
    public function validation_rules(): array
    {
        return []; // No validation for GET
    }

    public function process(): ApiResponse
    {
        $users = FnGetUsers::run();
        return res_ok(['users' => $users]);
    }
}
```

### 7. Run Migrations

```bash
php stone migrate verify
```

This checks for database drift and ensures your schema matches your code.

## CLI Commands

```bash
# Project Management
php stone setup                         # Interactive project setup
php stone serve                         # Start development server (port 9100)
php stone stop                          # Stop development server
php stone env                           # Generate .env file

# Code Generation
php stone generate route <name>         # Generate route handler
php stone generate model <file.pgsql>   # Generate model from PostgreSQL function
php stone generate auth:google          # Generate Google OAuth authentication
php stone generate auth:linkedin        # Generate LinkedIn OAuth authentication
php stone generate auth:apple           # Generate Apple OAuth authentication
php stone generate client               # Generate TypeScript client

# Database
php stone migrate verify                # Check database drift
php stone migrate run                   # Apply migrations (not yet implemented)

# Testing
php stone test                          # Run PHPUnit test suite

# Composer Shortcuts
composer serve                          # Same as: php stone serve
composer test                           # Same as: php stone test
composer migrate                        # Same as: php stone migrate verify
```

## Environment Configuration

The server extends the framework's `Env` class to add application-specific variables:

```php
use Framework\Env;

$env = Env::get_instance();  // Returns App\Env instance

// Framework variables (inherited)
$env->DEBUG_MODE;            // bool
$env->DATABASE_HOST;         // string
$env->DATABASE_PORT;         // int
$env->DATABASE_USER;         // string
$env->DATABASE_PASSWORD;     // string

// Application variables (from App\Env)
$env->APP_NAME;              // string
$env->APP_ENV;               // string
$env->APP_PORT;              // int
$env->JWT_PRIVATE_KEY_PATH;  // string
$env->JWT_PUBLIC_KEY_PATH;   // string
$env->JWT_EXPIRY;            // int
$env->ALLOWED_ORIGINS;       // string
```

### Adding Custom Variables

Edit `src/App/Env.php`:

```php
class Env extends FrameworkEnv
{
    public $STRIPE_API_KEY;

    public function getSchema(): array
    {
        $parentSchema = parent::getSchema();

        $appSchema = [
            'STRIPE_API_KEY' => [
                'type' => 'string',
                'required' => true,
                'default' => null,
                'description' => 'Stripe API key'
            ],
        ];

        return array_merge($parentSchema, $appSchema);
    }
}
```

Then add to `.env`:
```
STRIPE_API_KEY=sk_test_your_key_here
```

## Framework Updates

The core framework lives in `vendor/` and your application code in `src/` stays intact during upgrades.

```bash
# Update framework to latest version
composer update progalaxyelabs/stonescriptphp
```

The upgrade process:
- ✅ Updates only framework files in `vendor/`
- ✅ Preserves all your application code in `src/`
- ✅ Maintains database migrations history
- ✅ Keeps configuration files unchanged

## Versioning Strategy

This server skeleton follows the framework's versioning:

* **Patch versions (2.0.x)**: Bug fixes, security patches. Safe to update anytime.
* **Minor versions (2.x.0)**: New features, backward-compatible. Update when you need them.
* **Major versions (x.0.0)**: Breaking changes. Review migration guide before updating.

The `composer.json` uses `^2.0` to automatically receive framework updates within the same major version.

**Current stable:** v2.0.x - Production-ready with ongoing bug fixes

## Testing Local Framework Changes

To test changes to the framework without publishing to Packagist:

```json
// In your composer.json, add:
{
    "repositories": [
        {
            "type": "path",
            "url": "../StoneScriptPHP",
            "options": {"symlink": false}
        }
    ],
    "require": {
        "progalaxyelabs/stonescriptphp": "@dev"
    }
}
```

Then run `composer update progalaxyelabs/stonescriptphp`

## Requirements

### Required

* PHP >= 8.2
* PostgreSQL >= 13
* Composer
* PHP Extensions: `pdo`, `pdo_pgsql`, `json`, `openssl`

### Optional

* Redis server (for caching support)
* PHP Extension: `redis` (for Redis caching)

## Documentation

### 📖 Getting Started

* **[Getting Started Guide](https://stonescriptphp.org/docs/getting-started)** - Complete tutorial from installation to deployment
* **[CLI Usage Guide](https://stonescriptphp.org/docs/CLI-USAGE)** - Command reference
* **[Environment Configuration](https://stonescriptphp.org/docs/environment-configuration)** - Type-safe setup

### 🔧 Core Features

* [API Reference](https://stonescriptphp.org/docs/api-reference) - Complete API documentation
* [Logging & Exceptions](https://stonescriptphp.org/docs/logging-and-exceptions) - Production-ready logging
* [Request Validation](https://stonescriptphp.org/docs/validation) - Validation rules
* [Middleware Guide](https://stonescriptphp.org/docs/MIDDLEWARE) - Custom middleware

### 🔐 Security

* [Authentication](https://stonescriptphp.org/docs/authentication) - JWT and OAuth
* [RBAC](https://stonescriptphp.org/docs/RBAC) - Role-Based Access Control
* [Security Best Practices](https://stonescriptphp.org/docs/security-best-practices)

### ⚡ Performance

* [Redis Caching Guide](https://stonescriptphp.org/docs/CACHING) - Cache optimization
* [Performance Guidelines](https://stonescriptphp.org/docs/performance-guidelines)

## Architecture Philosophy

StoneScriptPHP follows a **PostgreSQL-first architecture**:

1. **Business Logic in Database** - SQL functions encapsulate complex queries
2. **Type-Safe PHP Models** - Generated classes wrap SQL functions
3. **Thin Route Layer** - Routes handle HTTP concerns (validation, auth)
4. **Clean Separation** - Database → Models → Services → Routes

**Benefits:**
- Leverages PostgreSQL's procedural capabilities
- Keeps logic close to the data
- Enables database performance optimization
- Facilitates testing and maintenance

## Related Packages

* **[stonescriptphp](https://github.com/progalaxyelabs/StoneScriptPHP)** - Core framework library (installed automatically as dependency)

## When to Use the Core Framework Directly

Most developers should use this server skeleton. However, you might want to use the [core framework](https://github.com/progalaxyelabs/StoneScriptPHP) directly if you're:

- Contributing to the framework core
- Building custom framework extensions
- Integrating StoneScriptPHP into an existing project
- Creating your own custom project template

```bash
# Direct framework installation (advanced usage)
composer require progalaxyelabs/stonescriptphp
```

## Support & Community

* **Website**: [stonescriptphp.org](https://stonescriptphp.org)
* **Full Documentation**: [stonescriptphp.org/docs](https://stonescriptphp.org/docs)
* **Server Issues**: [GitHub Issues](https://github.com/progalaxyelabs/StoneScriptPHP-Server/issues)
* **Framework Issues**: [GitHub Issues](https://github.com/progalaxyelabs/StoneScriptPHP/issues)
* **Discussions**: [GitHub Discussions](https://github.com/progalaxyelabs/StoneScriptPHP/discussions)

## Examples

Check out the `examples/` directory for sample implementations:
- Basic CRUD operations
- Authentication flows
- File uploads
- Real-time features

## Contributing

We welcome contributions! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines.

## License

MIT License - see [LICENSE](LICENSE) file for details

---

**Happy building! 🚀**

If you have questions or need help, visit [stonescriptphp.org](https://stonescriptphp.org) or open an issue on GitHub.
