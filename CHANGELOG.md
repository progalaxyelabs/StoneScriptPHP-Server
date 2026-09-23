# Changelog

All notable changes to this project template will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [4.0.0]

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
