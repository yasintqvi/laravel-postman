<?php

namespace YasinTgh\LaravelPostman;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use YasinTgh\LaravelPostman\Collections\Builder;
use YasinTgh\LaravelPostman\Collections\RouteGrouper;
use YasinTgh\LaravelPostman\Commands\GeneratePostmanDocs;
use YasinTgh\LaravelPostman\Contracts\CloudSyncServiceInterface;
use YasinTgh\LaravelPostman\Contracts\RouteAnalyzerInterface;
use YasinTgh\LaravelPostman\Services\CollectionMerger;
use YasinTgh\LaravelPostman\Services\NameGenerator;
use YasinTgh\LaravelPostman\Services\PostmanCloudSyncService;
use YasinTgh\LaravelPostman\Services\PostmanFormatter;
use YasinTgh\LaravelPostman\Services\RequestBodyGenerator;
use YasinTgh\LaravelPostman\Services\RouteAnalyzer;

class PostmanServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/postman.php', 'postman');

        $this->app->singleton(RouteAnalyzerInterface::class, function ($app) {
            return new RouteAnalyzer(
                $app->make(Router::class),
                $app->make(Config::class)->get('postman', [])
            );
        });

        $this->app->singleton(NameGenerator::class, function ($app) {
            return new NameGenerator(
                $app->make(Config::class)->get('postman', [])
            );
        });

        $this->app->singleton(Builder::class, function ($app) {
            $config = $app->make(Config::class)->get('postman', []);

            return new Builder(
                new RouteGrouper(
                    $config['structure']['folders']['strategy'] ?? 'prefix',
                    $config,
                    $app->make(NameGenerator::class),
                    $app->make(RequestBodyGenerator::class),
                    $config['structure']['requests']['default_values'] ?? [],
                ),
                $config
            );
        });

        $this->app->singleton(PostmanFormatter::class, function ($app) {
            return new PostmanFormatter(
                $app->make(Builder::class),
                $app->make(Config::class)->get('postman', [])
            );
        });

        $this->app->singleton(CollectionMerger::class, function ($app) {
            return new CollectionMerger(
                $app->make(Config::class)->get('postman', [])
            );
        });

        $this->app->singleton(CloudSyncServiceInterface::class, function ($app) {
            return new PostmanCloudSyncService(
                $app->make(Config::class)->get('postman', []),
                $app->make(CollectionMerger::class)
            );
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([GeneratePostmanDocs::class]);

            $this->publishes([
                __DIR__ . '/../config/postman.php' => config_path('postman.php'),
            ], 'postman-config');
        }
    }
}
