<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use StoneScriptPHP\Routing\IncomingRequest;
use StoneScriptPHP\Routing\Router;

/**
 * Framework v12.0.0 request semantics, exercised against the REAL shipped routes.php:
 * HEAD is answered wherever GET is routed, and a known path requested with a method
 * that has no route answers 405 + Allow (it used to be 404).
 */
class RequestSemanticsTest extends TestCase
{
    private function router(): Router
    {
        $router = new Router();
        $router->loadRoutes(require CONFIG_PATH . 'routes.php');
        return $router;
    }

    public function test_head_is_answered_where_get_is_routed(): void
    {
        $response = $this->router()->dispatch(new IncomingRequest(method: 'HEAD', path: '/health'));

        $this->assertSame('ok', $response->status);
    }

    public function test_wrong_method_on_known_path_is_405_with_allow(): void
    {
        $response = $this->router()->dispatch(new IncomingRequest(method: 'POST', path: '/health'));

        $this->assertSame(405, $response->httpStatusCode);
        $this->assertArrayHasKey('Allow', $response->headers);
        $this->assertStringContainsString('GET', $response->headers['Allow']);
        $this->assertStringContainsString('HEAD', $response->headers['Allow']);
    }

    public function test_unknown_path_stays_404(): void
    {
        $response = $this->router()->dispatch(new IncomingRequest(method: 'GET', path: '/no/such/path'));

        $this->assertSame('error', $response->status);
        $this->assertNotSame(405, $response->httpStatusCode);
        $this->assertArrayNotHasKey('Allow', $response->headers);
    }

    public function test_scaffold_does_not_override_the_framework_exchange_route(): void
    {
        // POST {auth prefix}/exchange belongs to the framework (ExternalAuthRoutes). A route
        // declared here would silently shadow it.
        $routes = require CONFIG_PATH . 'routes.php';

        foreach ($routes as $method => $byPath) {
            foreach (array_keys($byPath) as $path) {
                $this->assertStringNotContainsString('/exchange', (string) $path, "$method $path shadows the framework exchange route");
            }
        }
    }
}
