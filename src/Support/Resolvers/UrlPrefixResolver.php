<?php

declare(strict_types=1);

namespace TooInfinity\Lingua\Support\Resolvers;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\Request;
use TooInfinity\Lingua\Contracts\LocaleResolverInterface;

/**
 * Resolves locale from URL path prefix with pattern validation.
 *
 * This resolver validates the URL segment against configurable regex patterns
 * to prevent false positives (e.g., 'dashboard' being treated as a locale).
 */
final readonly class UrlPrefixResolver implements LocaleResolverInterface
{
    public function __construct(
        private ConfigRepository $config
    ) {}

    public function resolve(Request $request): ?string
    {
        $segment = $this->getSegmentValue($request);

        if ($segment === null) {
            return null;
        }

        if (! $this->matchesLocalePattern($segment)) {
            return null;
        }

        return $segment;
    }

    /**
     * @return array<string>
     */
    public function resolveAll(Request $request): array
    {
        $locale = $this->resolve($request);

        // Return empty array when segment doesn't match locale patterns
        // This prevents false positives like 'dashboard' being treated as locale
        return $locale !== null ? [$locale] : [];
    }

    /**
     * Get the segment value from the configured position.
     */
    private function getSegmentValue(Request $request): ?string
    {
        /** @var int $position */
        $position = $this->config->get('lingua.resolvers.url_prefix.segment', 1);

        // Position is 1-based, convert to 0-based index
        $index = $position - 1;

        if ($index < 0) {
            return null;
        }

        $segments = $request->segments();

        if (! isset($segments[$index])) {
            // Check if optional is enabled - if so, return null gracefully
            /** @var bool $optional */
            $optional = $this->config->get('lingua.resolvers.url_prefix.optional', true);

            return null;
        }

        return $segments[$index];
    }

    /**
     * Check if the segment matches any of the configured locale patterns.
     */
    private function matchesLocalePattern(string $segment): bool
    {
        /** @var array<string> $patterns */
        $patterns = $this->config->get('lingua.resolvers.url_prefix.patterns', [
            '^[a-z]{2}([_-][A-Za-z]{2})?$',
        ]);

        foreach ($patterns as $pattern) {
            if (preg_match('/'.$pattern.'/', $segment) === 1) {
                return true;
            }
        }

        return false;
    }
}
