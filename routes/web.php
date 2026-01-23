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
    Route::post('/locale', $controller)->name('lingua.locale.update');
});
