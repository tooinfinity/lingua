<?php

declare(strict_types=1);

namespace TooInfinity\Lingua;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Session\Session;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
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
     * @return array<string, mixed>
     */
    public function translations(): array
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
     */
    private function getLocaleFromSession(string $default): string
    {
        /** @var ConfigRepository $config */
        $config = $this->app->make(ConfigRepository::class);

        /** @var Session $session */
        $session = $this->app->make(Session::class);

        /** @var string $sessionKey */
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
}
