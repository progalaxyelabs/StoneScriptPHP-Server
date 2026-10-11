<?php

namespace App\Routes;

use StoneScriptPHP\ApiResponse;
use StoneScriptPHP\IRouteHandler;

/**
 * Health check route.
 *
 * Deliberately does NOT check database/gateway connectivity: this endpoint
 * backs Docker liveness probes, and a DB blip should not cascade into
 * a container restart loop.
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
