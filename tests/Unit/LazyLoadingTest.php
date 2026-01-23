<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use TooInfinity\Lingua\Lingua;

beforeEach(function (): void {
    $this->lingua = app(Lingua::class);

    // Set up test translation files
    $this->langPath = lang_path('en');
    File::ensureDirectoryExists($this->langPath);

    File::put($this->langPath.'/common.php', '<?php return ["app_name" => "Test App", "welcome" => "Welcome"];');
    File::put($this->langPath.'/auth.php', '<?php return ["login" => "Login", "logout" => "Logout"];');
    File::put($this->langPath.'/validation.php', '<?php return ["required" => "Required field", "email" => "Invalid email"];');
    File::put($this->langPath.'/dashboard.php', '<?php return ["title" => "Dashboard", "stats" => "Statistics"];');
});

afterEach(function (): void {
    // Cleanup
    File::deleteDirectory(lang_path());

});

describe('availableGroups', function (): void {
    it('returns all available translation groups for current locale', function (): void {
        $groups = $this->lingua->availableGroups();

        expect($groups)->toBeArray()
            ->toContain('common')
            ->toContain('auth')
            ->toContain('validation')
            ->toContain('dashboard');
    });

    it('returns sorted groups', function (): void {
        $groups = $this->lingua->availableGroups();

        expect($groups)->toBe(['auth', 'common', 'dashboard', 'validation']);
    });

    it('returns empty array when locale directory does not exist', function (): void {
        File::deleteDirectory(lang_path());

        $groups = $this->lingua->availableGroups();

        expect($groups)->toBe([]);
    });

    it('returns groups for current locale only', function (): void {
        // Create French translations with different groups
        $frPath = lang_path('fr');
        File::ensureDirectoryExists($frPath);
        File::put($frPath.'/messages.php', '<?php return ["hello" => "Bonjour"];');

        // Should return English groups (current locale is 'en')
        $groups = $this->lingua->availableGroups();

        expect($groups)->toContain('common')
            ->not->toContain('messages');

        // Switch to French
        $this->lingua->setLocale('fr');

        $groups = $this->lingua->availableGroups();

        expect($groups)->toContain('messages')
            ->not->toContain('common');
    });
});

describe('translationGroup', function (): void {
    it('loads a single translation group', function (): void {
        $translations = $this->lingua->translationGroup('auth');

        expect($translations)->toBe([
            'login' => 'Login',
            'logout' => 'Logout',
        ]);
    });

    it('falls back to default locale keys when missing', function (): void {
        config([
            'lingua.locales' => ['en', 'fr'],
            'lingua.default' => 'en',
        ]);

        File::ensureDirectoryExists(lang_path('fr'));
        File::put(lang_path('fr').'/auth.php', '<?php return ["login" => "Connexion"];');

        $this->lingua->setLocale('fr');

        $translations = $this->lingua->translationGroup('auth');

        expect($translations['login'])->toBe('Connexion')
            ->and($translations['logout'])->toBe('Logout');
    });

    it('returns current translations when default locale matches current', function (): void {
        config([
            'lingua.locales' => ['en'],
            'lingua.default' => 'en',
        ]);

        $translations = $this->lingua->translationGroup('auth');

        expect($translations)->toBe([
            'login' => 'Login',
            'logout' => 'Logout',
        ]);
    });

    it('returns current translations when fallback group is missing', function (): void {
        config([
            'lingua.locales' => ['en', 'fr'],
            'lingua.default' => 'en',
        ]);

        File::ensureDirectoryExists(lang_path('fr'));
        File::put(lang_path('fr').'/auth.php', '<?php return ["login" => "Connexion"];');
        File::delete(lang_path('en').'/auth.php');

        $this->lingua->setLocale('fr');

        $translations = $this->lingua->translationGroup('auth');

        expect($translations)->toBe([
            'login' => 'Connexion',
        ]);
    });

    it('returns empty array for non-existent group', function (): void {
        $translations = $this->lingua->translationGroup('nonexistent');

        expect($translations)->toBe([]);
    });

    it('loads different groups independently', function (): void {
        $auth = $this->lingua->translationGroup('auth');
        $validation = $this->lingua->translationGroup('validation');

        expect($auth)->toHaveKey('login')
            ->not->toHaveKey('required');

        expect($validation)->toHaveKey('required')
            ->not->toHaveKey('login');
    });
});

