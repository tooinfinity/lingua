<?php

declare(strict_types=1);

namespace TooInfinity\Lingua\Support\Resolvers;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\Request;
use TooInfinity\Lingua\Contracts\LocaleResolverInterface;

/**
 * Resolves locale from domain (subdomain or full domain mapping).
 *
 * Supports:
 * - Full domain mapping (e.g., example.de → de, example.fr → fr)
 * - Subdomain extraction (e.g., fr.example.com → fr)
 * - Configurable evaluation order (full map first vs subdomain first)
 */
final readonly class DomainResolver implements LocaleResolverInterface
{
    public function __construct(
        private ConfigRepository $config
    ) {}

    public function resolve(Request $request): ?string
    {
        /** @var array<string> $order */
        $order = $this->config->get('lingua.resolvers.domain.order', ['full', 'subdomain']);

        foreach ($order as $strategy) {
            $locale = match ($strategy) {
                'full' => $this->resolveFromFullDomainMap($request),
                'subdomain' => $this->resolveFromSubdomain($request),
                default => null,
            };

            if ($locale !== null) {
                return $locale;
            }
        }

        return null;
    }

    /**
     * @return array<string>
     */
    public function resolveAll(Request $request): array
    {
        $locale = $this->resolve($request);

        return $locale !== null ? [$locale] : [];
    }

    /**
     * Resolve locale from full domain map.
     *
     * Example config: ['example.de' => 'de', 'example.fr' => 'fr']
     */
    private function resolveFromFullDomainMap(Request $request): ?string
    {
        /** @var array<string, string> $fullMap */
        $fullMap = $this->config->get('lingua.resolvers.domain.full_map', []);

        if (empty($fullMap)) {
            return null;
        }

        $host = $request->getHost();

        return $fullMap[$host] ?? null;
    }

    /**
     * Resolve locale from subdomain.
     *
     * Example: fr.example.com → fr
     */
    private function resolveFromSubdomain(Request $request): ?string
    {
        /** @var bool $enabled */
        $enabled = $this->config->get('lingua.resolvers.domain.subdomain.enabled', true);

        if (! $enabled) {
            return null;
        }

        $host = $request->getHost();
        $subdomain = $this->extractSubdomain($host);

        if ($subdomain === null) {
            return null;
        }

        // Validate against base domains if configured
        if (! $this->isAllowedBaseDomain($host)) {
            return null;
        }

        // Validate against patterns
        if (! $this->matchesSubdomainPattern($subdomain)) {
            return null;
        }

        return $subdomain;
    }

    /**
     * Extract subdomain from host based on configured label position.
     */
    private function extractSubdomain(string $host): ?string
    {
        /** @var int $labelPosition */
        $labelPosition = $this->config->get('lingua.resolvers.domain.subdomain.label', 1);

        // Split host by dots
        $parts = explode('.', $host);

        // Need at least 3 parts for a subdomain (subdomain.domain.tld)
        if (count($parts) < 3) {
            return null;
        }

        // Label position is 1-based from the left
        $index = $labelPosition - 1;

        if ($index < 0 || ! isset($parts[$index])) {
            return null;
        }

        return $parts[$index];
    }

    /**
     * Check if the host matches any of the configured base domains.
     */
    private function isAllowedBaseDomain(string $host): bool
    {
        /** @var array<string> $baseDomains */
        $baseDomains = $this->config->get('lingua.resolvers.domain.subdomain.base_domains', []);

        // If no base domains configured, allow all
        if (empty($baseDomains)) {
            return true;
        }

        foreach ($baseDomains as $baseDomain) {
            // Check if host ends with the base domain
            if (str_ends_with($host, '.'.$baseDomain) || $host === $baseDomain) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the subdomain matches any of the configured patterns.
     */
    private function matchesSubdomainPattern(string $subdomain): bool
    {
        /** @var array<string> $patterns */
        $patterns = $this->config->get('lingua.resolvers.domain.subdomain.patterns', [
            '^[a-z]{2}([_-][A-Za-z]{2})?$',
        ]);

        foreach ($patterns as $pattern) {
            if (preg_match('/'.$pattern.'/', $subdomain) === 1) {
                return true;
            }
        }

        return false;
    }
}
