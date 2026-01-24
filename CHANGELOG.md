# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]

## [0.2.0] - 2026-01-21

### Added
- **Install Command** (`php artisan lingua:install`)
  - Publishes the Lingua configuration file automatically
  - Auto-detects package manager (npm, yarn, pnpm, bun) by checking lock files
  - Installs `@tooinfinity/lingua-react` React companion package
  - Provides informative console output with progress indicators
  - Graceful error handling with clear failure messages

- **Lazy Loading for Translations**
  - Enable with `lazy_loading.enabled => true` in config
  - Auto-detects Inertia page and loads matching translation groups
  - Page name to translation mapping (e.g., `Pages/Users/Index` → `users.php`)
  - Default groups always loaded (`common`, etc.)
  - In-memory and persistent caching support
  
- **Locale Management**
  - `Lingua::getLocale()` - Get the current locale from session/cookie
  - `Lingua::setLocale($locale)` - Set and persist locale in session
  - `Lingua::supportedLocales()` - Get array of configured supported locales
  - `Lingua::translations()` - Load all PHP/JSON translation files for current locale
  - `Lingua::isRtl()` - Check if locale uses right-to-left text direction
  - `Lingua::getDirection()` - Get text direction ('ltr' or 'rtl')

- **Locale Resolvers**
  - `SessionResolver` - Resolves locale from session storage
  - `CookieResolver` - Resolves locale from cookie
  - `LocaleResolverInterface` - Contract for custom resolvers

- **Middleware** (`lingua`)
  - Automatically applies locale from session to application
  - Register via route middleware alias
  - Auto-register to middleware group (configurable)

- **Inertia.js Integration**
  - Shares locale, supported locales, and translations with Inertia pages
  - Seamless React integration via `@tooinfinity/lingua-react` package

- **Configuration** (`config/lingua.php`)
  - `locales` - Define array of supported locales
  - `default` - Set default locale (falls back to `app.locale` if null)
  - `resolvers.session.key` - Customize session storage key for locale
  - `resolvers.cookie.key` - Customize cookie name for locale
  - `resolvers.cookie.persist_on_set` - Auto-set cookie when locale changes
  - `routes.enabled` - Enable/disable package routes
  - `routes.prefix` - Route prefix configuration
  - `routes.middleware` - Route middleware configuration
  - `translation_driver` - Support for 'php' and 'json' drivers
  - `rtl_locales` - Configure RTL language codes

- **Facade** (`Lingua`)
  - Convenient static access to all Lingua methods

- **Routes**
  - `POST /locale` - Endpoint for switching locales (configurable prefix)

- **React Package** (`@tooinfinity/lingua-react`)
  - `useTranslations()` hook for accessing translations in React components
  - Full TypeScript support
  - Compatible with React 18 & 19, Inertia.js 1.x & 2.x

- **Comprehensive Test Suite**
  - Feature tests for middleware, locale switching, install command, route configuration
  - Unit tests for Lingua class, Facade, resolvers, RTL detection

### Changed
- **README.md** - Completely rewritten for simplicity
  - Clear Quick Start guide
  - Concise API reference
  - Explicit lazy loading documentation
  - JSON translation documentation
