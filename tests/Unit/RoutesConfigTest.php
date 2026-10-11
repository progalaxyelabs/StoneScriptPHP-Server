<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use StoneScriptPHP\Routing\Router;
use StoneScriptPHP\Routing\IncomingRequest;

/**
 * The shipped routes.php must load through the real Router. Framework 6.0 removed the
 * 'public'/'protected' sectioned format (Router::loadRoutes() throws on it), so a project created
 * from a skeleton still in the old format would fatal on every request. This loads the REAL file
 * (not a fixture), so reverting to the old format is caught here.
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
