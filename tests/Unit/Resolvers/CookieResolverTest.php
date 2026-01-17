<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use TooInfinity\Lingua\Support\Resolvers\CookieResolver;

beforeEach(function (): void {
    $this->resolver = app(CookieResolver::class);
});

describe('CookieResolver', function (): void {
    it('returns locale from cookie', function (): void {
        $request = Request::create('/');
        $request->cookies->set('lingua_locale', 'fr');

        expect($this->resolver->resolve($request))->toBe('fr');
    });

    it('returns null when cookie is not set', function (): void {
        $request = Request::create('/');

        expect($this->resolver->resolve($request))->toBeNull();
    });

    it('returns null when cookie is empty string', function (): void {
        $request = Request::create('/');
        $request->cookies->set('lingua_locale', '');

        expect($this->resolver->resolve($request))->toBeNull();
    });

    it('uses custom cookie key from config', function (): void {
        config(['lingua.resolvers.cookie.key' => 'my_app_locale']);

        $resolver = app(CookieResolver::class);
        $request = Request::create('/');
        $request->cookies->set('my_app_locale', 'es');

        expect($resolver->resolve($request))->toBe('es');
    });

    it('handles locale with region code', function (): void {
        $request = Request::create('/');
        $request->cookies->set('lingua_locale', 'en-US');

        expect($this->resolver->resolve($request))->toBe('en-US');
    });
});
