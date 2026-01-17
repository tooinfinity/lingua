<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use TooInfinity\Lingua\Support\Resolvers\QueryResolver;

beforeEach(function (): void {
    $this->resolver = app(QueryResolver::class);
});

describe('QueryResolver', function (): void {
    it('returns locale from query parameter', function (): void {
        $request = Request::create('/?locale=fr');

        expect($this->resolver->resolve($request))->toBe('fr');
    });

    it('returns null when query parameter is not set', function (): void {
        $request = Request::create('/');

        expect($this->resolver->resolve($request))->toBeNull();
    });

    it('returns null when query parameter is empty string', function (): void {
        $request = Request::create('/?locale=');

        expect($this->resolver->resolve($request))->toBeNull();
    });

    it('uses custom query key from config', function (): void {
        config(['lingua.resolvers.query.key' => 'lang']);

        $resolver = app(QueryResolver::class);
        $request = Request::create('/?lang=de');

        expect($resolver->resolve($request))->toBe('de');
    });

    it('handles locale with region code', function (): void {
        $request = Request::create('/?locale=en-US');

        expect($this->resolver->resolve($request))->toBe('en-US');
    });

    it('ignores other query parameters', function (): void {
        $request = Request::create('/?page=1&locale=fr&sort=name');

        expect($this->resolver->resolve($request))->toBe('fr');
    });
});
