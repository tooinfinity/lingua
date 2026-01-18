<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use TooInfinity\Lingua\Support\LocalizedUrlGenerator;

beforeEach(function (): void {
    $this->generator = app(LocalizedUrlGenerator::class);
});

describe('LocalizedUrlGenerator', function (): void {
    describe('prefix strategy', function (): void {
        beforeEach(function (): void {
            config([
                'lingua.url.strategy' => 'prefix',
                'lingua.url.prefix.segment' => 1,
            ]);
            $this->generator = app(LocalizedUrlGenerator::class);
        });

        it('adds locale prefix to URL', function (): void {
            $url = $this->generator->localizedUrl('/dashboard', 'fr');

            expect($url)->toBe('/fr/dashboard');
        });

        it('adds locale prefix to root URL', function (): void {
            $url = $this->generator->localizedUrl('/', 'fr');

            expect($url)->toBe('/fr');
        });

        it('replaces existing locale prefix', function (): void {
            $url = $this->generator->localizedUrl('/en/dashboard', 'fr');

            expect($url)->toBe('/fr/dashboard');
        });

        it('preserves query string', function (): void {
            $url = $this->generator->localizedUrl('/dashboard?page=1&sort=name', 'fr');

            expect($url)->toBe('/fr/dashboard?page=1&sort=name');
        });

        it('handles absolute URLs', function (): void {
            $url = $this->generator->localizedUrl('https://example.com/dashboard', 'fr');

            expect($url)->toBe('https://example.com/fr/dashboard');
        });

        it('handles absolute URLs with existing locale', function (): void {
            $url = $this->generator->localizedUrl('https://example.com/en/dashboard', 'fr');

            expect($url)->toBe('https://example.com/fr/dashboard');
        });

        it('preserves port in absolute URLs', function (): void {
            $url = $this->generator->localizedUrl('https://example.com:8080/dashboard', 'fr');

            expect($url)->toBe('https://example.com:8080/fr/dashboard');
        });

        it('preserves fragment in URLs', function (): void {
            $url = $this->generator->localizedUrl('/dashboard#section', 'fr');

            expect($url)->toBe('/fr/dashboard#section');
        });

        it('handles URLs with query and fragment', function (): void {
            $url = $this->generator->localizedUrl('/dashboard?page=1#section', 'fr');

            expect($url)->toBe('/fr/dashboard?page=1#section');
        });
    });

    describe('domain strategy', function (): void {
        beforeEach(function (): void {
            config([
                'lingua.url.strategy' => 'domain',
                'lingua.url.domain.hosts' => [
                    'en' => 'example.com',
                    'fr' => 'fr.example.com',
                    'de' => 'example.de',
                ],
            ]);
            $this->generator = app(LocalizedUrlGenerator::class);
        });

        it('replaces host based on locale mapping', function (): void {
            $url = $this->generator->localizedUrl('https://example.com/dashboard', 'fr');

            expect($url)->toBe('https://fr.example.com/dashboard');
        });

        it('returns unchanged URL when locale has no host mapping', function (): void {
            $url = $this->generator->localizedUrl('https://example.com/dashboard', 'es');

            // Fail-soft: returns unchanged URL
            expect($url)->toBe('https://example.com/dashboard');
        });

        it('preserves path and query string', function (): void {
            $url = $this->generator->localizedUrl('https://example.com/dashboard?page=1', 'de');

            expect($url)->toBe('https://example.de/dashboard?page=1');
        });

        it('preserves port when changing host', function (): void {
            $url = $this->generator->localizedUrl('https://example.com:8080/dashboard', 'fr');

            expect($url)->toBe('https://fr.example.com:8080/dashboard');
        });

        it('preserves authentication in URL', function (): void {
            $url = $this->generator->localizedUrl('https://user:pass@example.com/dashboard', 'fr');

            expect($url)->toBe('https://user:pass@fr.example.com/dashboard');
        });
    });

    describe('no strategy (null)', function (): void {
        beforeEach(function (): void {
            config(['lingua.url.strategy' => null]);
            $this->generator = app(LocalizedUrlGenerator::class);
        });

        it('returns URL unchanged', function (): void {
            $url = $this->generator->localizedUrl('/dashboard', 'fr');

            expect($url)->toBe('/dashboard');
        });

        it('returns absolute URL unchanged', function (): void {
            $url = $this->generator->localizedUrl('https://example.com/dashboard', 'fr');

            expect($url)->toBe('https://example.com/dashboard');
        });
    });

    describe('switchLocaleUrl', function (): void {
        it('switches current URL to target locale with prefix strategy', function (): void {
            config([
                'lingua.url.strategy' => 'prefix',
                'lingua.url.prefix.segment' => 1,
            ]);

            $generator = app(LocalizedUrlGenerator::class);
            $request = Request::create('https://example.com/en/dashboard?page=1');

            $url = $generator->switchLocaleUrl('fr', $request);

            expect($url)->toBe('https://example.com/fr/dashboard?page=1');
        });

        it('switches current URL to target locale with domain strategy', function (): void {
            config([
                'lingua.url.strategy' => 'domain',
                'lingua.url.domain.hosts' => [
                    'en' => 'example.com',
                    'fr' => 'fr.example.com',
                ],
            ]);

            $generator = app(LocalizedUrlGenerator::class);
            $request = Request::create('https://example.com/dashboard');

            $url = $generator->switchLocaleUrl('fr', $request);

            expect($url)->toBe('https://fr.example.com/dashboard');
        });
    });

    describe('localizedRoute', function (): void {
        beforeEach(function (): void {
            config([
                'lingua.url.strategy' => 'prefix',
                'lingua.url.prefix.segment' => 1,
                'lingua.locales' => ['en', 'fr', 'de'],
            ]);
            $this->generator = app(LocalizedUrlGenerator::class);
        });

        it('generates route URL with locale parameter for prefix strategy', function (): void {
            $routeName = 'urlgen.dashboard.'.uniqid();
            Illuminate\Support\Facades\Route::get('/{locale}/dashboard', fn (): string => 'dashboard')->name($routeName);
            Illuminate\Support\Facades\Route::getRoutes()->refreshNameLookups();

            $url = $this->generator->localizedRoute($routeName, [], 'fr');

            expect($url)->toContain('/fr/dashboard');
        });

        it('generates route URL with domain strategy', function (): void {
            config([
                'lingua.url.strategy' => 'domain',
                'lingua.url.domain.hosts' => [
                    'en' => 'example.com',
                    'fr' => 'fr.example.com',
                ],
            ]);
            $generator = app(LocalizedUrlGenerator::class);

            $routeName = 'urlgen.settings.'.uniqid();
            Illuminate\Support\Facades\Route::get('/settings', fn (): string => 'settings')->name($routeName);
            Illuminate\Support\Facades\Route::getRoutes()->refreshNameLookups();

            $url = $generator->localizedRoute($routeName, [], 'fr');

            expect($url)->toContain('fr.example.com');
        });

        it('uses current locale when locale is null', function (): void {
            config(['lingua.locales' => ['en', 'fr']]);
            app(TooInfinity\Lingua\Lingua::class)->setLocale('fr');
            $generator = app(LocalizedUrlGenerator::class);

            $routeName = 'urlgen.account.'.uniqid();
            Illuminate\Support\Facades\Route::get('/{locale}/account', fn (): string => 'account')->name($routeName);
            Illuminate\Support\Facades\Route::getRoutes()->refreshNameLookups();

            $url = $generator->localizedRoute($routeName);

            expect($url)->toContain('/fr/account');
        });

        it('passes additional parameters to route', function (): void {
            $routeName = 'urlgen.posts.show.'.uniqid();
            Illuminate\Support\Facades\Route::get('/{locale}/posts/{post}', fn (): string => 'post')->name($routeName);
            Illuminate\Support\Facades\Route::getRoutes()->refreshNameLookups();

            $url = $this->generator->localizedRoute($routeName, ['post' => 42], 'de');

            expect($url)->toContain('/de/posts/42');
        });

        it('generates relative URL when absolute is false', function (): void {
            $routeName = 'urlgen.contact.'.uniqid();
            Illuminate\Support\Facades\Route::get('/{locale}/contact', fn (): string => 'contact')->name($routeName);
            Illuminate\Support\Facades\Route::getRoutes()->refreshNameLookups();

            $url = $this->generator->localizedRoute($routeName, [], 'fr', false);

            expect($url)->toBe('/fr/contact');
        });

        it('generates absolute URL by default', function (): void {
            $routeName = 'urlgen.about.'.uniqid();
            Illuminate\Support\Facades\Route::get('/{locale}/about', fn (): string => 'about')->name($routeName);
            Illuminate\Support\Facades\Route::getRoutes()->refreshNameLookups();

            $url = $this->generator->localizedRoute($routeName, [], 'fr');

            expect($url)->toStartWith('http');
        });
    });

    describe('edge cases', function (): void {
        it('returns original URL unchanged when prefix strategy receives malformed URL', function (): void {
            config([
                'lingua.url.strategy' => 'prefix',
                'lingua.url.prefix.segment' => 1,
            ]);
            $generator = app(LocalizedUrlGenerator::class);

            // URLs that cause parse_url to return false
            $malformedUrl = 'http:///example.com';

            $url = $generator->localizedUrl($malformedUrl, 'fr');

            expect($url)->toBe($malformedUrl);
        });

        it('returns original URL unchanged when domain strategy receives malformed URL', function (): void {
            config([
                'lingua.url.strategy' => 'domain',
                'lingua.url.domain.hosts' => [
                    'en' => 'example.com',
                    'fr' => 'fr.example.com',
                ],
            ]);
            $generator = app(LocalizedUrlGenerator::class);

            // URLs that cause parse_url to return false
            $malformedUrl = 'http:///example.com';

            $url = $generator->localizedUrl($malformedUrl, 'fr');

            expect($url)->toBe($malformedUrl);
        });

        it('handles URL with user info in prefix strategy', function (): void {
            config([
                'lingua.url.strategy' => 'prefix',
                'lingua.url.prefix.segment' => 1,
            ]);
            $generator = app(LocalizedUrlGenerator::class);

            $url = $generator->localizedUrl('https://user@example.com/dashboard', 'fr');

            expect($url)->toBe('https://user@example.com/fr/dashboard');
        });

        it('handles URL with user and password in prefix strategy', function (): void {
            config([
                'lingua.url.strategy' => 'prefix',
                'lingua.url.prefix.segment' => 1,
            ]);
            $generator = app(LocalizedUrlGenerator::class);

            $url = $generator->localizedUrl('https://user:password@example.com/dashboard', 'fr');

            expect($url)->toBe('https://user:password@example.com/fr/dashboard');
        });
    });
});
