<?php

use App\Routes\HealthRoute;
use App\Routes\HomeRoute;
use App\Routes\Auth\TokenExchangeRoute;

/**
 * Routes Configuration
 *
 * FLAT FORMAT ONLY as of framework v6.0.0 — the old 'public'/'protected'
 * sectioned format was removed and now throws a migration error at boot
 * (StoneScriptPHP\Routing\Router::loadRoutes(), see SPEC.md §3 Routing
 * Conventions). This is the same flat format already used by every real
 * StoneScriptPHP-powered platform.
 *
 * Each route is keyed by HTTP method, mapped to a path => config array:
 *   'GET' => ['/path' => ['handler' => X::class, 'service' => 'shared',
 *                          'group' => 'billing', 'is_public' => false]]
 *
 * - handler:    IRouteHandler class (required)
 * - service:    partitions routes for typed client generation (default 'shared')
 * - group:      domain-concept grouping, purely descriptive (optional)
 * - is_public:  true bypasses JWT auth entirely (default false = protected)
 *
 * A bare classname value (no array) is also accepted — shorthand for
 * ['handler' => X::class, 'service' => 'shared', 'is_public' => false].
 */
return [
    'GET' => [
        '/'       => ['handler' => HomeRoute::class,   'service' => 'infra', 'group' => 'system', 'is_public' => true],
        '/health' => ['handler' => HealthRoute::class, 'service' => 'infra', 'group' => 'system', 'is_public' => true],

        // Add your protected GET routes here
    ],

    'POST' => [
        // Token exchange: accepts identity token, returns platform token.
        // The route validates the incoming identity JWT itself, so it must
        // stay public (JWT middleware would otherwise reject the request
        // before the handler ever runs).
        '/api/auth/exchange' => ['handler' => TokenExchangeRoute::class, 'service' => 'infra', 'group' => 'auth', 'is_public' => true],

        // Add your protected POST routes here
    ],

    'PUT'    => [],
    'DELETE' => [],
];