describe('translationsFor', function (): void {
    it('loads multiple translation groups', function (): void {
        $translations = $this->lingua->translationsFor(['auth', 'common']);

        expect($translations)->toHaveKey('auth')
            ->toHaveKey('common')
            ->not->toHaveKey('validation')
            ->not->toHaveKey('dashboard');

        expect($translations['auth'])->toBe([
            'login' => 'Login',
            'logout' => 'Logout',
        ]);

        expect($translations['common'])->toBe([
            'app_name' => 'Test App',
            'welcome' => 'Welcome',
        ]);
    });

    it('returns empty array for empty groups array', function (): void {
        $translations = $this->lingua->translationsFor([]);

        expect($translations)->toBe([]);
    });

    it('skips non-existent groups', function (): void {
        $translations = $this->lingua->translationsFor(['auth', 'nonexistent', 'common']);

        expect($translations)->toHaveKey('auth')
            ->toHaveKey('common')
            ->not->toHaveKey('nonexistent');
    });

    it('handles single group in array', function (): void {
        $translations = $this->lingua->translationsFor(['dashboard']);

        expect($translations)->toBe([
            'dashboard' => [
                'title' => 'Dashboard',
                'stats' => 'Statistics',
            ],
        ]);
    });
});

describe('translations for php driver', function (): void {
    it('loads all translations', function (): void {
        $translations = $this->lingua->translations();

        expect($translations)->toHaveKey('common')
            ->toHaveKey('auth')
            ->toHaveKey('validation')
            ->toHaveKey('dashboard');
    });

    it('merges fallback groups when loading all translations', function (): void {
        config([
            'lingua.locales' => ['en', 'fr'],
            'lingua.default' => 'en',
        ]);

        File::ensureDirectoryExists(lang_path('fr'));
        File::put(lang_path('fr').'/common.php', '<?php return ["app_name" => "Nom App"];');
        File::delete(lang_path('fr').'/validation.php');

        $this->lingua->setLocale('fr');

        $translations = $this->lingua->translations();

        expect($translations['common']['app_name'])->toBe('Nom App')
            ->and($translations['common']['welcome'])->toBe('Welcome')
            ->and($translations)->toHaveKey('validation');
    });

    it('returns current translations when fallback locale files are missing', function (): void {
        config([
            'lingua.locales' => ['en', 'fr'],
            'lingua.default' => 'en',
        ]);

        File::ensureDirectoryExists(lang_path('fr'));
        File::put(lang_path('fr').'/common.php', '<?php return ["app_name" => "Nom App"];');
        File::deleteDirectory(lang_path('en'));

        $this->lingua->setLocale('fr');

        $translations = $this->lingua->translations();

        expect($translations)->toBe([
            'common' => [
                'app_name' => 'Nom App',
            ],
        ]);
    });
});

describe('translations with json driver', function (): void {
    it('always loads all translations with json driver regardless of lazy loading', function (): void {
        config([
            'lingua.translation_driver' => 'json',
        ]);

        // Create JSON translations
        File::put(lang_path('en.json'), json_encode([
            'Welcome' => 'Welcome!',
            'Hello' => 'Hello, World!',
            'Goodbye' => 'Goodbye!',
        ]));

        $translations = $this->lingua->translations();

        expect($translations)->toBe([
            'Welcome' => 'Welcome!',
            'Hello' => 'Hello, World!',
            'Goodbye' => 'Goodbye!',
        ]);
    });

    it('falls back to default locale keys when missing', function (): void {
        config([
            'lingua.translation_driver' => 'json',
            'lingua.locales' => ['en', 'fr'],
            'lingua.default' => 'en',
        ]);

        File::put(lang_path('en.json'), json_encode([
            'Welcome' => 'Welcome!',
            'Goodbye' => 'Goodbye!',
        ]));
        File::put(lang_path('fr.json'), json_encode([
            'Welcome' => 'Bienvenue!',
        ]));

        $this->lingua->setLocale('fr');

        $translations = $this->lingua->translations();

        expect($translations['Welcome'])->toBe('Bienvenue!')
            ->and($translations['Goodbye'])->toBe('Goodbye!');
    });

    it('returns current translations when default locale matches current', function (): void {
        config([
            'lingua.translation_driver' => 'json',
            'lingua.locales' => ['en'],
            'lingua.default' => 'en',
        ]);

        File::put(lang_path('en.json'), json_encode([
            'Welcome' => 'Welcome!',
        ]));

        $translations = $this->lingua->translations();

        expect($translations)->toBe([
            'Welcome' => 'Welcome!',
        ]);
    });

    it('returns current translations when fallback json is missing', function (): void {
        config([
            'lingua.translation_driver' => 'json',
            'lingua.locales' => ['en', 'fr'],
            'lingua.default' => 'en',
        ]);

        File::put(lang_path('fr.json'), json_encode([
            'Welcome' => 'Bienvenue!',
        ]));

        $this->lingua->setLocale('fr');

        $translations = $this->lingua->translations();

        expect($translations)->toBe([
            'Welcome' => 'Bienvenue!',
        ]);
    });
});
