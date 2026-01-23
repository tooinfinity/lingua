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
    | Available resolvers: 'session', 'cookie'
    |
    */
    'resolution_order' => ['session', 'cookie'],

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
            'persist_on_set' => false, // Set cookie when Lingua::setLocale() is called
            'ttl_minutes' => 60 * 24 * 30, // Cookie lifetime in minutes (30 days)
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
        'enabled' => true,  // Set it false to disable package routes
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

    /*
    |--------------------------------------------------------------------------
    | Translation Driver
    |--------------------------------------------------------------------------
    |
    | The driver to use for loading translations. Supported drivers: 'php', 'json'.
    | - 'php': Loads translations from PHP files in lang/{locale}/*.php
    | - 'json': Loads translations from JSON file at lang/{locale}.json
    |
    */
    'translation_driver' => 'php',

    /*
    |--------------------------------------------------------------------------
    | Lazy Loading Configuration
    |--------------------------------------------------------------------------
    |
    | Configure lazy/partial translation loading to improve performance for
    | applications with many translation files. When enabled, only specified
    | translation groups are loaded instead of all translations.
    |
    | Note: Lazy loading only works with the 'php' translation driver.
    | JSON translations are loaded as a single file and cannot be split.
    |
    */
    'lazy_loading' => [
        /*
        |--------------------------------------------------------------------------
        | Enable Lazy Loading
        |--------------------------------------------------------------------------
        |
        | Set to true to enable lazy loading of translations. When disabled,
        | all translations are loaded at once (default behavior).
        |
        */
        'enabled' => false,

        /*
        |--------------------------------------------------------------------------
        | Default Groups
        |--------------------------------------------------------------------------
        |
        | Translation groups that are always loaded regardless of route.
        | These are typically common translations used across the application.
        |
        */
        'default_groups' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | RTL (Right-to-Left) Locales
    |--------------------------------------------------------------------------
    |
    | An array of locale codes that use right-to-left text direction.
    | These are checked using the base language code (e.g., 'ar' matches 'ar_SA').
    |
    | Common RTL languages:
    | - ar: Arabic
    | - he: Hebrew
    | - fa: Persian/Farsi
    | - ur: Urdu
    | - ps: Pashto
    | - sd: Sindhi
    | - ku: Kurdish (Sorani)
    | - ug: Uyghur
    | - yi: Yiddish
    | - prs: Dari
    | - dv: Dhivehi (Maldivian)
    |
    */
    'rtl_locales' => ['ar', 'he', 'fa', 'ur', 'ps', 'sd', 'ku', 'ug', 'yi', 'prs', 'dv'],

];
