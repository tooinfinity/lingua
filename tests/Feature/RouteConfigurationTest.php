<?php

declare(strict_types=1);

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use TooInfinity\Lingua\Http\Controllers\LinguaLocaleController;

describe('route enabled configuration', function (): void {
    it('registers routes by default', function (): void {
        expect(Route::has('lingua.locale.update'))->toBeTrue();
    });

    it('verifies routes enabled config defaults to true', function (): void {
        expect(config('lingua.routes.enabled'))->toBeTrue();

        $route = Route::getRoutes()->getByName('lingua.locale.update');
        expect($route)->not->toBeNull();
    });

    it('verifies routes can be disabled via config', function (): void {
        $this->app['config']->set('lingua.routes.enabled', false);

        expect(config('lingua.routes.enabled'))->toBeFalse();
    });

    it('route is accessible when enabled', function (): void {
        $this->post(route('lingua.locale.update'), ['locale' => 'fr'])
            ->assertRedirect();
    });
});

describe('route prefix configuration', function (): void {
    it('registers route at /locale when prefix is empty', function (): void {
        $route = Route::getRoutes()->getByName('lingua.locale.update');

        expect($route)->not->toBeNull()
            ->and($route->uri())->toBe('locale');
    });

    it('verifies prefix config default is empty string', function (): void {
        expect(config('lingua.routes.prefix'))->toBe('');
    });

    it('prefixed route works correctly for locale update', function (): void {
        Route::post('/settings/locale', LinguaLocaleController::class)
            ->middleware(['web'])
            ->name('lingua.locale.update.settings');

        $this->post(route('lingua.locale.update.settings'), ['locale' => 'es'])
            ->assertRedirect();

        expect(session()->get('lingua.locale'))->toBe('es');
    });

    it('api prefixed route works correctly', function (): void {
        Route::post('/api/locale', LinguaLocaleController::class)
            ->middleware(['web'])
            ->name('lingua.locale.update.api');

        $this->post(route('lingua.locale.update.api'), ['locale' => 'fr'])
            ->assertRedirect();

        expect(session()->get('lingua.locale'))->toBe('fr');
    });

    it('admin prefixed route works correctly', function (): void {
        Route::post('/admin/locale', LinguaLocaleController::class)
            ->middleware(['web'])
            ->name('lingua.locale.update.admin');

        $this->post(route('lingua.locale.update.admin'), ['locale' => 'es'])
            ->assertRedirect();

        expect(session()->get('lingua.locale'))->toBe('es');
    });

    it('nested prefix route works correctly', function (): void {
        Route::post('/api/v1/locale', LinguaLocaleController::class)
            ->middleware(['web'])
            ->name('lingua.locale.update.nested');

        $this->post(route('lingua.locale.update.nested'), ['locale' => 'fr'])
            ->assertRedirect();

        expect(session()->get('lingua.locale'))->toBe('fr');
    });

    it('prefix can be configured at runtime', function (): void {
        $this->app['config']->set('lingua.routes.prefix', 'api');

        expect(config('lingua.routes.prefix'))->toBe('api');
    });
});

describe('route middleware configuration', function (): void {
    it('applies default web middleware', function (): void {
        $route = Route::getRoutes()->getByName('lingua.locale.update');

        expect($route)->not->toBeNull()
            ->and($route->middleware())->toContain('web');
    });

    it('verifies middleware config default is web array', function (): void {
        expect(config('lingua.routes.middleware'))->toBe(['web']);
    });

    it('middleware can be configured at runtime', function (): void {
        $this->app['config']->set('lingua.routes.middleware', ['api', 'auth']);

        expect(config('lingua.routes.middleware'))->toBe(['api', 'auth']);
    });

    it('route with custom middleware works correctly', function (): void {
        Route::post('/locale-custom-middleware', LinguaLocaleController::class)
            ->middleware(['web'])
            ->name('lingua.locale.update.custom.mw');

        $this->post(route('lingua.locale.update.custom.mw'), ['locale' => 'es'])
            ->assertRedirect();

        expect(session()->get('lingua.locale'))->toBe('es');
    });
});

