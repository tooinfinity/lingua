<?php

declare(strict_types=1);

namespace TooInfinity\Lingua\Contracts;

use Illuminate\Http\Request;

interface LocaleResolverInterface
{
    /**
     * Resolve the locale from the request.
     *
     * @return string|null The resolved locale, or null if not found
     */
    public function resolve(Request $request): ?string;

    /**
     * Resolve all possible locales from the request, ordered by preference.
     *
     * This method allows resolvers to return multiple candidates in priority order.
     * The LocaleResolverManager will iterate through these and return the first supported one.
     *
     * @return array<string> Array of locale candidates, ordered by preference
     */
    public function resolveAll(Request $request): array;
}
