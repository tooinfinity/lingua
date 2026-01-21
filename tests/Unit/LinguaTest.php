<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use TooInfinity\Lingua\Exceptions\UnsupportedLocaleException;
use TooInfinity\Lingua\Lingua;

beforeEach(function (): void {
    $this->lingua = app(Lingua::class);
});

describe('getLocale', function (): void {
    it('returns default locale when session is empty', function (): void {
        expect($this->lingua->getLocale())->toBe('en');
    });

    it('returns locale from session when set', function (): void {
        session()->put('lingua.locale', 'fr');

        expect($this->lingua->getLocale())->toBe('fr');
    });

    it('uses custom session key from config', function (): void {
        config(['lingua.session_key' => 'custom.locale.key']);
        session()->put('custom.locale.key', 'de');

        expect($this->lingua->getLocale())->toBe('de');
    });

    it('uses lingua default config over app locale', function (): void {
        config(['lingua.default' => 'es']);
        config(['app.locale' => 'en']);

        expect($this->lingua->getLocale())->toBe('es');
    });

    it('falls back to app locale when lingua default is null', function (): void {
        config(['lingua.default' => null]);
        config(['app.locale' => 'pt']);

        expect($this->lingua->getLocale())->toBe('pt');
    });
});

describe('setLocale', function (): void {
    it('stores locale in session', function (): void {
        config(['lingua.locales' => ['en', 'fr', 'de']]);

        $this->lingua->setLocale('fr');

        expect(session()->get('lingua.locale'))->toBe('fr');
    });

    it('sets app locale', function (): void {
        config(['lingua.locales' => ['en', 'fr', 'de']]);

        $this->lingua->setLocale('de');

        expect(app()->getLocale())->toBe('de');
    });

    it('uses custom session key from config', function (): void {
        config(['lingua.session_key' => 'my.custom.key']);
        config(['lingua.locales' => ['en', 'it']]);

        $this->lingua->setLocale('it');

        expect(session()->get('my.custom.key'))->toBe('it');
    });

    it('throws exception for unsupported locale', function (): void {
        config(['lingua.locales' => ['en', 'fr']]);

        $this->lingua->setLocale('invalid');
    })->throws(UnsupportedLocaleException::class, 'Locale "invalid" is not supported');

    it('normalizes locale before storing', function (): void {
        config(['lingua.locales' => ['en_US', 'fr']]);

        $this->lingua->setLocale('en-us');

        expect(session()->get('lingua.locale'))->toBe('en_US');
        expect(app()->getLocale())->toBe('en_US');
    });

    it('normalizes hyphenated locale to underscore format', function (): void {
        config(['lingua.locales' => ['pt_BR']]);

        $this->lingua->setLocale('pt-BR');

        expect(session()->get('lingua.locale'))->toBe('pt_BR');
    });

    it('normalizes case for locale with region', function (): void {
        config(['lingua.locales' => ['en_US']]);

        $this->lingua->setLocale('EN-US');

        expect(session()->get('lingua.locale'))->toBe('en_US');
    });

    it('trims whitespace from locale', function (): void {
        config(['lingua.locales' => ['fr']]);

        $this->lingua->setLocale('  fr  ');

        expect(session()->get('lingua.locale'))->toBe('fr');
    });
});

describe('normalizeLocale', function (): void {
    it('converts hyphens to underscores', function (): void {
        expect($this->lingua->normalizeLocale('en-US'))->toBe('en_US');
    });

    it('lowercases simple locale', function (): void {
        expect($this->lingua->normalizeLocale('FR'))->toBe('fr');
    });

    it('normalizes case for locale with region', function (): void {
        expect($this->lingua->normalizeLocale('EN-us'))->toBe('en_US');
        expect($this->lingua->normalizeLocale('pt-br'))->toBe('pt_BR');
    });

    it('trims whitespace', function (): void {
        expect($this->lingua->normalizeLocale('  en  '))->toBe('en');
    });

    it('handles already normalized locale', function (): void {
        expect($this->lingua->normalizeLocale('en_US'))->toBe('en_US');
        expect($this->lingua->normalizeLocale('fr'))->toBe('fr');
    });
});

