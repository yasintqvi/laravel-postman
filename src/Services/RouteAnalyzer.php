<?php

namespace YasinTgh\LaravelPostman\Services;

use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Str;
use YasinTgh\LaravelPostman\Contracts\RouteAnalyzerInterface;
use YasinTgh\LaravelPostman\DataTransferObjects\RouteInfoDto;

class RouteAnalyzer implements RouteAnalyzerInterface
{
    public function __construct(
        protected Router $router,
        protected array $config
    ) {}

    public function analyze(): array
    {
        return array_map(
            [$this, 'parseRoute'],
            $this->getFilteredRoutes()
        );
    }

    protected function getFilteredRoutes(): array
    {
        return array_filter($this->router->getRoutes()->getRoutes(), function (Route $route) {
            if ($this->shouldIncludeRoute($route)) {
                return str_starts_with($route->uri(), $this->config['routes']['prefix']);
            }
            return false;
        });
    }

    protected function parseRoute(Route $route): RouteInfoDto
    {
        return new RouteInfoDto(
            uri: $route->uri(),
            methods: $route->methods(),
            controller: $route->getControllerClass(),
            action: $route->getActionMethod(),
            middleware: $route->gatherMiddleware()
        );
    }

    protected function shouldIncludeRoute(Route $route): bool
    {
        $prefix = $this->config['routes']['prefix'] ?? 'api';
        if ($prefix && !str_starts_with($route->uri(), $prefix)) {
            return false;
        }

        if (!empty($this->config['routes']['include']['patterns'])) {
            $matched = false;
            foreach ($this->config['routes']['include']['patterns'] as $pattern) {
                if (Str::is($pattern, $route->uri())) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) return false;
        }

        foreach ($this->config['routes']['exclude']['patterns'] as $pattern) {
            if (Str::is($pattern, $route->uri())) {
                return false;
            }
        }

        if (!empty($this->config['routes']['include']['middleware'])) {
            $routeMiddleware = $route->gatherMiddleware();
            if (empty(array_intersect($this->config['routes']['include']['middleware'], $routeMiddleware))) {
                return false;
            }
        }

        foreach ($this->config['routes']['exclude']['middleware'] as $middleware) {
            if (in_array($middleware, $route->gatherMiddleware())) {
                return false;
            }
        }

        $controller = $route->getControllerClass();
        if ($controller && in_array($controller, $this->config['routes']['exclude']['controllers'])) {
            return false;
        }

        return true;
    }
}
