<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
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
        $this->lingua->setLocale('fr');

        expect(session()->get('lingua.locale'))->toBe('fr');
    });

    it('sets app locale', function (): void {
        $this->lingua->setLocale('de');

        expect(app()->getLocale())->toBe('de');
    });

    it('uses custom session key from config', function (): void {
        config(['lingua.session_key' => 'my.custom.key']);

        $this->lingua->setLocale('it');

        expect(session()->get('my.custom.key'))->toBe('it');
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
});