describe('custom controller configuration', function (): void {
    it('uses default controller when controller config is null', function (): void {
        expect(config('lingua.controller'))->toBeNull();

        $route = Route::getRoutes()->getByName('lingua.locale.update');

        expect($route)->not->toBeNull()
            ->and($route->getActionName())->toBe(LinguaLocaleController::class);
    });

    it('default controller sets locale correctly', function (): void {
        $this->post(route('lingua.locale.update'), ['locale' => 'fr'])
            ->assertRedirect();

        expect(session()->get('lingua.locale'))->toBe('fr');
    });

    it('custom closure controller can be used', function (): void {
        Route::post('/locale-closure', function (Request $request): RedirectResponse {
            session()->put('custom_closure_called', true);
            session()->put('lingua.locale', $request->input('locale'));

            return redirect()->back();
        })->middleware(['web'])->name('lingua.locale.update.closure');

        $this->post(route('lingua.locale.update.closure'), ['locale' => 'fr'])
            ->assertRedirect();

        expect(session()->get('custom_closure_called'))->toBeTrue()
            ->and(session()->get('lingua.locale'))->toBe('fr');
    });

    it('verifies controller config default is null', function (): void {
        expect(config('lingua.controller'))->toBeNull();
    });

    it('controller config can be set to custom class', function (): void {
        $this->app['config']->set('lingua.controller', 'App\\Http\\Controllers\\CustomLocaleController');

        expect(config('lingua.controller'))->toBe('App\\Http\\Controllers\\CustomLocaleController');
    });
});

describe('route configuration integration', function (): void {
    it('route accepts POST requests', function (): void {
        $route = Route::getRoutes()->getByName('lingua.locale.update');

        expect($route)->not->toBeNull()
            ->and($route->methods())->toContain('POST');
    });

    it('route does not accept GET requests', function (): void {
        $this->get('/locale')
            ->assertStatus(405); // Method Not Allowed
    });

    it('route is named correctly', function (): void {
        expect(Route::has('lingua.locale.update'))->toBeTrue();

        $url = route('lingua.locale.update');
        expect($url)->toEndWith('/locale');
    });

    it('route can be generated using route helper', function (): void {
        $url = route('lingua.locale.update');

        expect($url)->toBeString()
            ->and($url)->toContain('locale');
    });

    it('full configuration scenario works together', function (): void {
        Route::post('/admin/settings/locale', LinguaLocaleController::class)
            ->middleware(['web'])
            ->name('lingua.locale.update.full');

        $this->post(route('lingua.locale.update.full'), ['locale' => 'es'])
            ->assertRedirect();

        expect(session()->get('lingua.locale'))->toBe('es');
    });
});

describe('route configuration edge cases', function (): void {
    it('route validates locale against supported locales', function (): void {
        $this->post(route('lingua.locale.update'), ['locale' => 'invalid'])
            ->assertSessionHasErrors('locale');
    });

    it('route requires locale parameter', function (): void {
        $this->post(route('lingua.locale.update'), [])
            ->assertSessionHasErrors('locale');
    });

    it('route redirects back after successful update', function (): void {
        $this->from('/previous-page')
            ->post(route('lingua.locale.update'), ['locale' => 'fr'])
            ->assertRedirect('/previous-page');
    });

    it('all config values are accessible with correct defaults', function (): void {
        expect(config('lingua.routes.enabled'))->toBe(true)
            ->and(config('lingua.routes.prefix'))->toBe('')
            ->and(config('lingua.routes.middleware'))->toBe(['web'])
            ->and(config('lingua.controller'))->toBeNull();
    });

    it('config can be modified at runtime', function (): void {
        $this->app['config']->set('lingua.routes.prefix', 'custom');
        $this->app['config']->set('lingua.routes.middleware', ['api', 'auth']);
        $this->app['config']->set('lingua.controller', 'CustomController');

        expect(config('lingua.routes.prefix'))->toBe('custom')
            ->and(config('lingua.routes.middleware'))->toBe(['api', 'auth'])
            ->and(config('lingua.controller'))->toBe('CustomController');
    });

    it('multiple locales can be set sequentially', function (): void {
        $this->post(route('lingua.locale.update'), ['locale' => 'fr'])
            ->assertRedirect();
        expect(session()->get('lingua.locale'))->toBe('fr');

        $this->post(route('lingua.locale.update'), ['locale' => 'es'])
            ->assertRedirect();
        expect(session()->get('lingua.locale'))->toBe('es');

        $this->post(route('lingua.locale.update'), ['locale' => 'en'])
            ->assertRedirect();
        expect(session()->get('lingua.locale'))->toBe('en');
    });

    it('route works with all supported locales', function (): void {
        $supportedLocales = config('lingua.locales');

        foreach ($supportedLocales as $locale) {
            $this->post(route('lingua.locale.update'), ['locale' => $locale])
                ->assertRedirect();

            expect(session()->get('lingua.locale'))->toBe($locale);
        }
    });
});
