<?php

declare(strict_types=1);

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use TooInfinity\Lingua\Support\PageTranslationResolver;

beforeEach(function (): void {
    $this->config = app(ConfigRepository::class);
    $this->resolver = new PageTranslationResolver($this->config);
});

describe('extractGroupName', function (): void {
    it('converts simple page name to lowercase group', function (): void {
        expect($this->resolver->extractGroupName('Dashboard'))->toBe('dashboard');
    });

    it('handles PascalCase page names', function (): void {
        expect($this->resolver->extractGroupName('UserProfile'))->toBe('user-profile');
    });

    it('strips Pages prefix from path', function (): void {
        expect($this->resolver->extractGroupName('Pages/Dashboard'))->toBe('dashboard');
        expect($this->resolver->extractGroupName('pages/Dashboard'))->toBe('dashboard');
    });

    it('extracts parent folder for Index views', function (): void {
        expect($this->resolver->extractGroupName('Pages/Users/Index'))->toBe('users');
        expect($this->resolver->extractGroupName('Users/Index'))->toBe('users');
    });

    it('extracts parent folder for Show views', function (): void {
        expect($this->resolver->extractGroupName('Pages/Users/Show'))->toBe('users');
        expect($this->resolver->extractGroupName('Users/Show'))->toBe('users');
    });

    it('extracts parent folder for Edit views', function (): void {
        expect($this->resolver->extractGroupName('Pages/Users/Edit'))->toBe('users');
        expect($this->resolver->extractGroupName('Users/Edit'))->toBe('users');
    });

    it('extracts parent folder for Create views', function (): void {
        expect($this->resolver->extractGroupName('Pages/Users/Create'))->toBe('users');
        expect($this->resolver->extractGroupName('Users/Create'))->toBe('users');
    });

    it('extracts parent folder for Form views', function (): void {
        expect($this->resolver->extractGroupName('Pages/Users/Form'))->toBe('users');
    });

    it('extracts parent folder for List views', function (): void {
        expect($this->resolver->extractGroupName('Pages/Products/List'))->toBe('products');
    });

    it('handles nested admin paths', function (): void {
        expect($this->resolver->extractGroupName('Admin/Users/Index'))->toBe('admin-users');
        expect($this->resolver->extractGroupName('Admin/Dashboard'))->toBe('admin');
    });

    it('handles deeply nested paths', function (): void {
        expect($this->resolver->extractGroupName('Admin/Settings/Users/Index'))->toBe('admin-settings-users');
    });

    it('handles Settings paths', function (): void {
        expect($this->resolver->extractGroupName('Pages/Settings/Profile'))->toBe('settings');
        expect($this->resolver->extractGroupName('Settings/Profile'))->toBe('settings');
    });

    it('returns empty string for empty input', function (): void {
        expect($this->resolver->extractGroupName(''))->toBe('');
    });

    it('handles backslash separators', function (): void {
        expect($this->resolver->extractGroupName('Pages\\Users\\Index'))->toBe('users');
        expect($this->resolver->extractGroupName('Admin\\Dashboard'))->toBe('admin');
    });

    it('handles lowercase view suffixes', function (): void {
        expect($this->resolver->extractGroupName('Users/index'))->toBe('users');
        expect($this->resolver->extractGroupName('Users/show'))->toBe('users');
        expect($this->resolver->extractGroupName('Users/edit'))->toBe('users');
        expect($this->resolver->extractGroupName('Users/create'))->toBe('users');
    });

    it('handles PascalCase folder names', function (): void {
        expect($this->resolver->extractGroupName('UserManagement/Index'))->toBe('user-management');
        expect($this->resolver->extractGroupName('Pages/UserManagement/Index'))->toBe('user-management');
    });
});

describe('resolve', function (): void {
    it('returns array with single group for simple page', function (): void {
        $groups = $this->resolver->resolve('Dashboard');

        expect($groups)->toBe(['dashboard']);
    });

    it('returns array with group for nested page', function (): void {
        $groups = $this->resolver->resolve('Pages/Users/Index');

        expect($groups)->toBe(['users']);
    });

    it('returns empty array for empty page name', function (): void {
        $groups = $this->resolver->resolve('');

        expect($groups)->toBe([]);
    });

    it('returns array with hyphenated group for admin pages', function (): void {
        $groups = $this->resolver->resolve('Admin/Users/Index');

        expect($groups)->toBe(['admin-users']);
    });
});

describe('custom resolver', function (): void {
    it('uses custom closure resolver when configured', function (): void {
        config(['lingua.lazy_loading.page_group_resolver' => fn (string $page): array => ['custom-'.$page]]);

        // Create a new resolver instance to pick up the config change
        $resolver = new PageTranslationResolver(app(ConfigRepository::class));

        $groups = $resolver->resolve('Dashboard');

        expect($groups)->toBe(['custom-Dashboard']);
    });

    it('uses custom class resolver when configured', function (): void {
        // Create a mock resolver class
        $mockResolver = new class
        {
            /** @return array<string> */
            public function resolve(string $pageName): array
            {
                return ['mock-'.$pageName];
            }
        };

        // Bind the mock to the container
        app()->instance('CustomPageResolver', $mockResolver);

        config(['lingua.lazy_loading.page_group_resolver' => fn (string $page): array => app('CustomPageResolver')->resolve($page)]);

        $resolver = new PageTranslationResolver(app(ConfigRepository::class));

        $groups = $resolver->resolve('TestPage');

        expect($groups)->toBe(['mock-TestPage']);
    });

    it('falls back to default resolver when custom resolver is null', function (): void {
        config(['lingua.lazy_loading.page_group_resolver' => null]);

        $resolver = new PageTranslationResolver(app(ConfigRepository::class));

        $groups = $resolver->resolve('Pages/Users/Index');

        expect($groups)->toBe(['users']);
    });
});

describe('page name resolution rules from requirements', function (): void {
    it('resolves Dashboard to dashboard', function (): void {
        expect($this->resolver->resolve('Dashboard'))->toBe(['dashboard']);
    });

    it('resolves Pages/Dashboard to dashboard', function (): void {
        expect($this->resolver->resolve('Pages/Dashboard'))->toBe(['dashboard']);
    });

    it('resolves Pages/Users/Index to users', function (): void {
        expect($this->resolver->resolve('Pages/Users/Index'))->toBe(['users']);
    });

    it('resolves Pages/Users/Create to users', function (): void {
        expect($this->resolver->resolve('Pages/Users/Create'))->toBe(['users']);
    });

    it('resolves Pages/Users/Edit to users', function (): void {
        expect($this->resolver->resolve('Pages/Users/Edit'))->toBe(['users']);
    });

    it('resolves Pages/Settings/Profile to settings', function (): void {
        expect($this->resolver->resolve('Pages/Settings/Profile'))->toBe(['settings']);
    });

    it('resolves Admin/Users/Index to admin-users', function (): void {
        expect($this->resolver->resolve('Admin/Users/Index'))->toBe(['admin-users']);
    });
});
