<?php

declare(strict_types=1);

namespace TooInfinity\Lingua;

use Illuminate\Foundation\AliasLoader;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use TooInfinity\Lingua\Console\InstallCommand;
use TooInfinity\Lingua\Facades\Lingua as LinguaFacade;
use TooInfinity\Lingua\Http\Middleware\LinguaMiddleware;

final class LinguaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/lingua.php', 'lingua');

        $this->app->singleton(Lingua::class, fn ($app): Lingua => new Lingua($app));

        // Register facade alias
        $loader = AliasLoader::getInstance();
        $loader->alias('Lingua', LinguaFacade::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/lingua.php' => config_path('lingua.php'),
        ], 'lingua-config');

        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        /** @var Router $router */
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('lingua', LinguaMiddleware::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
            ]);
        }
    }
}
