# StoneScriptPHP Server

Application skeleton for **[StoneScriptPHP](https://github.com/progalaxyelabs/StoneScriptPHP)** - A modern PHP framework for building APIs with PostgreSQL.

## Installation

Create a new StoneScriptPHP project:

```bash
composer create-project progalaxyelabs/stonescriptphp-server my-api
cd my-api
```

The setup wizard will:
1. Configure database connection
2. Generate JWT keypair for authentication
3. Create `.env` file

## Start Development Server

```bash
php stone serve
# Your API is running at http://localhost:9100
```

## What's Included

This skeleton includes:

- **CLI Tools** - `stone` command for code generation and project management
- **Project Structure** - Organized folder structure for routes, models, and database
- **Environment Configuration** - Type-safe environment setup with `.env`
- **Example Route** - Sample `HomeRoute` to get started

## Framework Core

The core framework is installed as a dependency:
- Package: `progalaxyelabs/stonescriptphp`
- Location: `vendor/progalaxyelabs/stonescriptphp/`
- Upgradeable via: `composer update progalaxyelabs/stonescriptphp`

## Local Development Testing

To test changes without publishing to Packagist, use composer's path repository:

```bash
# In your test project's composer.json
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

Then run `composer install` to use your local framework code.

## Accessing Environment Variables

This server template extends the framework's Env class to add application-specific variables:

```php
use Framework\Env;

$env = Env::get_instance();  // Returns App\Env instance

// Framework variables (inherited from Framework\Env)
$env->DEBUG_MODE;            // bool
$env->TIMEZONE;              // string
$env->DATABASE_HOST;         // string
$env->DATABASE_PORT;         // int
$env->DATABASE_USER;         // string
$env->DATABASE_PASSWORD;     // string
$env->DATABASE_DBNAME;       // string

// Application-specific variables (from App\Env)
$env->APP_NAME;              // string
$env->APP_ENV;               // string
$env->APP_PORT;              // int
$env->JWT_PRIVATE_KEY_PATH;  // string
$env->JWT_PUBLIC_KEY_PATH;   // string
$env->JWT_EXPIRY;            // int
$env->ALLOWED_ORIGINS;       // string
$env->GOOGLE_CLIENT_ID;      // string (optional)
```

### Adding Custom Variables

Edit `src/App/Env.php` to add your own variables:

```php
class Env extends FrameworkEnv
{
    public $YOUR_CUSTOM_VAR;

    public function getSchema(): array
    {
        $parentSchema = parent::getSchema();

        $appSchema = [
            'YOUR_CUSTOM_VAR' => [
                'type' => 'string',
                'required' => false,
                'default' => null,
                'description' => 'Your custom variable'
            ],
        ];

        return array_merge($parentSchema, $appSchema);
    }
}
```

Then add `YOUR_CUSTOM_VAR=value` to your `.env` file.

## Versioning Strategy

StoneScriptPHP follows [Semantic Versioning](https://semver.org/):

- **Patch versions (2.0.x)**: Bug fixes, security patches, minor improvements. Safe to update anytime.
- **Minor versions (2.x.0)**: New features, backward-compatible changes. Update when you need new functionality.
- **Major versions (x.0.0)**: Breaking changes, major architectural updates. Review migration guide before updating.

The server and framework are versioned together during major releases but may have different patch versions as bugs are fixed independently. This server's `composer.json` uses `^2.0` to automatically receive framework patch updates while staying on the same major version.

**Current stable:** v2.0.x - Production-ready with ongoing bug fixes

## Documentation

- **[Getting Started Guide](https://stonescriptphp.org/docs/getting-started)** - Complete tutorial
- **[CLI Usage](https://stonescriptphp.org/docs/CLI-USAGE)** - Command reference
- **[Full Documentation](https://stonescriptphp.org/docs)** - Complete framework docs

## Requirements

- PHP >= 8.2
- PostgreSQL >= 13
- Composer
- PHP Extensions: `pdo`, `pdo_pgsql`, `json`, `openssl`

## Support

- Website: https://stonescriptphp.org
- Documentation: https://stonescriptphp.org/docs
- Framework Issues: https://github.com/progalaxyelabs/StoneScriptPHP/issues
- Server Issues: https://github.com/progalaxyelabs/StoneScriptPHP-Server/issues

## License

MIT
