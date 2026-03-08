<?php

return [
    // Auth mode:
    //   'builtin'  — StoneScriptPHP issues/validates its own JWTs (RSA keys in ./keys/)
    //   'external' — delegate all auth to a separate auth service (progalaxyelabs-auth or custom)
    //   'hybrid'   — validate external JWTs + optionally issue own tokens
    'mode' => 'external',

    // URL prefix for all registered auth routes (e.g. POST /auth/login, GET /auth/me)
    'prefix' => '/auth',

    // Platform identity — sent in every request to the auth service
    'platform' => [
        'code'   => 'myapp',    // Required: matches PLATFORM_CODE in auth service
        'secret' => null,       // Falls back to EXTERNAL_AUTH_CLIENT_SECRET env var. Used as X-Platform-Secret header for privileged endpoints (register-tenant)
    ],

    'server' => [
        // JWKS fetch URL — use container-internal hostname in Docker (e.g. http://auth-service:3139)
        'url' => 'http://localhost:3139',

        // JWT 'iss' claim value — must match exactly what the auth server stamps in tokens.
        // In Docker: AUTH_SERVICE_URL = container URL, AUTH_ISSUER = public URL the server stamps.
        // Leave empty to fall back to 'url' (single-host / local dev).
        'issuer' => '',

        // Override auth service endpoint paths if they differ from progalaxyelabs-auth defaults.
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
];
