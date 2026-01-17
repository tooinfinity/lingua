<?php

declare(strict_types=1);

namespace TooInfinity\Lingua\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use TooInfinity\Lingua\Lingua;

final readonly class LinguaMiddleware
{
    public function __construct(
        private Lingua $lingua
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->lingua->getLocale($request);

        app()->setLocale($locale);

        Inertia::share('lingua', fn (): array => [
            'locale' => $this->lingua->getLocale($request),
            'locales' => $this->lingua->supportedLocales(),
            'translations' => $this->lingua->translations(),
        ]);

        return $next($request);
    }
}
