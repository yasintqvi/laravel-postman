<?php

namespace YasinTgh\LaravelPostman\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use YasinTgh\LaravelPostman\PostmanServiceProvider;

require_once __DIR__ . '/Fixtures/Fixtures.php';

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [PostmanServiceProvider::class];
    }

    /**
     * The package config with overrides merged in.
     */
    protected function postmanConfig(array $overrides = []): array
    {
        return array_replace_recursive(config('postman'), $overrides);
    }
}
