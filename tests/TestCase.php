<?php

declare(strict_types=1);

namespace TooInfinity\Lingua\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use TooInfinity\Lingua\LinguaServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            LinguaServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('app.locale', 'en');
        $app['config']->set('lingua.locales', ['en', 'fr', 'es']);
        $app['config']->set('lingua.default', null);
    }
}
