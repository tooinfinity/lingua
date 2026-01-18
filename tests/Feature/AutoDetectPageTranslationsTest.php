<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use TooInfinity\Lingua\Http\Middleware\LinguaMiddleware;
use TooInfinity\Lingua\Lingua;

beforeEach(function (): void {
    // Set up test translation files
    $langPath = lang_path('en');
    File::ensureDirectoryExists($langPath);

    File::put($langPath.'/common.php', '<?php return ["app_name" => "Test App"];');
    File::put($langPath.'/auth.php', '<?php return ["login" => "Login"];');
    File::put($langPath.'/dashboard.php', '<?php return ["title" => "Dashboard Title"];');
    File::put($langPath.'/users.php', '<?php return ["list" => "User List", "create" => "Create User"];');
    File::put($langPath.'/settings.php', '<?php return ["profile" => "Profile Settings"];');
    File::put($langPath.'/admin-users.php', '<?php return ["manage" => "Manage Users"];');
    File::put($langPath.'/validation.php', '<?php return ["required" => "Required"];');
});

afterEach(function (): void {
    File::deleteDirectory(lang_path());

    // Clear translation cache
    app(Lingua::class)->clearTranslationCache();
});

describe('auto-detect page translations', function (): void {
    it('automatically loads translations based on Inertia page name', function (): void {
        config([
            'lingua.lazy_loading.enabled' => true,
            'lingua.lazy_loading.auto_detect_page' => true,
            'lingua.lazy_loading.default_groups' => ['common'],
        ]);

        // Simulate an Inertia JSON response (XHR request)
        Route::middleware(['web', LinguaMiddleware::class])->get('/test-dashboard',
            // Return a mock Inertia-like JSON response
            fn () => response()->json([
                'component' => 'Pages/Dashboard',
                'props' => [
                    'lingua' => [
                        'locale' => 'en',
                        'locales' => ['en'],
                        'translations' => [], // Initially empty, middleware should fill
                        'direction' => 'ltr',
                        'isRtl' => false,
                    ],
                ],
                'url' => '/test-dashboard',
                'version' => '1.0',
            ])->header('X-Inertia', 'true'));

        $response = $this->get('/test-dashboard', ['X-Inertia' => 'true']);

        $response->assertOk();

        $data = $response->json();

        // Should have auto-detected 'dashboard' from 'Pages/Dashboard' and merged with default 'common'
        expect($data['props']['lingua']['translations'])->toHaveKey('common')
            ->toHaveKey('dashboard');
    });

    it('loads translations for Users page from nested path', function (): void {
        config([
            'lingua.lazy_loading.enabled' => true,
            'lingua.lazy_loading.auto_detect_page' => true,
            'lingua.lazy_loading.default_groups' => ['common'],
        ]);

        Route::middleware(['web', LinguaMiddleware::class])->get('/test-users', fn () => response()->json([
            'component' => 'Pages/Users/Index',
            'props' => [
                'lingua' => [
                    'locale' => 'en',
                    'locales' => ['en'],
                    'translations' => [],
                    'direction' => 'ltr',
                    'isRtl' => false,
                ],
            ],
            'url' => '/test-users',
            'version' => '1.0',
        ])->header('X-Inertia', 'true'));

        $response = $this->get('/test-users', ['X-Inertia' => 'true']);

        $response->assertOk();

        $data = $response->json();

        // Should have auto-detected 'users' from 'Pages/Users/Index'
        expect($data['props']['lingua']['translations'])->toHaveKey('common')
            ->toHaveKey('users');

        expect($data['props']['lingua']['translations']['users'])->toBe([
            'list' => 'User List',
            'create' => 'Create User',
        ]);
    });

    it('does not auto-detect when middleware groups are explicitly specified', function (): void {
        config([
            'lingua.lazy_loading.enabled' => true,
            'lingua.lazy_loading.auto_detect_page' => true,
            'lingua.lazy_loading.default_groups' => ['common'],
        ]);

        // Use explicit middleware parameter
        Route::middleware(['web', LinguaMiddleware::class.':auth'])->get('/test-explicit', function () {
            $shared = Inertia::getShared();
            $linguaData = isset($shared['lingua']) && is_callable($shared['lingua'])
                ? $shared['lingua']()
                : ($shared['lingua'] ?? null);

            return response()->json(['lingua' => $linguaData]);
        });

        $response = $this->get('/test-explicit');

        $response->assertOk();

        $translations = $response->json('lingua.translations');

        // Should only have common + auth (explicitly specified), not dashboard
        expect($translations)->toHaveKey('common')
            ->toHaveKey('auth')
            ->not->toHaveKey('dashboard');
    });

    it('does not auto-detect when auto_detect_page is disabled', function (): void {
        config([
            'lingua.lazy_loading.enabled' => true,
            'lingua.lazy_loading.auto_detect_page' => false,
            'lingua.lazy_loading.default_groups' => ['common'],
        ]);

        // When auto-detect is disabled, we need to test via the shared data
        // The mock JSON response won't be modified by the middleware
        Route::middleware(['web', LinguaMiddleware::class])->get('/test-disabled', function () {
            $shared = Inertia::getShared();
            $linguaData = isset($shared['lingua']) && is_callable($shared['lingua'])
                ? $shared['lingua']()
                : ($shared['lingua'] ?? null);

            return response()->json(['lingua' => $linguaData]);
        });

        $response = $this->get('/test-disabled');

        $response->assertOk();

        $translations = $response->json('lingua.translations');

        // Should only have default groups, not auto-detected (even though page could be detected)
        expect($translations)->toHaveKey('common')
            ->not->toHaveKey('dashboard');
    });

    it('does not auto-detect when lazy loading is disabled', function (): void {
        config([
            'lingua.lazy_loading.enabled' => false,
            'lingua.lazy_loading.auto_detect_page' => true,
        ]);

        Route::middleware(['web', LinguaMiddleware::class])->get('/test-no-lazy', function () {
            $shared = Inertia::getShared();
            $linguaData = isset($shared['lingua']) && is_callable($shared['lingua'])
                ? $shared['lingua']()
                : ($shared['lingua'] ?? null);

            return response()->json(['lingua' => $linguaData]);
        });

        $response = $this->get('/test-no-lazy');

        $response->assertOk();

        $translations = $response->json('lingua.translations');

        // Should load ALL translations when lazy loading is disabled
        expect($translations)->toHaveKey('common')
            ->toHaveKey('auth')
            ->toHaveKey('dashboard')
            ->toHaveKey('users')
            ->toHaveKey('validation');
    });
});

