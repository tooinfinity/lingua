<?php

declare(strict_types=1);

namespace TooInfinity\Lingua\Facades;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use TooInfinity\Lingua\Lingua as LinguaService;

/**
 * @method static string getLocale(Request|null $request = null)
 * @method static void setLocale(string $locale)
 * @method static array<string> supportedLocales()
 * @method static array<string, mixed> translations()
 * @method static array<string, mixed> translationsFor(array<string> $groups)
 * @method static array<string, mixed> translationGroup(string $group)
 * @method static array<string> availableGroups()
 * @method static bool isLazyLoadingEnabled()
 * @method static bool isAutoDetectPageEnabled()
 * @method static array<string, mixed> translationsForPage(string $pageName)
 * @method static array<string> getGroupsForPage(string $pageName)
 * @method static void clearTranslationCache(?string $locale = null)
 * @method static bool isRtl(?string $locale = null)
 * @method static array<string> getRtlLocales()
 * @method static string getDirection(?string $locale = null)
 * @method static string localizedUrl(string $url, ?string $locale = null, ?Request $request = null)
 * @method static string localizedRoute(string $name, array<string, mixed> $parameters = [], ?string $locale = null, bool $absolute = true)
 * @method static string switchLocaleUrl(string $locale, ?Request $request = null)
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
