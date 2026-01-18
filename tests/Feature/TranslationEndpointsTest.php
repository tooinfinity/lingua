<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    // Set up test translation files
    $langPath = lang_path('en');
    File::ensureDirectoryExists($langPath);

    File::put($langPath.'/common.php', '<?php return ["app_name" => "Test App", "welcome" => "Welcome"];');
    File::put($langPath.'/auth.php', '<?php return ["login" => "Login", "logout" => "Logout"];');
    File::put($langPath.'/validation.php', '<?php return ["required" => "Required field"];');

    // Set up French translations
    $frPath = lang_path('fr');
    File::ensureDirectoryExists($frPath);
    File::put($frPath.'/common.php', '<?php return ["app_name" => "Application Test", "welcome" => "Bienvenue"];');
});

afterEach(function (): void {
    File::deleteDirectory(lang_path());
});

describe('GET /lingua/translations/{group}', function (): void {
    it('returns translations for a specific group', function (): void {
        $response = $this->get('/lingua/translations/auth');

        $response->assertOk()
            ->assertJson([
                'group' => 'auth',
                'locale' => 'en',
                'translations' => [
                    'login' => 'Login',
                    'logout' => 'Logout',
                ],
            ]);
    });

    it('returns empty translations for non-existent group', function (): void {
        $response = $this->get('/lingua/translations/nonexistent');

        $response->assertOk()
            ->assertJson([
                'group' => 'nonexistent',
                'locale' => 'en',
                'translations' => [],
            ]);
    });

    it('returns translations for current locale', function (): void {
        // Set locale to French
        session()->put('lingua.locale', 'fr');

        $response = $this->get('/lingua/translations/common');

        $response->assertOk()
            ->assertJson([
                'group' => 'common',
                'locale' => 'fr',
                'translations' => [
                    'app_name' => 'Application Test',
                    'welcome' => 'Bienvenue',
                ],
            ]);
    });

    it('validates group parameter format', function (): void {
        // Valid group names
        $this->get('/lingua/translations/auth')->assertOk();
        $this->get('/lingua/translations/my-group')->assertOk();
        $this->get('/lingua/translations/my_group')->assertOk();
        $this->get('/lingua/translations/Group123')->assertOk();
    });
});

describe('POST /lingua/translations', function (): void {
    it('returns translations for multiple groups', function (): void {
        $response = $this->postJson('/lingua/translations', [
            'groups' => ['auth', 'common'],
        ]);

        $response->assertOk()
            ->assertJson([
                'locale' => 'en',
                'translations' => [
                    'auth' => [
                        'login' => 'Login',
                        'logout' => 'Logout',
                    ],
                    'common' => [
                        'app_name' => 'Test App',
                        'welcome' => 'Welcome',
                    ],
                ],
            ]);
    });

    it('validates groups parameter is required', function (): void {
        $response = $this->postJson('/lingua/translations', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['groups']);
    });

    it('validates groups parameter is array', function (): void {
        $response = $this->postJson('/lingua/translations', [
            'groups' => 'auth',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['groups']);
    });

    it('validates groups array is not empty', function (): void {
        $response = $this->postJson('/lingua/translations', [
            'groups' => [],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['groups']);
    });

    it('validates each group is a string', function (): void {
        $response = $this->postJson('/lingua/translations', [
            'groups' => ['auth', 123],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['groups.1']);
    });

    it('skips non-existent groups', function (): void {
        $response = $this->postJson('/lingua/translations', [
            'groups' => ['auth', 'nonexistent'],
        ]);

        $response->assertOk();

        $translations = $response->json('translations');

        expect($translations)->toHaveKey('auth')
            ->not->toHaveKey('nonexistent');
    });
});

describe('GET /lingua/groups', function (): void {
    it('returns available groups for current locale', function (): void {
        $response = $this->get('/lingua/groups');

        $response->assertOk()
            ->assertJson([
                'locale' => 'en',
            ]);

        $groups = $response->json('groups');

        expect($groups)->toContain('auth')
            ->toContain('common')
            ->toContain('validation');
    });

    it('returns groups for French locale when set', function (): void {
        session()->put('lingua.locale', 'fr');

        $response = $this->get('/lingua/groups');

        $response->assertOk()
            ->assertJson([
                'locale' => 'fr',
            ]);

        $groups = $response->json('groups');

        expect($groups)->toContain('common')
            ->not->toContain('auth')
            ->not->toContain('validation');
    });

    it('returns empty groups when locale has no translations', function (): void {
        config(['lingua.locales' => ['en', 'fr', 'de']]);
        session()->put('lingua.locale', 'de');

        $response = $this->get('/lingua/groups');

        $response->assertOk()
            ->assertJson([
                'locale' => 'de',
                'groups' => [],
            ]);
    });
});

describe('route names', function (): void {
    it('has named route for single group endpoint', function (): void {
        expect(route('lingua.translations.group', ['group' => 'auth']))
            ->toContain('/lingua/translations/auth');
    });

    it('has named route for multiple groups endpoint', function (): void {
        expect(route('lingua.translations.groups'))
            ->toContain('/lingua/translations');
    });

    it('has named route for available groups endpoint', function (): void {
        expect(route('lingua.translations.available'))
            ->toContain('/lingua/groups');
    });
});
