<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use TooInfinity\Lingua\Lingua;
use TooInfinity\Lingua\Support\LocaleResolverManager;

beforeEach(function (): void {
    $this->lingua = app(Lingua::class);
    $this->manager = app(LocaleResolverManager::class);
});

describe('LocaleResolverManager', function (): void {
    it('returns available resolvers', function (): void {
        $resolvers = $this->manager->getAvailableResolvers();

        expect($resolvers)->toContain('session')
            ->toContain('cookie')
            ->toContain('query')
            ->toContain('header')
            ->toContain('url_prefix')
            ->toContain('domain');
    });

    it('returns configured resolution order', function (): void {
        config(['lingua.resolution_order' => ['query', 'session', 'header']]);

        $manager = app(LocaleResolverManager::class);

        expect($manager->getResolutionOrder())->toBe(['query', 'session', 'header']);
    });

    it('creates resolver instances', function (): void {
        // Ensure all resolvers are enabled for this test
        config(['lingua.resolvers.session.enabled' => true]);
        config(['lingua.resolvers.cookie.enabled' => true]);
        config(['lingua.resolvers.query.enabled' => true]);
        config(['lingua.resolvers.header.enabled' => true]);
        config(['lingua.resolvers.url_prefix.enabled' => true]);
        config(['lingua.resolvers.domain.enabled' => true]);

        $manager = app(LocaleResolverManager::class);

        expect($manager->createResolver('session'))->toBeInstanceOf(
            TooInfinity\Lingua\Support\Resolvers\SessionResolver::class
        );
        expect($manager->createResolver('cookie'))->toBeInstanceOf(
            TooInfinity\Lingua\Support\Resolvers\CookieResolver::class
        );
        expect($manager->createResolver('query'))->toBeInstanceOf(
            TooInfinity\Lingua\Support\Resolvers\QueryResolver::class
        );
        expect($manager->createResolver('header'))->toBeInstanceOf(
            TooInfinity\Lingua\Support\Resolvers\HeaderResolver::class
        );
        expect($manager->createResolver('url_prefix'))->toBeInstanceOf(
            TooInfinity\Lingua\Support\Resolvers\UrlPrefixResolver::class
        );
        expect($manager->createResolver('domain'))->toBeInstanceOf(
            TooInfinity\Lingua\Support\Resolvers\DomainResolver::class
        );
    });

    it('returns null for disabled resolver', function (): void {
        config(['lingua.resolvers.session.enabled' => false]);

        $manager = app(LocaleResolverManager::class);

        expect($manager->createResolver('session'))->toBeNull();
    });

    it('returns null for unknown resolver', function (): void {
        expect($this->manager->createResolver('unknown'))->toBeNull();
    });

    it('creates custom resolver class when configured', function (): void {
        // Create a custom resolver class for testing
        config(['lingua.resolvers.session.enabled' => true]);
        config(['lingua.resolvers.session.class' => TooInfinity\Lingua\Support\Resolvers\CookieResolver::class]);

        $manager = app(LocaleResolverManager::class);

        // Should create the custom class instead of SessionResolver
        expect($manager->createResolver('session'))->toBeInstanceOf(
            TooInfinity\Lingua\Support\Resolvers\CookieResolver::class
        );
    });

    it('skips empty string locales during resolution', function (): void {
        config(['lingua.locales' => ['en', 'fr']]);
        config(['lingua.resolution_order' => ['query', 'session']]);
        config(['lingua.resolvers.query.enabled' => true]);
        config(['lingua.default' => 'en']);

        // Set session to a valid locale
        session()->put('lingua.locale', 'fr');

        // Query parameter is empty string
        $request = Request::create('/?locale=');

        $lingua = app(Lingua::class);

        // Empty string from query should be skipped, falls back to session
        expect($lingua->getLocale($request))->toBe('fr');
    });

    it('skips empty string locales returned by resolveAll', function (): void {
        // Create an anonymous class that returns empty strings in resolveAll
        $emptyStringResolver = new class implements TooInfinity\Lingua\Contracts\LocaleResolverInterface
        {
            public function resolve(Request $request): string
            {
                return '';
            }

            public function resolveAll(Request $request): array
            {
                return ['', '', 'fr']; // Returns empty strings followed by valid locale
            }
        };

        // Bind the custom resolver
        app()->instance('test.empty_resolver', $emptyStringResolver);

        config(['lingua.locales' => ['en', 'fr', 'de']]);
        config(['lingua.resolution_order' => ['custom', 'session']]);
        config(['lingua.resolvers.custom.enabled' => true]);
        config(['lingua.resolvers.custom.class' => $emptyStringResolver::class]);
        config(['lingua.default' => 'en']);

        // Bind the resolver class
        app()->bind($emptyStringResolver::class, fn (): object => $emptyStringResolver);

        $manager = app(LocaleResolverManager::class);
        $request = Request::create('/');

        $result = $manager->resolve(
            $request,
            fn (string $locale): bool => in_array($locale, ['en', 'fr', 'de']),
            fn (string $locale): string => $locale
        );

        // Should skip empty strings and return 'fr'
        expect($result)->toBe('fr');
    });
});

