<?php

namespace YasinTgh\LaravelPostman;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use YasinTgh\LaravelPostman\Collections\Builder;
use YasinTgh\LaravelPostman\Collections\RouteGrouper;
use YasinTgh\LaravelPostman\Commands\GeneratePostmanDocs;
use YasinTgh\LaravelPostman\Contracts\RouteAnalyzerInterface;
use YasinTgh\LaravelPostman\Services\NameGenerator;
use YasinTgh\LaravelPostman\Services\PostmanFormatter;
use YasinTgh\LaravelPostman\Services\RequestBodyGenerator;
use YasinTgh\LaravelPostman\Services\RouteAnalyzer;

class PostmanServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/postman.php', 'postman');

        $this->app->singleton(RouteAnalyzerInterface::class, function ($app) {
            return new RouteAnalyzer(
                $app->make(Router::class),
                $app->make(Config::class)->get('postman', [])
            );
        });

        $this->app->singleton(Builder::class, function ($app) {
            return new Builder(
                new RouteGrouper(
                    $app->make(Config::class)->get('postman.structure.folders.strategy', 'prefix'),
                    $app->make(Config::class)->get('postman', []),
                    $app->make(NameGenerator::class),
                    $app->make(RequestBodyGenerator::class),
                    $app->make(Config::class)->get('postman.structure.requests.default_values', []),
                ),
                $app->make(Config::class)->get('postman', [])
            );
        });

        $this->app->singleton(NameGenerator::class, function ($app) {
            return new NameGenerator(
                $app->make(Config::class)->get('postman', []),
            );
        });

        $this->app->singleton(PostmanFormatter::class, function ($app) {
            return new PostmanFormatter(
                $app->make(Builder::class),
                $app->make(Config::class)->get('postman', []),
            );
        });
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([GeneratePostmanDocs::class]);

            $this->publishes([
                __DIR__ . '/../config/postman.php' => config_path('postman.php'),
            ], 'postman-config');
        }
    }
}
