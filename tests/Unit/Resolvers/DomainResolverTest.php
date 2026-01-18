<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use TooInfinity\Lingua\Support\Resolvers\DomainResolver;

beforeEach(function (): void {
    config(['lingua.resolvers.domain.enabled' => true]);
    $this->resolver = app(DomainResolver::class);
});

describe('DomainResolver', function (): void {
    it('returns locale from full domain map', function (): void {
        config([
            'lingua.resolvers.domain.full_map' => [
                'example.de' => 'de',
                'example.fr' => 'fr',
            ],
        ]);

        $resolver = app(DomainResolver::class);
        $request = Request::create('http://example.de/dashboard');

        expect($resolver->resolve($request))->toBe('de');
    });

    it('returns locale from subdomain', function (): void {
        config([
            'lingua.resolvers.domain.subdomain.enabled' => true,
        ]);

        $resolver = app(DomainResolver::class);
        $request = Request::create('http://fr.example.com/dashboard');

        expect($resolver->resolve($request))->toBe('fr');
    });

    it('respects evaluation order - full first', function (): void {
        config([
            'lingua.resolvers.domain.order' => ['full', 'subdomain'],
            'lingua.resolvers.domain.full_map' => [
                'de.example.com' => 'de-formal', // Full map takes precedence
            ],
            'lingua.resolvers.domain.subdomain.enabled' => true,
        ]);

        $resolver = app(DomainResolver::class);
        $request = Request::create('http://de.example.com/dashboard');

        // Full map match takes precedence
        expect($resolver->resolve($request))->toBe('de-formal');
    });

    it('respects evaluation order - subdomain first', function (): void {
        config([
            'lingua.resolvers.domain.order' => ['subdomain', 'full'],
            'lingua.resolvers.domain.full_map' => [
                'de.example.com' => 'de-formal',
            ],
            'lingua.resolvers.domain.subdomain.enabled' => true,
        ]);

        $resolver = app(DomainResolver::class);
        $request = Request::create('http://de.example.com/dashboard');

        // Subdomain match takes precedence (subdomain 'de' matches locale pattern)
        expect($resolver->resolve($request))->toBe('de');
    });

    it('respects subdomain label position', function (): void {
        config([
            'lingua.resolvers.domain.subdomain.enabled' => true,
            'lingua.resolvers.domain.subdomain.label' => 2, // Second label from left
        ]);

        $resolver = app(DomainResolver::class);
        $request = Request::create('http://www.fr.example.com/dashboard');

        expect($resolver->resolve($request))->toBe('fr');
    });

    it('respects base_domains filter', function (): void {
        config([
            'lingua.resolvers.domain.subdomain.enabled' => true,
            'lingua.resolvers.domain.subdomain.base_domains' => ['example.com'],
        ]);

        $resolver = app(DomainResolver::class);

        // Allowed base domain
        $request1 = Request::create('http://fr.example.com/dashboard');
        expect($resolver->resolve($request1))->toBe('fr');

        // Disallowed base domain
        $request2 = Request::create('http://fr.other-site.com/dashboard');
        expect($resolver->resolve($request2))->toBeNull();
    });

    it('returns empty when no match found', function (): void {
        config([
            'lingua.resolvers.domain.full_map' => [],
            'lingua.resolvers.domain.subdomain.enabled' => false,
        ]);

        $resolver = app(DomainResolver::class);
        $request = Request::create('http://example.com/dashboard');

        expect($resolver->resolve($request))->toBeNull();
        expect($resolver->resolveAll($request))->toBe([]);
    });

    it('validates subdomain against patterns', function (): void {
        config([
            'lingua.resolvers.domain.subdomain.enabled' => true,
            'lingua.resolvers.domain.subdomain.patterns' => ['^[a-z]{2}$'], // Only 2-letter codes
        ]);

        $resolver = app(DomainResolver::class);

        // Valid 2-letter subdomain
        $request1 = Request::create('http://fr.example.com/dashboard');
        expect($resolver->resolve($request1))->toBe('fr');

        // Invalid subdomain (www)
        $request2 = Request::create('http://www.example.com/dashboard');
        expect($resolver->resolve($request2))->toBeNull();

        // Invalid subdomain (api)
        $request3 = Request::create('http://api.example.com/dashboard');
        expect($resolver->resolve($request3))->toBeNull();
    });

    it('returns null for domains without subdomain', function (): void {
        config([
            'lingua.resolvers.domain.full_map' => [],
            'lingua.resolvers.domain.subdomain.enabled' => true,
        ]);

        $resolver = app(DomainResolver::class);
        $request = Request::create('http://example.com/dashboard');

        // Only 2 parts (example.com), no subdomain
        expect($resolver->resolve($request))->toBeNull();
    });

    it('handles locale with region code in subdomain', function (): void {
        config([
            'lingua.resolvers.domain.subdomain.enabled' => true,
            'lingua.resolvers.domain.subdomain.patterns' => [
                '^[a-z]{2}(-[a-z]{2})?$', // Case-insensitive pattern (hosts are lowercased)
            ],
        ]);

        $resolver = app(DomainResolver::class);
        // Note: HTTP hosts are normalized to lowercase by the Request
        $request = Request::create('http://en-us.example.com/dashboard');

        expect($resolver->resolve($request))->toBe('en-us');
    });

    it('falls back to subdomain when full map has no match', function (): void {
        config([
            'lingua.resolvers.domain.order' => ['full', 'subdomain'],
            'lingua.resolvers.domain.full_map' => [
                'example.de' => 'de',
            ],
            'lingua.resolvers.domain.subdomain.enabled' => true,
        ]);

        $resolver = app(DomainResolver::class);
        $request = Request::create('http://fr.example.com/dashboard');

        // No full map match, falls back to subdomain
        expect($resolver->resolve($request))->toBe('fr');
    });

    it('returns null for unknown strategy in order array', function (): void {
        config([
            'lingua.resolvers.domain.order' => ['unknown_strategy'],
            'lingua.resolvers.domain.full_map' => [],
            'lingua.resolvers.domain.subdomain.enabled' => false,
        ]);

        $resolver = app(DomainResolver::class);
        $request = Request::create('http://fr.example.com/dashboard');

        expect($resolver->resolve($request))->toBeNull();
    });

    it('skips unknown strategies and continues with valid ones', function (): void {
        config([
            'lingua.resolvers.domain.order' => ['invalid', 'unknown', 'subdomain'],
            'lingua.resolvers.domain.subdomain.enabled' => true,
        ]);

        $resolver = app(DomainResolver::class);
        $request = Request::create('http://fr.example.com/dashboard');

        // Should skip invalid strategies and find locale via subdomain
        expect($resolver->resolve($request))->toBe('fr');
    });

    it('returns null when label position is negative', function (): void {
        config([
            'lingua.resolvers.domain.order' => ['subdomain'],
            'lingua.resolvers.domain.subdomain.enabled' => true,
            'lingua.resolvers.domain.subdomain.label' => 0, // Results in index -1
        ]);

        $resolver = app(DomainResolver::class);
        $request = Request::create('http://fr.example.com/dashboard');

        expect($resolver->resolve($request))->toBeNull();
    });

    it('returns null when label position exceeds available parts', function (): void {
        config([
            'lingua.resolvers.domain.order' => ['subdomain'],
            'lingua.resolvers.domain.subdomain.enabled' => true,
            'lingua.resolvers.domain.subdomain.label' => 10, // Way beyond available parts
        ]);

        $resolver = app(DomainResolver::class);
        $request = Request::create('http://fr.example.com/dashboard');

        expect($resolver->resolve($request))->toBeNull();
    });

    it('returns null when label index does not exist in parts array', function (): void {
        config([
            'lingua.resolvers.domain.order' => ['subdomain'],
            'lingua.resolvers.domain.subdomain.enabled' => true,
            'lingua.resolvers.domain.subdomain.label' => 5, // Index 4 doesn't exist in 3-part domain
        ]);

        $resolver = app(DomainResolver::class);
        $request = Request::create('http://en.example.com/dashboard');

        expect($resolver->resolve($request))->toBeNull();
    });
});