describe('Resolution Order Priority', function (): void {
    beforeEach(function (): void {
        config(['lingua.locales' => ['en', 'fr', 'de', 'es']]);
        // Enable query resolver for these tests
        config(['lingua.resolvers.query.enabled' => true]);
    });

    it('uses first resolver that returns a supported locale', function (): void {
        config(['lingua.resolution_order' => ['query', 'session', 'cookie']]);

        session()->put('lingua.locale', 'de');
        $request = Request::create('/?locale=fr');

        // Query comes first, should return 'fr'
        expect($this->lingua->getLocale($request))->toBe('fr');
    });

    it('falls back to next resolver when first returns unsupported locale', function (): void {
        config(['lingua.resolution_order' => ['query', 'session']]);
        config(['lingua.locales' => ['en', 'de']]);

        session()->put('lingua.locale', 'de');
        $request = Request::create('/?locale=invalid');

        // Query returns 'invalid' which is not supported, falls back to session
        expect($this->lingua->getLocale($request))->toBe('de');
    });

    it('falls back to next resolver when first returns null', function (): void {
        config(['lingua.resolution_order' => ['query', 'session']]);

        session()->put('lingua.locale', 'fr');
        $request = Request::create('/'); // No query parameter

        expect($this->lingua->getLocale($request))->toBe('fr');
    });

    it('falls back to default when no resolver returns valid locale', function (): void {
        config(['lingua.resolution_order' => ['query']]);
        config(['lingua.default' => 'es']);

        $request = Request::create('/'); // No query parameter

        expect($this->lingua->getLocale($request))->toBe('es');
    });

    it('falls back to app locale when default is null', function (): void {
        config(['lingua.resolution_order' => ['query']]);
        config(['lingua.default' => null]);
        config(['app.locale' => 'de']);

        $request = Request::create('/');

        expect($this->lingua->getLocale($request))->toBe('de');
    });

    it('respects session before cookie in default order', function (): void {
        config(['lingua.resolution_order' => ['session', 'cookie']]);

        session()->put('lingua.locale', 'fr');
        $request = Request::create('/');
        $request->cookies->set('lingua_locale', 'de');

        expect($this->lingua->getLocale($request))->toBe('fr');
    });

    it('uses cookie when session is empty', function (): void {
        config(['lingua.resolution_order' => ['session', 'cookie']]);

        $request = Request::create('/');
        $request->cookies->set('lingua_locale', 'de');

        expect($this->lingua->getLocale($request))->toBe('de');
    });
});

