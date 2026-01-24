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
use TooInfinity\Lingua\Contracts\LocaleResolverInterface;
use TooInfinity\Lingua\Exceptions\UnsupportedLocaleException;
use TooInfinity\Lingua\Support\Resolvers\CookieResolver;
use TooInfinity\Lingua\Support\Resolvers\SessionResolver;

final readonly class Lingua
{
    /**
     * @param  array<LocaleResolverInterface>|null  $resolvers
     */
    public function __construct(private Application $app, private ?array $resolvers = null) {}

    /**
     * Get the current locale.
     *
     * @throws BindingResolutionException
     */
    public function getLocale(?Request $request = null): string
    {
        $this->app->make(ConfigRepository::class);

        $default = $this->getDefaultLocale();
        $resolvedLocale = $this->resolveLocale($request);

        if ($resolvedLocale !== null) {
            return $resolvedLocale;
        }

        return $this->normalizeLocale($default);
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
        $sessionKey = $config->get('lingua.resolvers.session.key', 'lingua.locale');

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
            $translations = $this->loadJsonTranslations();

            return $this->applyFallbackForJson($translations);
        }

        $translations = $this->loadPhpTranslations();

        return $this->applyFallbackForAllGroups($translations);
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

        $translations = $this->loadPhpTranslationGroup($locale, $group);

        return $this->applyFallbackForGroup($group, $translations, $locale);
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

        return $this->loadPhpTranslationsForLocale($locale);
    }

    /**
     * Load translations from PHP files in lang/{locale}/*.php for a specific locale.
     *
     * @return array<string, mixed>
     */
    private function loadPhpTranslationsForLocale(string $locale): array
    {
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
     * Apply fallback translations for all groups loaded in bulk.
     *
     * @param  array<string, mixed>  $translations
     * @return array<string, mixed>
     *
     * @throws BindingResolutionException
     */
    private function applyFallbackForAllGroups(array $translations): array
    {
        $currentLocale = $this->getLocale();
        $defaultLocale = $this->getDefaultLocale();

        if ($defaultLocale === $currentLocale) {
            return $translations;
        }

        $fallbackTranslations = $this->loadPhpTranslationsForLocale($defaultLocale);

        if ($fallbackTranslations === []) {
            return $translations;
        }

        return $this->mergeFallbackGroups($fallbackTranslations, $translations);
    }

    /**
     * Merge fallback translations with current translations for PHP groups.
     *
     * @param  array<string, mixed>  $fallbackTranslations
     * @param  array<string, mixed>  $translations
     * @return array<string, mixed>
     */
    private function mergeFallbackGroups(array $fallbackTranslations, array $translations): array
    {
        foreach ($fallbackTranslations as $group => $fallbackGroupTranslations) {
            if (! is_array($fallbackGroupTranslations)) {
                continue;
            }

            if (! isset($translations[$group]) || ! is_array($translations[$group])) {
                $translations[$group] = $fallbackGroupTranslations;

                continue;
            }

            /** @var array<string, mixed> $groupTranslations */
            $groupTranslations = $translations[$group];

            $translations[$group] = array_replace_recursive($fallbackGroupTranslations, $groupTranslations);
        }

        return $translations;
    }

    /**
     * Apply fallback translations for a single group when keys are missing.
     *
     * @param  array<string, mixed>  $translations
     * @return array<string, mixed>
     *
     * @throws BindingResolutionException
     */
    private function applyFallbackForGroup(string $group, array $translations, string $currentLocale): array
    {
        $defaultLocale = $this->getDefaultLocale();

        if ($defaultLocale === $currentLocale) {
            return $translations;
        }

        $fallbackTranslations = $this->loadPhpTranslationGroup($defaultLocale, $group);

        if ($fallbackTranslations === []) {
            return $translations;
        }

        /** @var array<string, mixed> $merged */
        $merged = array_replace_recursive($fallbackTranslations, $translations);

        return $merged;
    }

    /**
     * Apply fallback translations for JSON driver.
     *
     * @param  array<string, mixed>  $translations
     * @return array<string, mixed>
     *
     * @throws BindingResolutionException
     */
    private function applyFallbackForJson(array $translations): array
    {
        $defaultLocale = $this->getDefaultLocale();
        $currentLocale = $this->getLocale();

        if ($defaultLocale === $currentLocale) {
            return $translations;
        }

        $fallbackTranslations = $this->loadJsonTranslationsForLocale($defaultLocale);

        if ($fallbackTranslations === []) {
            return $translations;
        }

        return array_replace($fallbackTranslations, $translations);
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

        return $this->loadJsonTranslationsForLocale($locale);
    }

    /**
     * Load JSON translations for a specific locale.
     *
     * @return array<string, mixed>
     */
    private function loadJsonTranslationsForLocale(string $locale): array
    {
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
     * Resolve locale using configured resolver classes.
     *
     * Uses SessionResolver and CookieResolver in order of priority.
     * Returns the first supported locale found, or null if none.
     *
     * @throws BindingResolutionException
     */
    private function resolveLocale(?Request $request = null): ?string
    {
        $resolvers = $this->getResolvers();

        if (! $request instanceof Request) {
            $request = $this->app->make(Request::class);
        }

        foreach ($resolvers as $resolver) {
            $candidates = $resolver->resolveAll($request);

            foreach ($candidates as $candidate) {
                $normalized = $this->normalizeLocale($candidate);

                if ($this->isLocaleSupported($normalized)) {
                    return $normalized;
                }
            }
        }

        return null;
    }

    /**
     * Get the locale resolvers.
     *
     * @return array<LocaleResolverInterface>
     *
     * @throws BindingResolutionException
     */
    private function getResolvers(): array
    {
        if ($this->resolvers !== null) {
            return $this->resolvers;
        }

        return [
            $this->app->make(SessionResolver::class),
            $this->app->make(CookieResolver::class),
        ];
    }

    /**
     * Resolve the default locale, falling back to app.locale when needed.
     *
     * @throws BindingResolutionException
     */
    private function getDefaultLocale(): string
    {
        /** @var ConfigRepository $config */
        $config = $this->app->make(ConfigRepository::class);

        /** @var string|null $default */
        $default = $config->get('lingua.default');
        /** @var string $locale */
        $locale = $config->get('app.locale', 'en');

        return $default ?? $locale;
    }

    /**
     * Persist locale to a cookie when enabled in config.
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
