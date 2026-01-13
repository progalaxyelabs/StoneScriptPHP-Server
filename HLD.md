# StoneScriptPHP Server - High Level Design

## Overview

**StoneScriptPHP Server** is a production-ready application skeleton and reference implementation for building PostgreSQL-based REST APIs using the [StoneScriptPHP](https://github.com/progalaxyelabs/StoneScriptPHP) framework. It provides a complete, opinionated project structure with CLI tools, authentication, database integration, and Docker support out of the box.

This is the recommended starting point for developers building new API projects with StoneScriptPHP.

## Purpose

The StoneScriptPHP Server serves as:

1. **Application Skeleton** - A pre-configured project template eliminating boilerplate setup
2. **Reference Implementation** - A best-practices example of StoneScriptPHP architecture
3. **Development Foundation** - Complete structure for routes, services, models, and database migrations
4. **Production Template** - Docker and environment configuration ready for deployment
5. **Learning Resource** - Demonstrates core framework patterns and conventions

## Architecture

### High-Level Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                     HTTP Requests                            │
│                   (JSON/Form data)                           │
└────────────────────────────┬────────────────────────────────┘
                             │
                    ┌────────▼────────┐
                    │   Router        │
                    │ (routes.php)    │
                    └────────┬────────┘
                             │
                    ┌────────▼────────────────┐
                    │  Route Handler          │
                    │  (IRouteHandler)        │
                    │  - Validation           │
                    │  - Processing           │
                    └────────┬────────────────┘
                             │
        ┌────────────────────┼────────────────────┐
        │                    │                    │
   ┌────▼─────┐      ┌──────▼────────┐     ┌────▼──────┐
   │ Services  │      │  Models       │     │  Auth     │
   │(Business) │      │ (Wrappers)    │     │(JWT/OAuth)│
   └────┬─────┘      └──────┬────────┘     └────┬──────┘
        │                    │                    │
        └────────────────────┼────────────────────┘
                             │
                    ┌────────▼────────────────┐
                    │   PostgreSQL Functions  │
                    │   (SQL Business Logic)  │
                    └────────┬────────────────┘
                             │
                    ┌────────▼────────┐
                    │  PostgreSQL DB  │
                    │  - Tables       │
                    │  - Functions    │
                    └─────────────────┘
```

### Key Design Principles

1. **PostgreSQL-First Architecture** - Business logic lives in SQL functions, not PHP
2. **Thin Route Layer** - Routes handle HTTP concerns (validation, auth, response formatting)
3. **Generated Models** - Type-safe PHP classes automatically generated from SQL functions
4. **CLI-Driven Development** - CLI tools automate common tasks (route generation, model generation)
5. **Environment-Based Configuration** - Type-safe environment configuration via `Env` class
6. **JWT & OAuth Ready** - Built-in authentication with configurable keypairs

### Component Structure

```
StoneScriptPHP-Server/
├── src/
│   ├── App/
│   │   ├── Routes/
│   │   │   ├── HomeRoute.php           # Example GET route
│   │   │   ├── HealthRoute.php         # Health check route
│   │   │   └── [Generated Routes]      # Auto-generated routes
│   │   ├── DTO/                        # Data Transfer Objects
│   │   ├── Lib/                        # Custom libraries
│   │   └── AppEnv.php                  # Application environment configuration
│   ├── config/
│   │   ├── routes.php                  # URL-to-route mapping
│   │   └── allowed-origins.php         # CORS configuration
│   └── postgresql/
│       ├── tables/                     # Table schemas (.sql)
│       └── functions/                  # SQL functions (.sql)
├── migrations/                         # Database migrations (generated)
├── vendor/
│   └── progalaxyelabs/stonescriptphp/  # Framework + CLI tools
├── tests/                              # PHPUnit tests
├── public/
│   └── index.php                       # Application entry point
├── docker-compose.yml                  # Docker development environment
├── Dockerfile.dev                      # Development container
├── Dockerfile.prod                     # Production container
├── composer.json                       # Project dependencies
├── stone                               # CLI entry point
├── .env                                # Environment configuration
└── README.md
```

### Data Flow Example: GET /api/users

```
1. HTTP Request: GET /api/users

2. Router (routes.php)
   └─> Maps to GetUsersRoute::class

3. Route Handler (GetUsersRoute.php)
   ├─> Validates request (validation_rules())
   ├─> Authenticates (if required)
   └─> process() method executes

4. Service Layer (Optional)
   └─> Business logic and coordination

5. Database Model (FnGetUsers.php - Generated)
   └─> Calls SQL function via PDO

6. PostgreSQL Function (get_users.pgsql)
   ├─> Executes complex queries
   ├─> Applies business rules
   └─> Returns typed result set

7. Response Formatting
   └─> returns ApiResponse with JSON

8. HTTP Response: 200 OK
   {
     "success": true,
     "data": { "users": [...] },
     "message": "Success"
   }
```

## How to Use

### Initial Setup

#### 1. Create a New Project

```bash
# Option A: From Composer (Creates from template)
composer create-project progalaxyelabs/stonescriptphp-server my-api
cd my-api

# Option B: Clone repository (for development/reference)
git clone https://github.com/progalaxyelabs/StoneScriptPHP-Server.git my-api
cd my-api
composer install
```

#### 2. Run Setup Wizard

```bash
php stone setup
```

This interactive wizard will:
- Configure database connection
- Generate JWT keypair for authentication
- Create `.env` file with all required variables
- Initialize directory structure

Alternatively, set up manually:

```bash
# Generate JWT keys only
php stone generate jwt

# Create .env file with database config
cp .env.example .env
# Edit .env with your database credentials
```

#### 3. Start Development Server

```bash
# Local development (port 9100)
php stone serve

# Docker development (port 8000)
docker compose up -d
```

### Development Workflow

#### Step 1: Define Database Schema

Create table definitions in `src/postgresql/tables/`:

```sql
-- src/postgresql/tables/users_table.pssql
CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(100) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role_id INTEGER REFERENCES roles(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_role ON users(role_id);
```

#### Step 2: Create SQL Functions

Define business logic as PostgreSQL functions in `src/postgresql/functions/`:

```sql
-- src/postgresql/functions/get_users_by_role.pgsql
CREATE OR REPLACE FUNCTION get_users_by_role(p_role_id INTEGER)
RETURNS TABLE (
    id INTEGER,
    username VARCHAR,
    email VARCHAR,
    role_name VARCHAR
) AS $$
BEGIN
    RETURN QUERY
    SELECT u.id, u.username, u.email, r.name
    FROM users u
    LEFT JOIN roles r ON u.role_id = r.id
    WHERE u.role_id = p_role_id
    ORDER BY u.username;
END;
$$ LANGUAGE plpgsql;
```

#### Step 3: Generate PHP Model

```bash
php stone generate model get_users_by_role.pgsql
```

This creates `src/App/Models/FnGetUsersByRole.php`:

```php
class FnGetUsersByRole
{
    public static function run(int $p_role_id): array
    {
        // Auto-generated code to call PostgreSQL function
    }
}
```

#### Step 4: Generate Route Handler

```bash
php stone generate route get-users-by-role
```

This creates `src/App/Routes/GetUsersByRoleRoute.php`

#### Step 5: Implement Route Logic

Edit the generated route handler:

```php
namespace App\Routes;

use App\Database\Functions\FnGetUsersByRole;
use StoneScriptPHP\ApiResponse;
use StoneScriptPHP\IRouteHandler;

class GetUsersByRoleRoute implements IRouteHandler
{
    public function validation_rules(): array
    {
        return [
            'role_id' => 'required|integer|min:1'
        ];
    }

    public function process(): ApiResponse
    {
        $role_id = request('role_id');

        try {
            $users = FnGetUsersByRole::run($role_id);
            return res_ok(['users' => $users]);
        } catch (\Exception $e) {
            return res_error('Failed to fetch users', 500);
        }
    }
}
```

#### Step 6: Register Route

Map URL to route in `src/config/routes.php`:

```php
return [
    'GET' => [
        '/' => HomeRoute::class,
        '/api/users/{role_id}' => GetUsersByRoleRoute::class,
    ],
    'POST' => [
        '/auth/google' => GoogleOauthRoute::class
    ]
];
```

#### Step 7: Run Migrations

```bash
# Verify database schema matches code
php stone migrate verify
```

### Adding Authentication

#### JWT Authentication (Default)

Keys are generated during setup:

```bash
php stone generate jwt
```

Sets in `.env`:
```
JWT_PRIVATE_KEY_PATH=./keys/jwt-private.pem
JWT_PUBLIC_KEY_PATH=./keys/jwt-public.pem
JWT_EXPIRY=3600
```

Protected routes use the `@auth` middleware:

```php
class ProtectedRoute implements IRouteHandler
{
    public function validation_rules(): array
    {
        return ['@auth']; // Requires valid JWT token
    }

    public function process(): ApiResponse
    {
        $user = auth()->user();
        return res_ok(['user' => $user]);
    }
}
```

#### OAuth Integration

Generate OAuth handlers:

```bash
php stone generate auth:google
php stone generate auth:linkedin
php stone generate auth:apple
```

### Environment Configuration

Type-safe environment configuration via `src/App/AppEnv.php`:

```php
$env = Env::get_instance();

// Framework variables (inherited)
$env->DEBUG_MODE;           // bool
$env->DATABASE_HOST;        // string
$env->DATABASE_PORT;        // int

// Application variables
$env->APP_NAME;             // string
$env->APP_ENV;              // string
$env->APP_PORT;             // int
$env->JWT_EXPIRY;           // int
$env->ALLOWED_ORIGINS;      // string (comma-separated)
```

Add custom variables to `Env.php`:

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
                'required' => false,
                'default' => null,
                'description' => 'Stripe API key'
            ],
        ];
        return array_merge($parentSchema, $appSchema);
    }
}
```

### Docker Development

```bash
# Start all services (app + PostgreSQL)
docker compose up -d

# View logs
docker compose logs -f app

# Run CLI commands inside container
docker compose exec app php stone generate route my-route

# Stop services
docker compose down
```

### Running Tests

```bash
# Run PHPUnit tests
php stone test
composer test

# Inside Docker
docker compose exec app php stone test
```

### CLI Commands Reference

```bash
# Project setup
php stone setup                         # Interactive setup wizard
php stone env                           # Generate .env file
php stone generate jwt                  # Generate JWT keypair

# Development server
php stone serve                         # Start dev server (port 9100)
php stone stop                          # Stop dev server

# Code generation
php stone generate route <name>         # Generate route handler
php stone generate model <file.pgsql>   # Generate model from SQL function
php stone generate client               # Generate TypeScript client
php stone generate auth:google          # Generate Google OAuth

# Database
php stone migrate verify                # Check database drift

# Testing
php stone test                          # Run PHPUnit tests

# Composer shortcuts
composer serve                          # Same as: php stone serve
composer test                           # Same as: php stone test
```

## API Structure

### Request/Response Format

All API endpoints follow a standard JSON request/response format.

#### Response Format

```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com"
  },
  "message": "User retrieved successfully"
}
```

#### Error Response

```json
{
  "success": false,
  "data": null,
  "message": "Validation failed",
  "errors": {
    "email": ["Email is required"],
    "password": ["Password must be at least 8 characters"]
  }
}
```

### Helper Functions

The framework provides helper functions for common tasks:

#### Response Helpers

```php
res_ok($data, $message = 'Success')                    // 200 OK
res_created($data, $message = 'Created')               // 201 Created
res_error($message, $status_code = 500)                // Error response
res_unauthorized($message = 'Unauthorized')            // 401
res_forbidden($message = 'Forbidden')                  // 403
res_not_found($message = 'Not found')                  // 404
res_validation_error($errors)                          // 422
```

#### Request Helpers

```php
request($key, $default = null)                         // Get request parameter
request_all()                                          // Get all request parameters
request_only($keys)                                    // Get specific parameters
request_except($keys)                                  // Get all except specific
has_param($key)                                        // Check if parameter exists
```

#### Authentication Helpers

```php
auth()                                                 // Get auth guard
auth()->user()                                         // Get authenticated user
auth()->token()                                        // Get JWT token
check_permission($permission)                          // Check user permission
```

### Common Routes

#### Home Route
```
GET /
Response: {"success": true, "message": "visit home page"}
```

#### Google OAuth
```
POST /auth/google
Body: {"token": "google_id_token"}
Response:
{
  "success": true,
  "data": {
    "user": {...},
    "jwt_token": "..."
  }
}
```

### CORS Configuration

Configure allowed origins in `src/config/allowed-origins.php`:

```php
return [
    'http://localhost:3000',
    'http://localhost:4200',
    'https://example.com',
];
```

Or use `ALLOWED_ORIGINS` environment variable:
```
ALLOWED_ORIGINS=http://localhost:3000,http://localhost:4200,https://example.com
```

## Technology Stack

| Component | Technology | Version |
|-----------|-----------|---------|
| Language | PHP | >= 8.2 |
| Database | PostgreSQL | >= 13 |
| Framework | StoneScriptPHP | ^2.3.6 |
| Authentication | JWT (RSA) | OpenSSL |
| OAuth | Google, LinkedIn, Apple | - |
| Caching | Redis | Optional |
| Testing | PHPUnit | ^11.0 |
| Container | Docker | Latest |
| Package Manager | Composer | Latest |

## Key Features

- ✅ **PostgreSQL-First**: Business logic in SQL functions
- ✅ **Type-Safe**: Generated PHP models from SQL
- ✅ **JWT Authentication**: RSA keypair authentication included
- ✅ **OAuth Support**: Google, LinkedIn, Apple ready
- ✅ **Role-Based Access Control**: RBAC built-in
- ✅ **CLI Tools**: Auto-generate routes and models
- ✅ **Docker Ready**: Development and production configs
- ✅ **Environment Configuration**: Type-safe .env handling
- ✅ **Request Validation**: Built-in validation framework
- ✅ **CORS Support**: Configurable cross-origin requests
- ✅ **Hot Reload**: Docker volume mounts for development
- ✅ **Testing**: PHPUnit configured and ready
- ✅ **Error Handling**: Comprehensive logging and exceptions

## Best Practices

### 1. Keep Business Logic in Database

```php
// ✅ GOOD: Complex logic in SQL function
class GetUserWithOrdersRoute implements IRouteHandler {
    public function process(): ApiResponse {
        $user = FnGetUserWithOrders::run($user_id);
        return res_ok(['user' => $user]);
    }
}

// ❌ BAD: Complex logic in PHP route
class GetUserWithOrdersRoute implements IRouteHandler {
    public function process(): ApiResponse {
        $user = DB::fetch('SELECT * FROM users WHERE id = ?', [$user_id]);
        $orders = DB::fetch('SELECT * FROM orders WHERE user_id = ?', [$user_id]);
        $total = 0;
        foreach ($orders as $order) { /* calculate total */ }
        return res_ok(['user' => $user, 'total' => $total]);
    }
}
```

### 2. Use Validation Rules

```php
class CreateUserRoute implements IRouteHandler {
    public function validation_rules(): array {
        return [
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8|regex:/[A-Z]/',
            'name' => 'required|string|max:100'
        ];
    }

    public function process(): ApiResponse {
        // Request is already validated at this point
        $user = FnCreateUser::run(...);
        return res_created($user);
    }
}
```

### 3. Separate Routes from Business Logic

```php
// ✅ GOOD: Thin route with service layer
class ProcessPaymentRoute implements IRouteHandler {
    public function process(): ApiResponse {
        $result = (new PaymentService())->process(request('order_id'));
        return $result['success'] ? res_ok($result) : res_error($result['error']);
    }
}

// Services in src/App/Services/PaymentService.php
class PaymentService {
    public function process($order_id) {
        // Business logic here
    }
}
```

### 4. Use Environment Variables

```php
// ✅ GOOD: Type-safe environment access
$env = Env::get_instance();
$api_key = $env->STRIPE_API_KEY;
$debug = $env->DEBUG_MODE;

// ❌ BAD: Direct $_ENV access
$api_key = $_ENV['STRIPE_API_KEY'] ?? null;
```

### 5. Handle Errors Gracefully

```php
class GetUserRoute implements IRouteHandler {
    public function process(): ApiResponse {
        try {
            $user = FnGetUser::run(request('id'));
            return $user ? res_ok($user) : res_not_found('User not found');
        } catch (\Exception $e) {
            return res_error('Failed to retrieve user', 500);
        }
    }
}
```

## Deployment

### Local Development

```bash
php stone serve
# API at http://localhost:9100
```

### Docker Development

```bash
docker compose up -d
# API at http://localhost:8000
```

### Production (Docker)

```bash
# Build image
docker build -t my-api:1.0.0 .

# Use production environment
docker run -d \
  -e APP_ENV=production \
  -e DB_HOST=prod-db.example.com \
  -e DB_PASSWORD=secure-password \
  -p 8000:8000 \
  my-api:1.0.0
```

### Environment Setup for Production

```env
APP_ENV=production
APP_DEBUG=false
DEBUG_MODE=false
DATABASE_HOST=prod-database.example.com
DATABASE_PORT=5432
DATABASE_USER=prod_user
DATABASE_PASSWORD=secure_password
JWT_EXPIRY=7200
ALLOWED_ORIGINS=https://app.example.com
```

## Framework Updates

```bash
# Update to latest framework version
composer update progalaxyelabs/stonescriptphp

# This updates only framework files in vendor/
# Your application code in src/ is preserved
```

## Resources

- **Website**: [stonescriptphp.org](https://stonescriptphp.org)
- **Documentation**: [stonescriptphp.org/docs](https://stonescriptphp.org/docs)
- **GitHub**: [StoneScriptPHP-Server](https://github.com/progalaxyelabs/StoneScriptPHP-Server)
- **Framework**: [StoneScriptPHP](https://github.com/progalaxyelabs/StoneScriptPHP)
- **Issues**: [Report Issues](https://github.com/progalaxyelabs/StoneScriptPHP-Server/issues)

## Summary

StoneScriptPHP Server provides a complete, production-ready foundation for building PostgreSQL-backed REST APIs. Its PostgreSQL-first architecture keeps business logic close to the data, while the thin route layer handles HTTP concerns cleanly. With built-in CLI tools, JWT authentication, Docker support, and comprehensive documentation, you can start building robust APIs immediately.
