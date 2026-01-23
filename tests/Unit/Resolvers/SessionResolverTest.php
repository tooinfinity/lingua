<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use TooInfinity\Lingua\Support\Resolvers\SessionResolver;

beforeEach(function (): void {
    $this->resolver = app(SessionResolver::class);
});

describe('SessionResolver', function (): void {
    it('returns locale from session', function (): void {
        session()->put('lingua.locale', 'fr');

        $request = Request::create('/');

        expect($this->resolver->resolve($request))->toBe('fr');
    });

    it('returns null when session is empty', function (): void {
        $request = Request::create('/');

        expect($this->resolver->resolve($request))->toBeNull();
    });

    it('uses custom session key from config', function (): void {
        config(['lingua.resolvers.session.key' => 'custom.locale.key']);
        session()->put('custom.locale.key', 'de');

        $resolver = app(SessionResolver::class);
        $request = Request::create('/');

        expect($resolver->resolve($request))->toBe('de');
    });

    it('returns null when custom key has no value', function (): void {
        config(['lingua.resolvers.session.key' => 'custom.locale.key']);

        $resolver = app(SessionResolver::class);
        $request = Request::create('/');

        expect($resolver->resolve($request))->toBeNull();
    });

    it('returns locale array from resolveAll', function (): void {
        session()->put('lingua.locale', 'fr');

        $request = Request::create('/');

        expect($this->resolver->resolveAll($request))->toBe(['fr']);
    });

    it('returns empty array from resolveAll when session is empty', function (): void {
        $request = Request::create('/');

        expect($this->resolver->resolveAll($request))->toBe([]);
    });
});
