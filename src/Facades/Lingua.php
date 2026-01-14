<?php

declare(strict_types=1);

namespace TooInfinity\Lingua\Facades;

use Illuminate\Support\Facades\Facade;
use TooInfinity\Lingua\Lingua as LinguaService;

/**
 * @method static string getLocale()
 * @method static void setLocale(string $locale)
 * @method static array<string> supportedLocales()
 * @method static array<string, mixed> translations()
 *
 * @see LinguaService
 */
final class Lingua extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return LinguaService::class;
    }
}
