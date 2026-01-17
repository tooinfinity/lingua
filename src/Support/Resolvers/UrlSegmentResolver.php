<?php

declare(strict_types=1);

namespace TooInfinity\Lingua\Support\Resolvers;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\Request;
use TooInfinity\Lingua\Contracts\LocaleResolverInterface;

final readonly class UrlSegmentResolver implements LocaleResolverInterface
{
    public function __construct(
        private ConfigRepository $config
    ) {}

    public function resolve(Request $request): ?string
    {
        /** @var int $position */
        $position = $this->config->get('lingua.resolvers.url_segment.position', 1);

        // Position is 1-based, convert to 0-based index
        $index = $position - 1;

        if ($index < 0) {
            return null;
        }

        $segments = $request->segments();

        if (! isset($segments[$index])) {
            return null;
        }

        // Laravel's segments() already filters out empty strings,
        // so we can safely return the segment directly
        /** @var string */
        return $segments[$index];
    }

    /**
     * @return array<string>
     */
    public function resolveAll(Request $request): array
    {
        $locale = $this->resolve($request);

        return $locale !== null ? [$locale] : [];
    }
}
