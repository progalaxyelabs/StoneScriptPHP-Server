<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use StoneScriptPHP\Routing\Router;
use StoneScriptPHP\Routing\IncomingRequest;

/**
 * Regression test for a real production incident found during the
 * stonescriptphp 5.x -> 6.0.0 upgrade investigation (2026-07-15):
 *
 * `src/config/routes.php` shipped in the old 'public'/'protected' sectioned
 * format. Framework v6.0.0 (ROUTING-CONSOLIDATION-PLAN.md, Phase 1) removed
 * support for that format — Router::loadRoutes() now throws instead of
 * silently registering unreachable routes. Because this skeleton is what
 * `composer create-project progalaxyelabs/stonescriptphp-server` scaffolds,
 * ANY new project built from it and then upgraded to framework ^6.0 would
 * fatal on every single request (the exception is thrown during
 * Application::run(), before any route can match).
 *
 * This test loads the REAL routes.php shipped with this skeleton (not a
 * fixture) through the real Router, so a regression here is caught the
 * moment someone reverts to the old format or copies it from stale docs.
 */
class RoutesConfigTest extends TestCase
{
    public function test_shipped_routes_config_loads_without_throwing(): void
    {
        $routesConfig = require CONFIG_PATH . 'routes.php';

        $router = new Router();

        // Router::loadRoutes() throws \Exception if it detects the removed
        // 'public'/'protected' sectioned format. No exception = flat format.
        $router->loadRoutes($routesConfig);

        $this->assertTrue(true, 'routes.php loaded without throwing a migration error');
    }

    public function test_home_route_dispatches_and_is_public(): void
    {
        $routesConfig = require CONFIG_PATH . 'routes.php';
        $router = new Router();
        $router->loadRoutes($routesConfig);

        $response = $router->dispatch(new IncomingRequest(method: 'GET', path: '/'));

        $this->assertSame('ok', $response->status);
    }

    public function test_health_route_dispatches_and_reports_healthy(): void
    {
        $routesConfig = require CONFIG_PATH . 'routes.php';
        $router = new Router();
        $router->loadRoutes($routesConfig);

        $response = $router->dispatch(new IncomingRequest(method: 'GET', path: '/health'));

        $this->assertSame('ok', $response->status);
        $this->assertSame('healthy', $response->data['status']);
    }
}
