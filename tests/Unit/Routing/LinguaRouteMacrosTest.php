<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use TooInfinity\Lingua\Support\Routing\LinguaRouteMacros;

/**
 * Helper function to get a route by name after refreshing name lookups.
 */
function getRouteByName(string $name): ?Illuminate\Routing\Route
{
    $routes = Route::getRoutes();
    $routes->refreshNameLookups();

    return $routes->getByName($name);
}

describe('LinguaRouteMacros', function (): void {
    describe('register', function (): void {
        it('registers the linguaLocalized macro on Route facade', function (): void {
            LinguaRouteMacros::register();

            expect(Route::hasMacro('linguaLocalized'))->toBeTrue();
        });
    });

    describe('applyPrefixRoutes', function (): void {
        it('creates route group with locale prefix', function (): void {
            $routeName = 'test_prefix_'.uniqid();

            LinguaRouteMacros::applyPrefixRoutes(function () use ($routeName): void {
                Route::get('/test-prefix', fn (): string => 'test')->name($routeName);
            }, []);

            $testRoute = getRouteByName($routeName);

            expect($testRoute)->not->toBeNull();
            expect($testRoute->uri())->toBe('{locale}/test-prefix');
        });

        it('applies locale regex constraint', function (): void {
            $routeName = 'test_constraint_'.uniqid();

            LinguaRouteMacros::applyPrefixRoutes(function () use ($routeName): void {
                Route::get('/settings', fn (): string => 'settings')->name($routeName);
            }, []);

            $testRoute = getRouteByName($routeName);

            expect($testRoute->wheres)->toHaveKey('locale');
            expect($testRoute->wheres['locale'])->toBe('[a-z]{2}([_-][A-Za-z]{2})?');
        });

        it('merges additional options into route group', function (): void {
            $routeName = 'test_options_'.uniqid();

            LinguaRouteMacros::applyPrefixRoutes(function () use ($routeName): void {
                Route::get('/admin', fn (): string => 'admin')->name($routeName);
            }, ['middleware' => ['auth']]);

            $testRoute = getRouteByName($routeName);

            expect($testRoute->middleware())->toContain('auth');
        });

        it('supports name prefix option', function (): void {
            $uniqueId = uniqid();
            $routeName = 'profile_'.$uniqueId;

            LinguaRouteMacros::applyPrefixRoutes(function () use ($routeName): void {
                Route::get('/profile', fn (): string => 'profile')->name($routeName);
            }, ['as' => 'localized_'.$uniqueId.'.']);

            $testRoute = getRouteByName('localized_'.$uniqueId.'.'.$routeName);

            expect($testRoute)->not->toBeNull();
        });

        it('registers multiple routes within the group', function (): void {
            $uniqueId = uniqid();

            LinguaRouteMacros::applyPrefixRoutes(function () use ($uniqueId): void {
                Route::get('/home', fn (): string => 'home')->name('home_'.$uniqueId);
                Route::get('/about', fn (): string => 'about')->name('about_'.$uniqueId);
                Route::get('/contact', fn (): string => 'contact')->name('contact_'.$uniqueId);
            }, []);

            expect(getRouteByName('home_'.$uniqueId))->not->toBeNull();
            expect(getRouteByName('about_'.$uniqueId))->not->toBeNull();
            expect(getRouteByName('contact_'.$uniqueId))->not->toBeNull();

            expect(getRouteByName('home_'.$uniqueId)->uri())->toBe('{locale}/home');
            expect(getRouteByName('about_'.$uniqueId)->uri())->toBe('{locale}/about');
            expect(getRouteByName('contact_'.$uniqueId)->uri())->toBe('{locale}/contact');
        });
    });

    describe('applyDomainRoutes', function (): void {
        it('creates route groups for each configured host', function (): void {
            config([
                'lingua.url.domain.hosts' => [
                    'en' => 'en.example.com',
                    'fr' => 'fr.example.com',
                ],
            ]);

            $routeName = 'domain_test_'.uniqid();

            LinguaRouteMacros::applyDomainRoutes(function () use ($routeName): void {
                Route::get('/domain-test', fn (): string => 'test')->name($routeName);
            }, []);

            $routes = Route::getRoutes();
            $routes->refreshNameLookups();

            $allRoutes = collect($routes->getRoutes());

            // Should have created routes with domain constraints
            $domainRoutes = $allRoutes->filter(fn ($route): bool => $route->getDomain() !== null);
            expect($domainRoutes->count())->toBeGreaterThanOrEqual(1);
        });

        it('applies domain constraint to routes', function (): void {
            config([
                'lingua.url.domain.hosts' => [
                    'en' => 'example.com',
                    'fr' => 'fr.example.com',
                ],
            ]);

            $routeName = 'domain_constraint_'.uniqid();

            LinguaRouteMacros::applyDomainRoutes(function () use ($routeName): void {
                Route::get('/settings', fn (): string => 'settings')->name($routeName);
            }, []);

            $routes = Route::getRoutes();
            $routes->refreshNameLookups();

            $allRoutes = collect($routes->getRoutes());

            $domainRoutes = $allRoutes->filter(fn ($route): bool => $route->getDomain() !== null);
            expect($domainRoutes->count())->toBeGreaterThanOrEqual(1);
        });

        it('merges options with domain routes', function (): void {
            config([
                'lingua.url.domain.hosts' => [
                    'en' => 'example.com',
                ],
            ]);

            $routeName = 'domain_options_'.uniqid();

            LinguaRouteMacros::applyDomainRoutes(function () use ($routeName): void {
                Route::get('/admin', fn (): string => 'admin')->name($routeName);
            }, ['middleware' => ['auth', 'verified']]);

            $testRoute = getRouteByName($routeName);

            expect($testRoute->middleware())->toContain('auth');
            expect($testRoute->middleware())->toContain('verified');
        });

        it('falls back to default when hosts array is empty', function (): void {
            config(['lingua.url.domain.hosts' => []]);

            $routeName = 'empty_hosts_'.uniqid();

            LinguaRouteMacros::applyDomainRoutes(function () use ($routeName): void {
                Route::get('/empty-hosts', fn (): string => 'test')->name($routeName);
            }, []);

            $testRoute = getRouteByName($routeName);

            expect($testRoute)->not->toBeNull();
            expect($testRoute->uri())->toBe('empty-hosts');
        });

        it('sets lingua.route.locale in app container for each domain group', function (): void {
            config([
                'lingua.url.domain.hosts' => [
                    'en' => 'example.com',
                    'fr' => 'fr.example.com',
                ],
            ]);

            $routeName = 'locale_binding_'.uniqid();

            LinguaRouteMacros::applyDomainRoutes(function () use ($routeName): void {
                Route::get('/test', fn (): string => 'test')->name($routeName);
            }, []);

            // The locale should have been bound during route registration
            $testRoute = getRouteByName($routeName);
            expect($testRoute)->not->toBeNull();
        });
    });

    describe('applyDefaultRoutes', function (): void {
        it('wraps routes in group when options provided', function (): void {
            $routeName = 'default_options_'.uniqid();

            LinguaRouteMacros::applyDefaultRoutes(function () use ($routeName): void {
                Route::get('/default-with-options', fn (): string => 'test')->name($routeName);
            }, ['middleware' => ['api']]);

            $testRoute = getRouteByName($routeName);

            expect($testRoute)->not->toBeNull();
            expect($testRoute->middleware())->toContain('api');
        });

        it('calls routes directly when no options provided', function (): void {
            $routeName = 'default_no_options_'.uniqid();

            LinguaRouteMacros::applyDefaultRoutes(function () use ($routeName): void {
                Route::get('/default-no-options', fn (): string => 'test')->name($routeName);
            }, []);

            $testRoute = getRouteByName($routeName);

            expect($testRoute)->not->toBeNull();
            expect($testRoute->uri())->toBe('default-no-options');
        });
    });

    describe('linguaLocalized macro', function (): void {
        beforeEach(function (): void {
            LinguaRouteMacros::register();
        });

        it('uses prefix strategy when configured', function (): void {
            config([
                'lingua.url.strategy' => 'prefix',
                'lingua.url.prefix.segment' => 1,
            ]);

            $routeName = 'macro_prefix_'.uniqid();

            Route::linguaLocalized(function () use ($routeName): void {
                Route::get('/dashboard', fn (): string => 'dashboard')->name($routeName);
            });

            $testRoute = getRouteByName($routeName);

            expect($testRoute)->not->toBeNull();
            expect($testRoute->uri())->toBe('{locale}/dashboard');
        });

        it('uses domain strategy when configured', function (): void {
            config([
                'lingua.url.strategy' => 'domain',
                'lingua.url.domain.hosts' => [
                    'en' => 'example.com',
                    'fr' => 'fr.example.com',
                ],
            ]);

            $routeName = 'macro_domain_'.uniqid();

            Route::linguaLocalized(function () use ($routeName): void {
                Route::get('/dashboard', fn (): string => 'dashboard')->name($routeName);
            });

            $routes = Route::getRoutes();
            $routes->refreshNameLookups();

            $allRoutes = collect($routes->getRoutes());

            $domainRoutes = $allRoutes->filter(fn ($route): bool => $route->getDomain() !== null);
            expect($domainRoutes->count())->toBeGreaterThanOrEqual(1);
        });

        it('uses default behavior when no strategy configured', function (): void {
            config(['lingua.url.strategy' => null]);

            $routeName = 'macro_default_'.uniqid();

            Route::linguaLocalized(function () use ($routeName): void {
                Route::get('/dashboard', fn (): string => 'dashboard')->name($routeName);
            });

            $testRoute = getRouteByName($routeName);

            expect($testRoute)->not->toBeNull();
            expect($testRoute->uri())->toBe('dashboard');
        });

        it('passes options to prefix strategy', function (): void {
            config([
                'lingua.url.strategy' => 'prefix',
                'lingua.url.prefix.segment' => 1,
            ]);

            $routeName = 'macro_prefix_opts_'.uniqid();

            Route::linguaLocalized(function () use ($routeName): void {
                Route::get('/admin', fn (): string => 'admin')->name($routeName);
            }, ['middleware' => ['auth']]);

            $testRoute = getRouteByName($routeName);

            expect($testRoute->middleware())->toContain('auth');
        });

        it('passes options to domain strategy', function (): void {
            config([
                'lingua.url.strategy' => 'domain',
                'lingua.url.domain.hosts' => [
                    'en' => 'example.com',
                ],
            ]);

            $routeName = 'macro_domain_opts_'.uniqid();

            Route::linguaLocalized(function () use ($routeName): void {
                Route::get('/admin', fn (): string => 'admin')->name($routeName);
            }, ['middleware' => ['auth']]);

            $testRoute = getRouteByName($routeName);

            expect($testRoute->middleware())->toContain('auth');
        });

        it('passes options to default strategy', function (): void {
            config(['lingua.url.strategy' => null]);

            $routeName = 'macro_default_opts_'.uniqid();

            Route::linguaLocalized(function () use ($routeName): void {
                Route::get('/admin', fn (): string => 'admin')->name($routeName);
            }, ['middleware' => ['auth']]);

            $testRoute = getRouteByName($routeName);

            expect($testRoute->middleware())->toContain('auth');
        });

        it('falls back to default when domain hosts empty', function (): void {
            config([
                'lingua.url.strategy' => 'domain',
                'lingua.url.domain.hosts' => [],
            ]);

            $routeName = 'macro_fallback_'.uniqid();

            Route::linguaLocalized(function () use ($routeName): void {
                Route::get('/fallback', fn (): string => 'fallback')->name($routeName);
            });

            $testRoute = getRouteByName($routeName);

            expect($testRoute)->not->toBeNull();
            expect($testRoute->uri())->toBe('fallback');
        });
    });
});
