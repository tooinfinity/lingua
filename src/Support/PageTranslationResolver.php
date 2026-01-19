<?php

declare(strict_types=1);

namespace TooInfinity\Lingua\Support;

use Illuminate\Contracts\Config\Repository as ConfigRepository;

/**
 * Resolves translation groups from Inertia page component names.
 *
 * This class provides automatic mapping between Inertia page names and
 * their corresponding translation files, enabling lazy loading without
 * manual middleware configuration.
 */
final readonly class PageTranslationResolver
{
    public function __construct(
        private ConfigRepository $config
    ) {}

    /**
     * Resolve translation groups from an Inertia page component name.
     *
     * Examples:
     * - 'Dashboard' => ['dashboard']
     * - 'Pages/Dashboard' => ['dashboard']
     * - 'Pages/Users/Index' => ['users']
     * - 'Pages/Users/Edit' => ['users']
     * - 'Admin/Dashboard' => ['admin-dashboard']
     * - 'Admin/Users/Index' => ['admin-users']
     *
     * @return array<string>
     */
    public function resolve(string $pageName): array
    {
        // Check for custom resolver first
        /** @var callable|class-string|null $customResolver */
        $customResolver = $this->config->get('lingua.lazy_loading.page_group_resolver');

        if ($customResolver !== null) {
            return $this->resolveWithCustomResolver($customResolver, $pageName);
        }

        $groupName = $this->extractGroupName($pageName);

        if ($groupName === '') {
            return [];
        }

        return [$groupName];
    }

    /**
     * Extract the base group name from a page path.
     *
     * The extraction follows these rules:
     * 1. Remove common prefixes like 'Pages/'
     * 2. For nested paths, use the parent folder as the group name
     * 3. For simple names, use the page name itself
     * 4. Convert to kebab-case for consistency
     *
     * @param  string  $pageName  The Inertia page component name
     * @return string The extracted translation group name
     */
    public function extractGroupName(string $pageName): string
    {
        if ($pageName === '') {
            return '';
        }

        // Normalize separators (handle both forward and backslashes)
        $pageName = str_replace('\\', '/', $pageName);

        // Remove common "Pages" prefix if present
        $pageName = $this->stripPagesPrefix($pageName);

        // Split into segments
        $segments = array_filter(explode('/', $pageName));

        if ($segments === []) {
            return '';
        }

        // Re-index array after filtering
        $segments = array_values($segments);

        // If only one segment, use it directly (e.g., 'Dashboard' => 'dashboard')
        if (count($segments) === 1) {
            return $this->toGroupName($segments[0]);
        }

        // For nested paths, determine the group based on structure
        return $this->resolveNestedPath($segments);
    }

    /**
     * Strip the "Pages" prefix from a page path if present.
     */
    private function stripPagesPrefix(string $pageName): string
    {
        // Common prefixes to strip
        $prefixes = ['Pages/', 'pages/'];

        foreach ($prefixes as $prefix) {
            if (str_starts_with($pageName, $prefix)) {
                return substr($pageName, strlen($prefix));
            }
        }

        return $pageName;
    }

    /**
     * Resolve group name for nested page paths.
     *
     * Strategy:
     * - For paths like 'Users/Index', 'Users/Edit', 'Users/Create' => 'users'
     * - For paths like 'Admin/Users/Index' => 'admin-users'
     * - For paths like 'Settings/Profile' => 'settings'
     *
     * @param  array<string>  $segments
     */
    private function resolveNestedPath(array $segments): string
    {
        // Common view suffixes that indicate the parent is the resource
        $viewSuffixes = ['Index', 'index', 'Show', 'show', 'Edit', 'edit', 'Create', 'create', 'Form', 'form', 'List', 'list'];

        // Get the last segment
        $lastSegment = end($segments);

        // If the last segment is a common view suffix, use the parent folder
        if (in_array($lastSegment, $viewSuffixes, true)) {
            // Remove the last segment and combine remaining with hyphens
            array_pop($segments);

            if ($segments === []) {
                return '';
            }

            return $this->combineSegments($segments);
        }

        // For other nested paths, combine all segments except the last
        // e.g., 'Settings/Profile' => 'settings'
        if (count($segments) > 1) {
            // Use all but the last segment
            array_pop($segments);

            return $this->combineSegments($segments);
        }

        return $this->toGroupName($segments[0]);
    }

    /**
     * Combine multiple segments into a group name.
     *
     * @param  array<string>  $segments
     */
    private function combineSegments(array $segments): string
    {
        $converted = array_map(
            $this->toGroupName(...),
            $segments
        );

        return implode('-', $converted);
    }

    /**
     * Convert a page name segment to a group name.
     *
     * Converts PascalCase/camelCase to kebab-case and lowercases.
     */
    private function toGroupName(string $segment): string
    {
        // Convert PascalCase/camelCase to kebab-case
        /** @var string $kebab */
        $kebab = preg_replace('/([a-z])([A-Z])/', '$1-$2', $segment);

        return mb_strtolower($kebab);
    }

    /**
     * Resolve using a custom resolver.
     *
     * @return array<string>
     */
    private function resolveWithCustomResolver(callable|string $resolver, string $pageName): array
    {
        // If it's a callable (closure), invoke it directly
        if (is_callable($resolver)) {
            /** @var array<string> $result */
            $result = $resolver($pageName);

            return $result;
        }

        // If it's a class string, resolve it from the container
        if (class_exists($resolver)) {
            /** @var object $instance */
            $instance = app($resolver);

            if (method_exists($instance, 'resolve')) {
                /** @var array<string> $result */
                $result = $instance->resolve($pageName);

                return $result;
            }
        }

        return [];
    }
}
