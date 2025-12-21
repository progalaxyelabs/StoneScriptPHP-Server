<?php

namespace App\Routes;

use StoneScriptPHP\ApiResponse;
use StoneScriptPHP\IRouteHandler;
use StoneScriptPHP\Database;

class HealthRoute implements IRouteHandler
{
    function validation_rules(): array
    {
        return [];
    }

    function process(): ApiResponse
    {
        $health = [
            'status' => 'healthy',
            'timestamp' => date('c'),
            'checks' => []
        ];

        // Check database connection
        try {
            Database::query("SELECT 1");
            $health['checks']['database'] = 'ok';
        } catch (\Exception $e) {
            $health['status'] = 'unhealthy';
            $health['checks']['database'] = 'failed';
        }

        return res_ok($health, 'Health check');
    }
}
