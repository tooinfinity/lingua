<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use TooInfinity\Lingua\Support\Resolvers\UrlPrefixResolver;

beforeEach(function (): void {
    config(['lingua.resolvers.url_prefix.enabled' => true]);
    $this->resolver = app(UrlPrefixResolver::class);
});

describe('UrlPrefixResolver', function (): void {
    it('returns locale from first URL segment when matches pattern', function (): void {
        $request = Request::create('/fr/dashboard');

        expect($this->resolver->resolve($request))->toBe('fr');
    });

    it('returns empty array when segment does not match locale pattern', function (): void {
        $request = Request::create('/dashboard/settings');

        // Returns empty array for non-locale segments to prevent false positives
        expect($this->resolver->resolve($request))->toBeNull();
        expect($this->resolver->resolveAll($request))->toBe([]);
    });

    it('returns locale with region code', function (): void {
        $request = Request::create('/en-US/dashboard');

        expect($this->resolver->resolve($request))->toBe('en-US');
    });

    it('returns locale with underscore region code', function (): void {
        $request = Request::create('/en_US/dashboard');

        expect($this->resolver->resolve($request))->toBe('en_US');
    });

    it('respects configured segment position', function (): void {
        config(['lingua.resolvers.url_prefix.segment' => 2]);

        $resolver = app(UrlPrefixResolver::class);
        $request = Request::create('/admin/de/settings');

        expect($resolver->resolve($request))->toBe('de');
    });

    it('returns null when URL has no segments', function (): void {
        $request = Request::create('/');

        expect($this->resolver->resolve($request))->toBeNull();
    });

    it('returns empty array when URL has no segments', function (): void {
        $request = Request::create('/');

        expect($this->resolver->resolveAll($request))->toBe([]);
    });

    it('handles URLs with query parameters', function (): void {
        $request = Request::create('/fr/dashboard?page=1&sort=name');

        expect($this->resolver->resolve($request))->toBe('fr');
    });

    it('handles multiple pattern configurations', function (): void {
        config([
            'lingua.resolvers.url_prefix.patterns' => [
                '^[a-z]{2}$',           // Simple 2-letter codes
                '^[a-z]{2}-[A-Z]{2}$',  // With region using hyphen
            ],
        ]);

        $resolver = app(UrlPrefixResolver::class);

        // Simple locale
        $request1 = Request::create('/fr/dashboard');
        expect($resolver->resolve($request1))->toBe('fr');

        // Region code with hyphen
        $request2 = Request::create('/en-US/dashboard');
        expect($resolver->resolve($request2))->toBe('en-US');

        // Underscore variant won't match (not in patterns)
        $request3 = Request::create('/en_US/dashboard');
        expect($resolver->resolve($request3))->toBeNull();
    });

    it('rejects three letter codes by default', function (): void {
        $request = Request::create('/eng/dashboard');

        expect($this->resolver->resolve($request))->toBeNull();
    });

    it('rejects numeric segments', function (): void {
        $request = Request::create('/123/dashboard');

        expect($this->resolver->resolve($request))->toBeNull();
    });

    it('rejects long path segments', function (): void {
        $request = Request::create('/products/dashboard');

        expect($this->resolver->resolve($request))->toBeNull();
    });

    it('handles URLs with trailing slash', function (): void {
        $request = Request::create('/de/');

        expect($this->resolver->resolve($request))->toBe('de');
    });

    it('returns null when position exceeds available segments', function (): void {
        config(['lingua.resolvers.url_prefix.segment' => 5]);

        $resolver = app(UrlPrefixResolver::class);
        $request = Request::create('/fr/dashboard');

        expect($resolver->resolve($request))->toBeNull();
    });

    it('returns null when position is zero or negative', function (): void {
        config(['lingua.resolvers.url_prefix.segment' => 0]);

        $resolver = app(UrlPrefixResolver::class);
        $request = Request::create('/fr/dashboard');

        expect($resolver->resolve($request))->toBeNull();
    });
});
