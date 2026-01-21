# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [1.1.0] - 2026-01-21

### Added
- **Lazy Loading for Translations**
  - Enable with `lazy_loading.enabled => true` in config
  - Auto-detects Inertia page and loads matching translation groups
  - Page name to translation mapping (e.g., `Pages/Users/Index` → `users.php`)
  - Default groups always loaded (`common`, etc.)
  - In-memory and persistent caching support

- **Translation API Endpoints**
  - `GET /lingua/translations/{group}` - Fetch single translation group
  - `POST /lingua/translations` - Fetch multiple groups
  - `GET /lingua/groups` - List available translation groups

- **Middleware Parameters**
  - Support for explicit groups: `lingua:dashboard,notifications`

- **PageTranslationResolver**
  - Maps Inertia page names to translation file groups
  - Custom resolver support via config or closure

- **TranslationCache**
  - In-memory caching for translation groups
  - Configurable persistent cache with TTL

### Changed
- **README.md** - Completely rewritten for simplicity
  - Clear 5-step Quick Start guide
  - Concise API reference
  - Explicit lazy loading documentation
  - Reduced from ~630 lines to ~244 lines

## [Unreleased]

### Added
- **Install Command** (`php artisan lingua:install`)
  - Publishes the Lingua configuration file automatically
  - Auto-detects package manager (npm, yarn, pnpm, bun) by checking lock files
  - Installs `@tooinfinity/lingua-react` React companion package
  - Provides informative console output with progress indicators
  - Graceful error handling with clear failure messages

- **Locale Management**
  - `Lingua::getLocale()` - Get the current locale from session
  - `Lingua::setLocale($locale)` - Set and persist locale in session
  - `Lingua::supportedLocales()` - Get array of configured supported locales
  - `Lingua::translations()` - Load all PHP translation files for current locale

- **Middleware** (`lingua`)
  - Automatically applies locale from session to application
  - Register via route middleware alias

- **Inertia.js Integration**
  - Shares locale, supported locales, and translations with Inertia pages
  - Seamless React integration via `@tooinfinity/lingua-react` package

- **Configuration** (`config/lingua.php`)
  - `session_key` - Customize session storage key for locale
  - `locales` - Define array of supported locales
  - `default` - Set default locale (falls back to `app.locale` if null)

- **Facade** (`Lingua`)
  - Convenient static access to all Lingua methods

- **Routes**
  - `POST /lingua/switch` - Endpoint for switching locales

- **React Package** (`@tooinfinity/lingua-react`)
  - `useTranslations()` hook for accessing translations in React components
  - Full TypeScript support
  - Compatible with React 18 & 19, Inertia.js 1.x & 2.x

- **Comprehensive Test Suite**
  - 49 tests covering all functionality
  - Feature tests for middleware, locale switching, install command
  - Unit tests for Lingua class and Facade
