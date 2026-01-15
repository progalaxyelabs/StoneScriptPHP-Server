<?php

use App\Routes\HealthRoute;
use App\Routes\HomeRoute;
use App\Routes\Auth\LoginRoute;
use App\Routes\Auth\RegisterRoute;
use App\Routes\Auth\RefreshRoute;
use App\Routes\Auth\LogoutRoute;
use App\Routes\Auth\MeRoute;

return [
    'GET' => [
        '/' => HomeRoute::class,
        '/api/health' => HealthRoute::class,
        '/api/auth/me' => MeRoute::class,
    ],
    'POST' => [
        '/api/auth/login' => LoginRoute::class,
        '/api/auth/register' => RegisterRoute::class,
        '/api/auth/refresh' => RefreshRoute::class,
        '/api/auth/logout' => LogoutRoute::class,
    ]
];
