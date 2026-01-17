<?php

declare(strict_types=1);

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use TooInfinity\Lingua\Http\Middleware\LinguaMiddleware;

beforeEach(function (): void {
    // Set up a simple route that returns JSON instead of Inertia view
    Route::middleware(['web', LinguaMiddleware::class])->get('/test-lingua', function () {
        // Resolve the shared closure to get actual data
        $shared = Inertia::getShared();
        $linguaData = isset($shared['lingua']) && is_callable($shared['lingua'])
            ? $shared['lingua']()
            : ($shared['lingua'] ?? null);

        return response()->json([
            'locale' => app()->getLocale(),
            'lingua' => $linguaData,
        ]);
    });
});

it('sets locale from session', function (): void {
    session()->put('lingua.locale', 'fr');

    $response = $this->get('/test-lingua');

    $response->assertOk();
    $response->assertJson(['locale' => 'fr']);
});

it('uses default locale when no session value exists', function (): void {
    $response = $this->get('/test-lingua');

    $response->assertOk();
    $response->assertJson(['locale' => 'en']);
});

it('shares translations via inertia', function (): void {
    $response = $this->get('/test-lingua');

    $response->assertOk();

    $linguaData = $response->json('lingua');

    expect($linguaData)->toBeArray()
        ->toHaveKey('locale')
        ->toHaveKey('locales')
        ->toHaveKey('translations');

    expect($linguaData['locale'])->toBe('en');
    expect($linguaData['locales'])->toBe(['en', 'fr', 'es']);
});

describe('middleware auto-registration', function (): void {
    it('registers middleware alias for manual usage', function (): void {
        /** @var Router $router */
        $router = app(Router::class);
        $middlewareAliases = $router->getMiddleware();

        expect($middlewareAliases)->toHaveKey('lingua')
            ->and($middlewareAliases['lingua'])->toBe(LinguaMiddleware::class);
    });

    it('auto-registers middleware to web group by default', function (): void {
        /** @var Router $router */
        $router = app(Router::class);
        $middlewareGroups = $router->getMiddlewareGroups();

        expect($middlewareGroups)->toHaveKey('web')
            ->and($middlewareGroups['web'])->toContain(LinguaMiddleware::class);
    });

    it('verifies middleware auto_register config defaults to true', function (): void {
        expect(config('lingua.middleware.auto_register'))->toBeTrue();
    });

    it('verifies middleware group config defaults to web', function (): void {
        expect(config('lingua.middleware.group'))->toBe('web');
    });

    it('middleware config can be modified at runtime', function (): void {
        $this->app['config']->set('lingua.middleware.auto_register', false);
        $this->app['config']->set('lingua.middleware.group', 'api');

        expect(config('lingua.middleware.auto_register'))->toBeFalse()
            ->and(config('lingua.middleware.group'))->toBe('api');
    });

    it('middleware works when applied via alias', function (): void {
        Route::middleware(['web', 'lingua'])->get('/test-lingua-alias', function () {
            $shared = Inertia::getShared();
            $linguaData = isset($shared['lingua']) && is_callable($shared['lingua'])
                ? $shared['lingua']()
                : ($shared['lingua'] ?? null);

            return response()->json([
                'locale' => app()->getLocale(),
                'lingua' => $linguaData,
            ]);
        });

        session()->put('lingua.locale', 'es');

        $response = $this->get('/test-lingua-alias');

        $response->assertOk();
        $response->assertJson(['locale' => 'es']);
    });
});
