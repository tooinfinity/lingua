<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use TooInfinity\Lingua\Lingua;

beforeEach(function (): void {
    $this->lingua = app(Lingua::class);
});

describe('Locale resolution', function (): void {
    it('uses the session locale when available', function (): void {
        config(['lingua.locales' => ['en', 'fr']]);
        session()->put('lingua.locale', 'fr');

        $request = Request::create('/');
        $request->cookies->set('lingua_locale', 'en');

        expect($this->lingua->getLocale($request))->toBe('fr');
    });

    it('uses the cookie locale when session is empty and request provided', function (): void {
        config(['lingua.locales' => ['en', 'fr']]);

        $request = Request::create('/');
        $request->cookies->set('lingua_locale', 'fr');

        expect($this->lingua->getLocale($request))->toBe('fr');
    });

    it('ignores cookie when no request is provided', function (): void {
        config(['lingua.locales' => ['en', 'fr']]);
        session()->put('lingua.locale', 'en');

        expect($this->lingua->getLocale())->toBe('en');
    });

    it('falls back to default locale when no candidates are valid', function (): void {
        config(['lingua.locales' => ['en', 'fr']]);
        config(['lingua.default' => 'fr']);

        $request = Request::create('/');
        $request->cookies->set('lingua_locale', 'de');

        expect($this->lingua->getLocale($request))->toBe('fr');
    });

    it('falls back to app locale when default is null', function (): void {
        config(['lingua.default' => null]);
        config(['app.locale' => 'de']);

        expect($this->lingua->getLocale())->toBe('de');
    });

    it('normalizes locales from session and cookie', function (): void {
        config(['lingua.locales' => ['en_US', 'fr']]);

        session()->put('lingua.locale', 'EN-us');

        $request = Request::create('/');
        $request->cookies->set('lingua_locale', 'FR');

        expect($this->lingua->getLocale($request))->toBe('en_US');
    });

    it('uses cookie when session locale is unsupported', function (): void {
        config(['lingua.locales' => ['en', 'fr']]);
        session()->put('lingua.locale', 'de');

        $request = Request::create('/');
        $request->cookies->set('lingua_locale', 'fr');

        expect($this->lingua->getLocale($request))->toBe('fr');
    });
});
