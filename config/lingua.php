<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Supported Locales
    |--------------------------------------------------------------------------
    |
    | An array of locale codes that your application supports.
    |
    */
    'locales' => ['en'],

    /*
    |--------------------------------------------------------------------------
    | Default Locale
    |--------------------------------------------------------------------------
    |
    | The default locale to use when no locale can be resolved.
    | Set to null to use config('app.locale').
    |
    */
    'default' => null,

    /*
    |--------------------------------------------------------------------------
    | Session Key (Legacy)
    |--------------------------------------------------------------------------
    |
    | Legacy configuration option. Use 'resolvers.session.key' instead.
    | This is kept for backward compatibility.
    |
    */
    'session_key' => 'lingua.locale',

    /*
    |--------------------------------------------------------------------------
    | Locale Resolution Order
    |--------------------------------------------------------------------------
    |
    | The order in which locale resolvers are checked. The first resolver
    | that returns a valid, supported locale will be used.
    |
    | Available resolvers: 'session', 'cookie', 'query', 'header', 'url_segment'
    |
    */
    'resolution_order' => ['session', 'cookie', 'query', 'header'],

    /*
    |--------------------------------------------------------------------------
    | Resolver Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration options for each locale resolver.
    | Each resolver can be enabled/disabled individually using the 'enabled' key.
    | Set 'enabled' => false to temporarily disable a resolver without removing
    | it from the resolution_order array.
    |
    */
    'resolvers' => [
        'session' => [
            'enabled' => true,        // Enable/disable this resolver
            'key' => 'lingua.locale', // Session key to store the locale
        ],

        'cookie' => [
            'enabled' => true,         // Enable/disable this resolver
            'key' => 'lingua_locale',  // Cookie name
        ],

        'query' => [
            'enabled' => false,  // Enable/disable this resolver (disabled by default)
            'key' => 'locale',   // Query parameter name (e.g., ?locale=fr)
        ],

        'header' => [
            'enabled' => false,     // Enable/disable this resolver (disabled by default)
            'use_quality' => true,  // Respect quality values in Accept-Language header
        ],

        'url_segment' => [
            'enabled' => false,  // Enable/disable this resolver (disabled by default)
            'position' => 1,     // URL segment position (1-based, e.g., /fr/dashboard = position 1)
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Route Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the package's locale switching routes.
    |
    */
    'routes' => [
        'enabled' => true,  // Set to false to disable package routes
        'prefix' => '',     // Route prefix (e.g., 'api' or 'admin')
        'middleware' => ['web'],  // Middleware to apply to routes
    ],

    /*
    |--------------------------------------------------------------------------
    | Middleware Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for automatic middleware registration.
    |
    */
    'middleware' => [
        'auto_register' => true,  // Automatically register middleware to the specified group
        'group' => 'web',         // Middleware group to append to (e.g., 'web', 'api')
    ],

    /*
    |--------------------------------------------------------------------------
    | Controller Override
    |--------------------------------------------------------------------------
    |
    | Override the default locale switching controller with your own.
    | Set to null to use the default package controller.
    |
    */
    'controller' => null,
];
