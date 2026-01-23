<?php

declare(strict_types=1);

namespace TooInfinity\Lingua;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Contracts\Session\Session;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\File;
use TooInfinity\Lingua\Exceptions\UnsupportedLocaleException;
use TooInfinity\Lingua\Support\LocaleResolverManager;

final readonly class Lingua
{
    public function __construct(
        private Application $app
    ) {}

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

        $this->persistLocaleCookie($config, $normalizedLocale);
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
     * Use translationsFor() or translationGroup() for specific groups.
     *
     * @return array<string, mixed>
     *
     * @throws BindingResolutionException
     * @throws FileNotFoundException
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
     * Load translations for specific groups.
     *
     * @param  array<string>  $groups  The translation groups to load
     * @return array<string, mixed>
     *
     * @throws BindingResolutionException
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
     * @throws BindingResolutionException
     */
    public function translationGroup(string $group): array
    {
        $locale = $this->getLocale();

        return $this->loadPhpTranslationGroup($locale, $group);
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
     * Persist locale to a cookie when enabled in config.
     *
     * @throws BindingResolutionException
     */
    private function persistLocaleCookie(ConfigRepository $config, string $locale): void
    {
        /** @var bool $enabled */
        $enabled = $config->get('lingua.resolvers.cookie.persist_on_set', false);

        if (! $enabled) {
            return;
        }

        /** @var string $cookieName */
        $cookieName = $config->get('lingua.resolvers.cookie.key', 'lingua_locale');
        /** @var int $ttl */
        $ttl = $config->get('lingua.resolvers.cookie.ttl_minutes', 60 * 24 * 30);

        Cookie::queue($cookieName, $locale, $ttl);
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