describe('Backward Compatibility', function (): void {
    it('works without request parameter (session-only)', function (): void {
        session()->put('lingua.locale', 'fr');

        expect($this->lingua->getLocale())->toBe('fr');
    });

    it('uses default when session is empty without request', function (): void {
        config(['lingua.default' => 'es']);

        expect($this->lingua->getLocale())->toBe('es');
    });

    it('uses legacy session_key config', function (): void {
        config(['lingua.session_key' => 'legacy.locale.key']);
        config(['lingua.resolvers.session.key' => null]);

        session()->put('legacy.locale.key', 'de');

        expect($this->lingua->getLocale())->toBe('de');
    });

    it('prefers new resolver config over legacy session_key', function (): void {
        config(['lingua.session_key' => 'legacy.key']);
        config(['lingua.resolvers.session.key' => 'new.key']);

        session()->put('legacy.key', 'fr');
        session()->put('new.key', 'de');

        expect($this->lingua->getLocale())->toBe('de');
    });
});

describe('Locale Normalization in Resolution', function (): void {
    beforeEach(function (): void {
        config(['lingua.locales' => ['en_US', 'fr', 'de']]);
    });

    it('normalizes locale from query parameter', function (): void {
        config(['lingua.resolution_order' => ['query']]);
        config(['lingua.resolvers.query.enabled' => true]);

        $request = Request::create('/?locale=en-US');

        expect($this->lingua->getLocale($request))->toBe('en_US');
    });

    it('normalizes locale from header', function (): void {
        config(['lingua.resolution_order' => ['header']]);
        config(['lingua.resolvers.header.enabled' => true]);

        $request = Request::create('/');
        $request->headers->set('Accept-Language', 'en-us');

        expect($this->lingua->getLocale($request))->toBe('en_US');
    });

    it('normalizes locale from URL prefix', function (): void {
        config(['lingua.resolution_order' => ['url_prefix']]);
        config(['lingua.resolvers.url_prefix.enabled' => true]);

        $request = Request::create('/en-US/dashboard');

        expect($this->lingua->getLocale($request))->toBe('en_US');
    });
});

describe('Header Resolution Integration', function (): void {
    beforeEach(function (): void {
        config(['lingua.locales' => ['en', 'fr', 'de', 'es']]);
        config(['lingua.resolution_order' => ['header']]);
        config(['lingua.resolvers.header.enabled' => true]);
    });

    it('resolves best match from Accept-Language header', function (): void {
        $request = Request::create('/');
        $request->headers->set('Accept-Language', 'it,fr;q=0.9,de;q=0.8');

        // 'it' is not supported, 'fr' should be used
        expect($this->lingua->getLocale($request))->toBe('fr');
    });

    it('falls back to default when no header locales are supported', function (): void {
        config(['lingua.default' => 'es']);

        $request = Request::create('/');
        $request->headers->set('Accept-Language', 'it,pt,zh');

        expect($this->lingua->getLocale($request))->toBe('es');
    });
});

describe('URL Prefix Resolution Integration', function (): void {
    beforeEach(function (): void {
        config(['lingua.locales' => ['en', 'fr', 'de']]);
        config(['lingua.resolution_order' => ['url_prefix', 'session']]);
        config(['lingua.resolvers.url_prefix.enabled' => true]);
    });

    it('resolves locale from URL prefix', function (): void {
        $request = Request::create('/fr/dashboard');

        expect($this->lingua->getLocale($request))->toBe('fr');
    });

    it('falls back to session when URL prefix is not a valid locale', function (): void {
        session()->put('lingua.locale', 'de');

        $request = Request::create('/admin/dashboard');

        expect($this->lingua->getLocale($request))->toBe('de');
    });
});

describe('Empty Resolution Order', function (): void {
    it('falls back to default with empty resolution order', function (): void {
        config(['lingua.resolution_order' => []]);
        config(['lingua.default' => 'fr']);

        $request = Request::create('/?locale=de');

        expect($this->lingua->getLocale($request))->toBe('fr');
    });
});

