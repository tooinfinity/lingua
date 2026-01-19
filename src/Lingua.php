<?php

declare(strict_types=1);

namespace TooInfinity\Lingua;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Contracts\Session\Session;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use JsonException;
use Psr\SimpleCache\InvalidArgumentException;
use TooInfinity\Lingua\Exceptions\UnsupportedLocaleException;
use TooInfinity\Lingua\Support\LocaleResolverManager;
use TooInfinity\Lingua\Support\LocalizedUrlGenerator;
use TooInfinity\Lingua\Support\PageTranslationResolver;
use TooInfinity\Lingua\Support\TranslationCache;

final readonly class Lingua
{
    private TranslationCache $translationCache;

    public function __construct(
        private Application $app
    ) {
        $this->translationCache = new TranslationCache;
    }

    /**
     * Get the current locale.
     *
     * When called without a request, falls back to session-based resolution
     * for backward compatibility.
     *
     * @throws BindingResolutionException
     */
    public function getLocale(?Request $request = null): string
    {
        /** @var ConfigRepository $config */
        $config = $this->app->make(ConfigRepository::class);

        /** @var string $default */
        $default = $config->get('lingua.default') ?? $config->get('app.locale', 'en');

        // If a request is provided, use the resolver manager
        if ($request instanceof Request) {
            $resolvedLocale = $this->resolveLocaleFromRequest($request);

            if ($resolvedLocale !== null) {
                return $resolvedLocale;
            }

            return $this->normalizeLocale($default);
        }

        // Backward compatibility: session-only resolution when no request provided
        return $this->getLocaleFromSession($default);
    }

    /**
     * @throws BindingResolutionException
     */
    public function setLocale(string $locale): void
    {
        /** @var ConfigRepository $config */
        $config = $this->app->make(ConfigRepository::class);

        /** @var Session $session */
        $session = $this->app->make(Session::class);

        /** @var string $sessionKey */
        $sessionKey = $config->get('lingua.session_key', 'lingua.locale');

        $normalizedLocale = $this->normalizeLocale($locale);
        $this->validateLocale($normalizedLocale);

        $session->put($sessionKey, $normalizedLocale);
        $this->app->setLocale($normalizedLocale);
    }

    /**
     * Normalize a locale string to a consistent format.
     *
     * Handles common variations:
     * - Converts hyphens to underscores (en-US → en_US)
     * - Normalizes case: lowercase language, uppercase region (EN-us → en_US)
     * - Trims whitespace
     */
    public function normalizeLocale(string $locale): string
    {
        $locale = trim($locale);

        // Replace hyphens with underscores for consistency
        $locale = str_replace('-', '_', $locale);

        // Handle locale with region code (e.g., en_US, pt_BR)
        if (str_contains($locale, '_')) {
            $parts = explode('_', $locale, 2);
            $language = mb_strtolower($parts[0]);
            $region = mb_strtoupper($parts[1]);

            return $language.'_'.$region;
        }

        // Simple locale without region - lowercase
        return mb_strtolower($locale);
    }

    /**
     * Validate that a locale is in the supported locales list.
     *
     * @throws UnsupportedLocaleException When the locale is not supported
     * @throws BindingResolutionException
     */
    public function validateLocale(string $locale): void
    {
        $supportedLocales = $this->supportedLocales();

        // Also normalize supported locales for comparison
        $normalizedSupported = array_map(
            $this->normalizeLocale(...),
            $supportedLocales
        );

        if (! in_array($locale, $normalizedSupported, true)) {
            throw new UnsupportedLocaleException($locale, $supportedLocales);
        }
    }

    /**
     * Check if a locale is supported.
     *
     * @throws BindingResolutionException
     */
    public function isLocaleSupported(string $locale): bool
    {
        try {
            $this->validateLocale($this->normalizeLocale($locale));

            return true;
        } catch (UnsupportedLocaleException) {
            return false;
        }
    }

    /**
     * @return array<string>
     *
     * @throws BindingResolutionException
     */
    public function supportedLocales(): array
    {
        /** @var ConfigRepository $config */
        $config = $this->app->make(ConfigRepository::class);

        /** @var array<string> $locales */
        $locales = $config->get('lingua.locales', ['en']);

        return $locales;
    }

    /**
     * Get translations for the current locale based on the configured driver.
     *
     * When lazy loading is enabled and using the PHP driver, only default groups
     * are loaded. Use translationsFor() or translationGroup() for specific groups.
     *
     * @return array<string, mixed>
     *
     * @throws BindingResolutionException
     * @throws FileNotFoundException
     * @throws InvalidArgumentException
     * @throws JsonException
     */
    public function translations(): array
    {
        /** @var ConfigRepository $config */
        $config = $this->app->make(ConfigRepository::class);

        /** @var string $driver */
        $driver = $config->get('lingua.translation_driver', 'php');

        // JSON driver always loads all translations (single file)
        if ($driver === 'json') {
            return $this->loadJsonTranslations();
        }

        // Check if lazy loading is enabled
        /** @var bool $lazyLoadingEnabled */
        $lazyLoadingEnabled = $config->get('lingua.lazy_loading.enabled', false);

        if ($lazyLoadingEnabled) {
            /** @var array<string> $defaultGroups */
            $defaultGroups = $config->get('lingua.lazy_loading.default_groups', []);

            return $this->translationsFor($defaultGroups);
        }

        return $this->loadPhpTranslations();
    }

    /**
     * Load translations for specific groups.
     *
     * @param  array<string>  $groups  The translation groups to load
     * @return array<string, mixed>
     *
     * @throws BindingResolutionException|InvalidArgumentException
     */
    public function translationsFor(array $groups): array
    {
        if ($groups === []) {
            return [];
        }

        $translations = [];

        foreach ($groups as $group) {
            $groupTranslations = $this->translationGroup($group);
            if ($groupTranslations !== []) {
                $translations[$group] = $groupTranslations;
            }
        }

        return $translations;
    }

    /**
     * Load a single translation group.
     *
     * @return array<string, mixed>
     *
     * @throws BindingResolutionException|InvalidArgumentException
     */
    public function translationGroup(string $group): array
    {
        $locale = $this->getLocale();

        // Check in-memory cache first
        if ($this->translationCache->has($locale, $group)) {
            /** @var array<string, mixed> $cached */
            $cached = $this->translationCache->get($locale, $group);

            return $cached;
        }

        // Check persistent cache if enabled
        $cachedTranslations = $this->getFromPersistentCache($locale, $group);
        if ($cachedTranslations !== null) {
            $this->translationCache->put($locale, $group, $cachedTranslations);

            return $cachedTranslations;
        }

        // Load from file
        $translations = $this->loadPhpTranslationGroup($locale, $group);

        // Store in both caches
        $this->translationCache->put($locale, $group, $translations);
        $this->storeToPersistentCache($locale, $group, $translations);

        return $translations;
    }

    /**
     * Get all available translation groups for the current locale.
     *
     * @return array<string>
     *
     * @throws BindingResolutionException
     */
    public function availableGroups(): array
    {
        $locale = $this->getLocale();
        $path = $this->app->langPath($locale);

        if (! File::isDirectory($path)) {
            return [];
        }

        $groups = [];
        $files = File::files($path);

        foreach ($files as $file) {
            if ($file->getExtension() === 'php') {
                $groups[] = $file->getFilenameWithoutExtension();
            }
        }

        sort($groups);

        return $groups;
    }

    /**
     * Check if lazy loading is enabled.
     *
     * @throws BindingResolutionException
     */
    public function isLazyLoadingEnabled(): bool
    {
        /** @var ConfigRepository $config */
        $config = $this->app->make(ConfigRepository::class);

        /** @var bool $enabled */
        $enabled = $config->get('lingua.lazy_loading.enabled', false);

        return $enabled;
    }

    /**
     * Check if auto-detect page is enabled.
     *
     * @throws BindingResolutionException
     */
    public function isAutoDetectPageEnabled(): bool
    {
        /** @var ConfigRepository $config */
        $config = $this->app->make(ConfigRepository::class);

        /** @var bool $enabled */
        $enabled = $config->get('lingua.lazy_loading.auto_detect_page', true);

        return $enabled;
    }

    /**
     * Get translation groups for a specific Inertia page.
     *
     * This method resolves the page name to translation groups and merges
     * them with the default groups configured for lazy loading.
     *
     * @param  string  $pageName  The Inertia page component name
     * @return array<string, mixed>
     *
     * @throws BindingResolutionException
     */
    public function translationsForPage(string $pageName): array
    {
        /** @var PageTranslationResolver $resolver */
        $resolver = $this->app->make(PageTranslationResolver::class);

        $pageGroups = $resolver->resolve($pageName);

        /** @var ConfigRepository $config */
        $config = $this->app->make(ConfigRepository::class);

        /** @var array<string> $defaultGroups */
        $defaultGroups = $config->get('lingua.lazy_loading.default_groups', []);

        $allGroups = array_unique(array_merge($defaultGroups, $pageGroups));

        return $this->translationsFor($allGroups);
    }

    /**
     * Get translation groups for a page name without loading them.
     *
     * @param  string  $pageName  The Inertia page component name
     * @return array<string>
     *
     * @throws BindingResolutionException
     */
    public function getGroupsForPage(string $pageName): array
    {
        /** @var PageTranslationResolver $resolver */
        $resolver = $this->app->make(PageTranslationResolver::class);

        return $resolver->resolve($pageName);
    }

    /**
     * Clear the translation cache.
     *
     * @throws BindingResolutionException
     */
    public function clearTranslationCache(?string $locale = null): void
    {
        if ($locale !== null) {
            $this->translationCache->flushLocale($locale);
            $this->clearPersistentCacheForLocale($locale);
        } else {
            $this->translationCache->flush();
            $this->clearAllPersistentCache();
        }
    }

    /**
     * Check if a locale uses right-to-left text direction.
     *
     * When no locale is provided, checks the current locale.
     * Supports both full locale codes (ar_SA) and base language codes (ar).
     */
    public function isRtl(?string $locale = null): bool
    {
        $localeToCheck = $locale ?? $this->getLocale();
        $baseLanguage = $this->extractBaseLanguage($localeToCheck);

        return in_array($baseLanguage, $this->getRtlLocales(), true);
    }

    /**
     * Get the list of configured RTL locales.
     *
     * @return array<string>
     *
     * @throws BindingResolutionException
     */
    public function getRtlLocales(): array
    {
        /** @var ConfigRepository $config */
        $config = $this->app->make(ConfigRepository::class);

        /** @var array<string> $rtlLocales */
        $rtlLocales = $config->get('lingua.rtl_locales', [
            'ar', 'he', 'fa', 'ur', 'ps', 'sd', 'ku', 'ug', 'yi', 'prs', 'dv',
        ]);

        return $rtlLocales;
    }

    /**
     * Get the text direction for a locale.
     *
     * Returns 'rtl' for right-to-left locales, 'ltr' for left-to-right.
     * When no locale is provided, uses the current locale.
     */
    public function getDirection(?string $locale = null): string
    {
        return $this->isRtl($locale) ? 'rtl' : 'ltr';
    }

    /**
     * Generate a localized URL from a given URL.
     *
     * @param  string  $url  The URL to localize
     * @param  string|null  $locale  The target locale (defaults to current locale)
     * @param  Request|null  $request  Optional request for context
     *
     * @throws BindingResolutionException
     */
    public function localizedUrl(string $url, ?string $locale = null, ?Request $request = null): string
    {
        /** @var LocalizedUrlGenerator $generator */
        $generator = $this->app->make(LocalizedUrlGenerator::class);

        return $generator->localizedUrl($url, $locale, $request);
    }

    /**
     * Generate a localized route URL.
     *
     * @param  string  $name  The route name
     * @param  array<string, mixed>  $parameters  Route parameters
     * @param  string|null  $locale  The target locale (defaults to current locale)
     * @param  bool  $absolute  Whether to generate an absolute URL
     *
     * @throws BindingResolutionException
     */
    public function localizedRoute(
        string $name,
        array $parameters = [],
        ?string $locale = null,
        bool $absolute = true
    ): string {
        /** @var LocalizedUrlGenerator $generator */
        $generator = $this->app->make(LocalizedUrlGenerator::class);

        return $generator->localizedRoute($name, $parameters, $locale, $absolute);
    }

    /**
     * Generate a URL to switch the current page to a different locale.
     *
     * @param  string  $locale  The target locale
     * @param  Request|null  $request  The current request (defaults to current request)
     *
     * @throws BindingResolutionException
     */
    public function switchLocaleUrl(string $locale, ?Request $request = null): string
    {
        /** @var LocalizedUrlGenerator $generator */
        $generator = $this->app->make(LocalizedUrlGenerator::class);

        return $generator->switchLocaleUrl($locale, $request);
    }

    /**
     * Load translations from PHP files in lang/{locale}/*.php.
     *
     * @return array<string, mixed>
     *
     * @throws BindingResolutionException
     */
    private function loadPhpTranslations(): array
    {
        $locale = $this->getLocale();
        $path = $this->app->langPath($locale);

        if (! File::isDirectory($path)) {
            return [];
        }

        $translations = [];
        $files = File::files($path);

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $key = $file->getFilenameWithoutExtension();
            $content = require $file->getPathname();

            if (is_array($content)) {
                $translations[$key] = $content;
            }
        }

        return $translations;
    }

    /**
     * Load translations from JSON file at lang/{locale}.json.
     *
     * @return array<string, mixed>
     *
     * @throws FileNotFoundException
     * @throws BindingResolutionException
     */
    private function loadJsonTranslations(): array
    {
        $locale = $this->getLocale();
        $path = $this->app->langPath($locale.'.json');

        if (! File::exists($path)) {
            return [];
        }

        $content = File::get($path);

        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            return [];
        }

        return $decoded;
    }

    /**
     * Load a single PHP translation group for a specific locale.
     *
     * @return array<string, mixed>
     */
    private function loadPhpTranslationGroup(string $locale, string $group): array
    {
        $path = $this->app->langPath($locale.'/'.$group.'.php');

        if (! File::exists($path)) {
            return [];
        }

        $content = require $path;

        if (! is_array($content)) {
            return [];
        }

        /** @var array<string, mixed> $content */
        return $content;
    }

    /**
     * Get translations from persistent cache.
     *
     * @return array<string, mixed>|null
     *
     * @throws BindingResolutionException|InvalidArgumentException
     */
    private function getFromPersistentCache(string $locale, string $group): ?array
    {
        /** @var ConfigRepository $config */
        $config = $this->app->make(ConfigRepository::class);

        /** @var bool $cacheEnabled */
        $cacheEnabled = $config->get('lingua.lazy_loading.cache.enabled', true);

        if (! $cacheEnabled) {
            return null;
        }

        /** @var CacheRepository $cache */
        $cache = $this->app->make(CacheRepository::class);

        $cacheKey = $this->buildCacheKey($locale, $group);

        /** @var array<string, mixed>|null $cached */
        $cached = $cache->get($cacheKey);

        return $cached;
    }

    /**
     * Store translations to persistent cache.
     *
     * @param  array<string, mixed>  $translations
     *
     * @throws BindingResolutionException
     */
    private function storeToPersistentCache(string $locale, string $group, array $translations): void
    {
        /** @var ConfigRepository $config */
        $config = $this->app->make(ConfigRepository::class);

        /** @var bool $cacheEnabled */
        $cacheEnabled = $config->get('lingua.lazy_loading.cache.enabled', true);

        if (! $cacheEnabled) {
            return;
        }

        /** @var CacheRepository $cache */
        $cache = $this->app->make(CacheRepository::class);

        /** @var int $ttl */
        $ttl = $config->get('lingua.lazy_loading.cache.ttl', 3600);

        $cacheKey = $this->buildCacheKey($locale, $group);

        $cache->put($cacheKey, $translations, $ttl);
    }

    /**
     * Clear persistent cache for a specific locale.
     *
     * @throws BindingResolutionException
     */
    private function clearPersistentCacheForLocale(string $locale): void
    {
        /** @var ConfigRepository $config */
        $config = $this->app->make(ConfigRepository::class);

        /** @var bool $cacheEnabled */
        $cacheEnabled = $config->get('lingua.lazy_loading.cache.enabled', true);

        if (! $cacheEnabled) {
            return;
        }

        // Clear cache for all available groups for this locale
        $groups = $this->getAvailableGroupsForLocale($locale);

        /** @var CacheRepository $cache */
        $cache = $this->app->make(CacheRepository::class);

        foreach ($groups as $group) {
            $cacheKey = $this->buildCacheKey($locale, $group);
            $cache->forget($cacheKey);
        }
    }

    /**
     * Clear all persistent translation cache.
     *
     * @throws BindingResolutionException
     */
    private function clearAllPersistentCache(): void
    {
        foreach ($this->supportedLocales() as $locale) {
            $this->clearPersistentCacheForLocale($locale);
        }
    }

    /**
     * Build a cache key for a translation group.
     *
     * @throws BindingResolutionException
     */
    private function buildCacheKey(string $locale, string $group): string
    {
        /** @var ConfigRepository $config */
        $config = $this->app->make(ConfigRepository::class);

        /** @var string $prefix */
        $prefix = $config->get('lingua.lazy_loading.cache.prefix', 'lingua_translations');

        return $prefix.'.'.$locale.'.'.$group;
    }

    /**
     * Get available groups for a specific locale.
     *
     * @return array<string>
     */
    private function getAvailableGroupsForLocale(string $locale): array
    {
        $path = $this->app->langPath($locale);

        if (! File::isDirectory($path)) {
            return [];
        }

        $groups = [];
        $files = File::files($path);

        foreach ($files as $file) {
            if ($file->getExtension() === 'php') {
                $groups[] = $file->getFilenameWithoutExtension();
            }
        }

        return $groups;
    }

    /**
     * Resolve locale from request using the configured resolution order.
     */
    private function resolveLocaleFromRequest(Request $request): ?string
    {
        /** @var LocaleResolverManager $manager */
        $manager = $this->app->make(LocaleResolverManager::class);

        return $manager->resolve(
            $request,
            $this->isLocaleSupported(...),
            $this->normalizeLocale(...)
        );
    }

    /**
     * Get locale from session (backward compatibility method).
     *
     * @throws BindingResolutionException
     */
    private function getLocaleFromSession(string $default): string
    {
        /** @var ConfigRepository $config */
        $config = $this->app->make(ConfigRepository::class);

        /** @var Session $session */
        $session = $this->app->make(Session::class);

        $sessionKey = $this->getSessionKey($config);

        /** @var string $locale */
        $locale = $session->get($sessionKey, $default);

        return $locale;
    }

    /**
     * Get the session key for storing locale.
     *
     * Checks legacy config first for backward compatibility, then falls back to new resolver config.
     */
    private function getSessionKey(ConfigRepository $config): string
    {
        // Check if legacy session_key has been customized (different from default)
        /** @var string $legacyKey */
        $legacyKey = $config->get('lingua.session_key', 'lingua.locale');

        // Check the new resolver config
        /** @var string|null $resolverKey */
        $resolverKey = $config->get('lingua.resolvers.session.key');

        // If legacy key was customized and resolver key is still at default, use legacy
        if ($legacyKey !== 'lingua.locale' && $resolverKey === 'lingua.locale') {
            return $legacyKey;
        }

        // Otherwise use resolver config (with fallback to legacy for full backward compat)
        return $resolverKey ?? $legacyKey;
    }

    /**
     * Extract the base language code from a locale string.
     *
     * Examples:
     * - ar_SA → ar
     * - en-US → en
     * - fr → fr
     */
    private function extractBaseLanguage(string $locale): string
    {
        $normalized = $this->normalizeLocale($locale);

        if (str_contains($normalized, '_')) {
            return explode('_', $normalized, 2)[0];
        }

        return $normalized;
    }
}
