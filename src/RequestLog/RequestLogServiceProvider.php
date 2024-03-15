<?php

namespace Cego\RequestLog;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\ServiceProvider;
use Cego\RequestLog\Middleware\LogRequest;
use Illuminate\Contracts\Container\BindingResolutionException;

class RequestLogServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton(LogRequest::class);
    }

    /**
     * Bootstrap any application services.
     *
     * @throws BindingResolutionException
     *
     * @return void
     */
    public function boot(): void
    {
        $this->publishAndLoadPackageComponents();

        $this->pushGlobalMiddleware();
    }

    /**
     * Publishes and loads package components so they are available to the application
     */
    protected function publishAndLoadPackageComponents(): void
    {
        // Publish resource to the project consuming this package
        $this->publishes([
            __DIR__ . '/../../publishable/config/request-log.php' => config_path('request-log.php'),
        ]);

        // Merge default config into the published
        // This allows for default values, and should autoload the config
        $this->mergeConfigFrom(
            __DIR__ . '/../../publishable/config/request-log.php',
            'request-log'
        );

        // Makes sure migrations and factories are added
        $this->loadMigrationsFrom(__DIR__ . '/../../publishable/migrations');
    }

    /**
     * Pushes the RequestLog middleware to the global stack, if it is enabled
     *
     * @throws BindingResolutionException
     */
    protected function pushGlobalMiddleware(): void
    {
        // Push Middleware to global middleware stack
        $kernel = $this->app->make(Kernel::class);
        $kernel->pushMiddleware(LogRequest::class);
    }
}
