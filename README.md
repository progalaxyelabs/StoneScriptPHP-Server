# StoneScriptPHP Server

Application skeleton for **[StoneScriptPHP](https://github.com/progalaxyelabs/StoneScriptPHP)** - A modern PHP framework for building APIs with PostgreSQL.

## Installation

Create a new StoneScriptPHP project:

```bash
composer create-project progalaxyelabs/stonescriptphp-server my-api
cd my-api
```

The setup wizard will:
1. Ask you to choose a starter template (Basic API, Microservice, SaaS Boilerplate)
2. Scaffold your project structure from the selected template
3. Configure database connection
4. Generate JWT keys
5. Run initial migrations

## Start Development Server

```bash
php stone serve
# Your API is running at http://localhost:9100
```

## What's Included

This skeleton includes:

- **CLI Tools** - `stone` command for code generation and project management
- **Starter Templates** - Pre-built templates for different use cases:
  - `basic-api` - Simple REST API with PostgreSQL
  - `microservice` - Lightweight microservice template
  - `saas-boilerplate` - Multi-tenant SaaS with subscriptions
- **Project Structure** - Organized folder structure for routes, models, and database
- **Environment Configuration** - Type-safe environment setup with `.env`

## Framework Core

The core framework is installed as a dependency:
- Package: `progalaxyelabs/stonescriptphp`
- Location: `vendor/progalaxyelabs/stonescriptphp/`
- Upgradeable via: `composer update progalaxyelabs/stonescriptphp`

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
