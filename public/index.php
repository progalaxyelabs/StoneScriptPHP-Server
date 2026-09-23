<?php

define('INDEX_START_TIME', microtime(true));
define('ROOT_PATH', realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR);
define('SRC_PATH', ROOT_PATH . 'src' . DIRECTORY_SEPARATOR);
define('CONFIG_PATH', SRC_PATH . 'config' . DIRECTORY_SEPARATOR);

if (!defined('DEBUG_MODE')) {
    define('DEBUG_MODE', ($_SERVER['DEBUG_MODE'] ?? 'false') === 'true');
}

require_once ROOT_PATH . 'vendor/autoload.php';

use StoneScriptPHP\Application;
use StoneScriptPHP\Env;
use StoneScriptPHP\Auth\TrustedIssuerVerifier;
use StoneScriptPHP\Auth\Middleware\AccessTokenMiddleware;

$authConfig = require CONFIG_PATH . 'auth.php';

/**
 * Typed-auth wiring (framework v7.0.0+, see UPGRADING-7.0.md).
 *
 * As of v7.0.0, any registered route whose access model resolves to
 * `authentication`/`authorization` (this includes framework-owned routes —
 * e.g. ExternalAuthRoutes' `change-password` and `me` routes, both enabled
 * by default in src/config/auth.php's `features` list) REQUIRES
 * AccessTokenMiddleware to be wired, or Application::run() refuses to boot
 * (AuthWiringException — "typed-auth wiring incomplete"). This is not
 * optional scaffolding: with `auth.mode = 'external'` (this skeleton's
 * default) and the default feature toggles, the app will not boot without
 * this block.
 *
 * This skeleton has no local refresh-token routes (refresh is proxied
 * straight through to the external auth service as a public route — see
 * ExternalAuthRoutes' `/refresh-token`), so only AccessTokenMiddleware is
 * needed here. If you add a route with `token_type => 'refresh'` of your
 * own, you must also wire RefreshTokenMiddleware + a real RefreshTokenStore
 * (see StoneScriptPHP\Auth\InMemoryRefreshTokenStore for the contract —
 * back it with a real table in production) via
 * StoneScriptPHP\Auth\Middleware\AuthMiddlewareRegistrar::create().
 */
$env = Env::get_instance();
$authMode = $authConfig['mode'] ?? $env->AUTH_MODE ?? 'builtin';

$typedAuthMiddleware = [];
if ($authMode === 'external' || $authMode === 'hybrid') {
    // Identity tokens are minted by the external auth service — verify them
    // via its JWKS endpoint. Issuer/URL resolution mirrors
    // Application::buildAuthRouteOptions()'s own precedence (explicit config
    // wins, then the matching env var).
    $authServiceUrl = $authConfig['server']['url'] ?? $env->AUTH_SERVICE_URL;
    $authIssuer = $authConfig['server']['issuer'] ?? ($env->AUTH_ISSUER ?: null);
    $jwksPath = $authConfig['server']['paths']['jwks'] ?? '/api/auth/jwks';

    if (!empty($authServiceUrl) && !empty($authIssuer)) {
        $typedAuthMiddleware[] = new AccessTokenMiddleware(new TrustedIssuerVerifier([
            $authIssuer => [
                'kind'     => 'jwks',
                'jwks_url' => rtrim($authServiceUrl, '/') . $jwksPath,
            ],
        ]));
    }
    // If AUTH_SERVICE_URL/AUTH_ISSUER aren't configured yet, leave typed-auth
    // middleware unwired — Application::run() will fail boot with an
    // actionable AuthWiringException the moment a protected route is
    // registered, rather than silently leaving one unauthenticated.
} elseif ($authMode === 'builtin' && !empty($env->JWT_ISSUER) && is_file($env->JWT_PUBLIC_KEY_PATH)) {
    $typedAuthMiddleware[] = new AccessTokenMiddleware(new TrustedIssuerVerifier([
        $env->JWT_ISSUER => [
            'kind'            => 'local',
            'public_key_path' => $env->JWT_PUBLIC_KEY_PATH,
        ],
    ]));
}

Application::run([
    'routes'       => require CONFIG_PATH . 'routes.php',
    'auth'         => $authConfig,
    'subscription' => require CONFIG_PATH . 'subscription.php',
    'jwt'          => [
        'excluded_paths' => require CONFIG_PATH . 'jwt-excluded-paths.php',
    ],
    'middleware'   => $typedAuthMiddleware,
]);
