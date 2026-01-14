<?php

declare(strict_types=1);

namespace TooInfinity\Lingua;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Session\Session;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\File;

final readonly class Lingua
{
    public function __construct(
        private Application $app
    ) {}

    public function getLocale(): string
    {
        /** @var ConfigRepository $config */
        $config = $this->app->make(ConfigRepository::class);

        /** @var Session $session */
        $session = $this->app->make(Session::class);

        /** @var string $sessionKey */
        $sessionKey = $config->get('lingua.session_key', 'lingua.locale');

        /** @var string $default */
        $default = $config->get('lingua.default') ?? $config->get('app.locale', 'en');

        /** @var string $locale */
        $locale = $session->get($sessionKey, $default);

        return $locale;
    }

    public function setLocale(string $locale): void
    {
        /** @var ConfigRepository $config */
        $config = $this->app->make(ConfigRepository::class);

        /** @var Session $session */
        $session = $this->app->make(Session::class);

        /** @var string $sessionKey */
        $sessionKey = $config->get('lingua.session_key', 'lingua.locale');

        $session->put($sessionKey, $locale);
        $this->app->setLocale($locale);
    }

    /**
     * @return array<string>
     */
    public function supportedLocales(): array
    {
        /** @var ConfigRepository $config */
        $config = $this->app->make(ConfigRepository::class);

        /** @var array<string> $locales */
        $locales = $config->get('lingua.locales', ['en']);

        return $locales;
    }

    /**
     * @return array<string, mixed>
     */
    public function translations(): array
    {
        $locale = $this->getLocale();
        $path = $this->app->langPath($locale);

        if (! File::isDirectory($path)) {
            return [];
        }

        $translations = [];
        $files = File::files($path);

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $key = $file->getFilenameWithoutExtension();
            $content = require $file->getPathname();

            if (is_array($content)) {
                $translations[$key] = $content;
            }
        }

        return $translations;
    }
}
