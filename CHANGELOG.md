# Changelog

All notable changes to this project template will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [6.0.0] - 2026-10-26

Major release. A new project starts on the latest framework line, `progalaxyelabs/stonescriptphp` **^14.0.1**, which
needs **PHP >= 8.5.12**. It changes what `composer create-project` produces; it does not touch an existing project
(those keep the framework version in their own `composer.json`).

**Published history:** the last release on Packagist is **3.22.0** (framework `^5.1`, PHP `^8.2`). Versions 4.0.0 (framework
`^9.0`) and 5.0.0 (framework `^12.1`) were never published, so for anyone who installed from Packagist, 6.0.0 follows 3.22.0 directly.
5.0.0's changes are all part of 6.0.0 (see the 5.0.0 entry below, including the step-by-step 3.22 to 12 upgrade list).

### Changed (BREAKING for new projects)

- **Requires `progalaxyelabs/stonescriptphp: ^14.0.1` and `php: >=8.5.12`.** The framework refuses to install or boot on an older
  PHP (PHP2Shell, CVE-2026-109618 / CVE-2026-109619; 13.2.1) and 14.0.0 raises the single floor to 8.5.12. Distro builds that
  backport the fix but keep an old version number (Debian/Ubuntu `8.3.x`) are refused by design: use upstream PHP.
  It pulls `progalaxyelabs/stonescriptdb-gateway-client ^4.0.1` (no PHP 8.5 deprecations).
- `Dockerfile.dev`, `Dockerfile.prod` and `Dockerfile.cli` are based on PHP 8.5.12 (`php:8.5.12-fpm-bookworm`, `php:8.5.12-cli-alpine`).
- `tests/Unit/AuthSmokeTest.php`: the probe result is read from the last stdout line. Since framework 13.1 a rejected token is logged at
  warning level and the CLI logger writes to stdout, which broke the "whole stdout is one JSON line" assumption.
- Verified on PHP 8.5.12 with `error_reporting=-1`: `create-project` from this tree, 32 tests green, no `E_DEPRECATED`.

### What the framework 12 to 14 jump brings you (all on by default, no opt-in)

- 13.2.1 PHP2Shell boot guard; 14.0.0 PHP 8.5 floor.
- 13.0.0 PostgreSQL error text and query parameters no longer reach logs, exception messages or responses; fixes `[public:code:status]`
  marker injection. Database `RAISE` messages must be codes (`not_found`, `user_cap`), never interpolated text.
- 13.1.0 every handler failure is recorded in `request_logs`; failures no longer ship as HTTP 200. 13.2.0 `request_logs` is bounded
  (retention, no probe noise): `composer update` stages `rl_purge_request_logs.pgsql` into `src/postgresql/vendor/`, activate it with
  `php stone gateway:migrate-vendor-main`, and install the retention timer from `vendor/progalaxyelabs/stonescriptphp/contrib/systemd/`.
- 12.x Google OAuth popup posts tokens only to allowed origins, tenant-id rule, fail-closed typed-auth defaults (see 5.0.0).

### Upgrading an existing project

- **From 3.22.0 (framework 5.1):** follow the 3.22 to 12 list under 5.0.0 first (routes flat format, typed auth, `TRUSTED_PROXIES`, `PublicMessage`,
  delete your own `TokenExchangeRoute`), then:
- **From any 12.x:** raise the PHP image/runtime to 8.5.12 or newer first (upstream build, not a distro backport), then set
  `"php": ">=8.5.12"` and `"progalaxyelabs/stonescriptphp": "^14.0.1"` in `composer.json`, `composer update`, run `php stone env:check` as the
  PHP-FPM user, `php stone gateway:migrate-vendor-main`, and your tests. Read the framework `CHANGELOG.md` sections 13.0.0 (error-text and
  `RAISE` rules), 13.1.0 and 13.2.0 (retention timer) before deploying; there is no other API change in 14.0.
- Take this release's `Dockerfile.*` base images (PHP 8.5.12). Do not backport fixes to older framework lines: the fix is the upgrade.

## [5.0.0] - 2026-10-11

Major release. The framework baseline moves to `^12.1`, and a new project starts on the strict
settings with tested auth wiring. It changes what `composer create-project` produces; it does not
touch an existing project (those keep the framework version in their own `composer.json`).

**Versions 4.0.0 (framework `^9.0`) and the intermediate work were never published.** The last
published release is **3.22.0** (framework `^5.1`), and that is the installed base. A project created
from 3.22.0 does not upgrade by taking this skeleton: it upgrades its framework constraint and applies
the changes below by hand.