describe('isAutoDetectPageEnabled', function (): void {
    it('returns true by default', function (): void {
        config(['lingua.lazy_loading.auto_detect_page' => true]);

        $lingua = app(Lingua::class);

        expect($lingua->isAutoDetectPageEnabled())->toBeTrue();
    });

    it('returns false when disabled', function (): void {
        config(['lingua.lazy_loading.auto_detect_page' => false]);

        $lingua = app(Lingua::class);

        expect($lingua->isAutoDetectPageEnabled())->toBeFalse();
    });
});

describe('translationsForPage', function (): void {
    it('returns translations for page merged with defaults', function (): void {
        config([
            'lingua.lazy_loading.enabled' => true,
            'lingua.lazy_loading.default_groups' => ['common'],
        ]);

        $lingua = app(Lingua::class);

        $translations = $lingua->translationsForPage('Pages/Users/Index');

        expect($translations)->toHaveKey('common')
            ->toHaveKey('users');

        expect($translations['users'])->toBe([
            'list' => 'User List',
            'create' => 'Create User',
        ]);
    });

    it('works with simple page name', function (): void {
        config([
            'lingua.lazy_loading.enabled' => true,
            'lingua.lazy_loading.default_groups' => [],
        ]);

        $lingua = app(Lingua::class);

        $translations = $lingua->translationsForPage('Dashboard');

        expect($translations)->toHaveKey('dashboard');
        expect($translations['dashboard'])->toBe(['title' => 'Dashboard Title']);
    });

    it('returns only defaults when page has no matching translation file', function (): void {
        config([
            'lingua.lazy_loading.enabled' => true,
            'lingua.lazy_loading.default_groups' => ['common'],
        ]);

        $lingua = app(Lingua::class);

        // NonExistent page won't have a matching translation file
        $translations = $lingua->translationsForPage('Pages/NonExistent/Index');

        expect($translations)->toHaveKey('common')
            ->not->toHaveKey('non-existent');
    });
});