describe('validateLocale', function (): void {
    it('passes for supported locale', function (): void {
        config(['lingua.locales' => ['en', 'fr', 'de']]);

        $this->lingua->validateLocale('fr');

        expect(true)->toBeTrue(); // No exception thrown
    });

    it('throws exception for unsupported locale', function (): void {
        config(['lingua.locales' => ['en', 'fr']]);

        $this->lingua->validateLocale('invalid');
    })->throws(UnsupportedLocaleException::class);

    it('includes supported locales in error message', function (): void {
        config(['lingua.locales' => ['en', 'fr']]);

        try {
            $this->lingua->validateLocale('de');
        } catch (UnsupportedLocaleException $unsupportedLocaleException) {
            expect($unsupportedLocaleException->getMessage())->toContain('en, fr');
            expect($unsupportedLocaleException->getLocale())->toBe('de');
            expect($unsupportedLocaleException->getSupportedLocales())->toBe(['en', 'fr']);
        }
    });

    it('matches normalized versions of supported locales', function (): void {
        config(['lingua.locales' => ['en-US', 'pt-BR']]);

        // Should not throw - normalized versions should match
        $this->lingua->validateLocale('en_US');

        expect(true)->toBeTrue();
    });
});

describe('isLocaleSupported', function (): void {
    it('returns true for supported locale', function (): void {
        config(['lingua.locales' => ['en', 'fr', 'de']]);

        expect($this->lingua->isLocaleSupported('fr'))->toBeTrue();
    });

    it('returns false for unsupported locale', function (): void {
        config(['lingua.locales' => ['en', 'fr']]);

        expect($this->lingua->isLocaleSupported('invalid'))->toBeFalse();
    });

    it('handles locale variations', function (): void {
        config(['lingua.locales' => ['en_US']]);

        expect($this->lingua->isLocaleSupported('en-US'))->toBeTrue();
        expect($this->lingua->isLocaleSupported('EN-us'))->toBeTrue();
    });
});

describe('supportedLocales', function (): void {
    it('returns configured locales', function (): void {
        config(['lingua.locales' => ['en', 'fr', 'de']]);

        expect($this->lingua->supportedLocales())->toBe(['en', 'fr', 'de']);
    });

    it('returns single locale array', function (): void {
        config(['lingua.locales' => ['es']]);

        expect($this->lingua->supportedLocales())->toBe(['es']);
    });

    it('returns empty array when configured as empty', function (): void {
        config(['lingua.locales' => []]);

        expect($this->lingua->supportedLocales())->toBe([]);
    });
});

