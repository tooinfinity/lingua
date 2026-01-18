<?php

declare(strict_types=1);

namespace TooInfinity\Lingua\Facades;

use Illuminate\Support\Facades\Facade;
use TooInfinity\Lingua\Lingua as LinguaService;

/**
 * @method static string getLocale(\Illuminate\Http\Request|null $request = null)
 * @method static void setLocale(string $locale)
 * @method static array<string> supportedLocales()
 * @method static array<string, mixed> translations()
 * @method static bool isRtl(?string $locale = null)
 * @method static array<string> getRtlLocales()
 * @method static string getDirection(?string $locale = null)
 * @method static string localizedUrl(string $url, ?string $locale = null, ?\Illuminate\Http\Request $request = null)
 * @method static string localizedRoute(string $name, array<string, mixed> $parameters = [], ?string $locale = null, bool $absolute = true)
 * @method static string switchLocaleUrl(string $locale, ?\Illuminate\Http\Request $request = null)
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
