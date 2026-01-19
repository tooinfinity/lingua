<?php

declare(strict_types=1);

namespace TooInfinity\Lingua\Support\Routing;

use Closure;
use Illuminate\Support\Facades\Route;

/**
 * Registers route macros for localized routing.
 *
 * Provides the `Route::linguaLocalized()` macro that creates route groups
 * with locale support based on the configured URL strategy.
 */
final class LinguaRouteMacros
{
    /**
     * Register all Lingua route macros.
     */
    public static function register(): void
    {
        self::registerLinguaLocalizedMacro();
    }

    /**
     * Register routes with prefix strategy.
     *
     * Wraps routes in a group with `{locale}` prefix parameter.
     *
     * @param  Closure  $routes  The route definitions
     * @param  array<string, mixed>  $options  Additional route group options
     */
    public static function applyPrefixRoutes(Closure $routes, array $options): void
    {
        // Build prefix based on segment position
        // For segment 1, prefix is just '{locale}'
        // For segment 2, we'd need to handle it differently (usually segment 1 is most common)
        $prefix = '{locale}';

        /** @var array<string, mixed> $groupOptions */
        $groupOptions = array_merge($options, [
            'prefix' => $prefix,
            'where' => ['locale' => '[a-z]{2}([_-][A-Za-z]{2})?'],
        ]);

        Route::group($groupOptions, $routes);
    }

    /**
     * Register routes with domain strategy.
     *
     * Creates route groups for each configured locale host.
     *
     * @param  Closure  $routes  The route definitions
     * @param  array<string, mixed>  $options  Additional route group options
     */
    public static function applyDomainRoutes(Closure $routes, array $options): void
    {
        /** @var array<string, string> $hosts */
        $hosts = config('lingua.url.domain.hosts', []);

        if (empty($hosts)) {
            // Fallback to default routes if no hosts configured
            self::applyDefaultRoutes($routes, $options);

            return;
        }

        foreach ($hosts as $locale => $host) {
            /** @var array<string, mixed> $groupOptions */
            $groupOptions = array_merge($options, [
                'domain' => $host,
            ]);

            Route::group($groupOptions, static function () use ($routes, $locale): void {
                // Make locale available within the route group
                // This can be accessed via route parameter binding or middleware
                app()->instance('lingua.route.locale', $locale);

                $routes();
            });
        }
    }

    /**
     * Register routes without any locale transformation.
     *
     * Used when no URL strategy is configured.
     *
     * @param  Closure  $routes  The route definitions
     * @param  array<string, mixed>  $options  Additional route group options
     */
    public static function applyDefaultRoutes(Closure $routes, array $options): void
    {
        if ($options !== []) {
            Route::group($options, $routes);
        } else {
            $routes();
        }
    }

    /**
     * Register the linguaLocalized route macro.
     *
     * Usage:
     * ```php
     * Route::linguaLocalized(function () {
     *     Route::get('/dashboard', DashboardController::class);
     * });
     *
     * // With options
     * Route::linguaLocalized(function () {
     *     Route::get('/dashboard', DashboardController::class);
     * }, ['middleware' => ['auth']]);
     * ```
     */
    private static function registerLinguaLocalizedMacro(): void
    {
        Route::macro('linguaLocalized', function (Closure $routes, array $options = []): void {
            /** @var array<string, mixed> $options */
            /** @var string|null $strategy */
            $strategy = config('lingua.url.strategy');

            match ($strategy) {
                'prefix' => LinguaRouteMacros::applyPrefixRoutes($routes, $options),
                'domain' => LinguaRouteMacros::applyDomainRoutes($routes, $options),
                default => LinguaRouteMacros::applyDefaultRoutes($routes, $options),
            };
        });
    }
}