describe('getGroupsForPage', function (): void {
    it('returns resolved groups for a page', function (): void {
        $lingua = app(Lingua::class);

        $groups = $lingua->getGroupsForPage('Pages/Users/Index');

        expect($groups)->toBe(['users']);
    });

    it('returns groups for admin pages', function (): void {
        $lingua = app(Lingua::class);

        $groups = $lingua->getGroupsForPage('Admin/Users/Index');

        expect($groups)->toBe(['admin-users']);
    });

    it('returns empty array for empty page name', function (): void {
        $lingua = app(Lingua::class);

        $groups = $lingua->getGroupsForPage('');

        expect($groups)->toBe([]);
    });
});

describe('HTML response auto-detection', function (): void {
    it('detects page from HTML data-page attribute', function (): void {
        config([
            'lingua.lazy_loading.enabled' => true,
            'lingua.lazy_loading.auto_detect_page' => true,
            'lingua.lazy_loading.default_groups' => ['common'],
        ]);

        Route::middleware(['web', LinguaMiddleware::class])->get('/test-html', function () {
            $pageData = json_encode([
                'component' => 'Pages/Dashboard',
                'props' => [
                    'lingua' => [
                        'locale' => 'en',
                        'locales' => ['en'],
                        'translations' => [],
                        'direction' => 'ltr',
                        'isRtl' => false,
                    ],
                ],
                'url' => '/test-html',
                'version' => '1.0',
            ]);

            $encodedPageData = htmlspecialchars($pageData, ENT_QUOTES, 'UTF-8');

            return response('<html><body><div id="app" data-page="'.$encodedPageData.'"></div></body></html>')
                ->header('Content-Type', 'text/html');
        });

        $response = $this->get('/test-html');

        $response->assertOk();

        $content = $response->getContent();

        // Extract the data-page content and verify translations were added
        preg_match('/data-page="(.+?)"/s', $content, $matches);
        $pageData = html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
        $decoded = json_decode($pageData, true);

        expect($decoded['props']['lingua']['translations'])->toHaveKey('common')
            ->toHaveKey('dashboard');
    });
});

describe('backward compatibility', function (): void {
    it('still works with manual middleware parameters', function (): void {
        config([
            'lingua.lazy_loading.enabled' => true,
            'lingua.lazy_loading.auto_detect_page' => true,
            'lingua.lazy_loading.default_groups' => ['common'],
        ]);

        Route::middleware(['web', 'lingua:validation,auth'])->get('/test-manual', function () {
            $shared = Inertia::getShared();
            $linguaData = isset($shared['lingua']) && is_callable($shared['lingua'])
                ? $shared['lingua']()
                : ($shared['lingua'] ?? null);

            return response()->json(['lingua' => $linguaData]);
        });

        $response = $this->get('/test-manual');

        $response->assertOk();

        $translations = $response->json('lingua.translations');

        expect($translations)->toHaveKey('common')
            ->toHaveKey('validation')
            ->toHaveKey('auth')
            ->not->toHaveKey('dashboard')
            ->not->toHaveKey('users');
    });

    it('existing tests behavior is preserved when lazy loading disabled', function (): void {
        config(['lingua.lazy_loading.enabled' => false]);

        Route::middleware(['web', LinguaMiddleware::class])->get('/test-all-compat', function () {
            $shared = Inertia::getShared();
            $linguaData = isset($shared['lingua']) && is_callable($shared['lingua'])
                ? $shared['lingua']()
                : ($shared['lingua'] ?? null);

            return response()->json(['lingua' => $linguaData]);
        });

        $response = $this->get('/test-all-compat');

        $response->assertOk();

        $translations = $response->json('lingua.translations');

        // All translations should be loaded
        expect($translations)->toHaveKey('common')
            ->toHaveKey('auth')
            ->toHaveKey('dashboard')
            ->toHaveKey('users')
            ->toHaveKey('settings')
            ->toHaveKey('validation');
    });
});
