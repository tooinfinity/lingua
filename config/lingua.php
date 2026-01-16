<?php

declare(strict_types=1);

return [
    'session_key' => 'lingua.locale',
    'locales' => ['en'],
    'default' => null,  // null = use config('app.locale')

    // Route configuration
    'routes' => [
        'enabled' => true,  // Set to false to disable package routes
        'prefix' => '',     // Route prefix (e.g., 'api' or 'admin')
        'middleware' => ['web'],  // Middleware to apply to routes
    ],

    // Controller override (null = use default package controller)
    'controller' => null,
];
