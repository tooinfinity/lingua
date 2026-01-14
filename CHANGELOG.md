# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

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
