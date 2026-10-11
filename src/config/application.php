<?php

/**
 * The configuration public/index.php hands to Application::run(), in one place so the auth smoke
 * test (tests/Unit/ApplicationProbe.php) boots exactly what production boots.
 *
 * `typed_auth => true` makes the framework wire the typed-auth middleware (v7.0.0+) for the
 * configured AUTH_MODE: external/hybrid trust the auth service AND this API's own issuer
 * (the API tokens POST /api/auth/exchange signs); builtin uses the single-token middleware.
 * A missing issuer, key or URL stops the boot naming the variable, and so do empty
 * DB_GATEWAY_* settings (`php stone env:check` runs the same check).
 *
 * No local refresh-token routes are declared here. If you add a route with
 * `token_type => 'refresh'`, also wire RefreshTokenMiddleware and a real
 * RefreshTokenStore via AuthMiddlewareRegistrar::create().
 */
return [
    'routes'       => require __DIR__ . '/routes.php',
    'auth'         => require __DIR__ . '/auth.php',
    'subscription' => require __DIR__ . '/subscription.php',
    'jwt'          => [
        'excluded_paths' => require __DIR__ . '/jwt-excluded-paths.php',
    ],
    'typed_auth'   => true,
];
