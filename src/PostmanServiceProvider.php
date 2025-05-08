<?php

namespace YasinTgh\LaravelPostman;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use YasinTgh\LaravelPostman\Collections\Builder;
use YasinTgh\LaravelPostman\Collections\FolderStrategy;
use YasinTgh\LaravelPostman\Commands\GeneratePostmanDocs;
use YasinTgh\LaravelPostman\Contracts\RouteAnalyzerInterface;
use YasinTgh\LaravelPostman\Services\PostmanFormatter;
use YasinTgh\LaravelPostman\Services\RouteAnalyzer;

class PostmanServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/postman.php', 'postman');

        $this->app->bind(RouteAnalyzerInterface::class, function ($app) {
            return new RouteAnalyzer(
                $app->make(Router::class),
                $app->make(Config::class)->get('postman', [])
            );
        });

        $this->app->bind(Builder::class, function ($app) {
            return new Builder(
                new FolderStrategy(
                    $app->make(Config::class)->get('postman.structure.folders.strategy', 'prefix'),
                    $app->make(Config::class)->get('postman', [])
                ),
                $app->make(Config::class)->get('postman', [])
            );
        });

        $this->app->bind(PostmanFormatter::class, function ($app) {
            return new PostmanFormatter(
                $app->make(Builder::class),
                $app->make(Config::class)->get('postman', [])
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
