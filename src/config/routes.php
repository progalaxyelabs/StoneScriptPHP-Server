<?php

use App\Routes\HealthRoute;
use App\Routes\HomeRoute;

/**
 * Routes Configuration
 *
 * FLAT FORMAT ONLY as of framework v6.0.0 — the old 'public'/'protected'
 * sectioned format was removed and now throws a migration error at boot
 * (StoneScriptPHP\Routing\Router::loadRoutes()).
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
 *
 * Framework v7.0.0+ typed access model (see UPGRADING-7.0.md):
 * `is_public` still works and is the recommended shorthand for public routes,
 * but every route now also carries an `access` value derived from it —
 * `is_public => true` resolves to `access: public`; any other route (the
 * default) resolves to `access: authorization`. The moment you add your
 * first non-public route here (or enable a framework auth feature like
 * `change_password`/`profile` in config/auth.php, which are non-public by
 * default), Application::run() REQUIRES the typed-auth middleware to be
 * wired or it refuses to boot (AuthWiringException). This skeleton's
 * src/config/application.php already wires AccessTokenMiddleware for you — see the
 * comment block there before adding a protected route. Use explicit
 * `'access' => 'authorization', 'token_type' => 'access'` on a route only if
 * you need to override the is_public-derived default.
 *
 * Framework v12.0.0 request semantics (apply to every route you add):
 * - HEAD is answered wherever GET is routed. A GET handler with side effects
 *   (email verification, unsubscribe, magic link, one-time link) must declare
 *   `'head' => 'probe'` so a monitor's HEAD does not run it (or set
 *   HEAD_EXECUTES_GET=false in .env to probe every HEAD by default).
 *   A streaming GET route (SSE, long-poll) needs `'streaming' => true`.
 * - A known path requested with a method that has no route answers
 *   405 + Allow (it used to be 404). Unknown paths stay 404.
 */
return [
    'GET' => [
        '/'       => ['handler' => HomeRoute::class,   'service' => 'infra', 'group' => 'system', 'is_public' => true],
        '/health' => ['handler' => HealthRoute::class, 'service' => 'infra', 'group' => 'system', 'is_public' => true],

        // Add your protected GET routes here
    ],

    'POST' => [
        // Add your protected POST routes here.
        // POST /api/auth/exchange (identity token -> API token) is registered by the
        // framework; configure it through src/config/auth.php, do not declare it here.
    ],

    'PUT'    => [],
    'DELETE' => [],
];
