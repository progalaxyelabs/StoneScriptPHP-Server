# StoneScriptPHP Server

**A minimal API server skeleton for PostgreSQL-backed REST APIs on [StoneScriptPHP](https://github.com/progalaxyelabs/StoneScriptPHP) 14.**

A clean, secure-by-default starting point: add only what you need through CLI commands.

## What you get

- Gateway-only database access (`Database::fn()`); the API never connects to PostgreSQL directly
- Flat `routes.php`, with HEAD and 405 handled by the framework
- Typed-auth wiring in `src/config/application.php` (read by `public/index.php`) and the framework's auth routes (including
  `POST /api/auth/exchange`), driven by `src/config/auth.php` and `.env`
- A `.env.example` in which every key is read by the framework, and in which required values are empty
  on purpose so a forgotten value stops the app at boot
- Strict framework 14 settings for new projects (`PERSISTENCE_CONTRACT=enforced`, `DB_HYDRATION_MODE=strict`)
- Docker (nginx + PHP-FPM) for development and production, and the `stone` CLI

Not included: user tables, roles and permissions, seeders. Generate or write what you need.

---

## Quick start

### 1. Create the project

```bash
composer create-project progalaxyelabs/stonescriptphp-server my-api
cd my-api
```

This creates `.env` (a copy of `.env.example`) and the JWT keypair in `keys/`. Neither is committed.

### 2. Fill in `.env`

The values left empty are required; the app refuses to boot without them (`php stone env:check`, run as the PHP-FPM user, tells you before you deploy):

- `DB_GATEWAY_URL`, `DB_GATEWAY_PLATFORM` (and `DB_GATEWAY_SCHEMA_NAME`, `DB_GATEWAY_ADMIN_TOKEN`): the
  StoneScriptDB Gateway the API talks to
- `JWT_ISSUER`: this API's own public URL, the issuer of every token it signs (must differ from `AUTH_ISSUER`)
- `AUTH_MODE` (`external` is the shipped default). For `external`/`hybrid` also `AUTH_SERVICE_URL`,
  `AUTH_ISSUER` and `PLATFORM_CODE`

Then put the origin of your sign-in page in `src/config/allowed-origins.php` and check it:

```bash
php stone auth:check-origins
```

### 3. Register the schema with the gateway

Put your SQL in `src/postgresql/main/postgresql/tables/` and `.../functions/` (the layout the gateway commands read).

The `gateway:*` commands read their settings from the process environment, not from `.env`, and they
use `PLATFORM_ID` and `MAIN_SCHEMA_NAME` (set them in `.env` to the same values as `DB_GATEWAY_PLATFORM`
and `DB_GATEWAY_SCHEMA_NAME`). Load `.env` in a subshell, so the secrets do not stay in your shell:

```bash
( set -a; . ./.env; set +a; php stone gateway:register-main )
( set -a; . ./.env; set +a; php stone gateway:migrate-main )   # or: composer migrate
```

### 4. Run it

```bash
composer dev                                  # Docker (nginx + PHP-FPM), http://localhost:8000
php -S 127.0.0.1:9100 -t public public/index.php   # or the PHP built-in server
curl -i http://localhost:9100/health
```

Run the tests with `composer test` (or `php stone test`).

---

## Authentication

Set by `AUTH_MODE` in `.env` (`src/config/auth.php` leaves `mode`, `server.url` and `platform.code` as
`null` so `.env` is the single source of truth).

