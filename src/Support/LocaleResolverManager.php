<?php

declare(strict_types=1);

namespace TooInfinity\Lingua\Support;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use TooInfinity\Lingua\Contracts\LocaleResolverInterface;
use TooInfinity\Lingua\Support\Resolvers\CookieResolver;
use TooInfinity\Lingua\Support\Resolvers\DomainResolver;
use TooInfinity\Lingua\Support\Resolvers\HeaderResolver;
use TooInfinity\Lingua\Support\Resolvers\QueryResolver;
use TooInfinity\Lingua\Support\Resolvers\SessionResolver;
use TooInfinity\Lingua\Support\Resolvers\UrlPrefixResolver;

final readonly class LocaleResolverManager
{
    /**
     * @var array<string, class-string<LocaleResolverInterface>>
     */
    private const array RESOLVER_MAP = [
        'session' => SessionResolver::class,
        'cookie' => CookieResolver::class,
        'query' => QueryResolver::class,
        'header' => HeaderResolver::class,
        'url_prefix' => UrlPrefixResolver::class,
        'domain' => DomainResolver::class,
    ];

    public function __construct(
        private Application $app,
        private ConfigRepository $config
    ) {}

    /**
     * Resolve the locale from the request using the configured resolution order.
     *
     * @param  Request  $request  The HTTP request
     * @param  callable(string): bool  $isSupported  Callback to check if a locale is supported
     * @param  callable(string): string  $normalize  Callback to normalize a locale
     */
    public function resolve(Request $request, callable $isSupported, callable $normalize): ?string
    {
        $order = $this->getResolutionOrder();

        foreach ($order as $resolverName) {
            $resolver = $this->createResolver($resolverName);

            if (! $resolver instanceof LocaleResolverInterface) {
                continue;
            }

            // Use resolveAll to get all candidates from this resolver
            $locales = $resolver->resolveAll($request);

            foreach ($locales as $locale) {
                if ($locale === '') {
                    continue;
                }

                $normalizedLocale = $normalize($locale);

                if ($isSupported($normalizedLocale)) {
                    return $normalizedLocale;
                }
            }
        }

        return null;
    }

    /**
     * Get the configured resolution order.
     *
     * @return array<string>
     */
    public function getResolutionOrder(): array
    {
        /** @var array<string> $order */
        $order = $this->config->get('lingua.resolution_order', ['session']);

        return $order;
    }

    /**
     * Check if a resolver is enabled.
     */
    public function isResolverEnabled(string $name): bool
    {
        /** @var bool $enabled */
        $enabled = $this->config->get(sprintf('lingua.resolvers.%s.enabled', $name), true);

        return $enabled;
    }

    /**
     * Create a resolver instance by name.
     *
     * Returns null if the resolver is disabled or doesn't exist.
     */
    public function createResolver(string $name): ?LocaleResolverInterface
    {
        // Check if resolver is enabled (defaults to true for backward compatibility)
        if (! $this->isResolverEnabled($name)) {
            return null;
        }

        // Check for custom resolver class in config
        /** @var class-string<LocaleResolverInterface>|null $customClass */
        $customClass = $this->config->get(sprintf('lingua.resolvers.%s.class', $name));

        if ($customClass !== null && class_exists($customClass)) {
            return $this->app->make($customClass);
        }

        // Use built-in resolver
        if (! isset(self::RESOLVER_MAP[$name])) {
            return null;
        }

        return $this->app->make(self::RESOLVER_MAP[$name]);
    }

    /**
     * Get all available resolver names.
     *
     * @return array<string>
     */
    public function getAvailableResolvers(): array
    {
        return array_keys(self::RESOLVER_MAP);
    }

    /**
     * Get all enabled resolver names from the resolution order.
     *
     * @return array<string>
     */
    public function getEnabledResolvers(): array
    {
        return array_values(array_filter(
            $this->getResolutionOrder(),
            $this->isResolverEnabled(...)
        ));
    }
}
