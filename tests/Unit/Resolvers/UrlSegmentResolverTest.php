<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use TooInfinity\Lingua\Support\Resolvers\UrlSegmentResolver;

beforeEach(function (): void {
    $this->resolver = app(UrlSegmentResolver::class);
});

describe('UrlSegmentResolver', function (): void {
    it('returns locale from first URL segment by default', function (): void {
        $request = Request::create('/fr/dashboard');

        expect($this->resolver->resolve($request))->toBe('fr');
    });

    it('returns locale from configured position', function (): void {
        config(['lingua.resolvers.url_segment.position' => 2]);

        $resolver = app(UrlSegmentResolver::class);
        $request = Request::create('/admin/de/settings');

        expect($resolver->resolve($request))->toBe('de');
    });

    it('returns null when URL has no segments', function (): void {
        $request = Request::create('/');

        expect($this->resolver->resolve($request))->toBeNull();
    });

    it('returns null when position exceeds available segments', function (): void {
        config(['lingua.resolvers.url_segment.position' => 5]);

        $resolver = app(UrlSegmentResolver::class);
        $request = Request::create('/fr/dashboard');

        expect($resolver->resolve($request))->toBeNull();
    });

    it('returns null when position is zero or negative', function (): void {
        config(['lingua.resolvers.url_segment.position' => 0]);

        $resolver = app(UrlSegmentResolver::class);
        $request = Request::create('/fr/dashboard');

        expect($resolver->resolve($request))->toBeNull();
    });

    it('handles locale with region code in URL', function (): void {
        $request = Request::create('/en-US/dashboard');

        expect($this->resolver->resolve($request))->toBe('en-US');
    });

    it('handles deep nested URLs', function (): void {
        config(['lingua.resolvers.url_segment.position' => 3]);

        $resolver = app(UrlSegmentResolver::class);
        $request = Request::create('/admin/settings/es/preferences');

        expect($resolver->resolve($request))->toBe('es');
    });

    it('handles URLs with query parameters', function (): void {
        $request = Request::create('/fr/dashboard?page=1');

        expect($this->resolver->resolve($request))->toBe('fr');
    });

    it('handles URLs with trailing slash', function (): void {
        $request = Request::create('/de/');

        expect($this->resolver->resolve($request))->toBe('de');
    });

    it('returns segment value even if not a valid locale code', function (): void {
        $request = Request::create('/dashboard/settings');

        // Returns 'dashboard' - validation happens at manager level
        expect($this->resolver->resolve($request))->toBe('dashboard');
    });
});
