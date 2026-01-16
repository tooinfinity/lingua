<?php

declare(strict_types=1);

namespace TooInfinity\Lingua\Tests;

use Illuminate\Contracts\Config\Repository;
use Orchestra\Testbench\TestCase as Orchestra;
use Random\RandomException;
use TooInfinity\Lingua\LinguaServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            LinguaServiceProvider::class,
        ];
    }

    /**
     * @throws RandomException
     */
    protected function defineEnvironment($app): void
    {
        /** @var Repository $config */
        $config = $app['config'];

        $config->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $config->set('app.locale', 'en');
        $config->set('lingua.locales', ['en', 'fr', 'es']);
        $config->set('lingua.default');
    }
}