### Upgrading a 3.22.0 project (framework 5.1 to 12)

Go in this order; each step has a framework changelog section with the details.

1. **6.0** routing: `routes.php` must be the flat format (`'GET' => ['/path' => ['handler' => ..., 'service' => ..., 'group' => ..., 'is_public' => ...]]`). The old `public`/`protected` sections throw at boot. Route handlers implement `IRouteHandler` directly.
2. **7.0** typed auth: any non-public route (including the framework's own `me` and `change-password`) needs the typed-auth middleware or the app refuses to boot. Pass `'typed_auth' => true` to `Application::run()` as `public/index.php` does (framework 12.1). Routes carry `access`/`token_type`.
3. **10.0** expired subscriptions are read-only (HTTP 423), not locked out (402). If you use `src/config/subscription.php`, set `expired_mode` deliberately. This skeleton ships the module off (`'enabled' => false`).
4. **11.0** `client_ip()` no longer trusts `X-Forwarded-For`. Set `TRUSTED_PROXIES` to your reverse proxy before deploying, or every visitor shares one rate-limit bucket and the site answers 429.
5. **12.0** (always on): the Google OAuth popup posts tokens only to origins in `src/config/allowed-origins.php` (run `php stone auth:check-origins`); the tenant-id rule (`php stone tenants:check-ids`); HEAD is answered and a wrong method on a known path is 405; raw exception and database text no longer reach responses or logs (implement `PublicMessage` on exceptions whose text clients need). Gateway 4.7.0 or newer is required for tenant provisioning.
6. Delete your own `TokenExchangeRoute`/signer if you copied them from 3.x (see below), `.env` keys the framework no longer reads, and move to the `.env.example` of this release.

### Changed (BREAKING for new projects)

- **Requires `progalaxyelabs/stonescriptphp: ^12.1`.** 12.0.0's `stone` CLI looked for config in the wrong directory (`auth:check-origins` reported an empty list; fixed in 12.0.1), and 12.1 provides the mode-aware typed-auth wiring, boot checks, `env:check` and `--only` that this skeleton would otherwise carry itself. `composer.lock` is no longer committed: a pinned lock would hand every new project one exact patch release. `create-project` resolves the newest 12.x.
- **Typed-auth wiring is the framework's** (`'typed_auth' => true` in `public/index.php`, `AuthMiddlewareRegistrar::forMode()`).
  - `external`/`hybrid`: the auth service AND this API are trusted issuers. `POST /api/auth/exchange` signs API tokens with the local key (`iss` = `JWT_ISSUER`, purpose `authorization`); trusting only the auth service would answer 401 to every protected call after a successful exchange.
  - `builtin`: the single-token middleware. Login mints purpose `authentication` while routes default to `access: authorization`, so the strict middleware answered 403 to every logged-in user.
  - The detailed round-trip matrix lives in the framework; `tests/Unit/AuthSmokeTest.php` keeps a scaffold-level smoke that a real token reaches a protected route in each mode.
- **Framework-owned `POST /api/auth/exchange`.** The skeleton's `TokenExchangeRoute`, `PlatformJwtSigner` and `auth.php`'s `platform_jwt` block are removed: they shadowed the framework route (same path), read no `tenant_id`, signed with a key no validator trusted and stamped `iss` from the request `Host` header. Supply `auth.tenants_resolver` and `auth.roles_resolver` (set BOTH: with only `roles_resolver` the route trusts the body `tenant_id`).
- **`src/config/auth.php` resolves from `.env`.** `mode`, `server.url` and `platform.code` are `null` (= `AUTH_MODE`, `AUTH_SERVICE_URL`, `PLATFORM_CODE`); they used to be hard-coded and silently overrode `.env`. `legacy_compat` is off and sits under `features` (the only place `Application::run()` forwards it from).
- **`src/config/subscription.php` has `'enabled' => false`.** A missing key counts as ON in the framework, which wired `SubscriptionMiddleware` into every project; once the vendor subscription schema was applied every tenant without a subscription row was read-only (423 on writes).
- **`.env.example`:** required values (`DB_GATEWAY_URL`, `DB_GATEWAY_PLATFORM`, `JWT_ISSUER`, `AUTH_SERVICE_URL`, `AUTH_ISSUER`, `PLATFORM_CODE`) are empty, so a forgotten value fails at boot instead of running against a placeholder (the framework refuses to serve with empty `DB_GATEWAY_*`; `php stone env:check` is the pre-flight). `PERSISTENCE_CONTRACT=enforced` and `DB_HYDRATION_MODE=strict` (the framework's own defaults stay lenient for upgrades). New: `TRUSTED_PROXIES`, `HEAD_EXECUTES_GET`, `REFRESH_TOKEN_STORE`/`REFRESH_TOKEN_ROTATE`. `ALLOWED_ORIGINS` is commented out: the env var overrides `src/config/allowed-origins.php`, the file the README tells you to edit.
- **`post-create-project-cmd` no longer runs interactive `php stone setup`**, which writes an obsolete variable set (`DATABASE_*`, `JWT_ISSUER=example.com`) the gateway-only framework does not read. `scripts/post-create-project.php` copies `.env.example` to `.env` and generates the JWT keypair with `stone setup --quiet`; it exits non-zero if the keypair cannot be created. An existing `.env` is never touched.

### Added

- Dev container: `docker/dev-fpm-user.sh` runs PHP-FPM workers as the owner of the bind-mounted project, so the `0600` private key is readable under `composer dev` without being made group- or world-readable. `Dockerfile.prod` is unchanged (`www-data`, key mounted as a secret).
- `.dockerignore` excludes `keys/`, `*.pem`, `*.key` (a prod image had baked the signing key) and `.env`, `.env.*` (except `.env.example`), `**/.env.bak.*`; DOCKER.md documents mounting the key as a secret and has the current required variables.
- `.gitattributes` `export-ignore` for `docs/` (the auth smoke tests and `phpunit.xml` ship with a new project; `phpunit.xml` fails on an empty suite). `archive.exclude` in `composer.json` does not apply to Packagist installs (GitHub zipballs); `.gitattributes` does.
- `src/config/allowed-origins.php`: a literal list (the runtime and CLI read it as data) documenting its CORS and OAuth-popup role. `routes.php` documents HEAD, `head: 'probe'`, `streaming: true` and 405.
- README rewritten for the gateway-only shape: auth modes, exchange resolvers, required environment, refresh-token persistence, tenant-id rule, public error messages. Commands that do not exist in framework 12 (`migrate up/down/verify`, `serve`, `stop`) and the `/auth/login` example are gone.
- `src/config/auth.php` warns that `tenants_resolver` and `roles_resolver` must both be set (a resolver pair with only the roles side lets any signed-in user request an API token for any tenant).
- `LICENSE` (MIT).
- Tests: `Application::run()` boots in each auth mode (builtin, external, hybrid) and a protected route refuses a missing token and accepts a real one; `.env.example` ships no active `ALLOWED_ORIGINS`/`AUTH_COOKIE_*`. HEAD and 405 against the shipped `routes.php`, `auth.php`/`allowed-origins.php`/`subscription.php` as shipped, every active `.env.example` key is read by the framework, an auth smoke per mode. The test bootstrap uses a throwaway environment and keypair (`Env::resetForTesting()`).

### Fixed

- `README`/`.env.example`: `gateway:register-main` reads `PLATFORM_ID` and `MAIN_SCHEMA_NAME` from the process environment (not `.env`); `.env.example` ships `MAIN_SCHEMA_NAME=main` and the README shows the working invocation. `DOCKER.md` compose example uses long-syntax secrets with a top-level `secrets:` block and an explicit `AUTH_MODE`.
- `public/index.php` is now one call, `Application::run(require CONFIG_PATH . 'application.php')`; the auth smoke test boots that same config. `composer migrate` runs `gateway:migrate-main` with `.env` loaded (it pointed at a removed command); `composer dev` no longer passes `--env-file .env` (Docker keeps the quotes literally; the app reads the mounted `.env`) or builds twice.
- The empty schema directories moved to `src/postgresql/main/postgresql/{tables,functions}`: `gateway:register-main` rejects the flat `src/postgresql/{tables,functions}` layout the skeleton used to ship.
- `tests/bootstrap.php` pins the shipped `.env.example` defaults so a developer's own `.env` is not what the tests read (keys absent from `.env.example`, and variables already set in the process environment, can still reach them).
- `public/index.php` (builtin) resolved the key path against the working directory (`public/` under the PHP built-in server); it is now resolved against the project root.
- `Dockerfile.dev` required `composer.lock` (`COPY composer.json composer.lock`), which the skeleton no longer ships.
- `stone` defined `CONFIG_PATH` as `src/App/Config/` while the runtime uses `src/config/`; `stone` and `project-info.yaml` now say `src/config/`.
- `composer archive` excluded `.env.example`; it is kept. `project-info.yaml` and `HLD.md` no longer claim framework 2.x.
- `.env.example` no longer ships `AUTH_COOKIE_SECURE=` active: an empty value is read as false and turns the cookie `Secure` flag off. `AUTH_COOKIE_DOMAIN`/`AUTH_COOKIE_SECURE` are commented.
- `.gitignore` ignored every path containing `secret` or `credentials`; it now ignores only top-level `credentials*`, `secrets/` and `.secrets/`.
- `scripts/post-create-project.php` checks that `.env` was created and creates it `0640`.
- `DOCKER.md` rewritten for the shipped files (no compose file, no direct-PostgreSQL service, no `serve`/`stop`/`migrate verify`), with key ownership for plain compose (uid 33 / group read) and Swarm. `project-info.yaml` rewritten to match. `Dockerfile.cli` required framework `^5.1`; it now requires `^12.1`. `stone` is the 12.1 CLI.

### Removed

- `test-docker.sh`, `test-docker-cleanup.sh`, `TEST-DOCKER.md`: a pre-gateway test harness that no longer matched the skeleton.
- `HLD.md`, `SPEC.md` and `docs/CLIENT-SDK-SPEC.md`: maintainer design notes and an API-contract/auth description that no longer matched the shipped auth. The current docs are at https://stonescriptphp.org/docs.
- Dead code: `App\Lib\MyZeptoMail` (referenced the non-existent `App\Env`), `TokenExchangeRoute`, `PlatformJwtSigner`, and four stale maintainer notes that shipped to every project (`IMPROVEMENT-SUGGESTIONS*.md`, `PRODUCTION_READINESS_TEST.md`, `IMPORTANT-ROUTING-FIX.md`).

## [4.0.0] (never published)

### Changed — BREAKING: framework baseline moved from ^6.0 to ^9.0

This skeleton now requires `progalaxyelabs/stonescriptphp: ^9.0` (previously
`^6.0`). The framework's routing/config contract evolved considerably across
that range; this release updates the skeleton to match it.

- **Typed-auth boot requirement (framework v7.0.0+).** The framework's route
  model gained an `access`/`token_type` classification alongside the legacy
  `is_public` flag; any route resolving to a non-public access level now
  requires the typed-auth `AccessTokenMiddleware`/`RefreshTokenMiddleware` to
  be wired, or the application refuses to boot with an actionable error. This
  is not a theoretical concern for this skeleton: with the default
  `auth.mode = 'external'` and default feature toggles, the framework's own
  built-in auth routes (`change-password`, `me`) are registered as
  non-public — meaning an unmodified pre-4.0.0 skeleton upgraded to a v7+
  framework would fail to boot at all. `public/index.php` now builds a
  `TrustedIssuerVerifier` from the configured auth mode/issuer and wires
  `AccessTokenMiddleware` accordingly (JWKS-based for `external`/`hybrid`
  mode, local-key-based for `builtin` mode). No local refresh-token routes
  are registered by this skeleton, so `RefreshTokenMiddleware` is not wired
  by default — see the comment block in `public/index.php` for what to add
  if you introduce one.
- **`.env.example` rewritten.** It previously described a pre-gateway,
  pre-v3 environment shape (generic `DATABASE_*` variables, an `AUTH_MODE`
  of `standalone`/`centralized` that the framework has never recognized) and
  would not produce a bootable `.env` if copied as-is. It now lists the real
  variables the framework's `Env` class resolves, including the
  gateway-mode-required `DB_GATEWAY_URL`/`DB_GATEWAY_PLATFORM`/
  `DB_GATEWAY_SCHEMA_NAME` trio and the auth-mode-specific
  `AUTH_SERVICE_URL`/`AUTH_ISSUER`/`JWT_ISSUER` variables, none of which have
  an unsafe silent default.
- Added explanatory comments to `src/config/routes.php`, `src/config/auth.php`,
  and `src/config/subscription.php` documenting the typed-auth requirement
  and noting that the subscription module is legacy (still fully supported,
  disabled by default) in favor of the framework's newer billing/collection
  contracts for new integrations.
- Regenerated `composer.lock` and the vendored `stone` CLI script against the
  resolved `9.19.0` release.

### Verification

- Full skeleton test suite (`composer test`): 6/6 tests passing against
  framework 9.19.0.
- Boot smoke test (`php -S ... public/index.php`) against a locally-filled
  `.env` (no live database gateway or auth service reachable): `GET /` → 200,
  `GET /health` → 200, `GET /does-not-exist` → 404. Confirmed by direct
  regression check that reverting the typed-auth middleware wiring in
  `public/index.php` reproduces a 500 boot-configuration error on the exact
  same routes — i.e. the fix addresses a real, reproducible incompatibility,
  not a hypothetical one.
