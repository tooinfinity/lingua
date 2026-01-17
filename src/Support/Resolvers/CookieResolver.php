<?php

declare(strict_types=1);

namespace TooInfinity\Lingua\Support\Resolvers;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\Request;
use TooInfinity\Lingua\Contracts\LocaleResolverInterface;

final readonly class CookieResolver implements LocaleResolverInterface
{
    public function __construct(
        private ConfigRepository $config
    ) {}

    public function resolve(Request $request): ?string
    {
        /** @var string $key */
        $key = $this->config->get('lingua.resolvers.cookie.key', 'lingua_locale');

        /** @var string|null $locale */
        $locale = $request->cookie($key);

        if ($locale === null || $locale === '') {
            return null;
        }

        return $locale;
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
