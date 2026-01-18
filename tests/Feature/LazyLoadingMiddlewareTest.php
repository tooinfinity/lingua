<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use TooInfinity\Lingua\Http\Middleware\LinguaMiddleware;

beforeEach(function (): void {
    // Set up test translation files
    $langPath = lang_path('en');
    File::ensureDirectoryExists($langPath);

    File::put($langPath.'/common.php', '<?php return ["app_name" => "Test App"];');
    File::put($langPath.'/auth.php', '<?php return ["login" => "Login"];');
    File::put($langPath.'/dashboard.php', '<?php return ["title" => "Dashboard"];');
    File::put($langPath.'/validation.php', '<?php return ["required" => "Required"];');

    // Set up test routes
    Route::middleware(['web', LinguaMiddleware::class])->get('/test-all', function () {
        $shared = Inertia::getShared();
        $linguaData = isset($shared['lingua']) && is_callable($shared['lingua'])
            ? $shared['lingua']()
            : ($shared['lingua'] ?? null);

        return response()->json(['lingua' => $linguaData]);
    });
});

afterEach(function (): void {
    File::deleteDirectory(lang_path());
});

describe('middleware without lazy loading', function (): void {
    it('loads all translations when lazy loading is disabled', function (): void {
        config(['lingua.lazy_loading.enabled' => false]);

        $response = $this->get('/test-all');

        $response->assertOk();

        $translations = $response->json('lingua.translations');

        expect($translations)->toHaveKey('common')
            ->toHaveKey('auth')
            ->toHaveKey('dashboard')
            ->toHaveKey('validation');
    });
});

describe('middleware with lazy loading enabled', function (): void {
    it('loads only default groups when no route groups specified', function (): void {
        config([
            'lingua.lazy_loading.enabled' => true,
            'lingua.lazy_loading.default_groups' => ['common'],
        ]);

        $response = $this->get('/test-all');

        $response->assertOk();

        $translations = $response->json('lingua.translations');

        expect($translations)->toHaveKey('common')
            ->not->toHaveKey('auth')
            ->not->toHaveKey('dashboard')
            ->not->toHaveKey('validation');
    });

    it('loads route-specific groups via middleware parameters', function (): void {
        config([
            'lingua.lazy_loading.enabled' => true,
            'lingua.lazy_loading.default_groups' => ['common'],
        ]);

        // Register route with specific groups
        Route::middleware(['web', LinguaMiddleware::class.':dashboard,auth'])->get('/test-specific', function () {
            $shared = Inertia::getShared();
            $linguaData = isset($shared['lingua']) && is_callable($shared['lingua'])
                ? $shared['lingua']()
                : ($shared['lingua'] ?? null);

            return response()->json(['lingua' => $linguaData]);
        });

        $response = $this->get('/test-specific');

        $response->assertOk();

        $translations = $response->json('lingua.translations');

        // Should have default + route-specific groups
        expect($translations)->toHaveKey('common')
            ->toHaveKey('auth')
            ->toHaveKey('dashboard')
            ->not->toHaveKey('validation');
    });

    it('merges default groups with route groups without duplicates', function (): void {
        config([
            'lingua.lazy_loading.enabled' => true,
            'lingua.lazy_loading.default_groups' => ['common', 'auth'],
        ]);

        // Route specifies 'auth' again - should not duplicate
        Route::middleware(['web', LinguaMiddleware::class.':auth,dashboard'])->get('/test-merge', function () {
            $shared = Inertia::getShared();
            $linguaData = isset($shared['lingua']) && is_callable($shared['lingua'])
                ? $shared['lingua']()
                : ($shared['lingua'] ?? null);

            return response()->json(['lingua' => $linguaData]);
        });

        $response = $this->get('/test-merge');

        $response->assertOk();

        $translations = $response->json('lingua.translations');

        expect($translations)->toHaveKey('common')
            ->toHaveKey('auth')
            ->toHaveKey('dashboard')
            ->not->toHaveKey('validation');

        // Verify auth is only included once (as a key)
        expect(array_keys($translations))->toBe(['common', 'auth', 'dashboard']);
    });

    it('uses lingua middleware alias with parameters', function (): void {
        config([
            'lingua.lazy_loading.enabled' => true,
            'lingua.lazy_loading.default_groups' => [],
        ]);

        Route::middleware(['web', 'lingua:validation'])->get('/test-alias', function () {
            $shared = Inertia::getShared();
            $linguaData = isset($shared['lingua']) && is_callable($shared['lingua'])
                ? $shared['lingua']()
                : ($shared['lingua'] ?? null);

            return response()->json(['lingua' => $linguaData]);
        });

        $response = $this->get('/test-alias');

        $response->assertOk();

        $translations = $response->json('lingua.translations');

        expect($translations)->toHaveKey('validation')
            ->not->toHaveKey('common')
            ->not->toHaveKey('auth')
            ->not->toHaveKey('dashboard');
    });
});

describe('middleware still shares all lingua data', function (): void {
    it('shares locale, locales, direction, and isRtl with lazy loading', function (): void {
        config([
            'lingua.lazy_loading.enabled' => true,
            'lingua.lazy_loading.default_groups' => ['common'],
        ]);

        $response = $this->get('/test-all');

        $response->assertOk();

        $linguaData = $response->json('lingua');

        expect($linguaData)->toHaveKey('locale')
            ->toHaveKey('locales')
            ->toHaveKey('translations')
            ->toHaveKey('direction')
            ->toHaveKey('isRtl');

        expect($linguaData['locale'])->toBe('en');
        expect($linguaData['direction'])->toBe('ltr');
        expect($linguaData['isRtl'])->toBeFalse();
    });
});
