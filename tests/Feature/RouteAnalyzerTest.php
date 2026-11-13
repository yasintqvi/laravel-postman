<?php

namespace YasinTgh\LaravelPostman\Tests\Feature;

use Illuminate\Support\Facades\Route;
use YasinTgh\LaravelPostman\Services\RouteAnalyzer;
use YasinTgh\LaravelPostman\Tests\Fixtures\PostController;
use YasinTgh\LaravelPostman\Tests\Fixtures\StorePostRequest;
use YasinTgh\LaravelPostman\Tests\Fixtures\UserController;
use YasinTgh\LaravelPostman\Tests\TestCase;

class RouteAnalyzerTest extends TestCase
{
    private function analyzer(array $config = []): RouteAnalyzer
    {
        return new RouteAnalyzer($this->app['router'], $this->postmanConfig($config));
    }

    private function uris(array $routes): array
    {
        return array_map(fn($route) => $route->uri, $routes);
    }

    public function test_prefix_matches_whole_segments_only(): void
    {
        Route::get('api/users', [UserController::class, 'index']);
        Route::get('apiary/bees', [UserController::class, 'index']);
        Route::get('api-docs', [UserController::class, 'index']);

        $this->assertSame(['api/users'], $this->uris($this->analyzer()->analyze()));
    }

    public function test_include_patterns_ignore_surrounding_slashes(): void
    {
        Route::get('api/users', [UserController::class, 'index']);
        Route::get('api/posts', [PostController::class, 'index']);

        $routes = $this->analyzer(['routes' => ['include' => ['patterns' => ['/api/users*']]]])->analyze();

        $this->assertSame(['api/users'], $this->uris($routes));
    }

    public function test_include_controllers_filter_is_applied(): void
    {
        Route::get('api/users', [UserController::class, 'index']);
        Route::get('api/posts', [PostController::class, 'index']);

        $routes = $this->analyzer(['routes' => ['include' => ['controllers' => [UserController::class]]]])->analyze();

        $this->assertSame(['api/users'], $this->uris($routes));
    }

    public function test_broken_routes_are_skipped_with_a_reason(): void
    {
        Route::get('api/users', [UserController::class, 'index']);
        Route::get('api/missing', [UserController::class, 'nope']);
        Route::get('api/ghost', ['App\\Ghost\\Controller', 'index']);

        $analyzer = $this->analyzer();
        $routes = $analyzer->analyze();

        $this->assertSame(['api/users'], $this->uris($routes));
        $this->assertSame(['api/missing', 'api/ghost'], array_column($analyzer->skipped(), 'uri'));
        $this->assertStringContainsString('no valid handler', $analyzer->skipped()[0]['reason']);
    }

    public function test_union_typed_form_request_is_resolved(): void
    {
        Route::put('api/posts/{post}', [PostController::class, 'update']);

        [$route] = $this->analyzer()->analyze();

        $this->assertInstanceOf(StorePostRequest::class, $route->formRequest);
    }
}
