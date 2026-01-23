<?php

declare(strict_types=1);

namespace TooInfinity\Lingua\Http\Middleware;

use Closure;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
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
     * Handle an incoming request.
     *
     * When lazy loading is enabled, you can pass specific translation groups
     * as middleware parameters:
     *
     * Route::get('/dashboard', DashboardController::class)
     *     ->middleware('lingua:dashboard,common');
     *
     * @param  Closure(Request): Response  $next
     * @param  string  ...$groups  Optional translation groups to load (only when lazy loading is enabled)
     *
     * @throws BindingResolutionException
     */
    public function handle(Request $request, Closure $next, string ...$groups): Response
    {
        $locale = $this->lingua->getLocale($request);

        app()->setLocale($locale);

        $middlewareGroups = $groups;

        Inertia::share('lingua', fn (): array => [
            'locale' => $this->lingua->getLocale($request),
            'locales' => $this->lingua->supportedLocales(),
            'translations' => $this->resolveTranslations($middlewareGroups),
            'direction' => $this->lingua->getDirection(),
            'isRtl' => $this->lingua->isRtl(),
        ]);

        return $next($request);
    }

    /**
     * Resolve translations based on middleware groups.
     *
     * @param  array<string>  $middlewareGroups  Groups specified via middleware parameters
     * @return array<string, mixed>
     *
     * @throws BindingResolutionException
     * @throws FileNotFoundException
     */
    private function resolveTranslations(array $middlewareGroups): array
    {
        if ($middlewareGroups === []) {
            return $this->lingua->translations();
        }

        return $this->lingua->translationsFor($middlewareGroups);
    }
}
