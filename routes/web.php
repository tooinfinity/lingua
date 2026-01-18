<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use TooInfinity\Lingua\Http\Controllers\LinguaLocaleController;

$controller = config('lingua.controller') ?? LinguaLocaleController::class;
$prefix = config('lingua.routes.prefix', '');
$middleware = config('lingua.routes.middleware', ['web']);

Route::group([
    'prefix' => $prefix,
    'middleware' => $middleware,
], function () use ($controller) {
    // Locale switching
    Route::post('/locale', $controller)->name('lingua.locale.update');

    // Translation lazy loading endpoints
    Route::get('/lingua/translations/{group}', [LinguaLocaleController::class, 'translations'])
        ->name('lingua.translations.group')
        ->where('group', '[a-zA-Z0-9_-]+');

    Route::post('/lingua/translations', [LinguaLocaleController::class, 'translationsForGroups'])
        ->name('lingua.translations.groups');

    Route::get('/lingua/groups', [LinguaLocaleController::class, 'availableGroups'])
        ->name('lingua.translations.available');
});
