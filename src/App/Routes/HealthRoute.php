<?php

namespace App\Routes;

use StoneScriptPHP\ApiResponse;
use StoneScriptPHP\IRouteHandler;

/**
 * Health check route.
 *
 * Deliberately does NOT check database/gateway connectivity: this endpoint
 * backs Docker/swarm liveness probes, and a DB blip should not cascade into
 * a container restart loop. Matches the HealthRoute convention used by
 * StoneScriptPHP-powered platforms in general.
 *
 * NOTE: an earlier version of this route called `Database::query("SELECT 1")`
 * to check connectivity — that method does not exist on the framework's
 * `Database` class (the gateway-only architecture only exposes
 * `Database::fn($name, $params)`, no raw SQL). Because the resulting
 * `Call to undefined method` is a PHP \Error, not an \Exception, the
 * surrounding `catch (\Exception $e)` never caught it and every request to
 * this route fataled instead of degrading gracefully. Removed rather than
 * "fixed" with a real DB call, per the liveness-probe reasoning above.
 */
class HealthRoute implements IRouteHandler
{
    function validation_rules(): array
    {
        return [];
    }

    function process(): ApiResponse
    {
        return res_ok([
            'status' => 'healthy',
            'timestamp' => date('c'),
        ], 'Health check');
    }
}
