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

describe('middleware translations', function (): void {
    it('loads all translations by default', function (): void {
        $response = $this->get('/test-all');

        $response->assertOk();

        $translations = $response->json('lingua.translations');

        expect($translations)->toHaveKey('common')
            ->toHaveKey('auth')
            ->toHaveKey('dashboard')
            ->toHaveKey('validation');
    });

    it('loads route-specific groups via middleware parameters', function (): void {
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

        expect($translations)->toHaveKey('auth')
            ->toHaveKey('dashboard')
            ->not->toHaveKey('common')
            ->not->toHaveKey('validation');
    });

    it('uses lingua middleware alias with parameters', function (): void {
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
    it('shares locale, locales, direction, and isRtl', function (): void {
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
