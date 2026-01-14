<?php

declare(strict_types=1);

use TooInfinity\Lingua\Lingua;

it('sets session locale when posting valid locale', function (): void {
    $this->post(route('lingua.locale.update'), ['locale' => 'fr'])
        ->assertRedirect();

    expect(session()->get('lingua.locale'))->toBe('fr');
});

it('rejects invalid locale', function (): void {
    $this->post(route('lingua.locale.update'), ['locale' => 'invalid'])
        ->assertSessionHasErrors('locale');
});

it('redirects back after setting locale', function (): void {
    $this->from('/previous-page')
        ->post(route('lingua.locale.update'), ['locale' => 'es'])
        ->assertRedirect('/previous-page');
});

it('updates app locale after setting', function (): void {
    $this->post(route('lingua.locale.update'), ['locale' => 'fr'])
        ->assertRedirect();

    /** @var Lingua $lingua */
    $lingua = app(Lingua::class);

    expect($lingua->getLocale())->toBe('fr');
});
