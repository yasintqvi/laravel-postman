<?php

namespace YasinTgh\LaravelPostman;

use Illuminate\Support\ServiceProvider;

class PostmanServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/postman.php', 'postman');
    }

    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../config/postman.php' => config_path('postman.php'),
        ], 'config');
    }
}
