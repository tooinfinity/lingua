<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use TooInfinity\Lingua\Http\Middleware\LinguaMiddleware;

beforeEach(function (): void {
    // Set up a simple route that returns JSON instead of Inertia view
    Route::middleware(['web', LinguaMiddleware::class])->get('/test-lingua', function () {
        // Resolve the shared closure to get actual data
        $shared = Inertia::getShared();
        $linguaData = isset($shared['lingua']) && is_callable($shared['lingua'])
            ? $shared['lingua']()
            : ($shared['lingua'] ?? null);

        return response()->json([
            'locale' => app()->getLocale(),
            'lingua' => $linguaData,
        ]);
    });
});

it('sets locale from session', function (): void {
    session()->put('lingua.locale', 'fr');

    $response = $this->get('/test-lingua');

    $response->assertOk();
    $response->assertJson(['locale' => 'fr']);
});

it('uses default locale when no session value exists', function (): void {
    $response = $this->get('/test-lingua');

    $response->assertOk();
    $response->assertJson(['locale' => 'en']);
});

it('shares translations via inertia', function (): void {
    $response = $this->get('/test-lingua');

    $response->assertOk();

    $linguaData = $response->json('lingua');

    expect($linguaData)->toBeArray()
        ->toHaveKey('locale')
        ->toHaveKey('locales')
        ->toHaveKey('translations');

    expect($linguaData['locale'])->toBe('en');
    expect($linguaData['locales'])->toBe(['en', 'fr', 'es']);
});