describe('translations', function (): void {
    it('returns empty array when lang directory does not exist', function (): void {
        expect($this->lingua->translations())->toBe([]);
    });

    it('loads php translation files for current locale', function (): void {
        $langPath = lang_path('en');
        File::ensureDirectoryExists($langPath);
        File::put($langPath.'/messages.php', '<?php return ["welcome" => "Welcome!"];');

        $translations = $this->lingua->translations();

        expect($translations)->toHaveKey('messages')
            ->and($translations['messages'])->toBe(['welcome' => 'Welcome!']);

        // Cleanup
        File::deleteDirectory(lang_path());
    });

    it('loads multiple translation files', function (): void {
        $langPath = lang_path('en');
        File::ensureDirectoryExists($langPath);
        File::put($langPath.'/auth.php', '<?php return ["failed" => "Invalid credentials"];');
        File::put($langPath.'/validation.php', '<?php return ["required" => "This field is required"];');

        $translations = $this->lingua->translations();

        expect($translations)
            ->toHaveKey('auth')
            ->toHaveKey('validation')
            ->and($translations['auth']['failed'])->toBe('Invalid credentials')
            ->and($translations['validation']['required'])->toBe('This field is required');

        // Cleanup
        File::deleteDirectory(lang_path());
    });

    it('ignores non-php files', function (): void {
        $langPath = lang_path('en');
        File::ensureDirectoryExists($langPath);
        File::put($langPath.'/messages.php', '<?php return ["hello" => "Hello"];');
        File::put($langPath.'/readme.txt', 'This is a readme');
        File::put($langPath.'/data.json', '{"key": "value"}');

        $translations = $this->lingua->translations();

        expect($translations)
            ->toHaveKey('messages')
            ->not->toHaveKey('readme')
            ->not->toHaveKey('data');

        // Cleanup
        File::deleteDirectory(lang_path());
    });

    it('loads translations for the current locale', function (): void {
        // Setup French translations
        $frPath = lang_path('fr');
        File::ensureDirectoryExists($frPath);
        File::put($frPath.'/messages.php', '<?php return ["welcome" => "Bienvenue!"];');

        // Set locale to French
        $this->lingua->setLocale('fr');

        $translations = $this->lingua->translations();

        expect($translations['messages']['welcome'])->toBe('Bienvenue!');

        // Cleanup
        File::deleteDirectory(lang_path());
    });

    it('loads json translations when driver is set to json', function (): void {
        config(['lingua.translation_driver' => 'json']);

        $langPath = lang_path();
        File::ensureDirectoryExists($langPath);
        File::put($langPath.'/en.json', json_encode([
            'Welcome' => 'Welcome!',
            'Hello' => 'Hello, World!',
        ]));

        $translations = $this->lingua->translations();

        expect($translations)
            ->toHaveKey('Welcome')
            ->toHaveKey('Hello')
            ->and($translations['Welcome'])->toBe('Welcome!')
            ->and($translations['Hello'])->toBe('Hello, World!');

        // Cleanup
        File::deleteDirectory(lang_path());
    });

    it('returns empty array when json file does not exist', function (): void {
        config(['lingua.translation_driver' => 'json']);

        $translations = $this->lingua->translations();

        expect($translations)->toBe([]);
    });

    it('loads json translations for the current locale', function (): void {
        config(['lingua.translation_driver' => 'json']);

        $langPath = lang_path();
        File::ensureDirectoryExists($langPath);
        File::put($langPath.'/fr.json', json_encode([
            'Welcome' => 'Bienvenue!',
            'Goodbye' => 'Au revoir!',
        ]));

        // Set locale to French
        $this->lingua->setLocale('fr');

        $translations = $this->lingua->translations();

        expect($translations['Welcome'])->toBe('Bienvenue!')
            ->and($translations['Goodbye'])->toBe('Au revoir!');

        // Cleanup
        File::deleteDirectory(lang_path());
    });

    it('returns empty array for invalid json content', function (): void {
        config(['lingua.translation_driver' => 'json']);

        $langPath = lang_path();
        File::ensureDirectoryExists($langPath);
        File::put($langPath.'/en.json', 'invalid json content');

        $translations = $this->lingua->translations();

        expect($translations)->toBe([]);

        // Cleanup
        File::deleteDirectory(lang_path());
    });

    it('defaults to php driver when translation_driver is not set', function (): void {
        // Ensure the config key doesn't exist
        config(['lingua.translation_driver' => null]);

        $langPath = lang_path('en');
        File::ensureDirectoryExists($langPath);
        File::put($langPath.'/messages.php', '<?php return ["test" => "PHP Driver Works"];');

        $translations = $this->lingua->translations();

        expect($translations)->toHaveKey('messages')
            ->and($translations['messages']['test'])->toBe('PHP Driver Works');

        // Cleanup
        File::deleteDirectory(lang_path());
    });

    it('uses php driver for unknown driver values', function (): void {
        config(['lingua.translation_driver' => 'unknown']);

        $langPath = lang_path('en');
        File::ensureDirectoryExists($langPath);
        File::put($langPath.'/messages.php', '<?php return ["test" => "Fallback Works"];');

        $translations = $this->lingua->translations();

        expect($translations)->toHaveKey('messages')
            ->and($translations['messages']['test'])->toBe('Fallback Works');

        // Cleanup
        File::deleteDirectory(lang_path());
    });
});

