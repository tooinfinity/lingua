<?php

declare(strict_types=1);

namespace TooInfinity\Lingua\Support\Resolvers;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use TooInfinity\Lingua\Contracts\LocaleResolverInterface;

final readonly class SessionResolver implements LocaleResolverInterface
{
    public function __construct(
        private ConfigRepository $config,
        private Session $session
    ) {}

    public function resolve(Request $request): ?string
    {
        /** @var string $key */
        $key = $this->config->get('lingua.resolvers.session.key', 'lingua.locale');

        /** @var string|null $locale */
        $locale = $this->session->get($key);

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