describe('Resolver Enable/Disable', function (): void {
    it('skips disabled resolvers', function (): void {
        config(['lingua.locales' => ['en', 'fr', 'de']]);
        config(['lingua.resolution_order' => ['query', 'session']]);
        config(['lingua.resolvers.query.enabled' => false]);
        config(['lingua.default' => 'en']);

        session()->put('lingua.locale', 'de');

        $request = Request::create('/?locale=fr');

        // Query resolver is disabled, so it should use session value
        expect($this->lingua->getLocale($request))->toBe('de');
    });

    it('uses resolver when enabled', function (): void {
        config(['lingua.locales' => ['en', 'fr', 'de']]);
        config(['lingua.resolution_order' => ['query', 'session']]);
        config(['lingua.resolvers.query.enabled' => true]);
        config(['lingua.default' => 'en']);

        session()->put('lingua.locale', 'de');

        $request = Request::create('/?locale=fr');

        // Query resolver is enabled, so it should use query value
        expect($this->lingua->getLocale($request))->toBe('fr');
    });

    it('defaults to enabled when not specified', function (): void {
        config(['lingua.locales' => ['en', 'fr']]);
        config(['lingua.resolution_order' => ['cookie']]);
        // Remove the enabled key to test default behavior
        config(['lingua.resolvers.cookie' => ['key' => 'test_locale']]);
        config(['lingua.default' => 'en']);

        $request = Request::create('/');
        $request->cookies->set('test_locale', 'fr');

        // Should default to enabled
        expect($this->lingua->getLocale($request))->toBe('fr');
    });

    it('can disable all resolvers and fall back to default', function (): void {
        config(['lingua.locales' => ['en', 'fr', 'de']]);
        config(['lingua.resolution_order' => ['session', 'query', 'cookie']]);
        config(['lingua.resolvers.session.enabled' => false]);
        config(['lingua.resolvers.query.enabled' => false]);
        config(['lingua.resolvers.cookie.enabled' => false]);
        config(['lingua.default' => 'de']);

        session()->put('lingua.locale', 'fr');

        $request = Request::create('/?locale=fr');
        $request->cookies->set('lingua_locale', 'fr');

        // All resolvers disabled, should fall back to default
        expect($this->lingua->getLocale($request))->toBe('de');
    });

    it('can selectively enable resolvers in the middle of the order', function (): void {
        config(['lingua.locales' => ['en', 'fr', 'de', 'es']]);
        config(['lingua.resolution_order' => ['query', 'session', 'cookie']]);
        config(['lingua.resolvers.query.enabled' => false]);
        config(['lingua.resolvers.session.enabled' => true]);
        config(['lingua.resolvers.cookie.enabled' => false]);
        config(['lingua.default' => 'en']);

        session()->put('lingua.locale', 'de');

        $request = Request::create('/?locale=fr');
        $request->cookies->set('lingua_locale', 'es');

        // Only session is enabled
        expect($this->lingua->getLocale($request))->toBe('de');
    });

    it('provides list of enabled resolvers', function (): void {
        config(['lingua.resolution_order' => ['session', 'query', 'cookie', 'header']]);
        config(['lingua.resolvers.session.enabled' => true]);
        config(['lingua.resolvers.query.enabled' => false]);
        config(['lingua.resolvers.cookie.enabled' => true]);
        config(['lingua.resolvers.header.enabled' => false]);

        $manager = app(LocaleResolverManager::class);

        $enabled = $manager->getEnabledResolvers();

        expect($enabled)->toBe(['session', 'cookie']);
    });

    it('can check if specific resolver is enabled', function (): void {
        config(['lingua.resolvers.session.enabled' => true]);
        config(['lingua.resolvers.query.enabled' => false]);

        $manager = app(LocaleResolverManager::class);

        expect($manager->isResolverEnabled('session'))->toBeTrue();
        expect($manager->isResolverEnabled('query'))->toBeFalse();
    });
});
