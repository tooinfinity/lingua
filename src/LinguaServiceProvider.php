<?php

declare(strict_types=1);

namespace TooInfinity\Lingua;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Foundation\AliasLoader;
use Illuminate\Foundation\Application;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use TooInfinity\Lingua\Console\InstallCommand;
use TooInfinity\Lingua\Facades\Lingua as LinguaFacade;
use TooInfinity\Lingua\Http\Middleware\LinguaMiddleware;
use TooInfinity\Lingua\Support\LocaleResolverManager;

final class LinguaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/lingua.php', 'lingua');

        $this->app->singleton(Lingua::class, fn (Application $app): Lingua => new Lingua($app));

        $this->app->singleton(LocaleResolverManager::class, fn (Application $app): LocaleResolverManager => new LocaleResolverManager(
            $app,
            $app->make(ConfigRepository::class)
        ));

        // Register facade alias
        $loader = AliasLoader::getInstance();
        $loader->alias('Lingua', LinguaFacade::class);
    }

    /**
     * @throws BindingResolutionException
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/lingua.php' => config_path('lingua.php'),
        ], 'lingua-config');

        // Only load routes if enabled in config
        if (config('lingua.routes.enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        }

        $this->registerMiddleware();

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
            ]);
        }
    }

    /**
     * Register the middleware alias and optionally auto-register to a middleware group.
     *
     * @throws BindingResolutionException
     */
    private function registerMiddleware(): void
    {
        /** @var Router $router */
        $router = $this->app->make(Router::class);

        // Always register the middleware alias for manual usage
        $router->aliasMiddleware('lingua', LinguaMiddleware::class);

        // Auto-register middleware to the specified group if enabled
        if (config('lingua.middleware.auto_register', true)) {
            /** @var string $group */
            $group = config('lingua.middleware.group', 'web');
            $router->pushMiddlewareToGroup($group, LinguaMiddleware::class);
        }
    }
}
