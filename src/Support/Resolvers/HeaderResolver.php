<?php

declare(strict_types=1);

namespace TooInfinity\Lingua\Support\Resolvers;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\Request;
use TooInfinity\Lingua\Contracts\LocaleResolverInterface;

final readonly class HeaderResolver implements LocaleResolverInterface
{
    public function __construct(
        private ConfigRepository $config
    ) {}

    public function resolve(Request $request): ?string
    {
        $locales = $this->resolveAll($request);

        return $locales[0] ?? null;
    }

    /**
     * Resolve all locales from the Accept-Language header, ordered by preference.
     *
     * @return array<string>
     */
    public function resolveAll(Request $request): array
    {
        $header = $request->header('Accept-Language');

        if ($header === null || $header === '' || is_array($header)) {
            return [];
        }

        /** @var bool $useQuality */
        $useQuality = $this->config->get('lingua.resolvers.header.use_quality', true);

        if ($useQuality) {
            return $this->parseAllWithQuality($header);
        }

        return $this->parseAllSimple($header);
    }

    /**
     * Parse Accept-Language header with quality values, returning all locales sorted by quality.
     *
     * Example: "en-US,en;q=0.9,fr;q=0.8" returns ["en-US", "en", "fr"]
     *
     * @return array<string>
     */
    private function parseAllWithQuality(string $header): array
    {
        $locales = $this->parseAcceptLanguageHeader($header);

        if ($locales === []) {
            return [];
        }

        // Sort by quality (descending)
        uasort($locales, static fn (float $a, float $b): int => $b <=> $a);

        return array_keys($locales);
    }

    /**
     * Parse Accept-Language header without quality values, returning all locales in order.
     *
     * @return array<string>
     */
    private function parseAllSimple(string $header): array
    {
        $locales = [];
        $parts = explode(',', $header);

        foreach ($parts as $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            // Remove quality value if present (e.g., "en;q=0.9" -> "en")
            $locale = trim(explode(';', $part)[0]);

            if ($locale !== '') {
                $locales[] = $locale;
            }
        }

        return $locales;
    }

    /**
     * Parse the Accept-Language header into an array of locales with quality values.
     *
     * @return array<string, float> Associative array of locale => quality
     */
    private function parseAcceptLanguageHeader(string $header): array
    {
        $locales = [];
        $parts = explode(',', $header);

        foreach ($parts as $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            $segments = explode(';', $part);
            $locale = trim($segments[0]);

            if ($locale === '') {
                continue;
            }

            $quality = 1.0;

            if (isset($segments[1])) {
                $qualityPart = trim($segments[1]);

                if (str_starts_with($qualityPart, 'q=')) {
                    $qualityValue = (float) substr($qualityPart, 2);
                    $quality = max(0.0, min(1.0, $qualityValue));
                }
            }

            $locales[$locale] = $quality;
        }

        return $locales;
    }
}
