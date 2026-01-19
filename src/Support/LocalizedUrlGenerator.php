<?php

declare(strict_types=1);

namespace TooInfinity\Lingua\Support;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Http\Request;
use TooInfinity\Lingua\Lingua;

/**
 * Service class for URL generation with locale transformation.
 *
 * Supports two strategies:
 * - prefix: Insert/replace locale segment in URL path (e.g., /fr/dashboard)
 * - domain: Replace host based on locale mapping (e.g., fr.example.com)
 */
final readonly class LocalizedUrlGenerator
{
    public function __construct(
        private ConfigRepository $config,
        private UrlGenerator $urlGenerator,
        private Lingua $lingua
    ) {}

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
        $locale ??= $this->lingua->getLocale($request);
        $strategy = $this->getStrategy();

        return match ($strategy) {
            'prefix' => $this->applyPrefixStrategy($url, $locale),
            'domain' => $this->applyDomainStrategy($url, $locale),
            default => $url,
        };
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
        $locale ??= $this->lingua->getLocale();
        $strategy = $this->getStrategy();

        // For prefix strategy, add locale to parameters if route expects it
        if ($strategy === 'prefix') {
            $parameters['locale'] = $locale;
        }

        $url = $this->urlGenerator->route($name, $parameters, $absolute);

        // For domain strategy, we need to replace the host
        if ($strategy === 'domain') {
            return $this->applyDomainStrategy($url, $locale);
        }

        return $url;
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
        $request ??= request();
        $currentUrl = $request->fullUrl();

        return $this->localizedUrl($currentUrl, $locale, $request);
    }

    /**
     * Get the configured URL strategy.
     */
    private function getStrategy(): ?string
    {
        /** @var string|null $strategy */
        $strategy = $this->config->get('lingua.url.strategy');

        return $strategy;
    }

    /**
     * Apply prefix strategy to URL.
     *
     * Inserts or replaces the locale segment in the URL path.
     */
    private function applyPrefixStrategy(string $url, string $locale): string
    {
        $parsed = parse_url($url);

        if ($parsed === false) {
            return $url;
        }

        /** @var array{scheme?: string, host?: string, port?: int, user?: string, pass?: string, path?: string, query?: string, fragment?: string} $parsed */
        $path = $parsed['path'] ?? '/';
        $segments = array_values(array_filter(explode('/', $path), static fn (string $s): bool => $s !== ''));

        /** @var int $segmentPosition */
        $segmentPosition = $this->config->get('lingua.url.prefix.segment', 1);
        $index = $segmentPosition - 1;

        // Check if current first segment looks like a locale and should be replaced
        if (isset($segments[$index]) && $this->looksLikeLocale($segments[$index])) {
            $segments[$index] = $locale;
        } else {
            // Insert locale at the configured position
            array_splice($segments, $index, 0, [$locale]);
        }

        $newPath = '/'.implode('/', $segments);

        return $this->rebuildUrl($parsed, $newPath);
    }

    /**
     * Apply domain strategy to URL.
     *
     * Replaces the host based on locale mapping.
     * Returns unchanged URL when locale has no host mapping (fail-soft).
     */
    private function applyDomainStrategy(string $url, string $locale): string
    {
        $parsed = parse_url($url);

        if ($parsed === false) {
            return $url;
        }

        /** @var array{scheme?: string, host?: string, port?: int, user?: string, pass?: string, path?: string, query?: string, fragment?: string} $parsed */

        /** @var array<string, string> $hosts */
        $hosts = $this->config->get('lingua.url.domain.hosts', []);

        // Fail-soft: return unchanged URL if no mapping exists for this locale
        if (! isset($hosts[$locale])) {
            return $url;
        }

        $parsed['host'] = $hosts[$locale];

        return $this->rebuildUrl($parsed, $parsed['path'] ?? '/');
    }

    /**
     * Check if a string looks like a locale code.
     */
    private function looksLikeLocale(string $segment): bool
    {
        // Match common locale patterns: en, en-US, en_US, etc.
        return preg_match('/^[a-z]{2}([_-][A-Za-z]{2})?$/', $segment) === 1;
    }

    /**
     * Rebuild a URL from parsed components.
     *
     * @param  array{scheme?: string, host?: string, port?: int, user?: string, pass?: string, path?: string, query?: string, fragment?: string}  $parsed  Parsed URL components
     * @param  string  $path  The new path
     */
    private function rebuildUrl(array $parsed, string $path): string
    {
        $url = '';

        if (isset($parsed['scheme'])) {
            $url .= $parsed['scheme'].'://';
        }

        if (isset($parsed['user'])) {
            $url .= $parsed['user'];
            if (isset($parsed['pass'])) {
                $url .= ':'.$parsed['pass'];
            }

            $url .= '@';
        }

        if (isset($parsed['host'])) {
            $url .= $parsed['host'];
        }

        if (isset($parsed['port'])) {
            $url .= ':'.$parsed['port'];
        }

        $url .= $path;

        if (isset($parsed['query'])) {
            $url .= '?'.$parsed['query'];
        }

        if (isset($parsed['fragment'])) {
            $url .= '#'.$parsed['fragment'];
        }

        return $url;
    }
}
