<?php

declare(strict_types=1);

use TooInfinity\Lingua\Facades\Lingua;

it('can get locale via facade', function (): void {
    expect(Lingua::getLocale())->toBe('en');
});

it('can set locale via facade', function (): void {
    Lingua::setLocale('fr');

    expect(Lingua::getLocale())->toBe('fr');
    expect(app()->getLocale())->toBe('fr');
});

it('can get supported locales via facade', function (): void {
    config(['lingua.locales' => ['en', 'fr', 'de']]);

    expect(Lingua::supportedLocales())->toBe(['en', 'fr', 'de']);
});

it('can get translations via facade', function (): void {
    expect(Lingua::translations())->toBeArray();
});
