# Lingua

[![Tests](https://github.com/tooinfinity/lingua/actions/workflows/tests.yml/badge.svg)](https://github.com/tooinfinity/lingua/actions/workflows/tests.yml)
[![Formats](https://github.com/tooinfinity/lingua/actions/workflows/formats.yml/badge.svg)](https://github.com/tooinfinity/lingua/actions/workflows/formats.yml)
[![PHP Version](https://img.shields.io/packagist/php-v/tooinfinity/lingua)](https://packagist.org/packages/tooinfinity/lingua)
[![Laravel Version](https://img.shields.io/badge/Laravel-11.x%20%7C%2012.x-red.svg)](https://laravel.com)
[![Latest Stable Version](https://img.shields.io/packagist/v/tooinfinity/lingua)](https://packagist.org/packages/tooinfinity/lingua)
[![Total Downloads](https://img.shields.io/packagist/dt/tooinfinity/lingua)](https://packagist.org/packages/tooinfinity/lingua)
[![License](https://img.shields.io/packagist/l/tooinfinity/lingua)](LICENSE.md)

A minimal Laravel localization package for Inertia.js and React applications. Share your Laravel translations with your React frontend seamlessly.

## Features

- 🌐 Session-based locale storage
- 📁 PHP translation files support
- 🔄 Middleware for locale detection
- ⚡ Share translations with Inertia.js
- ⚛️ React hook for translations (`useTranslations`)
- 🎛️ Basic locale switching controller
- 🎭 Facade for easy static access
- ⚙️ Simple configuration

## Requirements

- PHP 8.4+
- Laravel 11.0+ or 12.0+
- Inertia.js 2.0+
- React 18+

## Installation

### Quick Install (Recommended)

Install the Laravel package via Composer:

```bash
composer require tooinfinity/lingua
```

Then run the install command to publish the config and install the React package:

```bash
php artisan lingua:install
```

This command will:
- Publish the configuration file to `config/lingua.php`
- Auto-detect your package manager (npm, yarn, pnpm, or bun)
- Install the `@tooinfinity/lingua-react` package

### Manual Installation

If you prefer to install manually:

**1. Install the Laravel package:**

```bash
composer require tooinfinity/lingua
```

**2. Publish the configuration file:**

```bash
php artisan vendor:publish --tag=lingua-config
```

**3. Install the React package:**

```bash
# Using npm
npm install @tooinfinity/lingua-react

# Using yarn
yarn add @tooinfinity/lingua-react

# Using pnpm
pnpm add @tooinfinity/lingua-react

# Using bun
bun add @tooinfinity/lingua-react
```

## Configuration

After publishing, you'll find the config file at `config/lingua.php`:

```php
<?php

return [
    // Session key for storing the locale
    'session_key' => 'lingua.locale',

    // Supported locales
    'locales' => ['en'],

    // Default locale (null = use config('app.locale'))
    'default' => null,
];
```

### Configuration Options

| Option | Type | Description |
|--------|------|-------------|
| `session_key` | string | The session key used to store the user's locale |
| `locales` | array | List of supported locale codes |
| `default` | string\|null | Default locale. If `null`, uses Laravel's `app.locale` |

## Usage

### 1. Add the Middleware

Add the `lingua` middleware to your routes that use Inertia:

```php
// routes/web.php
Route::middleware(['web', 'lingua'])->group(function () {
    Route::get('/', fn () => Inertia::render('Home'));
    Route::get('/dashboard', fn () => Inertia::render('Dashboard'));
});
```

Or add it globally in `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->web(append: [
        \TooInfinity\Lingua\Http\Middleware\LinguaMiddleware::class,
    ]);
})
```

### 2. Create Translation Files

Create your PHP translation files in the `lang` directory:

```
lang/
├── en/
│   ├── auth.php
│   ├── messages.php
│   └── validation.php
└── fr/
    ├── auth.php
    ├── messages.php
    └── validation.php
```

Example `lang/en/messages.php`:

```php
<?php

return [
    'welcome' => 'Welcome to our application!',
    'greeting' => 'Hello, :name!',
    'items_count' => 'You have :count items.',
];
```

Example `lang/fr/messages.php`:

```php
<?php

return [
    'welcome' => 'Bienvenue dans notre application!',
    'greeting' => 'Bonjour, :name!',
    'items_count' => 'Vous avez :count articles.',
];
```

### 3. Use in React Components

Import and use the `useTranslations` hook in your React components:

```tsx
import { useTranslations } from 'lingua/resources/js';

function Welcome() {
    const { t, locale, locales } = useTranslations();

    return (
        <div>
            <h1>{t('messages.welcome')}</h1>
            <p>{t('messages.greeting', { name: 'John' })}</p>
            <p>{t('messages.items_count', { count: 5 })}</p>
            <p>Current locale: {locale}</p>
        </div>
    );
}
```

### 4. Switch Locale

Lingua provides a built-in route for switching locales:

**Using a Form:**

```tsx
function LocaleSwitcher() {
    const { locale, locales } = useTranslations();

    return (
        <form method="POST" action="/lingua/locale">
            <input type="hidden" name="_token" value={csrfToken} />
            <select name="locale" onChange={(e) => e.target.form?.submit()}>
                {locales.map((loc) => (
                    <option key={loc} value={loc} selected={loc === locale}>
                        {loc.toUpperCase()}
                    </option>
                ))}
            </select>
        </form>
    );
}
```

**Using Inertia Router:**

```tsx
import { router } from '@inertiajs/react';

function LocaleSwitcher() {
    const { locale, locales } = useTranslations();

    const switchLocale = (newLocale: string) => {
        router.post('/lingua/locale', { locale: newLocale });
    };

    return (
        <div>
            {locales.map((loc) => (
                <button
                    key={loc}
                    onClick={() => switchLocale(loc)}
                    className={loc === locale ? 'active' : ''}
                >
                    {loc.toUpperCase()}
                </button>
            ))}
        </div>
    );
}
```

## API Reference

### React Hook: `useTranslations()`

Returns an object with the following properties:

| Property | Type | Description |
|----------|------|-------------|
| `t` | `(key: string, replacements?: Record<string, string \| number>) => string` | Translation function |
| `locale` | `string` | Current locale |
| `locales` | `string[]` | List of supported locales |

#### Translation Function `t()`

```tsx
// Simple translation
t('messages.welcome')  // "Welcome to our application!"

// With replacements (Laravel-style :placeholder)
t('messages.greeting', { name: 'John' })  // "Hello, John!"
t('messages.items_count', { count: 5 })   // "You have 5 items."

// Returns the key if translation not found
t('messages.nonexistent')  // "messages.nonexistent"
```

### Inertia Shared Props

Lingua shares the following data with Inertia:

```typescript
interface LinguaProps {
    lingua: {
        locale: string;           // Current locale
        locales: string[];        // Supported locales
        translations: {           // All translation groups
            auth: Record<string, string>;
            messages: Record<string, string>;
            // ... other translation files
        };
    };
}
```

### Facade: `Lingua`

Use the Facade for easy static access anywhere in your application:

```php
use TooInfinity\Lingua\Facades\Lingua;

// Get current locale
$locale = Lingua::getLocale();

// Set locale
Lingua::setLocale('fr');

// Get supported locales
$locales = Lingua::supportedLocales();

// Get all translations
$translations = Lingua::translations();
```

**Available Methods:**

| Method | Return Type | Description |
|--------|-------------|-------------|
| `getLocale()` | `string` | Get the current locale |
| `setLocale(string $locale)` | `void` | Set the current locale |
| `supportedLocales()` | `array<string>` | Get list of supported locales |
| `translations()` | `array<string, mixed>` | Get all translations for current locale |

### PHP Service: `Lingua`

You can also inject the Lingua service directly:

```php
use TooInfinity\Lingua\Lingua;

class MyController
{
    public function __construct(
        private readonly Lingua $lingua
    ) {}

    public function index()
    {
        // Get current locale
        $locale = $this->lingua->getLocale();

        // Set locale
        $this->lingua->setLocale('fr');

        // Get supported locales
        $locales = $this->lingua->supportedLocales();

        // Get all translations
        $translations = $this->lingua->translations();
    }
}
```

### Routes

| Method | URI | Name | Description |
|--------|-----|------|-------------|
| POST | `/lingua/locale` | `lingua.locale.update` | Switch the current locale |

**Request Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `locale` | string | Yes | The locale to switch to (must be in `locales` config) |

## TypeScript Support

Lingua includes TypeScript definitions. You can import types directly:

```tsx
import { useTranslations, type LinguaProps, type TranslateFunction } from 'lingua/resources/js';
```

## Testing

```bash
composer test
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