describe('getDirection', function (): void {
    it('returns rtl for Arabic locale', function (): void {
        config(['lingua.locales' => ['en', 'ar']]);
        $this->lingua->setLocale('ar');

        expect($this->lingua->getDirection())->toBe('rtl');
    });

    it('returns ltr for English locale', function (): void {
        config(['lingua.locales' => ['en', 'ar']]);
        $this->lingua->setLocale('en');

        expect($this->lingua->getDirection())->toBe('ltr');
    });

    it('returns rtl for specified RTL locale parameter', function (): void {
        expect($this->lingua->getDirection('ar'))->toBe('rtl');
        expect($this->lingua->getDirection('he'))->toBe('rtl');
        expect($this->lingua->getDirection('fa'))->toBe('rtl');
    });

    it('returns ltr for specified LTR locale parameter', function (): void {
        expect($this->lingua->getDirection('en'))->toBe('ltr');
        expect($this->lingua->getDirection('fr'))->toBe('ltr');
        expect($this->lingua->getDirection('de'))->toBe('ltr');
    });

    it('returns rtl for locale with region code when base is RTL', function (): void {
        expect($this->lingua->getDirection('ar_SA'))->toBe('rtl');
        expect($this->lingua->getDirection('he_IL'))->toBe('rtl');
    });

    it('returns ltr for locale with region code when base is LTR', function (): void {
        expect($this->lingua->getDirection('en_US'))->toBe('ltr');
        expect($this->lingua->getDirection('pt_BR'))->toBe('ltr');
    });
});

describe('localizedUrl', function (): void {
    it('generates localized URL with prefix strategy', function (): void {
        config([
            'lingua.url.strategy' => 'prefix',
            'lingua.url.prefix.segment' => 1,
        ]);

        $url = $this->lingua->localizedUrl('/dashboard', 'fr');

        expect($url)->toBe('/fr/dashboard');
    });

    it('generates localized URL with domain strategy', function (): void {
        config([
            'lingua.url.strategy' => 'domain',
            'lingua.url.domain.hosts' => [
                'en' => 'example.com',
                'fr' => 'fr.example.com',
            ],
        ]);

        $url = $this->lingua->localizedUrl('https://example.com/dashboard', 'fr');

        expect($url)->toBe('https://fr.example.com/dashboard');
    });

    it('uses current locale when locale parameter is null', function (): void {
        config([
            'lingua.url.strategy' => 'prefix',
            'lingua.url.prefix.segment' => 1,
            'lingua.locales' => ['en', 'fr'],
        ]);
        $this->lingua->setLocale('fr');

        $url = $this->lingua->localizedUrl('/dashboard');

        expect($url)->toBe('/fr/dashboard');
    });
});

