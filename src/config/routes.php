<?php

use App\Routes\HealthRoute;
use App\Routes\HomeRoute;

return [
    'GET' => [
        '/' => HomeRoute::class,
        '/health' => HealthRoute::class,
    ],
    'POST' => [
        // Add your POST routes here
    ]
];
