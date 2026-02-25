<?php

use App\Routes\HealthRoute;
use App\Routes\HomeRoute;

return [
    // Public routes: no JWT required
    'public' => [
        'GET' => [
            '/'       => HomeRoute::class,
            '/health' => HealthRoute::class,
        ],
        'POST' => [
            // Add your public POST routes here
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
