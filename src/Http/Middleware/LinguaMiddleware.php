<?php

declare(strict_types=1);

namespace TooInfinity\Lingua\Http\Middleware;

use Closure;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Http\Request;
use Inertia\Inertia;
use JsonException;
use Psr\SimpleCache\InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;
use TooInfinity\Lingua\Lingua;
use TooInfinity\Lingua\Support\PageTranslationResolver;

final readonly class LinguaMiddleware
{
    public function __construct(
        private Lingua $lingua,
        private PageTranslationResolver $pageResolver
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
     * With auto_detect_page enabled (default), the middleware will automatically
     * detect the Inertia page name and load the corresponding translations:
     *
     * // Controller renders 'Pages/Users/Index'
     * // Automatically loads: default_groups + 'users' translations
     *
     * @param  Closure(Request): Response  $next
     * @param  string  ...$groups  Optional translation groups to load (only when lazy loading is enabled)
     *
     * @throws BindingResolutionException
     * @throws InvalidArgumentException
     * @throws JsonException
     */
    public function handle(Request $request, Closure $next, string ...$groups): Response
    {
        $locale = $this->lingua->getLocale($request);

        app()->setLocale($locale);

        // Store middleware groups for later use in the lazy callback
        $middlewareGroups = $groups;

        // Share translations lazily - resolved when Inertia renders
        // This allows us to potentially access the page name at render time
        Inertia::share('lingua', fn (): array => [
            'locale' => $this->lingua->getLocale($request),
            'locales' => $this->lingua->supportedLocales(),
            'translations' => $this->resolveTranslations($middlewareGroups),
            'direction' => $this->lingua->getDirection(),
            'isRtl' => $this->lingua->isRtl(),
        ]);

        $response = $next($request);

        // For Inertia responses, detect the page and update translations
        if ($this->isInertiaResponse($response) && $this->shouldAutoDetect($middlewareGroups)) {
            $this->shareTranslationsWithPageDetection($response, $middlewareGroups);
        }

        return $response;
    }

    /**
     * Resolve translations based on configuration and middleware groups.
     *
     * @param  array<string>  $middlewareGroups  Groups specified via middleware parameters
     * @return array<string, mixed>
     *
     * @throws BindingResolutionException
     * @throws FileNotFoundException
     * @throws InvalidArgumentException|JsonException
     */
    private function resolveTranslations(array $middlewareGroups): array
    {
        // If lazy loading is disabled, load all translations
        if (! $this->lingua->isLazyLoadingEnabled()) {
            return $this->lingua->translations();
        }

        // Get default groups
        /** @var array<string> $defaultGroups */
        $defaultGroups = config('lingua.lazy_loading.default_groups', []);

        // If middleware groups are specified, use them (explicit override)
        if ($middlewareGroups !== []) {
            $allGroups = array_unique(array_merge($defaultGroups, $middlewareGroups));

            return $this->lingua->translationsFor($allGroups);
        }

        // Return default groups only (page detection happens in response handling)
        return $this->lingua->translationsFor($defaultGroups);
    }

    /**
     * Check if auto-detection should be performed.
     *
     * @param  array<string>  $middlewareGroups
     *
     * @throws BindingResolutionException
     */
    private function shouldAutoDetect(array $middlewareGroups): bool
    {
        // Don't auto-detect if lazy loading is disabled
        if (! $this->lingua->isLazyLoadingEnabled()) {
            return false;
        }

        // Don't auto-detect if auto_detect_page is disabled
        if (! $this->lingua->isAutoDetectPageEnabled()) {
            return false;
        }

        // Don't auto-detect if middleware groups were explicitly specified
        return $middlewareGroups === [];
    }

    /**
     * Check if the response is an Inertia response.
     */
    private function isInertiaResponse(Response $response): bool
    {
        // Check for Inertia header in XHR responses
        if ($response->headers->has('X-Inertia')) {
            return true;
        }

        // Check content type for JSON (Inertia XHR)
        $contentType = $response->headers->get('Content-Type', '') ?? '';
        if (str_contains($contentType, 'application/json')) {
            $content = $response->getContent();
            if ($content !== false && str_contains($content, '"component"')) {
                return true;
            }
        }

        // Check for HTML response with Inertia page data
        $content = $response->getContent();

        return $content !== false && str_contains($content, 'data-page=');
    }

    /**
     * Share translations after detecting the Inertia page from the response.
     *
     * @param  array<string>  $middlewareGroups
     *
     * @throws BindingResolutionException
     * @throws InvalidArgumentException
     * @throws JsonException
     */
    private function shareTranslationsWithPageDetection(
        Response $response,
        array $middlewareGroups
    ): void {
        $pageName = $this->extractPageNameFromResponse($response);

        if ($pageName === null) {
            return;
        }

        // Get page-specific groups
        $pageGroups = $this->pageResolver->resolve($pageName);

        if ($pageGroups === []) {
            return;
        }

        // Get default groups
        /** @var array<string> $defaultGroups */
        $defaultGroups = config('lingua.lazy_loading.default_groups', []);

        // Merge all groups
        $allGroups = array_unique(array_merge($defaultGroups, $middlewareGroups, $pageGroups));

        // Load translations for all groups
        $translations = $this->lingua->translationsFor($allGroups);

        // Update the shared data with page-specific translations
        $this->updateResponseWithTranslations($response, $translations);
    }

    /**
     * Extract the Inertia page component name from the response.
     *
     * @throws JsonException
     */
    private function extractPageNameFromResponse(Response $response): ?string
    {
        $content = $response->getContent();

        if ($content === false) {
            return null;
        }

        // Try to extract from JSON response (Inertia XHR)
        $contentType = $response->headers->get('Content-Type', '') ?? '';
        if ($response->headers->has('X-Inertia') || str_contains($contentType, 'application/json')) {
            return $this->extractPageNameFromJson($content);
        }

        // Try to extract from HTML response (initial page load)
        return $this->extractPageNameFromHtml($content);
    }

    /**
     * Extract page name from JSON response content.
     *
     * @throws JsonException
     */
    private function extractPageNameFromJson(string $content): ?string
    {
        /** @var array{component?: string}|null $data */
        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($data) || ! isset($data['component'])) {
            return null;
        }

        return $data['component'];
    }

    /**
     * Extract page name from HTML response content.
     */
    private function extractPageNameFromHtml(string $content): ?string
    {
        // Match data-page attribute in the HTML
        // The format is: data-page="{&quot;component&quot;:&quot;PageName&quot;,...}"
        // or data-page='{"component":"PageName",...}'
        if (preg_match('/data-page=["\'](.+?)["\']/s', $content, $matches)) {
            $pageData = $matches[1];

            // Decode HTML entities
            $pageData = html_entity_decode($pageData, ENT_QUOTES, 'UTF-8');

            /** @var array{component?: string}|null $decoded */
            $decoded = json_decode($pageData, true);

            if (is_array($decoded) && isset($decoded['component'])) {
                return $decoded['component'];
            }
        }

        return null;
    }

    /**
     * Update the response content with the new translations.
     *
     * @param  array<string, mixed>  $translations
     *
     * @throws JsonException
     */
    private function updateResponseWithTranslations(Response $response, array $translations): void
    {
        $content = $response->getContent();

        if ($content === false) {
            return;
        }

        // Handle JSON response (Inertia XHR)
        $contentType = $response->headers->get('Content-Type', '') ?? '';
        if ($response->headers->has('X-Inertia') || str_contains($contentType, 'application/json')) {
            $this->updateJsonResponse($response, $content, $translations);

            return;
        }

        // Handle HTML response (initial page load)
        $this->updateHtmlResponse($response, $content, $translations);
    }

    /**
     * Update JSON response with translations.
     *
     * @param  array<string, mixed>  $translations
     *
     * @throws JsonException
     */
    private function updateJsonResponse(Response $response, string $content, array $translations): void
    {
        /** @var array<string, mixed>|null $data */
        $data = json_decode($content, true);

        if (! is_array($data) || ! isset($data['props'])) {
            return;
        }

        /** @var array<string, mixed> $props */
        $props = $data['props'];

        // Update the lingua translations in props
        if (isset($props['lingua']) && is_array($props['lingua'])) {
            /** @var array<string, mixed> $lingua */
            $lingua = $props['lingua'];
            $lingua['translations'] = $translations;
            $props['lingua'] = $lingua;
            $data['props'] = $props;
        }

        $response->setContent(json_encode($data, JSON_THROW_ON_ERROR));
    }

    /**
     * Update HTML response with translations.
     *
     * @param  array<string, mixed>  $translations
     *
     * @throws JsonException
     */
    private function updateHtmlResponse(Response $response, string $content, array $translations): void
    {
        // Match and update the data-page attribute
        $updated = preg_replace_callback(
            '/data-page=(["\'])(.+?)\1/s',
            function (array $matches) use ($translations): string {
                $quote = $matches[1];
                $pageData = html_entity_decode($matches[2], ENT_QUOTES, 'UTF-8');

                /** @var array<string, mixed>|null $decoded */
                $decoded = json_decode($pageData, true);

                if (! is_array($decoded) || ! isset($decoded['props'])) {
                    return $matches[0];
                }

                /** @var array<string, mixed> $props */
                $props = $decoded['props'];

                if (! isset($props['lingua']) || ! is_array($props['lingua'])) {
                    return $matches[0];
                }

                /** @var array<string, mixed> $lingua */
                $lingua = $props['lingua'];

                // Update translations
                $lingua['translations'] = $translations;
                $props['lingua'] = $lingua;
                $decoded['props'] = $props;

                // Re-encode
                $encoded = json_encode($decoded, JSON_THROW_ON_ERROR);

                // Escape for HTML attribute
                if ($quote === '"') {
                    $encoded = htmlspecialchars($encoded, ENT_QUOTES, 'UTF-8');
                }

                return 'data-page='.$quote.$encoded.$quote;
            },
            $content
        );

        if ($updated !== null) {
            $response->setContent($updated);
        }
    }
}
