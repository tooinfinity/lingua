<?php

declare(strict_types=1);

namespace TooInfinity\Lingua\Support;

/**
 * Simple in-memory cache for loaded translation groups.
 *
 * This cache stores translations during a single request lifecycle
 * to avoid reloading the same translation groups multiple times.
 */
final class TranslationCache
{
    /**
     * In-memory cache storage for translation groups.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $cache = [];

    /**
     * Check if a translation group is cached for a locale.
     */
    public function has(string $locale, string $group): bool
    {
        return isset($this->cache[$locale][$group]);
    }

    /**
     * Get a cached translation group for a locale.
     *
     * @return array<string, mixed>|null
     */
    public function get(string $locale, string $group): ?array
    {
        if (! isset($this->cache[$locale][$group])) {
            return null;
        }

        /** @var array<string, mixed> $translations */
        $translations = $this->cache[$locale][$group];

        return $translations;
    }

    /**
     * Store a translation group in cache for a locale.
     *
     * @param  array<string, mixed>  $translations
     */
    public function put(string $locale, string $group, array $translations): void
    {
        if (! isset($this->cache[$locale])) {
            $this->cache[$locale] = [];
        }

        $this->cache[$locale][$group] = $translations;
    }

    /**
     * Get all cached groups for a locale.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getAllForLocale(string $locale): array
    {
        if (! isset($this->cache[$locale])) {
            return [];
        }

        /** @var array<string, array<string, mixed>> $localeCache */
        $localeCache = $this->cache[$locale];

        return $localeCache;
    }

    /**
     * Clear all cached translations.
     */
    public function flush(): void
    {
        $this->cache = [];
    }

    /**
     * Clear cached translations for a specific locale.
     */
    public function flushLocale(string $locale): void
    {
        unset($this->cache[$locale]);
    }

    /**
     * Clear a specific cached group for a locale.
     */
    public function forget(string $locale, string $group): void
    {
        unset($this->cache[$locale][$group]);
    }
}
