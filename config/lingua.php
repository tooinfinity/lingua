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
    | Available resolvers: 'session', 'cookie', 'query', 'header', 'url_prefix', 'domain'
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

        'url_prefix' => [
            'enabled' => false,  // Enable/disable this resolver (disabled by default)
            'segment' => 1,      // URL segment position (1-based, e.g., /fr/dashboard = segment 1)
            'optional' => true,  // Allow missing locale prefix (graceful handling)
            'patterns' => [      // Regex patterns to validate locale format
                '^[a-z]{2}([_-][A-Za-z]{2})?$',
            ],
        ],

        'domain' => [
            'enabled' => false,       // Enable/disable this resolver (disabled by default)
            'order' => ['full', 'subdomain'],  // Evaluation order: check full map first, then subdomain
            'full_map' => [           // Full domain to locale mapping (e.g., 'example.de' => 'de')
                // 'example.de' => 'de',
                // 'example.fr' => 'fr',
            ],
            'subdomain' => [
                'enabled' => true,    // Enable subdomain locale detection
                'label' => 1,         // Subdomain label position (1-based from left, e.g., fr.example.com = label 1)
                'patterns' => [       // Regex patterns to validate subdomain as locale
                    '^[a-z]{2}([_-][A-Za-z]{2})?$',
                ],
                'base_domains' => [], // Restrict to specific base domains (empty = allow all)
            ],
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
        | Auto-Detect Page
        |--------------------------------------------------------------------------
        |
        | When enabled, automatically detects the Inertia page name and loads
        | the corresponding translation file. This eliminates the need to
        | manually specify translation groups via middleware parameters.
        |
        | Examples:
        | - 'Pages/Dashboard' => loads 'dashboard' translations
        | - 'Pages/Users/Index' => loads 'users' translations
        | - 'Admin/Users/Edit' => loads 'admin-users' translations
        |
        */
        'auto_detect_page' => true,

        /*
        |--------------------------------------------------------------------------
        | Page Group Resolver
        |--------------------------------------------------------------------------
        |
        | Custom resolver for mapping Inertia page names to translation groups.
        | Can be a closure or a class name that implements a resolve() method.
        |
        | Set to null to use the default PageTranslationResolver.
        |
        | Example with closure:
        | 'page_group_resolver' => fn (string $page) => [Str::kebab($page)],
        |
        | Example with class:
        | 'page_group_resolver' => App\Support\CustomPageResolver::class,
        |
        */
        'page_group_resolver' => null,

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

        /*
        |--------------------------------------------------------------------------
        | Cache Configuration
        |--------------------------------------------------------------------------
        |
        | Configure caching for loaded translation groups. This uses Laravel's
        | cache system to store translations between requests.
        |
        */
        'cache' => [
            'enabled' => true,
            'ttl' => 3600, // Cache TTL in seconds (1 hour)
            'prefix' => 'lingua_translations', // Cache key prefix
        ],
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

    /*
    |--------------------------------------------------------------------------
    | URL Localization Strategy
    |--------------------------------------------------------------------------
    |
    | Configure how URLs are localized for your application.
    |
    | Supported strategies:
    | - null: No URL transformation (default)
    | - 'prefix': Locale in URL path (e.g., /fr/dashboard)
    | - 'domain': Locale-specific domains (e.g., fr.example.com or example.fr)
    |
    */
    'url' => [
        'strategy' => null,  // 'prefix' | 'domain' | null

        'prefix' => [
            'segment' => 1,  // URL segment position for locale (1-based)
        ],

        'domain' => [
            'hosts' => [     // Locale to host mapping for URL generation
                // 'en' => 'example.com',
                // 'fr' => 'fr.example.com',
                // 'de' => 'example.de',
            ],
        ],
    ],
];
