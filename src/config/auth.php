<?php

// Typed-auth note (framework v7.0.0+): with mode = 'external'/'hybrid', the
// framework's ExternalAuthRoutes registers some routes as non-public by default
// (e.g. `change_password`, `profile` below). Those require the typed-auth
// AccessTokenMiddleware to be wired in public/index.php or the app refuses to
// boot. This skeleton's src/config/application.php already does that from the values
// resolved here and in .env.
//
// Single source of truth: every value left as `null` below is resolved from
// the environment (.env) by the framework, so the file and .env cannot
// disagree. Set a value here only to pin it in code.
return [
    // Auth mode:
    //   'builtin'  — StoneScriptPHP issues/validates its own JWTs (RSA keys in ./keys/)
    //   'external' — delegate all auth to a separate auth service (an external auth service or custom)
    //   'hybrid'   — validate external JWTs + optionally issue own tokens
    // null = read AUTH_MODE from .env (the shipped .env.example sets 'external').
    'mode' => null,

    // URL prefix for all registered auth routes — the canonical prefix is /api/auth.
    // (legacy /auth prefix: see `legacy_compat` under 'features' below.)
    'prefix' => '/api/auth',

    // Platform identity — sent in every request to the auth service
    'platform' => [
        'code'   => null,       // null = PLATFORM_CODE from .env. Must match the code registered in the auth service
        'secret' => null,       // Falls back to EXTERNAL_AUTH_CLIENT_SECRET env var. Used as X-Platform-Secret header for privileged endpoints (register-tenant)
    ],

    'server' => [
        // JWKS fetch URL — use container-internal hostname in Docker (e.g. http://auth-service:3139)
        // null = AUTH_SERVICE_URL from .env.
        'url' => null,

        // JWT 'iss' claim value — must match exactly what the auth server stamps in tokens.
        // In Docker: AUTH_SERVICE_URL = container URL, AUTH_ISSUER = public URL the server stamps.
        // null = resolved from AUTH_ISSUER env var at runtime (set AUTH_ISSUER in .env).
        // NEVER set to '' — framework v5.2 throws RuntimeException at boot on empty string.
        'issuer' => null,

        // Override auth service endpoint paths if they differ from your auth service's defaults.
        // Only set entries that differ — unset entries use the defaults shown here.
        'paths' => [
            'jwks'              => '/api/auth/jwks',
            'login'             => '/api/auth/login',
            'register'          => '/api/auth/register',
            'register_tenant'   => '/api/auth/register-tenant',
            'refresh'           => '/api/auth/refresh',
            'logout'            => '/api/auth/logout',
            'select_tenant'     => '/api/auth/select-tenant',
            'memberships'       => '/api/auth/memberships',
            'check_slug'        => '/api/auth/check-tenant-slug',
            'onboarding_status' => '/api/auth/onboarding/status',
            'forgot_password'   => '/api/auth/forgot-password',
            'reset_password'    => '/api/auth/reset-password',
            'change_password'   => '/api/auth/change-password',
            'invite'            => '/api/auth/invite-member',
            'accept_invite'     => '/api/auth/accept-invite',
            'verify_email'      => '/api/auth/verify-email',
            'resend_code'       => '/api/auth/resend-code',
            'profile'           => '/api/auth/me',
            'oauth_initiate'    => '/api/auth/oauth/initiate',
            'oauth_callback'    => '/api/auth/oauth/callback',
        ],
    ],

    // Registration mode:
    //   'tenant'   — creates org + user + provisions tenant DB (standard for most platforms)
    //   'identity' — creates user only (no org, no DB provisioning)
    'registration_mode' => 'tenant',

    // Enable/disable individual auth routes
    'features' => [
        'register'          => true,
        'login'             => true,
        'logout'            => true,
        'refresh'           => true,
        'exchange'          => true,
        'select_tenant'     => true,
        'memberships'       => true,
        'check_slug'        => true,
        'onboarding_status' => true,
        'password_reset'    => true,
        'change_password'   => true,
        'invite'            => true,
        'accept_invite'     => true,
        'verify_email'      => true,
        'resend_code'       => true,
        'oauth'             => false,
        'profile'           => true,
        'health'            => false,

        // Also answer every route on the old /auth prefix, for clients written before the prefix
        // moved to /api/auth. A new project has no such clients, so it is off (while on, the
        // framework logs a deprecation warning on every request). It lives in this list because
        // Application::run() only forwards the keys of this list to the auth routes.
        'legacy_compat'     => false,
    ],

    // Lifecycle hooks — called on successful auth events.
    // Use for tenant DB provisioning, audit logging, session setup, etc.
    // Hook functions receive ($result, $input) where $result is the auth service response.
    // Hook exceptions are caught and logged — they never affect the HTTP response.
    'hooks' => [
        'before_register'      => null,  // fn(array $input): array — can modify input
        'after_register'       => null,  // fn(array $result, array $input): void
        'after_login'          => null,
        'after_select_tenant'  => null,
        'after_password_reset' => null,
        'after_accept_invite'  => null,
    ],

    // ─────────────────────────────────────────────────────────────────────────────
    // Token exchange (identity token -> API token) is provided by the framework:
    // POST {prefix}/exchange (feature 'exchange', on by default). Do not add a route
    // of your own at that path. Supply your tenant and role lookups here:
    //
    //   'tenants_resolver' => fn(array $authClaims): array => [...],      // tenants of this identity
    //   'roles_resolver'   => fn(array $claimsWithTenant): array => [...], // roles in that tenant
    //
    // SET BOTH. Without a tenants_resolver the route trusts the `tenant_id` in the request body,
    // so a roles_resolver that does not itself check that the identity belongs to that tenant
    // lets any signed-in user mint an API token for ANY tenant. Without a roles_resolver the
    // route answers 501 and no API token is issued.
    //
    // The API token is signed with the app's own JWT keypair (JWT_PRIVATE_KEY_PATH,
    // created by `php stone setup`).
    // ─────────────────────────────────────────────────────────────────────────────
];