describe('localizedRoute', function (): void {
    it('generates localized route URL with prefix strategy', function (): void {
        config([
            'lingua.url.strategy' => 'prefix',
            'lingua.url.prefix.segment' => 1,
        ]);

        $routeName = 'lingua.dashboard.'.uniqid();
        Illuminate\Support\Facades\Route::get('/{locale}/dashboard', fn (): string => 'dashboard')->name($routeName);
        Illuminate\Support\Facades\Route::getRoutes()->refreshNameLookups();

        $url = $this->lingua->localizedRoute($routeName, [], 'fr');

        expect($url)->toContain('/fr/dashboard');
    });

    it('generates localized route URL with domain strategy', function (): void {
        config([
            'lingua.url.strategy' => 'domain',
            'lingua.url.domain.hosts' => [
                'en' => 'example.com',
                'fr' => 'fr.example.com',
            ],
        ]);

        $routeName = 'lingua.dashboard.domain.'.uniqid();
        Illuminate\Support\Facades\Route::get('/dashboard', fn (): string => 'dashboard')->name($routeName);
        Illuminate\Support\Facades\Route::getRoutes()->refreshNameLookups();

        $url = $this->lingua->localizedRoute($routeName, [], 'fr');

        expect($url)->toContain('fr.example.com');
    });

    it('passes additional parameters to route', function (): void {
        config([
            'lingua.url.strategy' => 'prefix',
            'lingua.url.prefix.segment' => 1,
        ]);

        $routeName = 'lingua.users.show.'.uniqid();
        Illuminate\Support\Facades\Route::get('/{locale}/users/{id}', fn (): string => 'user')->name($routeName);
        Illuminate\Support\Facades\Route::getRoutes()->refreshNameLookups();

        $url = $this->lingua->localizedRoute($routeName, ['id' => 123], 'fr');

        expect($url)->toContain('/fr/users/123');
    });

    it('generates relative URL when absolute is false', function (): void {
        config([
            'lingua.url.strategy' => 'prefix',
            'lingua.url.prefix.segment' => 1,
        ]);

        $routeName = 'lingua.profile.'.uniqid();
        Illuminate\Support\Facades\Route::get('/{locale}/profile', fn (): string => 'profile')->name($routeName);
        Illuminate\Support\Facades\Route::getRoutes()->refreshNameLookups();

        $url = $this->lingua->localizedRoute($routeName, [], 'de', false);

        expect($url)->toBe('/de/profile');
    });
});

describe('switchLocaleUrl', function (): void {
    it('generates URL to switch locale with prefix strategy', function (): void {
        config([
            'lingua.url.strategy' => 'prefix',
            'lingua.url.prefix.segment' => 1,
        ]);

        $request = Illuminate\Http\Request::create('https://example.com/en/dashboard');

        $url = $this->lingua->switchLocaleUrl('fr', $request);

        expect($url)->toBe('https://example.com/fr/dashboard');
    });

    it('generates URL to switch locale with domain strategy', function (): void {
        config([
            'lingua.url.strategy' => 'domain',
            'lingua.url.domain.hosts' => [
                'en' => 'example.com',
                'fr' => 'fr.example.com',
            ],
        ]);

        $request = Illuminate\Http\Request::create('https://example.com/dashboard');

        $url = $this->lingua->switchLocaleUrl('fr', $request);

        expect($url)->toBe('https://fr.example.com/dashboard');
    });

    it('preserves query string when switching locale', function (): void {
        config([
            'lingua.url.strategy' => 'prefix',
            'lingua.url.prefix.segment' => 1,
        ]);

        $request = Illuminate\Http\Request::create('https://example.com/en/dashboard?page=1&sort=name');

        $url = $this->lingua->switchLocaleUrl('fr', $request);

        expect($url)->toBe('https://example.com/fr/dashboard?page=1&sort=name');
    });
});

describe('Lingua Coverage Edge Cases', function (): void {
    it('uses persistent cache when available', function (): void {
        config(['lingua.lazy_loading.cache.enabled' => true]);

        // Use Laravel's real cache with array driver for testing
        config(['cache.default' => 'array']);

        // Pre-populate the cache with test data
        $cacheKey = 'lingua_translations.en.test-group';
        cache()->put($cacheKey, ['foo' => 'bar'], 3600);

        $lingua = new Lingua($this->app);

        $translations = $lingua->translationGroup('test-group');

        expect($translations)->toBe(['foo' => 'bar']);
    });

    it('returns empty array when php translation file returns non-array', function (): void {
        $langPath = lang_path('en');
        if (! File::exists($langPath)) {
            File::makeDirectory($langPath, 0755, true);
        }

        File::put($langPath.'/bad.php', '<?php return "not an array";');

        $lingua = new Lingua($this->app);

        // Ensure cache doesn't interfere
        $lingua->clearTranslationCache();

        $translations = $lingua->translationGroup('bad');

        expect($translations)->toBe([]);

        File::delete($langPath.'/bad.php');
    });
});