| Mode | Who issues tokens | Accepted on protected routes |
|---|---|---|
| `external` / `hybrid` | The auth service issues identity tokens (purpose `authentication`). `POST /api/auth/exchange` swaps one, plus a `tenant_id`, for an API token signed with this app's own key (`JWT_PRIVATE_KEY_PATH`, purpose `authorization`). | Both issuers are trusted: the auth service (JWKS) and this API (`JWT_ISSUER`). Routes default to `access: authorization`, so after the exchange every call carries the API token. |
| `builtin` | This API (login routes you generate, or the framework's builtin Google popup). | Single-token middleware: issuer, signature, expiry and type are enforced; the authentication/authorization purpose gate is not (one principal, one key). |

`src/config/application.php` passes `typed_auth => true`, so the framework builds the middleware for the mode
(`AuthMiddlewareRegistrar::forMode()`); `tests/Unit/AuthSmokeTest.php` boots the app in each mode and sends real, forged and malformed tokens through it. It ships with your project: keep it passing when you change auth wiring.

### Token exchange (`external` / `hybrid`)

The framework registers `POST /api/auth/exchange`. Do not add a route of your own at that path: it
would shadow the framework's. Supply the lookups in `src/config/auth.php`:

```php
'tenants_resolver' => fn(array $authClaims): array => [/* ['id' => ..., ...] tenants of this identity */],
'roles_resolver'   => fn(array $claimsWithTenant): array => [/* role ids in that tenant */],
```

**Set both.** Without `tenants_resolver` the route trusts the `tenant_id` in the request body, so a
`roles_resolver` that does not itself check that the identity belongs to that tenant lets any signed-in
user mint an API token for any tenant. Without `roles_resolver` the route answers 501.

### Generators

`php stone generate auth:email-password | auth:google | auth:linkedin | auth:apple | auth:api-key`
scaffold provider-specific routes and SQL. Review what they create before you use it.

---

## Environment

`.env.example` is the reference; a test in this repo fails if it lists a key the framework never reads.

| Setting | What to do |
|---|---|
| `JWT_ISSUER`, `DB_GATEWAY_*`, `AUTH_*`, `PLATFORM_CODE` | Required, empty by default (see Quick start). |
| `src/config/allowed-origins.php` | List the origin of every page that calls the API, including your sign-in page (CORS, and the Google OAuth popup only posts tokens to listed origins). An `ALLOWED_ORIGINS` variable would override the file, so do not set both. Check with `php stone auth:check-origins`. |
| `TRUSTED_PROXIES` | Set to your reverse proxy (explicit address or CIDR) if anything sits between the internet and PHP; otherwise all visitors share one rate-limit bucket and the site answers 429. Leave unset when nginx faces the internet directly. |
| `PERSISTENCE_CONTRACT=enforced`, `DB_HYDRATION_MODE=strict` | A failed write is never a 2xx; SQL NULL stays NULL. |
| `REFRESH_TOKEN_STORE` | `none` by default. See "Persisting refresh tokens". |
| `src/config/subscription.php` | `enabled => false`. Turn it on only if you run subscriptions: enabled, every tenant without a subscription row is read-only (HTTP 423 on writes) once the subscription schema is applied. |

### Persisting refresh tokens

Composer stages the framework's opt-in schemas in `src/postgresql/vendor/postgresql/` on install. Apply
only the refresh-token one (the others, such as Subscriptions, change how your tenants behave):

```bash
php stone gateway:migrate-vendor-main --list                    # what is staged
php stone gateway:migrate-vendor-main --only=RefreshTokens
php stone auth:check-refresh-store
# set REFRESH_TOKEN_STORE=postgres in .env
php stone auth:purge-refresh-tokens      # schedule hourly or daily
```

### Multi-tenant platforms

Tenant ids become part of the database name `{platform}_{schema}_{id}`. They must be lower-case letters
and digits in groups separated by single hyphens (canonical UUIDs pass), and the name must fit 63 bytes.
Check your id generator before you provision tenants:

```bash
php stone tenants:check-ids --platform=<code> --schema=<tenant schema> --sample-generator
```

### Errors and logs

A response never carries raw exception or database text. To show a message to the client, throw an
exception that implements `StoneScriptPHP\Exceptions\PublicMessage` (or return a business error as in
the framework's `docs/PERSISTENCE-CONTRACT.md`); any other exception answers a generic sentence plus a
`correlation_id` that is also in the log line. Log with the framework logger (`log_error()` etc.), which
masks emails and database values; do not `error_log()` exception text.

---

## Docker

`composer dev` builds and starts `Dockerfile.dev`; see [DOCKER.md](DOCKER.md) for production. The
signing key is never baked into an image (`keys/` and `*.pem` are in `.dockerignore`); mount it as a
secret (DOCKER.md). The container's nginx is an inner nginx: it passes forwarding headers through, so
set `TRUSTED_PROXIES` when it sits behind a proxy.

## Project structure

```
my-api/
├── scripts/                 # post-create-project.php (.env + keys, runs once)
├── public/index.php         # entry point
├── src/
│   ├── App/
│   │   ├── Routes/          # route handlers
│   │   └── AppEnv.php       # application environment
│   ├── config/
│   │   ├── routes.php           # route definitions (flat format)
│   │   ├── auth.php             # auth mode and features (values default to .env)
│   │   ├── allowed-origins.php  # CORS + OAuth popup origins
│   │   └── subscription.php     # off by default
│   └── postgresql/          # main/postgresql/{tables,functions} (SQL)
├── tests/
├── Dockerfile.dev / Dockerfile.prod
└── .env
```

## Useful commands

```bash
php stone generate route POST /orders        # route handler
php stone generate model get_user.pgsql      # PHP wrapper for a SQL function (always generate, never hand-write)
php stone generate client --tenancy=T1       # TypeScript client (pass T1, T2 or T3 explicitly)
php stone migrate status                     # gateway connection status
php stone validate sqlintegrity              # undefined table/function references
php stone gateway:register-main              # register schema with the gateway
php stone gateway:migrate-main               # migrate the main database
```

## Contributing

This is the application skeleton. For framework contributions, see [StoneScriptPHP](https://github.com/progalaxyelabs/StoneScriptPHP).

## License

MIT License - see LICENSE file for details.
