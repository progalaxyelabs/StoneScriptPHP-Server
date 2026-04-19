<?php

use App\Routes\HealthRoute;
use App\Routes\HomeRoute;
use App\Routes\Auth\TokenExchangeRoute;

return [
    // Public routes: no JWT required
    'public' => [
        'GET' => [
            '/'       => HomeRoute::class,
            '/health' => HealthRoute::class,
        ],
        'POST' => [
            // Token exchange: accepts identity token, returns platform token
            // The route validates the incoming identity JWT itself
            '/api/auth/exchange' => TokenExchangeRoute::class,
        ],
    ],

    // Protected routes: JWT required
    'protected' => [
        'GET' => [
            // Add your protected GET routes here
        ],
        'POST' => [
            // Add your protected POST routes here
        ],
        'PUT'    => [],
        'DELETE' => [],
    ],
];
