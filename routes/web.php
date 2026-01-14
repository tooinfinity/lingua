<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use TooInfinity\Lingua\Http\Controllers\LinguaLocaleController;

Route::post('/locale', LinguaLocaleController::class)
    ->middleware('web')
    ->name('lingua.locale.update');
