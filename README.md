# Lingua

[![Tests](https://github.com/tooinfinity/lingua/actions/workflows/tests.yml/badge.svg)](https://github.com/tooinfinity/lingua/actions/workflows/tests.yml)
[![Formats](https://github.com/tooinfinity/lingua/actions/workflows/formats.yml/badge.svg)](https://github.com/tooinfinity/lingua/actions/workflows/formats.yml)
[![PHP Version](https://img.shields.io/packagist/php-v/tooinfinity/lingua)](https://packagist.org/packages/tooinfinity/lingua)
[![Laravel Version](https://img.shields.io/badge/Laravel-11.x%20%7C%2012.x-red.svg)](https://laravel.com)
[![Latest Stable Version](https://img.shields.io/packagist/v/tooinfinity/lingua)](https://packagist.org/packages/tooinfinity/lingua)
[![License](https://img.shields.io/packagist/l/tooinfinity/lingua)](LICENSE.md)

> ⚠️ **Under Development**: This package is currently under active development and is not ready for production use.

Share Laravel translations with your Inertia.js + React frontend.

## Quick Start

### 1. Install

```bash
composer require tooinfinity/lingua
php artisan lingua:install
```

This installs the Laravel package, publishes the config, and installs the React package (`@tooinfinity/lingua-react`).

### 2. Configure Locales

Edit `config/lingua.php`:

```php
'locales' => ['en', 'fr', 'es'],
```

### 3. Middleware

**The middleware is auto-registered to the `web` group by default.** No action needed for most apps.

To disable auto-registration and add manually:

```php
// config/lingua.php
'middleware' => [
    'auto_register' => false,
],

// bootstrap/app.php
->withMiddleware(function (Middleware $middleware) {
    $middleware->web(append: [
        \TooInfinity\Lingua\Http\Middleware\LinguaMiddleware::class,
    ]);
})
```

### 4. Create Translations

```
lang/
├── en/
│   └── messages.php
└── fr/
    └── messages.php
```

```php
// lang/en/messages.php
return [
    'welcome' => 'Welcome!',
    'greeting' => 'Hello, :name!',
];

// lang/fr/messages.php
return [
    'welcome' => 'Bienvenue!',
    'greeting' => 'Bonjour, :name!',
];
```

### 5. Use in React

```tsx
import { useTranslations } from '@tooinfinity/lingua-react';

function Welcome() {
    const { __, locale, locales } = useTranslations();

    return (
        <div>
            <h1>{__('messages.welcome')}</h1>
            <p>{__('messages.greeting', { name: 'John' })}</p>
            <p>Current: {locale}</p>
        </div>
    );
}
```

## Locale Switching

```tsx
import { router } from '@inertiajs/react';
import { useTranslations } from '@tooinfinity/lingua-react';

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
                    disabled={loc === locale}
                >
                    {loc.toUpperCase()}
                </button>
            ))}
        </div>
    );
}
```

## Lazy Loading (Optional)

Load only the translations needed for each request instead of all at once.

```php
// config/lingua.php
'lazy_loading' => [
    'enabled' => true,
    'default_groups' => ['common', 'validation'], // Always loaded
],
```

When enabled, Lingua only loads the configured default groups unless you request specific groups via `Lingua::translationsFor()` or middleware parameters.

## API Reference

### `useTranslations()` Hook

```tsx
const { __, locale, locales, direction, isRtl } = useTranslations();

__('messages.welcome')                    // "Welcome!"
__('messages.greeting', { name: 'John' }) // "Hello, John!"
```

| Property | Type | Description |
|----------|------|-------------|
| `__` | `function` | Translation function |
| `locale` | `string` | Current locale |
| `locales` | `string[]` | Supported locales |
| `direction` | `'ltr' \| 'rtl'` | Text direction |
| `isRtl` | `boolean` | Is RTL locale |

### Facade

```php
use TooInfinity\Lingua\Facades\Lingua;

Lingua::getLocale();           // Get current locale
Lingua::setLocale('fr');       // Set locale (optionally persists cookie)
Lingua::supportedLocales();    // Get supported locales
Lingua::translations();        // Get all translations (missing keys fall back to default locale)
```

### Fallback Locale Behavior

When a translation key is missing in the current locale, Lingua automatically fills it from the default locale.
This applies to:
- PHP translation groups loaded via `Lingua::translationGroup()` and `Lingua::translations()`
- JSON translations loaded via `Lingua::translations()` when `translation_driver` is `json`

If the current locale already matches the default locale, no fallback merge occurs.

```php
// config/lingua.php
'default' => 'en',
```

```php
// lang/en/auth.php
return [
    'login' => 'Login',
    'logout' => 'Logout',
];

// lang/fr/auth.php
return [
    'login' => 'Connexion',
];

Lingua::setLocale('fr');

// 'logout' comes from the default locale
Lingua::translationGroup('auth');
// ['login' => 'Connexion', 'logout' => 'Logout']
```

### Routes

| Method | URI | Description |
|--------|-----|-------------|
| POST | `/lingua/locale` | Switch locale |

## Advanced

### Custom Controller

```php
// config/lingua.php
'controller' => \App\Http\Controllers\LocaleController::class,
```

```php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use TooInfinity\Lingua\Lingua;

class LocaleController
{
    public function __invoke(Request $request, Lingua $lingua)
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', Rule::in($lingua->supportedLocales())],
        ]);

        $lingua->setLocale($validated['locale']);

        return redirect()->route('dashboard');
    }
}
```

## License

MIT License. See [LICENSE.md](LICENSE.md).
